<?php
namespace Phpws\Autoloader;

/**
 *
 */
class WSAutoloadRule implements \Phpws\Interfaces\AutoloadRule
{
  public function load($origClassName)
  {
    $className = strtolower($origClassName);
    $ds = DIRECTORY_SEPARATOR;

    // Check if the global config is initialized yet without triggering autoloading
    if (class_exists('\\Phpws\\Config', false))
    {
      $config = \Phpws\Config::getSingleton();
      $pwd = $config->getSetting('present_working_directory');
    }
    else
    {
      $pwd = getcwd();
    }

    if (strpos($className, 'phpws\\') === 0)
    {
      $pathParts = explode('\\', $className);

      // Silently discard first element, as we know it's 'Phpws', and our base directory is 'core'
      array_shift($pathParts);
      array_unshift($pathParts, 'core');

      $shortName = array_pop($pathParts);

      $extPath = implode($ds, $pathParts);

      if (file_exists($pwd . $ds . $extPath . $ds . $shortName . '.php'))
      {
        require_once($pwd . $ds . $extPath . $ds . $shortName . '.php');
        return true;
      }
      elseif (file_exists($pwd . $ds . $extPath . $ds . $shortName . $ds . $shortName . '.php'))
      {
        require_once($pwd . $ds . $extPath . $ds . $shortName . $ds . $shortName . '.php');
        return true;
      }
    }
    return false;
  }
}
