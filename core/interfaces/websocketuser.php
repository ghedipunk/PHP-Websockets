<?php
namespace Phpws\Interfaces;

interface WebsocketUser {
	public function getTlsStatus();
	public function getNetHandle();
}