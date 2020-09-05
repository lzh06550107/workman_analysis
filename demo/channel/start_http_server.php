<?php

use Channel\Client;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

// 用来处理http请求，向任意客户端推送数据，需要传workerID和connectionID
$http_worker = new Worker('http://0.0.0.0:4237');
$http_worker->name = 'publisher';

$http_worker->onWorkerStart = function()
{
    Client::connect('127.0.0.1', 2206);
};

$http_worker->onMessage = function($connection, $request)
{
    // 兼容workerman4.x
    if (!is_array($request)) {
        $_GET = $request->get();
    }
    $connection->send('ok');
    if(empty($_GET['content'])) return;

    // 是向某个worker进程中某个连接推送数据
    if(isset($_GET['to_worker_id']) && isset($_GET['to_connection_id']))
    {
        $event_name = $_GET['to_worker_id'];
        $to_connection_id = $_GET['to_connection_id'];
        $content = $_GET['content'];
        Client::publish($event_name, array( // 发布消息
            'to_connection_id' => $to_connection_id,
            'content'          => $content
        ));
    } else { // 是全局广播数据
        $event_name = '广播';
        $content = $_GET['content'];
        Client::publish($event_name, array( // 发布消息
            'content'          => $content
        ));
    }
};

Worker::runAll();