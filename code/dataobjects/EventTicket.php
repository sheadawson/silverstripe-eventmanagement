<?php
/**
 * A ticket type that can be attached to a registrable event. Each ticket can
 * have a specific quantity available for each event time.
 *
 * @package silverstripe-eventmanagement
 */
class EventTicket extends DataObject {

	private static $db = array(
		'Title'       => 'Varchar(255)',
		'Type'        => 'Enum("Free, Price")',
		'Price'       => 'Money',
		'Description' => 'Text',
		'StartDate'   => 'SS_Datetime',
		'EndDate'     => 'SS_Datetime',
		'MinTickets'  => 'Int',
		'MaxTickets'  => 'Int'
	);

	private static $has_one = array(
		'Event' => 'RegistrableEvent'
	);

	private static $defaults = array(
		'MinTickets' => 1
	);

	private static $summary_fields = array(
		'Title'        => 'Title',
		'StartSummary' => 'Sales Start',
		'PriceSummary' => 'Price'
	);

	private static $searchable_fields = array(
		'Title',
		'Type'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript('eventmanagement/javascript/event-ticket-cms.js');

		$fields->removeByName('EventID');
		$fields->removeByName('StartDate');
		$fields->removeByName('EndDate');

		if (class_exists('Payment')) {
			$fields->insertBefore(
				new OptionSetField('Type', 'Ticket type', array(
					'Free'  => 'Free ticket',
					'Price' => 'Fixed price ticket'
				)),
				'Price'
			);
		} else {
			$fields->removeByName('Type');
			$fields->removeByName('Price');
		}

		foreach (array('Start', 'End') as $type) {
			$fields->addFieldsToTab('Root.Main', 
				$dateTime = new DatetimeField("{$type}Date", "{$type} Date / Time")
			);
			$dateTime->getDateField()->setConfig('showcalendar', true);
			$dateTime->getTimeField()->setConfig('showdropdown', true);
		}

		$fields->addFieldsToTab('Root.Main', array(
			new TextareaField('Description', 'Description'),
			new HeaderField("Availability"),
			new NumericField('MinTickets', 'Minimum tickets per order'),
			new NumericField('MaxTickets', 'Maximum tickets per order')
		));

		return $fields;
	}

	public function validate() {
		$result = parent::validate();
		if ($this->Type == 'Price' && !$this->Price->exists()) {
			$result->error('You must enter a currency and price for fixed price tickets');
		}
		if (!$this->StartDate){
			$result->error('You must enter a start date');	
		}
		if (!$this->EndDate) {
			$result->error('You must enter an end date');
		}

		return $result;
	}

	public function populateDefaults() {
		$this->StartDate = date('Y-m-d H:i:s');
		parent::populateDefaults();
	}

	protected function onBeforeWrite() {
		if (!class_exists('Payment')) {
			$this->Type = 'Free';
		}
		//clear price if ticket is free
		if($this->Type != "Price"){
			$this->Price = "";
			$this->PriceAmount = 0;
			$this->PriceCurrency = "";
		}

		parent::onBeforeWrite();
	}

	/**
	 * @return RequiredFields
	 */
	public function getValidator() {
		return new RequiredFields('Title', 'Type', 'StartDate', 'EndDate');
	}

	/**
	 * Returns the number of tickets available for an event time.
	 *
	 * @param  RegistrableDateTime $time
	 * @param  int $excludeId A registration ID to exclude from calculations.
	 * @return array
	 */
	public function getAvailableForDateTime(RegistrableDateTime $time, $excludeId = null) {
		$start = strtotime($this->StartDate);

		if ($start >= time()) {
			return array(
				'available'    => false,
				'reason'       => 'Tickets are not yet available.',
				'available_at' => $start);
		}

		$end = strtotime($this->EndDate);

		if (time() >= $end) {
			return array(
				'available' => false,
				'reason'    => 'Tickets are no longer available.');
		}

		if (!$quantity = $this->Available) {
			return array('available' => true);
		}

		$bookings = EventRegistration::get()
			->innerJoin("EventRegistration_Tickets",'"EventRegistration"."ID" = "EventRegistrationID"')
			->filter("Status:not","Canceled")
			->filter("EventTicketID",$this->ID)
			->filter("EventRegistration.TimeID",$time->ID);
		if ($excludeId) {
			$bookings = $bookings->where('"EventRegistration"."ID" != '.$excludeId);
		}
		$booked = $bookings->sum("Quantity");

		if ($booked < $quantity) {
			return array('available' => $quantity - $booked);
		} else {
			return array(
				'available' => false,
				'reason'    => 'All tickets have been booked.');
		}
	}

	/**
	 * Calculates the timestamp for when this ticket stops going on sale for an
	 * event date time.
	 *
	 * @param  RegistrableDateTime $datetime
	 * @return int
	 */
	public function getSaleEndForDateTime(RegistrableDateTime $datetime) {
		return strtotime($this->EndDate);
	}

	/**
	 * @return string
	 */
	public function StartSummary() {
		return $this->obj('StartDate')->Nice();
	}

	/**
	 * @return string
	 */
	public function PriceSummary() {
		switch ($this->Type) {
			case 'Free':  return 'Free';
			case 'Price': return $this->obj('Price')->Nice();
		}
	}

	/**
	 * @return string
	 */
	public function Summary() {
		$summary = "{$this->Title} ({$this->PriceSummary()})";
		return $summary . ($this->Available ? " ($this->Available available)" : '');
	}

	public function canEdit($member = null) {
		return $this->Event()->canEdit($member);
	}

	public function canCreate($member = null) {
		return $this->Event()->canCreate($member);
	}

	public function canDelete($member = null) {
		return $this->Event()->canDelete($member);
	}

	public function canView($member = null) {
		return $this->Event()->canView($member);
	}
}
