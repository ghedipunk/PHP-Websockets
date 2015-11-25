<?php
namespace Gpws\Core;

class Message implements \Gpws\Interfaces\Message {

    private $recipients;
    private $message;
    private $messageType = self::MESSAGE_TYPE_TEXT;

    public function addRecipient(\Gpws\Interfaces\WebsocketUser $recipient) {
        if (!in_array($recipient, $this->recipients)) {
            $this->recipients[] = $recipient;
        }
    }

    public function addRecipients(array $recipients) {
        foreach ($recipients as $recipient) {
            if (!($recipient instanceof \Gpws\Interfaces\WebsocketUser)) {
                throw new InvalidArgumentException('The array passed to addRecipients must only contain objects that implement \Gpws\Interfaces\WebsocketUser');
            }
            if (!in_array($recipient, $this->recipients)) {
                $this->recipients[] = $recipient;
            }
        }
    }

    public function removeRecipient(\Gpws\Interfaces\WebsocketUser $recipient) {
        if (($key = array_search($recipient, $this->recipients)) !== false) {
            unset($this->recipients[$key]);
        }
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function getRecipients() {
        return $this->recipients;
    }

    function __construct($text = '', $recipients = array()) {
        $this->recipients = array();
        foreach ($recipients as $recipient) {
            if (!($recipient instanceof \Gpws\Interfaces\WebsocketUser)) {
                throw new InvalidArgumentException('The recipients must implement \Gpws\Interfaces\WebsocketUser');
            }
            if (!in_array($recipient, $this->recipients)) {
                $this->recipients[] = $recipient;
            }
        }
    }
}