<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/17
 * Time: 16:35
 */

namespace Workerman\HandleOrder;

class HandleOrder
{
    private static $terminal_handle_log='share_battery_tcp_handle_log';
    public static $Handle_url = "http://a.opapp.cn/huJiu";
    public static $Handle_key = 'c3NrZXl2bGlua2VkMjAxODEwMTg=';//与接口通信密钥
    private static $my_Box_db = 'share_battery_MyBox';

    /**
     * @param $db
     * @param $box_id  string 机柜ID
     * @param $TerminalID   string  充电宝ID
     * @param $Slot    string 槽位ID
     */
    public static function HandleMyOrder($db, $box_id, $TerminalID, $Slot)
    {
        self::get_handle_url($db, $box_id);
        $url = self::$Handle_url . "/share_battery/api/apiHandleTcpOrder.php";
        $info = compact('box_id', 'TerminalID', 'Slot');
        $time = time();
        $token = md5(self::$Handle_key . $time . json_encode($info));
        $post_data = compact('info', 'time', 'token');
        $result= self::send_post($url, $post_data);
        self:: SaveHandleLog($db, $box_id, $TerminalID, $Slot, 0, $result, $url);
        // $res = $db->query($sql);
    }

    public static function HandleNoLock($db, $box_id, $TerminalID, $Slot)
    {
        self::get_handle_url($db, $box_id);
        $url = self::$Handle_url . "/share_battery/api/apiHandleTcpUnlockOrder.php";
        $info = compact('box_id', 'TerminalID', 'Slot');
        $time = time();
        $token = md5(self::$Handle_key . $time . json_encode($info));
        $post_data = compact('info', 'time', 'token');
        $result= self::send_post($url, $post_data);
        self:: SaveHandleLog($db, $box_id, $TerminalID, $Slot, 1, $result, $url);
    }

    public static function send_post($url, $post_data)
    {

        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
    }

    private static function get_handle_url($db, $box_id)
    {
        $db_name = self::$my_Box_db;
        $res = $db->query("SELECT handle_url FROM `$db_name` WHERE box_id='$box_id'  limit 1");
        if (count($res) > 0) {
            $handle_url = $res[0]['handle_url'] == NULL ? '未分配' : $res[0]['handle_url'];
            if ($handle_url != '' && $handle_url != '未分配') {
                self::$Handle_url = $handle_url;
            }
        }
    }

    private static function SaveHandleLog($db, $box_id, $TerminalID, $Slot, $type, $result, $handle_url)
    {
        $db_name = self::$terminal_handle_log;
        $db->query("INSERT INTO `$db_name` (box_id,create_time,`type`,TerminalID,Slot,result,handle_url) VALUE ('$box_id',now(),'$type','$TerminalID','$Slot','$result','$handle_url')");
    }
}