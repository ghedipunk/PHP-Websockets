#!/usr/bin/env php
<?php
namespace \Phpws;

require_once(__DIR__ . '/core/bootstrap.php');

use Gpws\Eventloop\Socket;

class EchoServer {
}

$ws = new WebsocketServer('0.0.0.0', 9000);
$ws->run();
