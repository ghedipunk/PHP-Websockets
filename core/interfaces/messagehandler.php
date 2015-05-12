<?php
namespace Phpws\Interfaces

interface MessageHandler
{
  public function process($user, $message);
  public function connected($user);
  public function closed($user);
}