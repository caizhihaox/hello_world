<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/12/17
 * Time: 14:46
 */

require_once __DIR__ . "/../mysql-master/src/Connection.php";
require_once __DIR__ . "/MyFunc.php";

//连接云数据库
$db = new \Workerman\MySQL\Connection('sh-cdb-oqsr2dxe.sql.tencentcdb.com', '62768', 'root', 'mojitoVlinked1024', 'share_battery');


$json = '[{"Slot":"01","TerminalID":"574c44428b000218","Level":"04"},{"Slot":"02","TerminalID":"574c44428b000235","Level":"04"},{"Slot":"03","TerminalID":"574c44428b000261","Level":"04"},{"Slot":"04","TerminalID":"574c44428b000665","Level":"04"},{"Slot":"05","TerminalID":"574c44428b000353","Level":"04"},{"Slot":"06","TerminalID":"574c44428b000240","Level":"04"},{"Slot":"07","TerminalID":"574c44428b000271","Level":"04"},{"Slot":"08","TerminalID":"574c44428b000363","Level":"04"},{"Slot":"09","TerminalID":"574c44428b000441","Level":"04"},{"Slot":"0a","TerminalID":"574c44428b000219","Level":"04"},{"Slot":"0b","TerminalID":"574c44428b000672","Level":"04"},{"Slot":"0c","TerminalID":"574c44428b000802","Level":"04"},{"Slot":"0d","TerminalID":"574c44428b000443","Level":"04"},{"Slot":"0e","TerminalID":"574c44428b000397","Level":"04"},{"Slot":"0f","TerminalID":"574c44428b000375","Level":"04"},{"Slot":"10","TerminalID":"574c44428b000675","Level":"04"},{"Slot":"11","TerminalID":"574c44428b000312","Level":"04"},{"Slot":"12","TerminalID":"574c44428b000713","Level":"04"},{"Slot":"13","TerminalID":"574c44428b000222","Level":"04"},{"Slot":"14","TerminalID":"574c44428b000224","Level":"04"},{"Slot":"15","TerminalID":"574c44428b000368","Level":"04"},{"Slot":"16","TerminalID":"574c44428b000415","Level":"04"},{"Slot":"17","TerminalID":"574c44428b000826","Level":"04"},{"Slot":"18","TerminalID":"574c44428b000807","Level":"04"}]';
$arr = json_decode($json, true);
$level_arr = $arr;
//查询库存获得的数量
$search_num = count($arr);
echo "报文中充电宝数量：$search_num\n";
//数据库录入的库存
$db_num = 0;
//通过client_id获取box_id
$db_name = 'share_battery_tcp_terminal_status';
$box_id = 'WLDB361811000421';

$no_lock_slot_arr = array();
//查看数据库中库存
$Stock_arr = $db->query("SELECT TerminalID,Slot FROM `$db_name` WHERE status=1 and box_id='$box_id'");
echo "<pre>";

$TerminalID_arr = array();
foreach ($Stock_arr as $item) {
    $Slot = $item['Slot'];
    $TerminalID_k = "T" . $item['TerminalID'];
    $no_lock_slot_arr[$TerminalID_k] = $Slot;
    $TerminalID_arr[] = $item['TerminalID'];
}

$db_num = count($Stock_arr);
echo "数据库中充电宝数量：$db_num\n";

$sql = "insert into `$db_name` (box_id,create_time,`status`,TerminalID,update_time,Slot) values ";
$cc = 0;

for ($i = 0; $i < $search_num; $i++) {
    $TerminalID = $level_arr[$i]['TerminalID'];
    if (!in_array($TerminalID, $TerminalID_arr)) {
        $Slot = $level_arr[$i]['Slot'];
        //保存日志

        $sql_l = $sql . " ('$box_id',now(),'1','$TerminalID',now(),'$Slot') ON DUPLICATE KEY UPDATE status='1',box_id='$box_id',update_time=now(),Slot='$Slot'";
        echo $sql_l;
        echo "\n";

        //$db->query($sql_l);
        $cc++;
    }

}