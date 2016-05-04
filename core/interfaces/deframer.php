<?php

namespace Gpws\Interfaces;

interface Deframer {
    /**
     * @param string $message
     * @return string
     */
    public function deframe($message);
}