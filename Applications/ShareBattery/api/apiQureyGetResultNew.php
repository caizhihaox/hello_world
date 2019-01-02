<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/18
 * Time: 10:48
 */
require_once 'api_config.php';
header("Access-Control-Allow-Origin: *");
$err = -1;
$msg = "";

if (isset($_GET['trans_id']) && !empty($_GET['trans_id']) &&
    isset($_GET['box_id']) && !empty($_GET['box_id'])
) {
    $trans_id = $_GET['trans_id'];
    $box_id = $_GET['box_id'];//借的机柜ID
    for ($i = 0; $i < 20; $i++) {

        //借充电宝成功
        $sql = "select id from `{$db_trans_log}` where trans_id='$trans_id' and remark='借充电宝成功' limit 1";
        $res = $db->query($sql);
        if (is_array($res) && count($res) > 0) {
            $err = '0000';
            $msg = "借充电宝成功";
            break;
        } else {
            //通过box换取client_id 判断主备用服务器
            $sql = "select client_id from `{$db_box_status}` where box_id='$box_id' and status=1 limit 1";
            $res_c = $db->query($sql);
            if (is_array($res_c) && count($res_c) > 0) {
                $client_id = $res_c[0]['client_id'];
                if (substr($client_id, 0, 7) == 'Standby') {
                    $addr = $SHARE_BATTERY_ADDR_BY;
                } else {
                    $addr = $SHARE_BATTERY_ADDR;
                }
                if ($i > 0 && $i % 5 == 0 && $i < 20) {
                    //建立socket
                    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 1, "usec" => 0));
                    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 6, "usec" => 0));
                    if (socket_connect($socket, $addr, $SHARE_BATTERY_PORT) == false) {
                        $err = 3;
                        $msg = '服务建立失败：' . socket_strerror(socket_last_error());
                        break;
                    } else {
                        $time = time();
                        $service = "getTerminalAgain";//

                        $info = compact('box_id', 'service', 'trans_id');
                        $token = md5($key . $time . json_encode($info));
                        $json = json_encode(compact("info", "time", "token"), JSON_UNESCAPED_UNICODE);
                        socket_write($socket, $json, strlen($json));
                    }

                }
                if ($i == 19) {

                    $err = 1;
                    $msg = "租借失败";
                }

            } else {
                $err = 1;
                $msg = "租借失败";
            }

        }
        sleep(1);
    }
} else {
    $err = 1;
    $msg = "参数错误";
}
ob_clean();
$res_arr = compact('err', 'msg');
echo json_encode($res_arr);




