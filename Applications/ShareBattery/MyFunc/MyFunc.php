<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/15
 * Time: 9:32
 */

namespace Workerman\MyFunc;
require_once __DIR__ . "/../HandleOrder/HandleOrder.php";

class MyFunc
{
    public static $is_standby_service = false;//备用服务器？

    public static $box_status_db = "share_battery_tcp_status";
    public static $terminal_db = 'share_battery_tcp_terminal_status';
    public static $terminal_trans_log = 'share_battery_tcp_trans_log';

    public static $qu_re_log = 'share_battery_qu_re_log';

    public static $command_log = 'share_battery_tcp_command_log';
    public static $api_log = 'share_battery_tcp_api_log';

    private static $my_Terminal_db = 'share_battery_MyTerminal';
    private static $my_Box_db = 'share_battery_MyBox';


    /**  保存报文日志文件
     * @param $msg string 保存文件的信息
     */
    public static function MyLog($msg)
    {
        $log_file = __DIR__ . "/../msg.log";
        if (!is_file($log_file)) {
            touch($log_file);
            chmod($log_file, 0622);
        }
        $msg = $msg . "\n";
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' ' . $msg, FILE_APPEND | LOCK_EX);
    }

    /**  保存判断充电宝是否为同一归属地日志文件
     * @param $msg string 保存文件的信息
     */
    public static function IsMyTerminalLog($msg)
    {
        $log_file = __DIR__ . "/../Terminal_msg.log";
        if (!is_file($log_file)) {
            touch($log_file);
            chmod($log_file, 0622);
        }
        $msg = $msg . "\n";
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' ' . $msg, FILE_APPEND | LOCK_EX);
    }


    /** 保存充电宝借出还入记录
     * @param $db
     * @param $box_id
     * @param $TerminalID
     * @param $Slot
     * @param $type
     * @param $remark
     * @param string $trans_id
     */
    public static function SaveTransLog($db, $box_id, $TerminalID, $Slot, $type, $remark, $trans_id = '')
    {
        $db_name = self::$terminal_trans_log;
        $db->query("INSERT INTO `$db_name` (box_id,create_time,`type`,TerminalID,Slot,remark,trans_id) VALUE ('$box_id',now(),'$type','$TerminalID','$Slot','$remark','$trans_id')");
    }

    /**  保存指令日志
     * @param $db
     * @param $box_id
     * @param $type  string 服务器下行报文 机柜上行报文
     * @param $hex_info
     * @param $command
     * @param $remark
     * @param $info
     * @param string $trans_id
     * @param bool $box_unknow
     */
    public static function SaveCommandLog($db, $box_id, $type, $hex_info, $command, $remark, $info, $trans_id = '', $box_unknow = false)
    {
        if ($box_unknow) {//不知道box_id，通过client换取
            if (self::$is_standby_service) {
                $client_id = self::GetStandbyClientID($box_id);
            } else {
                $client_id = $box_id;
            }

            $db_name = self::$box_status_db;
            //找到对应的box_id
            $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
            if (count($res) > 0) {
                $box_id = $res[0]['box_id'];//借的机柜
            } else {
                $box_id = "未知";
            }

        }
        $db_name = self::$command_log;


        $db->query("INSERT INTO `$db_name` (box_id,create_time,`type`,hex_info,command,remark,trans_id,`info`) VALUE ('$box_id',now(),'$type','$hex_info','$command','$remark','$trans_id','$info')");
    }

    /**  保存接口日志
     * @param $db
     * @param $box_id
     * @param $info
     * @param $command
     * @param $remark
     * @param string $trans_id
     */
    public static function SaveApiLog($db, $box_id, $info, $command, $remark, $trans_id = '')
    {
        $db_name = self::$api_log;
        $db->query("INSERT INTO `$db_name` (box_id,create_time,`info`,command,remark,trans_id) VALUE ('$box_id',now(),'$info','$command','$remark','$trans_id')");
    }

    /**   机柜登陆
     * @param $db
     * @param $client_id
     * @param $BoxID
     * @return bool
     */
    public static function Login($db, $client_id, $BoxID)
    {
        $re = false;
        $db_name = self::$box_status_db;
        $res = $db->query("SELECT id FROM `$db_name` WHERE box_id='$BoxID'");
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        if (count($res) > 0) {
            $db_id = $res[0]['id'];
            $row_count = $db->query("UPDATE `$db_name` SET client_id='$client_id',status='1',update_time=now() WHERE id='$db_id' limit 1");
            if ($row_count) {
                $re = true;
            }
        } else {
            $row_count = $db->query("INSERT INTO `$db_name` (box_id,create_time,`status`,client_id,update_time) VALUE ('$BoxID',now(),'1','$client_id',now())");
            if ($row_count) {
                $re = true;
            }
        }
        return $re;
    }

    /**  机柜下线
     * @param $db
     * @param $client_id
     * @return bool
     */
    public static function Logout($db, $client_id)
    {
        $re = false;
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $db_id = $res[0]['box_id'];
            $row_count = $db->query("UPDATE `$db_name` SET client_id='',status='0',update_time=now() WHERE box_id='$db_id' limit 1");
            if ($row_count) {
                $re = $db_id;
            }
        }
        return $re;
    }

    /**  退出所有机柜
     * @param $db
     * @return bool
     */
    public static function LogoutAll($db)
    {
        $re = false;
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $sql = "UPDATE `$db_name` SET client_id='',status='0',update_time=now() where status='1' and LEFT(`client_id`,7)='Standby'";
        } else {
            $sql = "UPDATE `$db_name` SET client_id='',status='0',update_time=now() where status='1' and LEFT(`client_id`,7)!='Standby'";
        }

        $re = $db->query($sql);
        return $re;
    }

    /**  心跳时间更新
     * @param $db
     * @param $client_id
     * @return bool
     */
    public static function Pop($db, $client_id)
    {
        $re = false;
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        //找到对应的id看是否异常
        $res = $db->query("SELECT id FROM `$db_name` WHERE status=1 and client_id='$client_id' ORDER BY update_time desc limit 1");
        if (count($res) == 1) {
            $db_id = $res[0]['id'];
            $row_count = $db->query("UPDATE `$db_name` SET pop_time=now() WHERE id='$db_id' limit 1");
            if ($row_count) {
                $re = true;
            }
        } else {
            //通知管理员 $client_id 异常

        }
        return $re;
    }

    /** 管理员获取充电宝
     * @param $db
     * @param $Result
     * @param $Slot
     * @param $TerminalID
     * @param $client_id
     * @return bool
     */
    public static function AdminGetTerminal($db, $Result, $Slot, $TerminalID, $client_id)
    {
        $re = false;
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        //找到对应的box_id
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $box_id = $res[0]['box_id'];//借的机柜
            $db_name = self::$terminal_db;
            //获取指令ID $trans_id
            $trans_re = $db->query("SELECT trans_id FROM `$db_name` WHERE  TerminalID='$TerminalID' limit 1");
            @ $trans_id = $trans_re[0]['trans_id'];

            if ($Result == '01') {//成功
                //更新状态

                $db->query("UPDATE `$db_name` SET box_id='',Slot='',trans_id='',status=0,update_time=now(),want='0' WHERE TerminalID='$TerminalID' limit 1");
                $re = true;
                //保存日志
                self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 0, '管理员弹出充电宝成功', $trans_id);
            } else {//失败
                $db->query("UPDATE `$db_name` SET trans_id='',update_time=now(),want='0' where  TerminalID='$TerminalID' limit 1");

                //通知管理员
                //保存日志
                self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 0, '管理员弹出充电宝Result：' . $Result, $trans_id);
            }

        }
        return $re;
    }

    /*
     * 借充电宝响应成功
     */
    public static function GetTerminal($db, $Result, $Slot, $TerminalID, $client_id)
    {
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $re = false;
        $db_name = self::$box_status_db;
        //找到对应的box_id
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $box_id = $res[0]['box_id'];//借的机柜
            $db_name = self::$terminal_db;
            //获取指令ID $trans_id
            $trans_re = $db->query("SELECT trans_id FROM `$db_name` WHERE  TerminalID='$TerminalID' limit 1");
            @ $trans_id = $trans_re[0]['trans_id'];
            //更新响应情况
            $db_name = self::$qu_re_log;
            $db->query("UPDATE `$db_name` SET is_re=1,re_time=now(),res='$Result' where trans_id='$trans_id' and is_re=0 limit 1");
            //去除充电宝trans_id
            $db_name = self::$terminal_db;
            //  $db->query("UPDATE `$db_name` SET trans_id='',update_time=now() where  TerminalID='$TerminalID' limit 1");

            if ($Result == '01') {//成功
                //更新状态
                $db->query("UPDATE `$db_name` SET box_id='',Slot='',trans_id='',status=0,update_time=now(),want='0' WHERE TerminalID='$TerminalID' limit 1");
                $re = true;
                //保存日志
                self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 0, '借充电宝成功', $trans_id);

            } else {//失败
                $db->query("UPDATE `$db_name` SET trans_id='',update_time=now(),want='0' where  TerminalID='$TerminalID' limit 1");

                //通知管理员

                //保存日志
                self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 0, '借充电宝失败Result：' . $Result, $trans_id);

            }

        }
        return $re;
    }

    /**  归还充电宝
     * @param $db
     * @param $Slot
     * @param $TerminalID
     * @param $client_id
     * @return bool
     */

    public static function ReTerminal($db, $Slot, $TerminalID, $client_id)
    {
        $re = "-1";
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        //找到对应的box_id
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $box_id = $res[0]['box_id'];//归还的机柜
            $db_name = self::$terminal_db;
            //是否为合法的充电宝
            if (self::IsMyTerminal($TerminalID, $box_id, $db)) {


                //是否存在该充电宝的记录
                $res = $db->query("SELECT id,status,box_id FROM `$db_name` WHERE TerminalID='$TerminalID'");
                if (count($res) > 0) {
                    //获取状态
                    $status = $res[0]['status'];
                    $db_id = $res[0]['id'];
                    //获取充电宝所在的机柜ID
                    $o_box_id = $res[0]['box_id'];
                    if ($status == '1' && $o_box_id == $box_id) {//本身状态为1 机柜id不变 为登陆后上行报文
                        //更新时间
                        $row_count = $db->query("UPDATE `$db_name` SET update_time=now() WHERE id='$db_id' limit 1");
                        if ($row_count) {
                            $re = "2";
                            //保存日志
                            self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 1, '登陆上行报文');
                        }
                    } else {  //充电宝人为归还

                        //改变状态，结束订单
                        $row_count = $db->query("UPDATE `$db_name` SET Slot='$Slot',box_id='$box_id',status='1',want='0',update_time=now() WHERE id='$db_id' limit 1");
                        if ($row_count) {
                            //判断为用户归还还是管理员
                            $re_name = '未知';
                            $db_name = self::$terminal_trans_log;
                            $sql = "select `remark` from `{$db_name}` where `type`='0' and TerminalID='$TerminalID' order by create_time desc limit 1";
                            $res = $db->query($sql);
                            if (is_array($res) && count($res) > 0) {

                                $remark = $res[0]['remark'];
                                if ($remark == '借充电宝成功' || $remark == '库存校对判定为借出状态') {//用户借
                                    //在此处理订单
                                    $re_name = "用户";
                                    $handle = new \Workerman\HandleOrder\HandleOrder();
                                    $handle->HandleMyOrder($db, $box_id, $TerminalID, $Slot);
                                    $re = "1";
                                } else {//管理员借
                                    $re_name = "管理员";
                                    $re = "10";
                                }
                            } else {
                                //没有借出记录
                                $re = "8888";
                            }


                            //保存日志
                            self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 1, $re_name . '还充电宝');


                        }
                    }

                } else {//首次入库
                    $row_count = $db->query("INSERT INTO `$db_name` (box_id,create_time,`status`,TerminalID,update_time,Slot) VALUE ('$box_id',now(),'1','$TerminalID',now(),'$Slot')");
                    if ($row_count) {
                        $re = "3";
                        //保存日志
                        self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 1, '首次入库');
                    }
                }
            } else {
                //非法
                $re = "-1";
            }

        }
        return $re;

    }


    /** 更新充电宝电量
     * @param $db
     * @param $arr
     * @param $client_id
     * @return bool
     */
    public static function UpdateTerminalLevel($db, $arr, $client_id)
    {
        $re = false;
        //通过client_id获取box_id
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $db_name = self::$box_status_db;
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $db_name = self::$terminal_db;
            $box_id = $res[0]['box_id'];
            $sql = "update {$db_name} set `Level`= case ";
            foreach ($arr as $v) {
                $TerminalID = $v['TerminalID'];
                $Level = $v['Level'];
                $sql .= " when `TerminalID`='$TerminalID' then '$Level' ";
            }
            $sql .= " end where box_id='$box_id'";
            $re = $db->query($sql);
        }

        return $re;

    }


    /** 更新充电宝槽位信息
     * @param $db
     * @param $arr
     * @param $client_id
     * @return bool
     */
    public static function UpdateTerminalSlot($db, $arr, $client_id)
    {
        $re = false;
        //通过client_id获取box_id
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $db_name = self::$box_status_db;
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $db_name = self::$terminal_db;
            $box_id = $res[0]['box_id'];

            $sql = "update {$db_name} set `Slot`= case ";
            foreach ($arr as $v) {
                $TerminalID = $v['TerminalID'];
                $Slot = $v['Slot'];
                $sql .= " when `TerminalID`='$TerminalID' then '$Slot' ";
            }
            $sql .= " end where box_id='$box_id'";
            $re = $db->query($sql);
        }

        return $re;
    }

    /**  机柜是否在线
     * @param $db
     * @param $box_id
     * @return bool
     *
     */
    public static function GetBoxStatus($db, $box_id)
    {
        $re = false;
        $db_name = self::$box_status_db;
        $date_limit = date("Y-m-d H:i:s", time() - 60);//在线且pop_time 在60s之内有更新
        //本服务器 box是否在线
        if (self::$is_standby_service) {
            $sql = "SELECT client_id FROM `$db_name` WHERE status=1 and pop_time>'$date_limit' and box_id='$box_id' and LEFT(`client_id`,7)='Standby'";
        } else {
            $sql = "SELECT client_id FROM `$db_name` WHERE status=1 and pop_time>'$date_limit' and box_id='$box_id'and LEFT(`client_id`,7)!='Standby'";
        }
        $res = $db->query($sql);
        if (count($res) > 0) {
            $re = $res[0]['client_id'];
            if (self::$is_standby_service) {
                $re = self::ReStandbyClientID($re);
            }
        }
        return $re;
    }

    /**  获取机柜中借出充电宝的槽位ID
     * @param $db
     * @param $box_id
     * @return bool
     */
    public static function GetTerminalSlot($db, $box_id)
    {
        $re = false;
        //box中是否有充电宝
        $db_name = self::$terminal_db;
        //是否存在可借充电宝
        $res = $db->query("SELECT id,TerminalID,Slot FROM `$db_name` WHERE status='1' and want='0' and box_id='$box_id' order by `Level` desc , `update_time`");
        if (count($res) > 0) {
            $id = $res[0]['id'];
            $Slot = $res[0]['Slot'];
            $TerminalID = $res[0]['TerminalID'];
            $trans_id = "CLI" . time() . rand();
            $db->query("UPDATE `$db_name` SET trans_id='$trans_id',update_time=now(),want='1' where id='$id'");
            $re = compact('Slot', 'TerminalID', 'trans_id');
            //本次借充电宝 情况 存入日志
            $db_name = self::$qu_re_log;
            $sql = "insert into `$db_name` (box_id,create_time,`trans_id`,TerminalID,send_times,Slot) value ";
            $sql .= " ('$box_id',now(),'$trans_id','$TerminalID','1','$Slot')";
            $db->query($sql);
        }
        return $re;

    }

    /**  根据 trans_id 获取再次借用的槽位
     * @param $db
     * @param $trans_id
     * @return bool
     */
    public static function TransGetSlot($db, $trans_id, $box_id)
    {
        $re = false;
        $db_name = self::$qu_re_log;
        //寻找未回应的报文
        $res = $db->query("SELECT id,TerminalID,Slot FROM `$db_name` WHERE is_re='0' and trans_id='$trans_id' limit 1");
        if (count($res) > 0) {
            $db->query("UPDATE `$db_name` SET `send_times`=`send_times`+1 WHERE is_re='0' and trans_id='$trans_id' limit 1");
            $re = $res[0]['Slot'];
            //want=1
            $db_name = self::$terminal_db;
            $db->query("UPDATE `$db_name` SET  update_time=now(),want='1' where box_id='$box_id' and Slot='$re' limit 1");
        }

        return $re;
    }


    /**  库存校对与更新
     * @param $db
     * @param $level_arr
     * @param $client_id
     * @return bool|string
     */
    public static function TerminalStock($db, $level_arr, $client_id)
    {
        $re = false;
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        //查询库存获得的数量
        $search_num = count($level_arr);
        //数据库录入的库存
        $db_num = 0;
        //通过client_id获取box_id
        $db_name = self::$box_status_db;
        $res = $db->query("SELECT box_id FROM `$db_name` WHERE status=1 and client_id='$client_id' order by update_time desc limit 1");
        if (count($res) > 0) {
            $no_lock_slot_arr = array();
            $db_name = self::$terminal_db;
            $box_id = $res[0]['box_id'];
            //查看数据库中库存
            $Stock_arr = $db->query("SELECT TerminalID,Slot FROM `$db_name` WHERE status=1 and box_id='$box_id'");
            $TerminalID_arr = array();
            foreach ($Stock_arr as $item) {
                $Slot = $item['Slot'];
                $TerminalID_k = "T" . $item['TerminalID'];
                $no_lock_slot_arr[$TerminalID_k] = $Slot;
                $TerminalID_arr[] = $item['TerminalID'];
            }
            $db_num = count($Stock_arr);
            $sql = "insert into `$db_name` (box_id,create_time,`status`,TerminalID,update_time,Slot) values ";
            $cc = 0;
            if ($db_num < $search_num) {//补充数据库中充电宝
                //在此处理订单
                $handle = new \Workerman\HandleOrder\HandleOrder();

                for ($i = 0; $i < $search_num; $i++) {
                    $TerminalID = $level_arr[$i]['TerminalID'];
                    if (!in_array($TerminalID, $TerminalID_arr)) {
                        $Slot = $level_arr[$i]['Slot'];
                        //保存日志
                        self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 1, '库存校对判定为在柜状态');
                        $handle->HandleMyOrder($db, $box_id, $TerminalID, $Slot);
                        $sql_l = $sql . " ('$box_id',now(),'1','$TerminalID',now(),'$Slot') ON DUPLICATE KEY UPDATE status='1',box_id='$box_id',update_time=now(),Slot='$Slot'";
                        $db->query($sql_l);
                        $cc++;
                    }

                }

                $re = "增加库存数量->{$cc}";

            } elseif ($db_num == $search_num) {//库存不需要更新
                $re = '不需要更新库存';
            } else {
                //库存异常 通知管理员
                $re = '库存异常';
                //归还没锁住充电宝 导致上报库存<数据库库存
                $level_id_arr = array();
                $no_lock_arr = array();

                foreach ($level_arr as $item) {

                    $level_id_arr[] = $item['TerminalID'];

                }
                foreach ($Stock_arr as $item) {
                    $TerminalID = $item['TerminalID'];
                    if (!in_array($TerminalID, $level_id_arr)) {
                        $no_lock_arr[] = $TerminalID;
                    }
                }
                // $re.=json_encode($no_lock_arr);
                //改变归还异常充电宝状态
                $db_name = self::$terminal_db;
                foreach ($no_lock_arr as $item) {
                    if ($item != '0000000000000000') {
                        $TerminalID = $item;
                        $TerminalID_k = "T" . $TerminalID;
                        $Slot = $no_lock_slot_arr[$TerminalID_k];
                        $sql = "update {$db_name} set status=0,update_time=now() WHERE status=1 and TerminalID='$TerminalID' limit 1";
                        $db->query($sql);
                        //保存日志
                        self::SaveTransLog($db, $box_id, $TerminalID, $Slot, 0, '库存校对判定为借出状态');
                        //通知订单变更
                        //在此处理订单
                        $handle = new \Workerman\HandleOrder\HandleOrder();
                        $handle->HandleNoLock($db, $box_id, $TerminalID, $Slot);
                    }

                }

                $re .= "改变" . json_encode($no_lock_arr) . "状态";

            }

        }
        return $re;
    }

    /**   内容转16进制 返回 16进制 长度+内容
     * @param $Content
     * @param bool $is_string
     * @return string
     */
    public static function ContentTobeLCHex($Content, $is_string = true)
    {
        $xx = '';
        $Name = $Content;
        if ($is_string) {
            $Name = bin2hex($Name) . '00';
        } else {
            $Name = bin2hex($Name);
        }
        $Len = strlen($Name) / 2;
        $Len = dechex($Len);//10进制转16进制
        while (strlen($Len) < 4) {
            $Len = '0' . $Len;
        }
        $xx .= $Len . $Name;
        return $xx;
    }

    public static function GetStatusForWS($db, $arr = [])
    {
        $db_name = self::$box_status_db;
        if (count($arr) > 0) {
            $arr = json_encode($arr);
            $str = json_encode($arr);
            $str = substr($str, 1, -1);
            $id_str = "(" . $str . ")";
            $sql = "select box_id,status,update_time as login_time from `{$db_name}` where box_id in {$id_str} and is_del=0 order by id desc";

        } else {
            $sql = "select box_id,status,update_time as login_time from `{$db_name}` where is_del=0 order by id desc limit 10";
        }

        $res = $db->query($sql);
        if (count($res) > 0) {
            return $res;
        } else {
            return 0;
        }
    }

    public static function GetInfoStatusForWS($db)
    {
        $db_name = self::$terminal_db;
        $res = $db->query("select box_id,count(id) as num from `{$db_name}` where status=1 group by box_id");
        if (count($res) > 0) {
            return $res;
        } else {
            return 0;
        }

    }

    /**
     * 换取备用服务器id 存入数据库加上前缀
     * @param $client_id string 通信id
     */
    public static function GetStandbyClientID($client_id)
    {
        return 'Standby_' . $client_id;
    }

    /*
     * 去除备用服务器前缀 进行通信
     */
    public static function ReStandbyClientID($client_id)
    {
        return substr($client_id, 8, strlen($client_id) - 8);
    }

    private static function IsMyTerminal($TerminalID, $box_id, $db)
    {
        $msg = '';
        $bool = true;
        //是否为入库的充电宝
        $db_name = self::$my_Terminal_db;
        $res = $db->query("SELECT belongTo FROM `$db_name` WHERE TerminalID='$TerminalID'  limit 1");
        if (count($res) > 0) {
            $Terminal_belong = $res[0]['belongTo'] == NULL ? '未分配' : $res[0]['belongTo'];
            //是否为入库的机柜
            $db_name = self::$my_Box_db;
            $res2 = $db->query("SELECT belongTo FROM `$db_name` WHERE box_id='$box_id'  limit 1");
            if (count($res2) > 0) {
                //机柜是否与充电宝同一归属
                $Box_belong = $res2[0]['belongTo'] == NULL ? '未分配' : $res2[0]['belongTo'];
                if ($Box_belong == $Terminal_belong) {
                    $bool = true;
                    $msg = $box_id . "#@#" . $Box_belong . "|" . $TerminalID . "#@#" . $Terminal_belong . "|" . $bool;
                } else {
                    if ($Terminal_belong == '未分配') {//充电宝未分配
                        if ($Box_belong == '未分配') {
                            $bool = false;
                            $msg = $box_id . "#@#未分配" . "|" . $bool;
                        } else {
                            //自动入库
                            $db_name = self::$my_Terminal_db;
                            $res = $db->query("update  `$db_name` set belongTo='$Box_belong' WHERE TerminalID='$TerminalID'  limit 1");
                            if ($res) {
                                $bool = true;
                                $msg = $box_id . "#@#" . $Box_belong . "|" . $TerminalID . "#@#入库成功|" . $bool;

                            } else {

                                $bool = true;
                                $msg = $box_id . "#@#" . $Box_belong . "|" . $TerminalID . "#@#入库失败|" . $bool;

                            }
                        }
                    } else {
                        //充电宝已分配 归属地不一致
                        $bool = false;
                        $msg = $box_id . "#@#" . $Box_belong . "|" . $TerminalID . "#@#" . $Terminal_belong . "|" . $bool;
                    }
                }
            } else {
                //获取不到充电宝归属 防止充电宝弹出 返回true
                //return true;
                $bool = true;
                $msg = $box_id . "#@#获取不到充电宝归属|" . $TerminalID . "#@#" . $Terminal_belong . "|" . $bool;

            }
        } else {
            //获取不到机柜归属 防止充电宝弹出 返回true
            $bool = true;
            $msg = $box_id . "#@#获取不到机柜归属|" . $bool;

        }
        self::IsMyTerminalLog($msg);
        return $bool;

    }

    public static function getAllLoginOutArr($db)
    {
        // $re = false;
        $db_name = self::$box_status_db;
        if (self::$is_standby_service) {
            $sql = "select box_id from  `$db_name` where status='1' and LEFT(`client_id`,7)='Standby'";
        } else {
            $sql = "select box_id from  `$db_name` where status='1' and LEFT(`client_id`,7)!='Standby'";
        }

        $re = $db->query($sql);
        if (count($re) > 0) {
            foreach ($re as $item) {
                $arr[] = $item['box_id'];
            }
            return $arr;
        } else {
            return [];
        }

    }

    public static function setICCID($db, $client_id, $iccid)
    {
        $re = 0;
        //通过client_id获取box_id
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $db_name = self::$box_status_db;
        $sql = "update `$db_name` set ICCID='$iccid' where  status=1 and client_id='$client_id'";
        $re = $db->query($sql);
        return $re;
    }

    public static function getBox_id($db, $client_id)
    {
        $re = '未知';
        //通过client_id获取box_id
        if (self::$is_standby_service) {
            $client_id = self::GetStandbyClientID($client_id);
        }
        $db_name = self::$box_status_db;
        $sql = "select box_id from `$db_name`  where  status=1 and client_id='$client_id'";
        $res = $db->query($sql);
        if (count($res) > 0) {
            $re = $res[0]['box_id'];
        }
        return $re;

    }

}