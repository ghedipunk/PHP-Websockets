<?php
namespace Phpws;

class Autoloader
{
  private $rules = array();

  public function __construct() {
      spl_autoload_register(array($this, 'load'));
  }

  public function addRule(\Phpws\Interfaces\Autoloadrule $autoloadRule)
  {
    $this->rules[] = $autoloadRule;
  }

  public function load($className)
  {
    foreach ($this->rules as $rule)
    {
      if ($rule->load($className))
      {
        return;
      }
    } 
  }
}
