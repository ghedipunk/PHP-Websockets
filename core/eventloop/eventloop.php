<?php
namespace Phpws;

class EventLoop implements \Phpws\Interfaces\EventLoop
{
  public function run()
  {
    echo 'Running!!!';
  }
}