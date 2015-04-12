<?php
namespace Phpws\Interfaces;

interface GlobalConfig
{
  public static function getSingleton();
  public function getValue($section, $name);
}