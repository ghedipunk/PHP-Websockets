<?php
namespace Phpws\Interfaces;

interface Message {
	public function addRecipient(\Phpws\Interfaces\WebsocketUser $recipient);
	public function addRecipients(array $recipients);
	public function removeRecipient(\Phpws\Interfaces\WebsocketUser $recipient);
	public function setMessage($message);
	public function send();
	public function getRecipients();
	public function getSendStatus();
}