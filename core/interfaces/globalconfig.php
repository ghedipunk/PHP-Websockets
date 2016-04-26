<?php

namespace Gpws\Interfaces;

interface GlobalConfig {
    public function getSetting($name, $default = null);
    public function loadSettings(array $settings);
}