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
if(isset($_GET['trans_id']) && !empty($_GET['trans_id'])){
    $trans_id=$_GET['trans_id'];
    for($i=0;$i<60;$i++){
        //借充电宝成功
        $sql="select remark from `{$db_trans_log}` where trans_id='$trans_id' limit 1";
        $res=$db->query($sql);
        if(is_array($res) && count($res)>0){
            break;
        }else{
            sleep(1);
        }
    }
    if(isset($res[0]['remark']) && $res[0]['remark']=='管理员弹出充电宝成功'){
        $err = '0000';
        $msg = "借充电宝成功";
    }else{
        $err = 2;
        $msg = "借充电宝失败";
    }
}else{
    $err = 1;
    $msg = "参数错误";
}
ob_clean();
$res_arr=compact('err','msg');
echo json_encode($res_arr);

