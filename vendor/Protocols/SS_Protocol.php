<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/15
 * Time: 9:32
 */
// 用户自定义协议命名空间统一为Protocols
namespace Protocols;
//简单文本协议，协议格式为 文本+换行
class MyTextProtocol
{
    // 分包功能，返回当前包的长度
    public static function input($recv_buffer)
    {
        // 查找换行符
        $pos = strpos($recv_buffer, "\n");
        // 没找到换行符，表示不是一个完整的包，返回0继续等待数据
        if($pos === false)
        {
            return 0;
        }
        // 查找到换行符，返回当前包的长度，包括换行符
        return $pos+1;
    }

    // 收到一个完整的包后通过decode自动解码，这里只是把换行符trim掉
    public static function decode($recv_buffer)
    {
        $recv_buffer=bin2hex($recv_buffer);
        // /000->0转义
        $recv_buffer=preg_replace ('/5c303030/', '00', $recv_buffer);




        return $recv_buffer;
    }

    // 给客户端send数据前会自动通过encode编码，然后再发送给客户端，这里加了换行
    public static function encode($data)
    {
        return $data."\n";
    }
}