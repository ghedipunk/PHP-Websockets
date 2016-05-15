<?php
namespace Gpws\Interfaces;

interface Message {
	public function addRecipient(\Gpws\Interfaces\WebsocketConnection $recipient);
	public function addRecipients(array $recipients);
	public function removeRecipient(\Gpws\Interfaces\WebsocketConnection $recipient);
	public function setMessage($message);
	public function getRecipients();
}