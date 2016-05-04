<?php

namespace Gpws\Interfaces;

interface IncomingMessage {
	public function addBytes($bytes);
	public function getSender();
	public function getMessage();
}