<?php

use Channel\Client;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

// websocket服务端，用来订阅channel中指定的事件，当事件发生的时候推送消息到各个连接客户端
$worker = new Worker('websocket://0.0.0.0:4236');
$worker->name = 'pusher';

$worker->onWorkerStart = function($worker)
{
    // Channel客户端连接到Channel服务端
    Client::connect('127.0.0.1', 2206);
    // 以自己的进程id为事件名称
    $event_name = $worker->id;

    // 订阅worker->id事件并注册事件处理函数
    Client::on($event_name, function($event_data)use($worker){
        $to_connection_id = $event_data['to_connection_id'];
        $message = $event_data['content'];
        if(!isset($worker->connections[$to_connection_id]))
        {
            echo "connection not exists\n";
            return;
        }
        $to_connection = $worker->connections[$to_connection_id];
        $to_connection->send($message);
    });

    // 订阅广播事件
    $event_name = '广播';
    // 收到广播事件后向当前进程内所有客户端连接发送广播数据
    Channel\Client::on($event_name, function($event_data)use($worker){
        $message = $event_data['content'];
        foreach($worker->connections as $connection)
        {
            $connection->send($message);
        }
    });
};

$worker->onConnect = function($connection)use($worker)
{
    $msg = "workerID:{$worker->id} connectionID:{$connection->id} connected\n";
    echo $msg;
    $connection->send($msg); // 向各个客户端推送自己的workerID和connectionID
};

Worker::runAll();