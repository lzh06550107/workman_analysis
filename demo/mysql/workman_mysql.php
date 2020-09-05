<?php

use Workerman\MySQL\Connection;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

$worker = new Worker('websocket://0.0.0.0:8484');

$worker->onWorkerStart = function($worker)
{
    // 将db实例存储在全局变量中(也可以存储在某类的静态成员中)
    global $db;
    // 要在本地启动mysql服务器
    $db = new Connection('127.0.0.1', '3306', 'root', 'root', 'test');
};

$worker->onMessage = function($connection, $data)
{
    // 通过全局变量获得db实例
    global $db;
    // 执行SQL
    $all_tables = $db->query('show tables');
    $connection->send(json_encode($all_tables));
};

// 运行worker
Worker::runAll();
