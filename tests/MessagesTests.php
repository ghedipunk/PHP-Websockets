<?php

require_once('../core/interfaces/message.php');
require_once('../core/interfaces/websocketuser.php');
require_once('../core/message/message.php');
require_once('../core/user/users.php');

class MessagesTest extends PHPUnit_Framework_TestCase
{
	public function testAddUsers()
	{
		$message = new \Phpws\Core\Message();
		$user = new \Phpws\Core\WebsocketUser('Not A Real Id', 'Not A Real Socket');

		$message->addUser($user);
		$recipients = $message->getRecipients();
		assertContains($user, $recipients);
	}
}