<?php
use Workerman\Worker;
require_once __DIR__ . '/../Autoloader.php';

// 注意：这里与上个例子不同，使用的是websocket协议
$ws_worker = new Worker("websocket://0.0.0.0:2000");

$ws_worker->uidConnections = array();

$ws_worker->onMessage = function($connection, $data)
{
    global $ws_worker;
    // 判断当前客户端是否已经验证,既是否设置了uid
    if(!isset($connection->uid))
    {
        // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
        $connection->uid = $data;
        /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
         * 实现针对特定uid推送数据
         */
        $ws_worker->uidConnections[$connection->uid] = $connection;
        broadcast($data . "进入聊天室");
        return;
    }

    broadcast($data);
};

// 当有客户端连接断开时
$ws_worker->onClose = function($connection)
{
    global $ws_worker;
    if(isset($connection->uid))
    {
        // 连接断开时删除映射
        unset($ws_worker->uidConnections[$connection->uid]);
    }
};

// 向所有验证的用户推送数据
function broadcast($message)
{
    global $ws_worker;
    foreach($ws_worker->uidConnections as $connection)
    {
        $connection->send($message);
    }
}


// 运行worker
Worker::runAll();
