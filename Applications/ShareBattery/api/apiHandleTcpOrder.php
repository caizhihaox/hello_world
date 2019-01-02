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

if (isset($_POST['time']) && !empty($_POST['time']) &&
    isset($_POST['token']) && !empty($_POST['token'])&&
    isset($_POST['info']) && !empty($_POST['info'])
) {
    $now = time();
    $token = $_POST['token'];
    $time = $_POST['time'];
    $info=$_POST['info'];
    if ($now - 60 < $time && $time < $now + 60) {
        $row_str = $key . $time.json_encode($info);
        $my_token = md5($row_str);
        if ($my_token == $token) {
            //处理订单
            $err = '0000';
            $msg = "处理成功";
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
$res_arr = compact('err', 'msg');
echo json_encode($res_arr);

