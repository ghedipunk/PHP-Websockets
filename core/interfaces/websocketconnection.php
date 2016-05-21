<?php
namespace Gpws\Interfaces;

interface WebsocketConnection extends Connection {
	public function getCookies();
	public function getRequestHeaders();
	public function getRequestUri();
	public function getSession();

	public function performHandshake();
	public function closeConnection($reason, $message);
}
