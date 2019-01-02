<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . "/mysql-master/src/Connection.php";
require_once __DIR__ . "/MyFunc/MyFunc.php";

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{

    public static $SS_key = 'c3NrZXl2bGlua2VkMjAxODEwMTg=';//与接口通信密钥
    public static $WS_address = "111.231.94.130:8181";
    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;
    /**
     * 新建一个类的静态成员，用来保存自定义方法实例
     */
    public static $my_func = null;
    /**
     * 新建一个类的静态成员，用来保存机柜ID及对应的 $client_id
     */
    public static $login_id_arr = array();

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        //连接云数据库
        self::$db = new \Workerman\MySQL\Connection('sh-cdb-oqsr2dxe.sql.tencentcdb.com', '62768', 'root', 'mojitoVlinked1024', 'share_battery');
        self::$my_func = new \Workerman\MyFunc\MyFunc();
    }


    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        $CommandName = '无';
        $msg_type = '';
        $recv_buffer = '';
        echo "\n--------------------------{$client_id}收到消息--" . date("Y-m-d H:i:s") . "-------------------------------\n";
        // echo $message . "\n";
        @$msg = json_decode($message, true);
        if (isset($msg) && count($msg) > 0) {
            $msg_type = 'admin';
        } else {
            $msg_type = 'user';
        }
        if ($msg_type == 'user') {
            echo "\n--------------------------{$msg_type}消息类型---------------------------------\n";

        }

        if ($msg_type == 'admin') {
            $trans_id = "API" . time() . rand();
            $box_id = "";
            $service = "";
            $admin_res_arr = array();
            $admin_res_arr['info'] = array();
            $CommandName = '接口上行报文';
            echo "--------------------------验证---------------------------------\n";
            $now = time();
            $info = $msg['info'];
            $time = $msg['time'];
            $token = $msg['token'];
            if ($now - 60 < $time && $time < $now + 60) {
                $row_str = self::$SS_key . $time . json_encode($info);
                echo $row_str . "\n";
                $my_token = md5($row_str);
                echo $my_token . "\n";
                if ($token == $my_token) {
                    echo "--------------------------验证通过---------------------------------\n";
                    $service = $msg['info']['service'];
                    switch ($service) {
                        case "getTerminal"://借充电宝
                            echo "--------------------------借充电宝指令---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                echo "--------------------------查充电宝电量---------------------------------\n";
                                $xx = '000764013300001234';
                                self::$my_func->SaveCommandLog(self::$db, $box_id, '服务器下行报文', $xx, '查询机柜库存及响应', $service, '', $trans_id);
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                echo "--------------------------借电量最高的充电宝槽位---------------------------------\n";
                                $Slot_info = self::$my_func->GetTerminalSlot(self::$db, $box_id);
                                if (is_array($Slot_info)) {
                                    $xx = '000865013300001234' . $Slot_info['Slot'];
                                    self::$my_func->SaveCommandLog(self::$db, $box_id, '服务器下行报文', $xx, '借充电宝及响应Slot:' . $Slot_info['Slot'], $service, '', $trans_id);
                                    echo $send_client . ":发送借充电宝请求：" . $xx;
                                    $xx = hex2bin($xx);
                                    Gateway::sendToClient($send_client, $xx);
                                    $admin_res_arr['info'] = $Slot_info;
                                    $admin_res_arr['code'] = '0000';
                                    $admin_res_arr['msg'] = '借充电宝请求发送成功';
                                } else {
                                    echo "--------------------------设备中没有充电宝---------------------------------\n";
                                    $admin_res_arr['code'] = '2222';
                                    $admin_res_arr['msg'] = '设备中没有充电宝';
                                }
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case 'getTerminalAgain'://未响应再次发送借充电宝指令
                            echo "--------------------------未响应再次发送借充电宝指令---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id, $box_id);
                            if ($send_client) {
                                if (isset($msg['info']['trans_id']) && $msg['info']['trans_id'] != '') {//为无响应情况

                                    $Slot = self::$my_func->TransGetSlot(self::$db, $msg['info']['trans_id']);
                                    if ($Slot) {
                                        $xx = '000865013300001234' . $Slot;
                                        self::$my_func->SaveCommandLog(self::$db, $box_id, '服务器下行报文', $xx, '再次借充电宝及响应Slot:' . $Slot, $service, '', '');
                                        echo $send_client . ":再次发送借充电宝请求：" . $xx;
                                        $xx = hex2bin($xx);
                                        Gateway::sendToClient($send_client, $xx);
                                        echo "--------------------------再次借充电宝请求发送成功---------------------------------\n";
                                        $admin_res_arr['code'] = '0000';
                                        $admin_res_arr['msg'] = '再次借充电宝请求发送成功';

                                    } else {
                                        echo "--------------------------已响应或没有记录---------------------------------\n";
                                        $admin_res_arr['code'] = '8888';
                                        $admin_res_arr['msg'] = '已响应或没有记录';
                                    }

                                } elseif (isset($msg['info']['Slot']) && $msg['info']['Slot'] != '') {//主动弹出指定槽位
                                    $xx = '000865013300001234' . $msg['info']['Slot'];
                                    self::$my_func->SaveCommandLog(self::$db, $box_id, '服务器下行报文', $xx, '再次借充电宝及响应', $service, '', '');
                                    echo "发送借充电宝请求：" . $xx;
                                    $xx = hex2bin($xx);
                                    Gateway::sendToClient($send_client, $xx);
                                    // $admin_res_arr['info'] = $Slot_info;
                                    echo "--------------------------再次借充电宝请求发送成功---------------------------------\n";

                                    $admin_res_arr['code'] = '0000';
                                    $admin_res_arr['msg'] = '再次借充电宝请求发送成功';
                                } else {//参数错误
                                    echo "--------------------------参数错误---------------------------------\n";
                                    $admin_res_arr['code'] = '1112';
                                    $admin_res_arr['msg'] = '参数错误';
                                }

                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminCheckTerminal"://管理充电宝，查询库存
                            echo "--------------------------管理充电宝，查询库存---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                echo "--------------------------查询机柜库存---------------------------------\n";
                                $xx = '000764013300001234';
                                self::$my_func->SaveCommandLog(self::$db, $box_id, '服务器下行报文', $xx, '查询机柜库存及响应', $service, '', $trans_id);
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                echo "--------------------------查询机柜库存---------------------------------\n";
                                $admin_res_arr['code'] = '0000';
                                $admin_res_arr['msg'] = '查询机柜库存指令发送成功';

                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }

                            break;
                        case "adminTerminal"://管理充电宝，强制弹出
                            echo "--------------------------管理充电宝，强制弹出---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                echo "--------------------------获取电量最高的充电宝槽位弹出---------------------------------\n";
                                $Slot_info = self::$my_func->GetTerminalSlot(self::$db, $box_id);
                                if (is_array($Slot_info)) {
                                    $xx = '000880013300001234' . $Slot_info['Slot'];;
                                    echo "发送强制弹出充电宝请求：" . $xx;
                                    $xx = hex2bin($xx);
                                    Gateway::sendToClient($send_client, $xx);
                                    $admin_res_arr['info'] = $Slot_info;
                                    $admin_res_arr['code'] = '0000';
                                    $admin_res_arr['msg'] = '强制弹出充电宝请求发送成功';
                                } else {
                                    echo "--------------------------设备中没有充电宝---------------------------------\n";
                                    $admin_res_arr['code'] = '2222';
                                    $admin_res_arr['msg'] = '设备中没有充电宝';
                                }
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }

                            break;
                        case "adminReload"://管理 重启机柜
                            echo "--------------------------管理 重启机柜---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '000767013300001234';
                                echo "发送重启机柜请求：" . $xx;
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                $admin_res_arr['code'] = '0000';
                                $admin_res_arr['msg'] = '发送重启请求成功';
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminSoftVer"://管理 查询机柜软件版本号
                            echo "--------------------------管理 查询机柜软件版本号---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '000762013300001234';
                                echo "发送查询机柜软件版本号请求：" . $xx;
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                $admin_res_arr['code'] = '0000';
                                $admin_res_arr['msg'] = '发送查询机柜软件版本号请求成功';
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminSetAddress"://管理 设置服务器地址及响应
                            echo "--------------------------管理 设置服务器地址及响应---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '63013300001234';//7字节
                                $Address = $msg['info']['Address'];//'182.254.129.182';
                                if (preg_match("/^(1\d{2}|2[0-4]\d|25[0-5]|[1-9]\d|[1-9])(\.(1\d{2}|2[0-4]\d|25[0-5]|[1-9]\d|\d)){3}$/", $Address)) {
                                    $xx .= self::$my_func->ContentTobeLCHex($Address, true);
                                    $Port = $msg['info']['Port'];//'1306';
                                    $xx .= self::$my_func->ContentTobeLCHex($Port, true);
                                    $Heartbeat = '1e';
                                    $xx .= $Heartbeat;
                                    $PacketLen = strlen($xx) / 2;
                                    $PacketLen = dechex($PacketLen);//10进制转16进制
                                    while (strlen($PacketLen) < 4) {
                                        $PacketLen = '0' . $PacketLen;
                                    }
                                    $xx = $PacketLen . $xx;
                                    echo "发送设置服务器地址及响应请求：" . $xx;
                                    $xx = hex2bin($xx);
                                    Gateway::sendToClient($send_client, $xx);
                                    $admin_res_arr['code'] = '0000';
                                    $admin_res_arr['msg'] = '发送设置服务器地址及响应请求成功';

                                } else {
                                    echo "--------------------------ip地址不正确---------------------------------\n";
                                    $admin_res_arr['code'] = '1112';
                                    $admin_res_arr['msg'] = 'ip地址不正确';
                                }

                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminGetICCID"://管理 查询ICCID
                            echo "--------------------------管理 查询ICCID---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '000769013300001234';
                                echo "发送查询ICCID请求：" . $xx;
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                $admin_res_arr['code'] = '0000';
                                $admin_res_arr['msg'] = '发送查询ICCID请求成功';
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminGetAddr"://管理  查询服务器地址及响应
                            echo "--------------------------管理 查询服务器地址及响应---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '00076A010011223344';
                                echo "发送查询服务器地址及响应请求：" . $xx;
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($send_client, $xx);
                                $admin_res_arr['code'] = '0000';
                                $admin_res_arr['msg'] = '发送查询服务器地址及响应请求成功';
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }
                            break;
                        case "adminRemoteUp"://管理 远程升级
                            echo "--------------------------管理 远程升级---------------------------------\n";
                            echo "--------------------------获取box对应的client---------------------------------\n";
                            $box_id = $msg['info']['box_id'];
                            $send_client = self::$my_func->GetBoxStatus(self::$db, $box_id);
                            if ($send_client) {
                                $xx = '68013300001234';//7字节
                                $FTPAddress = $msg['info']['FTPAddress'];//'182.254.129.182';
                                if (preg_match("/^(1\d{2}|2[0-4]\d|25[0-5]|[1-9]\d|[1-9])(\.(1\d{2}|2[0-4]\d|25[0-5]|[1-9]\d|\d)){3}$/", $Address)) {
                                    $xx .= self::$my_func->ContentTobeLCHex($FTPAddress, true);
                                    $FTPPort = $msg['info']['FTPPort'];//'1306';
                                    $xx .= self::$my_func->ContentTobeLCHex($FTPPort, true);
                                    $FileName = $msg['info']['FileName'];//'123.php';
                                    $xx .= self::$my_func->ContentTobeLCHex($FileName, true);
                                    $Username = $msg['info']['Username'];//'test';
                                    $xx .= self::$my_func->ContentTobeLCHex($Username, true);
                                    $Password = $msg['info']['Password'];//'123456';
                                    $xx .= self::$my_func->ContentTobeLCHex($Password, true);
                                    $PacketLen = strlen($xx) / 2;
                                    $PacketLen = dechex($PacketLen);//10进制转16进制
                                    while (strlen($PacketLen) < 4) {
                                        $PacketLen = '0' . $PacketLen;
                                    }
                                    $xx = $PacketLen . $xx;
                                    echo "发送远程升级请求：" . $xx;
                                    $xx = hex2bin($xx);
                                    Gateway::sendToClient($send_client, $xx);
                                    $admin_res_arr['code'] = '0000';
                                    $admin_res_arr['msg'] = '发送远程升级请求成功';

                                } else {
                                    echo "--------------------------ip地址不正确---------------------------------\n";
                                    $admin_res_arr['code'] = '1112';
                                    $admin_res_arr['msg'] = 'ip地址不正确';
                                }
                            } else {
                                echo "--------------------------设备不在线---------------------------------\n";
                                $admin_res_arr['code'] = '1111';
                                $admin_res_arr['msg'] = '设备不在线';
                            }

                            break;
                        default:
                            echo "--------------------------指令不存在---------------------------------\n";
                            $admin_res_arr['code'] = '2221';
                            $admin_res_arr['msg'] = '指令不存在';
                            break;
                    }

                } else {
                    echo "--------------------------验证不通过---------------------------------\n";
                    $admin_res_arr['code'] = '3331';
                    $admin_res_arr['msg'] = '验证不通过';
                }
            } else {
                echo "--------------------------指令过期---------------------------------\n";
                $admin_res_arr['code'] = '3332';
                $admin_res_arr['msg'] = '指令过期';
            }
            $admin_res_arr['time'] = time();
            $row_str = self::$SS_key . $admin_res_arr['time'] . json_encode($admin_res_arr['info']);
            $admin_res_arr['token'] = md5($row_str);
            $admin_res_json = json_encode($admin_res_arr);
            echo $admin_res_json . "\n";
            self::$my_func->SaveApiLog(self::$db, $box_id, json_encode($info), $service, $admin_res_json, $trans_id);
            Gateway::sendToClient($client_id, $admin_res_json);
            echo "-------------------{$client_id}-------关闭该连接---------------------------------\n";
            Gateway::closeClient($client_id);

        } else {
            $box_trans_id = "BOX" . time() . rand();
            $CommandName = '机柜上行报文';
            echo "--------------------------转义---------------------------------\n";
            $recv_buffer = bin2hex($message);
            // /000->0转义
            $recv_buffer = preg_replace('/5c303030/', '00', $recv_buffer);
            echo $recv_buffer . "\n";
            //原始$message写入日志
            $data = array();
            $PacketLen = hexdec(substr($recv_buffer, 0, 4));
            $Command = substr($recv_buffer, 4, 2);
            $VSN = substr($recv_buffer, 6, 2);
            $CheckSum = substr($recv_buffer, 8, 2);
            $Token = substr($recv_buffer, 10, 8);
            $Payload = substr($recv_buffer, 18, strlen($recv_buffer) - 18);


            $data = compact("PacketLen", "Command", "VSN", "CheckSum", "Token", "Payload", "CommandName", 'recv_buffer');
            echo "--------------------------解析---------------------------------\n";
            //var_dump($data);
            $hex = $data;
            $C_name = $CommandName;
            switch ($hex['Command']) {
                // 60 机柜登陆及响应
                case '60':
                    $CommandName = '机柜登陆及响应';
                    echo "--------------------------机柜登陆及响应---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $Rand = substr($Payload, 0, 8);
                    $Magic = substr($Payload, 8, 4);
                    $BoxIDLen = hexdec(substr($Payload, 12, 4)) * 2;
                    $BoxID = hex2bin(substr($Payload, 16, $BoxIDLen - 2));
                    //字符串\000去除
                    $ReqDataLen = hexdec(substr($Payload, 16 + $BoxIDLen, 4));
                    if ($ReqDataLen > 0) {
                        $ReqData = substr($ReqDataLen, 16 + $BoxIDLen + 4, $ReqDataLen);
                    } else {
                        $ReqData = '';
                    }
                    $login_data = compact('Rand', 'Magic', 'BoxIDLen', 'BoxID', 'ReqDataLen', 'ReqData');


                    //var_dump($login_data);

                    if (self::$my_func->Login(self::$db, $client_id, $BoxID)) {
                        $Result = '01';
                        echo "-----------------------{$BoxID}---登陆成功---------------------------------\n";
                    } else {
                        $Result = '00';
                        echo "-----------------------{$BoxID}---登陆失败---------------------------------\n";

                    }
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, $Result, json_encode($login_data), '', true);

//                    $Re = substr($hex['recv_buffer'], 4, 14);
//                    $Result = '0008' . $Re . $Result;
//                    $Result = hex2bin($Result);
                    $xx = '000860010012344321' . $Result;
                    echo "发送：" . $xx;
                    $xx = hex2bin($xx);
                    Gateway::sendToClient($client_id, $xx);
                    if ($Result == '01') {
                        echo "-----------------------{$BoxID}---查询ICCID---------------------------------\n";
                        $xx = '000769013300001234';
                        echo "发送查询ICCID请求：" . $xx;
                        $xx = hex2bin($xx);
                        Gateway::sendToClient($client_id, $xx);
                    }
                    // var_dump(self::$my_func->Logout(self::$db,$client_id));
                    //$connection->send($xx, true);
                    echo "--------------------------机柜登陆1分钟后后查询库存---------------------------------\n";
                    Timer::add(60, function ($client_id, $C_name) {
                        $xx = '000764013300001234';
                        self::$my_func->SaveCommandLog(self::$db, $client_id, '服务器下行报文', $xx, "查询机柜库存及响应", "登陆1分钟后查询库存", '', 'S' . time() . rand(), true);
                        $xx = hex2bin($xx);
                        Gateway::sendToClient($client_id, $xx);
                    }, array($client_id, $C_name), false);
                    echo "--------------------------机柜登陆,通知ws服务广播---------------------------------\n";
                    // Websocket protocol for client.
                    $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
                    $ws_connection->onConnect = function ($connection) {
                        echo "\n" . '*************连接成功*****************' . "\n";
                        $key = self::$SS_key;
                        $time = time();
                        $service = "BoxLogin";//借充电宝指令
                        $info = compact('service');
                        $token = md5($key . $time . json_encode($info));
                        $json = json_encode(compact("info", "time", "token"));
                        $connection->send($json);
                        echo "\n" . "*************发送{$json}*****************" . "\n";
                    };
                    $ws_connection->onMessage = function ($connection, $data) {
                        echo json_encode($connection) . "recv: $data\n";
                        $connection->close();
                    };
                    $ws_connection->onError = function ($connection, $code, $msg) {
                        echo "error: $msg\n";
                    };
                    $ws_connection->onClose = function ($connection) {
                        echo "connection closed\n";
                    };
                    $ws_connection->connect();
                    break;
                //61 心跳及响应
                case '61':
                    $CommandName = '心跳及响应';
                    echo "--------------------------心跳及响应---------------------------------\n";
                    if (self::$my_func->Pop(self::$db, $client_id)) {
                        echo "--------------------------心跳db更新成功---------------------------------\n";
                    } else {
                        echo "--------------------------心跳db更新失败---------------------------------\n";
                    }
                    $xx = $hex['recv_buffer'];
                    // echo "发送：" . $xx;
                    $xx = hex2bin($xx);
                    Gateway::sendToClient($client_id, $xx);
                    //$connection->send($xx, true);
                    break;
                //62 查询机柜软件版本号
                case '62':
                    $CommandName = '查询机柜软件版本号';
                    echo "--------------------------查询机柜软件版本号---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $SoftVerLen = hexdec(substr($Payload, 0, 4));//字节
                    $SoftVer = substr($Payload, 4, $SoftVerLen * 2 - 2);
                    $SoftVer = hex2bin($SoftVer);
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', $SoftVer, '', true);
                    echo "--------------------------机柜软件版本号{$SoftVer}---------------------------------\n";
                    break;
                //63 设置机柜服务器地址及响应
                case '63':
                    $CommandName = '设置机柜服务器地址及响应';
                    echo "--------------------------设置机柜服务器地址及响应---------------------------------\n";
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', '', '', true);
                    break;
                //64   查询机柜库存及响应
                case '64':
                    $CommandName = '查询机柜库存及响应';
                    echo "--------------------------查询机柜库存及响应---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $RemainNum = hexdec(substr($Payload, 0, 2));//剩余充电宝个数
                    if ($RemainNum > 0) {
                        $level_arr = array();
                        $ii = 2;
                        for ($i = 0; $i < $RemainNum; $i++) {
                            $item['Slot'] = substr($Payload, $ii, 2);
                            $ii += 2;
                            $item['TerminalID'] = substr($Payload, $ii, 16);
                            $ii += 16;
                            $item['Level'] = substr($Payload, $ii, 2);
                            $ii += 2;
                            $level_arr[] = $item;
                        }
                        self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', json_encode($level_arr), '', true);
                        //var_dump($level_arr);
                        echo "--------------------------校对库存---------------------------------\n";
                        $Stock = self::$my_func->TerminalStock(self::$db, $level_arr, $client_id);
                        if ($Stock) {
                            echo "--------------------------{$Stock}---------------------------------\n";
                        } else {
                            echo "--------------------------校对库存失败---------------------------------\n";

                        }
                        //
                        echo "--------------------------更新充电宝槽位信息---------------------------------\n";
                        $le_res = self::$my_func->UpdateTerminalSlot(self::$db, $level_arr, $client_id);
                        $up_num = $le_res == false ? '0' : $le_res;
                        echo "\n--------------------------更新{$up_num}个充电宝槽位信息-------------------------------\n";

                        echo "--------------------------更新充电宝电量---------------------------------\n";
                        $le_res = self::$my_func->UpdateTerminalLevel(self::$db, $level_arr, $client_id);
                        $up_num = $le_res == false ? '0' : $le_res;
                        echo "\n--------------------------更新{$up_num}个充电宝电量-------------------------------\n";

                    } else {
                        echo "\n--------------------------机柜中无充电宝-------------------------------\n";
                        echo "--------------------------校对库存---------------------------------\n";
                        $Stock = self::$my_func->TerminalStock(self::$db, [], $client_id);
                        if ($Stock) {
                            echo "--------------------------{$Stock}---------------------------------\n";
                        } else {
                            echo "--------------------------校对库存失败---------------------------------\n";

                        }
                    }
                    break;
                //65   借充电宝
                case '65':
                    $CommandName = '借充电宝及响应';
                    echo "--------------------------借充电宝及响应---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $Slot = substr($Payload, 0, 2);                //槽位ID
                    $Result = substr($Payload, 2, 2);
                    $TerminalID = substr($Payload, 4, 16);
                    if (self::$my_func->GetTerminal(self::$db, $Result, $Slot, $TerminalID, $client_id)) {
                        echo "--------------------------借充电宝{$TerminalID}成功---------------------------------\n";
                        echo "--------------------------借充电宝,通知ws服务广播---------------------------------\n";
                        // Websocket protocol for client.
                        $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
                        $ws_connection->onConnect = function ($connection) {
                            echo "\n" . '*************连接成功*****************' . "\n";
                            $key = self::$SS_key;
                            $time = time();
                            $service = "TerminalChange";//指令
                            $info = compact('service');
                            $token = md5($key . $time . json_encode($info));
                            $json = json_encode(compact("info", "time", "token"));
                            $connection->send($json);
                            echo "\n" . "*************发送{$json}*****************" . "\n";
                        };
                        $ws_connection->onMessage = function ($connection, $data) {
                            echo json_encode($connection) . "recv: $data\n";
                            $connection->close();
                        };
                        $ws_connection->onError = function ($connection, $code, $msg) {
                            echo "error: $msg\n";
                        };
                        $ws_connection->onClose = function ($connection) {
                            echo "connection closed\n";
                        };
                        $ws_connection->connect();
                    } else {
                        echo "--------------------------借充电宝{$TerminalID}失败---------------------------------\n";
                        $xx = '000764013300001234';
                        self::$my_func->SaveCommandLog(self::$db, $client_id, '服务器下行报文', $xx, "查询机柜库存及响应", "借充电宝失败查询库存", '', 'S' . time() . rand(), true);
                        $xx = hex2bin($xx);
                        Gateway::sendToClient($client_id, $xx);
                        echo "--------------------------借充电宝{$TerminalID}失败，查询机柜库存及响应---------------------------------\n";

                    }
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', $TerminalID, '', true);
                    break;
                //66 还充电宝及响应
                case '66':
                    $CommandName = '还充电宝及响应';
                    echo "--------------------------还充电宝及响应---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $Slot = substr($Payload, 0, 2);                //槽位ID
                    $TerminalID = substr($Payload, 2, 16);//充电宝ID
                    $Terminal_data = compact('Slot', 'TerminalID');
                    //  var_dump($Terminal_data);
                    $ReTerminal_code = self::$my_func->ReTerminal(self::$db, $Slot, $TerminalID, $client_id);
                    if ($ReTerminal_code != '-1') {
                        $re_code = '01';//归还结果
                        echo "-----------------------{$TerminalID}---归还成功---------------------------------\n";
                        echo "--------------------------归还充电宝,通知ws服务广播---------------------------------\n";
                        // Websocket protocol for client.
                        $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
                        $ws_connection->onConnect = function ($connection) {
                            echo "\n" . '*************连接成功*****************' . "\n";
                            $key = self::$SS_key;
                            $time = time();
                            $service = "TerminalChange";//指令
                            $info = compact('service');
                            $token = md5($key . $time . json_encode($info));
                            $json = json_encode(compact("info", "time", "token"));
                            $connection->send($json);
                            echo "\n" . "*************发送{$json}*****************" . "\n";
                        };
                        $ws_connection->onMessage = function ($connection, $data) {
                            echo json_encode($connection) . "recv: $data\n";
                            $connection->close();
                        };
                        $ws_connection->onError = function ($connection, $code, $msg) {
                            echo "error: $msg\n";
                        };
                        $ws_connection->onClose = function ($connection) {
                            echo "connection closed\n";
                        };
                        $ws_connection->connect();
                        if ($ReTerminal_code == '1') {
                            echo "-----------------------{$TerminalID}---人为归还后20s发查库存指令---------------------------------\n";
                            Timer::add(20, function ($client_id) {
                                $xx = '000764013300001234';
                                $xx = hex2bin($xx);
                                Gateway::sendToClient($client_id, $xx);
                            }, array($client_id), false);
                        } elseif ($ReTerminal_code == '10') {
                            echo "-----------------------{$TerminalID}---管理员归还---------------------------------\n";
                        } else {
                            echo "-----------------------{$TerminalID}---无借出记录---------------------------------\n";
                        }
                    } else {
                        $re_code = '00';//归还结果
                        echo "-----------------------{$TerminalID}---归还失败---------------------------------\n";

                    }
                    $xx = '000966014111223344' . $Slot . $re_code;
                    echo "发送：" . $xx;
                    $xx = hex2bin($xx);
                    Gateway::sendToClient($client_id, $xx);
                    // $connection->send($xx, true);
                    break;
                //67   远程重启机柜
                case '67':
                    $CommandName = '远程重启机柜 ';
                    echo "--------------------------机柜收到远程重启机柜指令---------------------------------\n";
                    if (self::$my_func->Logout(self::$db, $client_id)) {
                        echo "\n--------------------------{$client_id}断开连接，退出成功--" . date("Y-m-d H:i:s") . "-------------------------------\n";

                    } else {
                        echo "\n--------------------------{$client_id}断开连接，退出失败--" . date("Y-m-d H:i:s") . "-------------------------------\n";

                    };
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', '', '', true);
                    break;
                //68 远程升级及响应
                case '68':
                    $CommandName = '远程升级及响应';
                    echo "--------------------------设置机柜服务器地址及响应---------------------------------\n";
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', '', '', true);
                    break;
                //69 查询ICCID
                case '69':
                    $CommandName = '查询ICCID';
                    echo "--------------------------查询ICCID---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $ICCIDLen = hexdec(substr($Payload, 0, 4));
                    $ICCID = substr($Payload, 4, $ICCIDLen * 2 - 2);
                    $ICCID = hex2bin($ICCID);
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', $ICCID, '', true);
                    echo "--------------------------机柜ICCID{$ICCID}---------------------------------\n";
                    $ic_re = self::$my_func->setICCID(self::$db, $client_id, $ICCID);
                    if ($ic_re) {
                        echo "--------------------------数据库更新成功---------------------------------\n";
                    } else {
                        echo "--------------------------数据库更新失败---------------------------------\n";
                    }
                    break;
                //6a 查询服务器地址及响应
                case '6a':
                    $CommandName = '查询服务器地址及响应';
                    echo "--------------------------查询服务器地址及响应---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $AddressLen = hexdec(substr($Payload, 0, 4));
                    $Address = hex2bin(substr($Payload, 4, $AddressLen * 2 - 2));
                    $PortLen = hexdec(substr($Payload, $AddressLen * 2 + 4, 4));
                    $Port = hex2bin(substr($Payload, $AddressLen * 2 + 8, $PortLen * 2 - 2));
                    $Heartbeat = hexdec(substr($Payload, $AddressLen * 2 + 8 + $PortLen * 2, 2));
                    $addr_arr = compact('Address', 'Port', 'Heartbeat');
                    self::$my_func->SaveCommandLog(self::$db, $client_id, '机柜上行报文', $recv_buffer, $CommandName, '', json_encode($addr_arr), '', true);
                    echo "--------------------------服务器地址{$Address}---------------------------------\n";
                    break;
                //80 强制弹出充电宝
                case '80':
                    $CommandName = '强制弹出充电宝';
                    echo "--------------------------强制弹出充电宝---------------------------------\n";
                    $Payload = $hex['Payload'];
                    $Slot = substr($Payload, 0, 2);                //槽位ID
                    $Result = substr($Payload, 2, 2);
                    $TerminalID = substr($Payload, 4, 16);
                    if (self::$my_func->AdminGetTerminal(self::$db, $Result, $Slot, $TerminalID, $client_id)) {
                        echo "--------------------------强制弹出充电宝{$TerminalID}成功---------------------------------\n";
                        echo "--------------------------强制弹出充电宝,通知ws服务广播---------------------------------\n";
                        // Websocket protocol for client.
                        $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
                        $ws_connection->onConnect = function ($connection) {
                            echo "\n" . '*************连接成功*****************' . "\n";
                            $key = self::$SS_key;
                            $time = time();
                            $service = "TerminalChange";//指令
                            $info = compact('service');
                            $token = md5($key . $time . json_encode($info));
                            $json = json_encode(compact("info", "time", "token"));
                            $connection->send($json);
                            echo "\n" . "*************发送{$json}*****************" . "\n";
                        };
                        $ws_connection->onMessage = function ($connection, $data) {
                            echo json_encode($connection) . "recv: $data\n";
                            $connection->close();
                        };
                        $ws_connection->onError = function ($connection, $code, $msg) {
                            echo "error: $msg\n";
                        };
                        $ws_connection->onClose = function ($connection) {
                            echo "connection closed\n";
                        };
                        $ws_connection->connect();
                    } else {
                        echo "--------------------------强制弹出充电宝{$TerminalID}失败---------------------------------\n";
                    }
                    break;
                default:
                    break;
            }
        }
        $box_id = self::$my_func-> getBox_id(self::$db, $client_id);
//保存接收消息文件日志
        self::$my_func->MyLog($client_id . "|" . $box_id . ':' . $msg_type . '@#@' . $CommandName . '@#@' . $message . '@#@' . $recv_buffer);
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        //  var_dump(self::$my_func->Logout(self::$db,$client_id));
        if (self::$my_func->Logout(self::$db, $client_id)) {
            echo "\n--------------------------{$client_id}断开连接，退出成功--" . date("Y-m-d H:i:s") . "-------------------------------\n";
            echo "--------------------------用户断开连接,通知ws服务广播---------------------------------\n";
            // Websocket protocol for client.
            $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
            $ws_connection->onConnect = function ($connection) {
                echo "\n" . '*************连接成功*****************' . "\n";
                $key = self::$SS_key;
                $time = time();
                $service = "BoxLogOut";//借充电宝指令
                $info = compact('service');
                $token = md5($key . $time . json_encode($info));
                $json = json_encode(compact("info", "time", "token"));
                $connection->send($json);
                echo "\n" . "*************发送{$json}*****************" . "\n";
            };
            $ws_connection->onMessage = function ($connection, $data) {
                echo json_encode($connection) . "recv: $data\n";
                $connection->close();
                // AsyncTcpConnection::cancelReconnect();
            };
            $ws_connection->onError = function ($connection, $code, $msg) {
                echo "error: $msg\n";
            };
            $ws_connection->onClose = function ($connection) {
                echo "connection closed\n";
            };
            $ws_connection->connect();
        } else {
            echo "\n--------------------------{$client_id}断开连接，退出失败--" . date("Y-m-d H:i:s") . "-------------------------------\n";

        };

        // 向所有人发送
        //GateWay::sendToAll("$client_id logout\r\n");
    }

    /**
     *  当关闭进程时触发
     */
    public static function onWorkerStop()
    {
        //退出所有机柜
        $res = self::$my_func->LogoutAll(self::$db);
        $ex_num = $res == false ? '0' : $res;
        echo "\n--------------------------结束进程，退出登陆{$ex_num}个机柜-------------------------------\n";
        echo "--------------------------结束进程,通知ws服务广播---------------------------------\n";
        // Websocket protocol for client.
        $ws_connection = new AsyncTcpConnection("ws://" . self::$WS_address);
        $ws_connection->onConnect = function ($connection) {
            echo "\n" . '*************连接成功*****************' . "\n";
            $key = self::$SS_key;
            $time = time();
            $service = "BoxLogOut";//借充电宝指令
            $info = compact('service');
            $token = md5($key . $time . json_encode($info));
            $json = json_encode(compact("info", "time", "token"));
            $connection->send($json);
            echo "\n" . "*************发送{$json}*****************" . "\n";
        };
        $ws_connection->onMessage = function ($connection, $data) {
            echo json_encode($connection) . "recv: $data\n";
            $connection->close();
        };
        $ws_connection->onError = function ($connection, $code, $msg) {
            echo "error: $msg\n";
        };
        $ws_connection->onClose = function ($connection) {
            echo "connection closed\n";
        };
        $ws_connection->connect();

    }
}
