<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/15
 * Time: 9:58
 */
require_once './workerman/workerman/Autoloader.php';
use Workerman\Worker;

// #### MyTextProtocol worker ####
$text_worker = new Worker("SS_Protocol://0.0.0.0:5678");

/*
 * 收到一个完整的数据（结尾是换行）后，自动执行MyTextProtocol::decode('收到的数据')
 * 结果通过$data传递给onMessage回调
 */
$text_worker->onMessage =  function($connection, $data)
{
    var_dump($data);
    /*
     * 给客户端发送数据，会自动调用MyTextProtocol::encode('hello world')进行协议编码，
     * 然后再发送到客户端
     */
    $connection->send("hello world");
};

// run all workers
Worker::runAll();