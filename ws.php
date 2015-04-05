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

$router = new \Phpws\Router();