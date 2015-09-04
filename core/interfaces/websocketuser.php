<?php
namespace Phpws\Interfaces;

interface WebsocketUser {
	public function getTLSStatus();
	public function getTLSTunnel();
	public function getConnectionId();
	public function getSocket();
}