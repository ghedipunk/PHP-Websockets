<?php

namespace Gpws\Interfaces;

interface Router
{
	public function sendMessage(\Gpws\Interfaces\Message $message);
	public function recieveMessage(\Gpws\Interfaces\IncomingMessage $message);
	public function registerEventHandler($event, callable $handler);
	public function callEvent($event, $args); // Should this be public?
}
