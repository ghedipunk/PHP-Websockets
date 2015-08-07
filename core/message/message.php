<?php
namespace Phpws\Core;

class Message implements \Phpws\Interfaces\Message {

  const STATUS_NOT_SENT = 0;
  const STATUS_SENT = 1;

  const MESSAGE_TYPE_CONTINUATION = 0;
  const MESSAGE_TYPE_TEXT = 1;
  const MESSAGE_TYPE_BINARY = 2;
  const MESSAGE_TYPE_CLOSE = 8;
  const MESSAGE_TYPE_PING = 9;
  const MESSAGE_TYPE_PONG = 10;

  private $recipients;
  private $message;
  private $framedMessage;
  private $isSent = false;
  private $messageType = self::MESSAGE_TYPE_TEXT;
  private $messageIsComplete = true;

  function __construct($text = '', $recipients = array()) {
    if ($recipients instanceof \Phpws\Interfaces\WebsocketUser) {
      $this->recipients[] = $recipients;
    }
  }

  protected function frame() {
    if ($this->isSent) {
      return false;
    }

    $bytes[0] = $this->messageType;

    if ($this->messageIsComplete) {
      $bytes[0] += 128;
    }

    $length = strlen($this->message);
    if ($length < 126) {
      $bytes[1] = $length;
    }
    elseif ($length <= 65536) {
      $bytes[1] = 126;
      $bytes[2] = ( $length >> 8 ) & 255;
      $bytes[3] = ( $length      ) & 255;
    }
    else {
      $bytes[1] = 127;
      $bytes[2] = ( $length >> 56 ) & 255;
      $bytes[3] = ( $length >> 48 ) & 255;
      $bytes[4] = ( $length >> 40 ) & 255;
      $bytes[5] = ( $length >> 32 ) & 255;
      $bytes[6] = ( $length >> 24 ) & 255;
      $bytes[7] = ( $length >> 16 ) & 255;
      $bytes[8] = ( $length >>  8 ) & 255;
      $bytes[9] = ( $length       ) & 255;
    }
    $headers = "";
    foreach ($bytes as $chr) {
      $headers .= chr($chr);
    }
    return $headers . $this->message;
  }

  protected function tls_frame($recipient) {
    if ($this->isSent) {
      // Can't frame a message if it has already been sent.
    }

    if (empty($this->framedMessage)) {
      $this->frame();
    }


  }

  /**
   * @param int $messageType
   */
  public function setMessageType($messageType) {
    // Only 4 bits available for a message type
    if ((int)$messageType == $messageType && $messageType < 16 && $messageType >= 0) {
      $this->messageType = $messageType;
    }
  }

  public function getMessageType() {
    return $this->messageType;
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

  public function send() {
    // Once a message is sent, don't send it again.
    if ($this->isSent) {
      return false;
    }
    if (empty($this->framedMessage)) {
      $this->frame();
    }
    $this->isSent = true;

    foreach ($this->recipients as $recipient) {

    }
  }

  public function removeRecipient(\Phpws\Interfaces\WebsocketUser $recipient) {
    foreach ($this->recipients as $index => $currentRecipient) {
      if ($currentRecipient == $recipient) {
        unset($this->recipients[$index]);
      }
    }
  }

  public function setMessage($message) {
    $this->message = $message;
  }

  public function getRecipients() {
    return $this->recipients;
  }

  public function getSendStatus() {
    if ($this->isSent) {
      return self::STATUS_SENT;
    }
    return self::STATUS_NOT_SENT;
  }
}