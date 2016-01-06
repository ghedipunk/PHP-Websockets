<?php

require_once('../core/interfaces/message.php');
require_once('../core/interfaces/websocketuser.php');
require_once('../core/message/message.php');
require_once('../core/user/users.php');

class MessagesTest extends PHPUnit_Framework_TestCase
{
	public function testAddRecipient()
	{
		$message = new \Gpws\Core\Message();
		$user = new \Gpws\Core\WebsocketUser('Not A Real Id', 'Not A Real Socket');

		$message->addRecipient($user);
		$recipients = $message->getRecipients();
		$this->assertContains($user, $recipients);
	}

	public function testAddRecipients()
	{
		$message = new \Gpws\Core\Message();
		$users = array(
			1 => new \Phpws\Core\WebsocketUser('1', '1'),
			2 => new \Phpws\Core\WebsocketUser('2', '2'),
			3 => new \Phpws\Core\WebsocketUser('3', '3'),
			4 => new \Phpws\Core\WebsocketUser('4', '4'),
			5 => new \Phpws\Core\WebsocketUser('5', '5'),
			6 => new \Phpws\Core\WebsocketUser('6', '6'),
			7 => new \Phpws\Core\WebsocketUser('7', '7'),
		);

		$message->addRecipients($users);

		$recipients = $message->getRecipients();
		$this->assertContains($users[1], $recipients);
		$this->assertContains($users[2], $recipients);
		$this->assertContains($users[3], $recipients);
		$this->assertContains($users[4], $recipients);
		$this->assertContains($users[5], $recipients);
		$this->assertContains($users[6], $recipients);
		$this->assertContains($users[7], $recipients);
	}

	public function testRemoveRecipient()
	{
		$message = new \Gpws\Core\Message();
		$user = new \Gpws\Core\WebsocketUser('1', '1');

		$message->addRecipient($user);

		$recipients = $message->getRecipients();
		$this->assertContains($user, $recipients);

		$message->removeRecipient($user);

		$recipients = $message->getRecipients();
		$this->assertEmpty($recipients);
	}

	public function testGetRecipients()
	{
		$message = new \Gpws\Core\Message();
		$user = new \Gpws\Core\WebsocketUser('1', '1');

		$recipients = $message->getRecipients();

		$this->assertEmpty($recipients);

		$message->addRecipient($user);

		$recipients = $message->getRecipients();

		$this->assertContains($user, $recipients);
	}
}

