<?php

use GlobalData\Client;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

$worker = new Worker('tcp://0.0.0.0:6636');

// 进程启动时
$worker->onWorkerStart = function () {
    // 初始化一个全局的global data client
    global $global;
    $global = new Client('127.0.0.1:2207');
};

// 每次服务端收到消息时
$worker->onMessage = function ($connection, $data) {
    // 更改$global->somedata的值，其它进程会共享这个$global->somedata变量
    global $global;
    echo "now global->somedata=" . var_export($global->somedata, true) . "\n";
    echo "set \$global->somedata=$data";
    $global->somedata = $data;
};

Worker::runAll();