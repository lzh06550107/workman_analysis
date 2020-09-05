<?php

use GlobalData\Server;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

$worker = new Server('127.0.0.1', 2207);

Worker::runAll();
