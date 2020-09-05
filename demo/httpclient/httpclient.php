<?php

use Workerman\Http\Client;
use Workerman\Worker;

require_once __DIR__ . '/../../Autoloader.php';
require_once __DIR__ .'/../../vendor/autoload.php';

$worker = new Worker();

$worker->onWorkerStart = function(){

    $http = new Client();

    $http->get('https://www.baidu.com/', function($response){
        var_dump($response->getStatusCode());
        echo $response->getBody();
    }, function($exception){
        echo $exception;
    });

    $http->post('https://www.baidu.com/', ['key1'=>'value1','key2'=>'value2'], function($response){
        var_dump($response->getStatusCode());
        echo $response->getBody();
    }, function($exception){
        echo $exception;
    });

    $http->request('https://www.baidu.com/', [
        'method'  => 'POST',
        'version' => '1.1',
        'headers' => ['Connection' => 'keep-alive'],
        'data'    => ['key1' => 'value1', 'key2'=>'value2'],
        'success' => function ($response) {
            echo $response->getBody();
        },
        'error'   => function ($exception) {
            echo $exception;
        }
    ]);
};

Worker::runAll();