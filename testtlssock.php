#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {

	/**
	 * Overrides the base setupConnection to implement TLS.
	 *
	 * @return void
	 */
	protected function setupConnection() {
		$errno = $errstr = null;

		$options = array(
			'ssl' => array(
				'peer_name' => 'YOUR DOMAIN NAME HERE',
				'verify_peer' => false,
				'local_cert' => 'cert.pem',
				'local_pk' => 'pk.key',
				'disable_compression' => true,
				'SNI_enabled' => true,
				'ciphers' => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK',
			),
		);

		$context = stream_context_create($options);

		$this->master = stream_socket_server(
			'tls://' . $this->listenAddress . ':' . $this->listenPort,
			$errno,
			$errstr,
			STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
			$context
		);
	}

	/**
	 * @param WebSocketUser $user
	 * @param string $message
	 */
	protected function process ($user, $message) {
		$this->send($user,$message);
	}

	/**
	 * @param WebSocketUser $user
	 */
	protected function connected ($user) {
		// Do nothing: This is just an echo server, there's no need to track the user.
		// However, if we did care about the users, we would probably have a cookie to
		// parse at this step, would be looking them up in permanent storage, etc.
	}

	/**
	 * @param WebSocketUser $user
	 */
	protected function closed ($user) {
		// Do nothing: This is where cleanup would go, in case the user had any sort of
		// open files or other objects associated with them.  This runs after the socket
		// has been closed, so there is no need to clean up the socket itself here.
	}
}

$echo = new echoServer("0.0.0.0","9000");

try {
	$echo->run();
}
catch (Exception $e) {
	$echo->stdout($e->getMessage());
}
