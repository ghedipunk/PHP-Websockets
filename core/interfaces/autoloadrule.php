<?php
namespace Phpws\Interfaces;

interface AutoloadRule
{
  public function load($classname);
}