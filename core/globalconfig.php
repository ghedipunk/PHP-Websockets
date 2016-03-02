<?php
/**
 * Contains the GlobalConfig class
 */

namespace Gpws\Core;

/**
 * Global Configuration
 *
 * @property string $configFile The path to the configuration file.
 * @property array $config An array containing key=>value sets of configuration options.
 */
class GlobalConfig
{
    private $configFile;
    private $config;

    public function __construct($configFile) {
        if (is_file(__DIR__ . '/../' . $configFile)) {
            $this->configFile = $configFile;
        }
        else {
            throw new \InvalidArgumentException("$configFile is not a valid filename");
        }
    }

    /**
     * Retrieves a configuration value based on its key.
     *
     * @param string $key
     * 
     * @return string|null The value, if set, or null otherwise.
     */
    public function getValue($section, $key)
    {
        if (!$this->config && $this->configFile)
        {
            $this->config = parse_ini_file($this->configFile);
        }
        if (isset($this->config[$section][$key]))
        {
            return $this->config[$section][$key];
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
            $this->config = parse_ini_file(__DIR__ . '/../' . $this->configFile, true);
        }
        else
        {
            $this->config = null;
        }
    }
}
