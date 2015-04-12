<?php
namespace Phpws\Interfaces

interface ClientApplication
{
  public function process($user, $message);
  public function connected($user);
  public function closed($user);
}
