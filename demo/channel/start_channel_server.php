<?php

use Channel\Server;
use Workerman\Worker;
require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

// 初始化一个Channel服务端
$channel_server = new Server('0.0.0.0', 2206);

Worker::runAll();