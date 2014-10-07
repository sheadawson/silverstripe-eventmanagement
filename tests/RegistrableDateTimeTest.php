<?php
/**
 * Tests for the {@link RegistrableDateTime} class.
 *
 * @package    silverstripe-eventmanagement
 * @subpackage tests
 */
class RegistrableDateTimeTest extends SapphireTest {

	public static $fixture_file = 'eventmanagement/tests/RegistrableDateTimeTest.yml';

	/**
	 * @covers RegistrableDateTime::onBeforeWrite()
	 */
	public function testEventDetailsChangedNotificationEmail() {
		$event    = $this->objFromFixture('RegistrableEvent', 'event');
		$datetime = $this->objFromFixture('RegistrableDateTime', 'datetime');

		// First test that no emails are sent out for trivial changes.
		$datetime->StartTime = 0;
		$datetime->write();
		$this->assertNull($this->findEmail('test@example.com'));
		$this->assertNull($this->findEmail('canceled@example.com'));

		// Now do a non-emailed change and check they're still not send.
		$datetime->EndTime = '12:00:00';
		$datetime->write();
		$this->assertNull($this->findEmail('test@example.com'));
		$this->assertNull($this->findEmail('canceled@example.com'));

		// Now change a property that users are notified of a change in and
		// check an email is sent.
		$datetime->StartDate = '2011-01-02';
		$datetime->write();
		$this->assertEmailSent('test@example.com');
		$this->assertNull($this->findEmail('canceled@example.com'));
		$this->clearEmails();

		// Now disable notification and do the same and check no emails are
		// sent.
		$event->EmailNotifyChanges = false;
		$event->ParentID = 12345; //prevent complaints about page not allow to be root
		$event->write();

		$datetime->StartDate = '2011-01-03';
		$datetime->flushCache();

		$datetime = RegistrableDateTime::get()->byID($datetime->ID);
		$datetime->write();

		$this->assertNull($this->findEmail('test@example.com'));
		$this->assertNull($this->findEmail('canceled@example.com'));
	}

	/**
	 * @covers RegistrableDateTime::getRemainingCapacity()
	 */
	public function testGetRemainingCapacity() {
		$event    = $this->objFromFixture('RegistrableEvent', 'event');
		$datetime = $this->objFromFixture('RegistrableDateTime', 'datetime');
		$ticket   = $this->objFromFixture('EventTicket', 'ticket');

		$datetime->Capacity = 0;
		$datetime->write();
		$this->assertEquals(true, $datetime->getRemainingCapacity());

		$datetime->Capacity = 50;
		$datetime->write();
		$this->assertEquals(50, $datetime->getRemainingCapacity());

		$rego = new EventRegistration();
		$rego->TimeID = $datetime->ID;
		$rego->write();
		$rego->Tickets()->add($ticket, array('Quantity' => 49));

		$this->assertEquals(1, $datetime->getRemainingCapacity());
		$this->assertEquals(50, $datetime->getRemainingCapacity($rego->ID));

		$rego->Tickets()->remove($ticket);
		$rego->Tickets()->add($ticket, array('Quantity' => 50));
		$this->assertFalse(!!$datetime->getRemainingCapacity());
		$this->assertEquals(50, $datetime->getRemainingCapacity($rego->ID));
	}

}