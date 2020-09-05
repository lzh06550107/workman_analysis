<?php
// 异步访问外部wss端口，并设置本地ssl证书

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
require_once __DIR__ . '/../Autoloader.php';

$worker = new Worker();

$worker->onWorkerStart = function($worker){

    // 设置访问对方主机的本地ip及端口以及ssl证书
    $context_option = array(
        'socket' => array(
            // ip必须是本机网卡ip，并且能访问对方主机，否则无效
            'bindto' => '114.215.84.87:2333',
        ),
        // ssl选项，参考http://php.net/manual/zh/context.ssl.php
        'ssl' => array(
            // 本地证书路径。 必须是 PEM 格式，并且包含本地的证书及私钥。
            'local_cert'        => '/your/path/to/pemfile',
            // local_cert 文件的密码。
            'passphrase'        => 'your_pem_passphrase',
            // 是否允许自签名证书。
            'allow_self_signed' => true,
            // 是否需要验证 SSL 证书。
            'verify_peer'       => false
        )
    );

    // 发起异步连接
    $con = new AsyncTcpConnection('ws://echo.websocket.org:443', $context_option);

    // 设置以ssl加密方式访问
    $con->transport = 'ssl';

    $con->onConnect = function($con) {
        $con->send('hello');
    };

    $con->onMessage = function($con, $data) {
        echo $data;
    };

    $con->connect();
};

Worker::runAll();
