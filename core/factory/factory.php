<?php
namespace Phpws;

class Factory implements \Phpws\Interfaces\Factory
{

  public static function create($objectName, array $args = null)
  {
    $config = \Phpws\GlobalConfig::getSingleton();

    if (($className = $config->getValue('factory overrides', $objectName)) !== null)
    {
      return new $className();
    }

    $objLName = strtolower($objectName);

    if (file_exists('core/' . $objLName . '/' . $objLName . '.php'))
    {
      $className = '\\Phpws\\' . $objectName;
      $widget = new $className($this->config);
      if (!is_null($widget))
      {
        return $widget;
      }
    }

    throw new \Exception('Suitable candidate class for initializing "' . $objectName . '" not found.');
  }
}