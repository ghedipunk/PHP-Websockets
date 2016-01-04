<?php
/**
 * Contains the GlobalConfig class
 */

namespace Gpws\Core;

/**
 * Global Configuration
 *
 * Yep, it's a singleton!  Kind of.  There's nothing stopping anyone from spinning up multiple instances of this,
 * each with the same source config file or even separate ones.
 *
 * @property string $configFile The path to the configuration file.
 * @property array $config An array containing key=>value sets of configuration options.
 */
class GlobalConfig
{
    private $configFile;
    private $config;

    /**
     * Sets the filename for where the configuration is stored.
     *
     * @param string $filename
     */
    public function setConfigFile($filename)
    {
        $this->configFile = $filename;
    }

    /**
     * Retrieves a configuration value based on its key.
     *
     * @param string $key
     * 
     * @return string|null The value, if set, or null otherwise.
     */
    public function getValue($key)
    {
        if (!$this->config && $this->configFile)
        {
            $this->config = parse_ini_file($this->configFile);
        }
        if (isset($this->config[$key]))
        {
            return $this->config[$key];
        }
        return null;
    }

    /**
     * Clears out the configuration and, if a filename is currently set, re-populates it from file.
     */
    public function resetConfig()
    {
        if ($this->configFile)
        {
            $this->config = parse_ini_file($this->configFile);
        }
        else
        {
            $this->config = null;
        }
    }
}