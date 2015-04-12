#!/usr/bin/env php
<?php

// 

// Bootstrap our autoloader.
require_once('core/Interfaces/autoloadrule.php');
require_once('core/autoloader/autoloader.php');

// Bootstrap our core autoload rules
require_once('core/autoloader/wsautoloadrule.php');

$autoloader = new \Phpws\Autoloader();
$autoloader->addRule(new \Phpws\Autoloader\WSAutoloadRule());

// If you hate the word 'singleton' then go read the rant I've placed above the config's getSingleton method.
$config = \Phpws\GlobalConfig::getSingleton();

if (($factoryClass = $config->getValue('factory overrides', 'FactoryClass')) !== null)
{
  $factory = new $factoryClass($config);
}
else
{
  $factory = new \Phpws\Factory($config);
}

$evloop = $factory->create('EventLoop');

$evloop->run();
