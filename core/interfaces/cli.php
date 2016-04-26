<?php

namespace Gpws\Interfaces;

interface Cli {
    public function stdout($message);
    public function readline($prompt);
}