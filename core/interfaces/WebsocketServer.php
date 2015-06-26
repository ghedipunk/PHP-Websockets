<?php
namespace Phpws\Interfaces;

interface WebsocketServer {
	public function run();
	public function setEventListener($event, $callback);
}