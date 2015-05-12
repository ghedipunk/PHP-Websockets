<?php


class EventLoopTest extends \PHPUnit_Framework_TestCase
{
  public function testEventLoop()
  {
    require_once('core/interfaces/globalconfig.php');
    require_once('core/globalconfig/globalconfig.php');
    require_once('core/autoloader/wsautoloadrule.php');

    $config = \Phpws\GlobalConfig::getSingleton('core/tests/resources/evloop.ini.test');

    // Obviously not done here...
    // TODO: Find some way to connect to the socket while testing... or some way to trigger the stop.
  }
}