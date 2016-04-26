<?php

namespace Gpws\GlobalConfig;

class GlobalConfig implements \Gpws\Interfaces\GlobalConfig {

    /**
     * GlobalConfig constructor.
     * @param array $settings
     */
    public function __construct(array $settings) {
        $this->loadSettings($settings);
    }

    /**
     * @param $name
     * @param $default
     *
     * @return mixed
     */
    public function getSetting($name, $default = null) {
        if (isset($this->settings[$name])) {
            return $this->settings[$name];
        }
        return $default;
    }

    public function loadSettings(array $settings) {
        $this->settings = $settings;
    }

    /** @var array */
    private $settings;
}