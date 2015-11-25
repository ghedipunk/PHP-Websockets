<?php

namespace Gpws\Interfaces;

interface IncomingMessage {
	public function getSender();
	public function getMessage();
}