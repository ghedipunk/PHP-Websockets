<?php


class ConfigTest extends \PHPUnit_Framework_TestCase
{
  public function testConfig()
  {
    require_once('core/interfaces/globalconfig.php');
    require_once('core/globalconfig/globalconfig.php');

    $config0 = \Phpws\GlobalConfig::getSingleton('core/tests/resources/config.ini.doesntexist');
    $this->assertNull($config0);

    $config1 = \Phpws\GlobalConfig::getSingleton('core/tests/resources/config.ini.test1');
    $this->assertNotNull($config1);
    $this->assertEquals($config1->getValue('main', 'test'), 1);

    $config2 = \Phpws\GlobalConfig::getSingleton('core/tests/resources/config.ini.test2');
    $this->assertNotNull($config2);
    $this->assertEquals($config2->getValue('main', 'test'), 1);

    $config2 = \Phpws\GlobalConfig::getSingleton('core/tests/resources/config.ini.test2', true);
    $this->assertNotNull($config2);
    $this->assertEquals($config2->getValue('main', 'test'), 2);
    
    $configAny = \Phpws\GlobalConfig::getSingleton();
    $this->assertNotNull($configAny);
    $this->assertEquals($configAny->getValue('main', 'test'), 2);
    
    $this->assertNotSame($config1, $config2);
    $this->assertSame($config2, $configAny);
  }
}