<?php
namespace Phpws\Core;

class Message implements \Phpws\Interfaces\Message {

  private $recipients;
  private $message;
  private $framedMessage;
  private $isSent = false;

  function __construct($text = '', $recipients = array()) {
    if ($recipients instanceof \Phpws\Interfaces\WebsocketUser) {
      $this->recipients[] = $recipients;
    }
  }

  protected function frame()
  {
    if ($this->isSent) {
      //
    }
  }

  protected function tls_frame($recipient) {

  }

  public function addRecipient(\Phpws\Interfaces\WebsocketUser $recipient) {
    if (!in_array($recipient, $this->recipients)) {
      $this->recipients[] = $recipient;
    }
  }

  public function addRecipients(array $recipients) {
    foreach ($recipients as $recipient) {
      if ($recipient instanceof \Phpws\Interfaces\WebsocketUser && !in_array($recipient, $this->recipients)) {
        $this->recipients[] = $recipient;
      }
    }
  }
}