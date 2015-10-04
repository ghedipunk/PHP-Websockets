<?php

class MessagesTest extends PHPUnit_Framework_TestCase
{
	public function testAddUsers()
	{
		$message = new \Phpws\Core\Message();
		$user = new \Phpws\Core\WebsocketUser();

		$message->addUser($user);
		$recipients = $message->getRecipients();
		assertContains($user, $recipients);
	}
}