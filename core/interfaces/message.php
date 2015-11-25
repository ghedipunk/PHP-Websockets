<?php
namespace Gpws\Interfaces;

interface Message {
	public function addRecipient(\Gwps\Interfaces\WebsocketUser $recipient);
	public function addRecipients(array $recipients);
	public function removeRecipient(\Gpws\Interfaces\WebsocketUser $recipient);
	public function setMessage($message);
	public function getRecipients();
}