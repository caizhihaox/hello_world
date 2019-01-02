<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/15
 * Time: 9:32
 */
// 用户自定义协议命名空间统一为Protocols
namespace Workerman\Protocols;
//简单文本协议，协议格式为 文本+换行
class SSProtocol
{


    // 收到一个完整的包后通过decode自动解码，这里只是把换行符trim掉
    public static function decode($recv_buffer)
    {
        $recv_buffer=bin2hex($recv_buffer);
        // /000->0转义
        $recv_buffer=preg_replace ('/5c303030/', '00', $recv_buffer);
        $data=array();
        $PacketLen =hexdec(substr($recv_buffer,0,4)) ;
        $Command=substr($recv_buffer,4,2);
        $VSN =substr($recv_buffer,6,2);
        $CheckSum=substr($recv_buffer,8,2);
        $Token=substr($recv_buffer,10,8);
        $Payload =substr($recv_buffer,18,strlen($recv_buffer)-18);
        $CommandName='';
        switch ($Command){
            case "60":
                $CommandName='机柜登陆及响应';
                break;
            default :
                $CommandName='无';
                break;
        }
        $data=compact("PacketLen","Command","VSN","CheckSum","Token","Payload","CommandName",'recv_buffer');
        return $data;
    }

    // 给客户端send数据前会自动通过encode编码，然后再发送给客户端，这里加了换行
    public static function encode($data)
    {
        $data= hex2bin($data);
        return $data;
    }
}