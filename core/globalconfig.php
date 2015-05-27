<?php
/**
 * Contains the GlobalConfig class
 */

namespace Phpws\Core;

/**
 * Global Configuration
 *
 * The class that has a whole mess of static methods that interact with the global configuration.
 *
 * @property string $configFile The path to the configuration file.
 * @property array $config An array containing key=>value sets of configuration options.
 */
class GlobalConfig
{

    public static $configFile;
    public static $config;

    /**
     * Sets the filename for where the configuration is stored.
     *
     * @param string $filename
     */
    public static function setConfigFile($filename)
    {
        self::$configFile = $filename;
    }

    /**
     * Retrieves a configuration value based on its key.
     *
     * @param string $key
     * 
     * @return string|null The value, if set, or null otherwise.
     */
    public static function getValue($key)
    {
        if (!self::$config && self::$configFile)
        {
            self::$config = parse_ini_file(self::$configFile);
        }
        if (isset(self::$config[$key]))
        {
            return self::$config[$key];
        }
        return null;
    }

    /**
     * Clears out the configuration and, if a filename is currently set, re-populates it from file.
     */
    public static function resetConfig()
    {
        if (self::$configFile)
        {
            self::$config = parse_ini_file(self::$filename);
        }
        else
        {
            self::$config = null;
        }
    }
}