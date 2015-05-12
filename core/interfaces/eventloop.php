<?php
namespace Phpws\Interfaces;

interface EventLoop
{
  public function run();
  public static function stop();
}
