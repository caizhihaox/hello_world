<?php
/**  获取在线柜库存
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/18
 * Time: 10:48
 */
require_once 'api_config.php';
header("Access-Control-Allow-Origin: *");
$err = -1;
$msg = "";
$info = array();
if (isset($_GET['time']) && !empty($_GET['time']) &&
    isset($_GET['token']) && !empty($_GET['token'])
) {
    $now = time();
    $token = $_GET['token'];
    $time = $_GET['time'];
   // if ($now - 60 < $time && $time < $now + 60) {
        if(1){
        $row_str = $key . $time;
        $my_token = md5($row_str);
        if ($my_token == $token) {
            $sql = "select box_id from `{$db_box_status}` where status='1'";//获取在线box_id
            $res = $db->query($sql);
            if (is_array($res) && count($res) > 0) {
                $box_id_arr=[];
                foreach ($res as $item) {
                    $box_id_arr[] = $item['box_id'];
                }
                $sql="select count(id) as num,box_id from `{$db_terminal_status}` where status='1' group by box_id";
                $res2 = $db->query($sql);
                foreach ($res2 as $item){
                   $box_id=$item['box_id'];
                   $num=$item['num'];
                    if(in_array($box_id,$box_id_arr)){
                        $info[]=compact('box_id','num');
                    }
                }
                $err = '0000';
                $msg = "获取成功";
            } else {
                $err = '0004';
                $msg = "没有设备在线";
            }
        } else {
            $err = '0003';
            $msg = "验证失败";
        }
    } else {
        $err = '0002';
        $msg = "请求过期";
    }
} else {
    $err = "0001";
    $msg = "参数错误";
}
ob_clean();
$res_arr = compact('err', 'msg', 'info');
echo json_encode($res_arr);

