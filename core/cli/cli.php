<?php

namespace Gpws\Cli;

use Gpws\Interfaces\GlobalConfig;

class Cli implements \Gpws\Interfaces\Cli{

    /**
     * Cli constructor.
     * @param GlobalConfig $globalConfig
     */
    public function __construct($globalConfig) {
        $this->globalConfig = $globalConfig;

    }

    /**
     * @param string $message
     */
    public function stdout($message) {
        if ($this->globalConfig->getSetting('cliInteractiveMode', true)) {
            echo $message;
        }
    }

    /**
     * @param string $prompt
     *
     * @return string
     */
    public function readline($prompt = null) {
        if ($this->globalConfig->getSetting('cliInteractiveMode', true)) {
            return readline($prompt);
        }
        return '';
    }

    /** @var GlobalConfig */
    private $globalConfig;
}