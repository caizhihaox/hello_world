<?php
/**
 * Created by PhpStorm.
 * User: czh
 * Date: 2018/10/18
 * Time: 10:51
 */

require_once __DIR__ . "/../mysql-master/src/Connection.php";
$db= new \Workerman\MySQL\Connection('sh-cdb-oqsr2dxe.sql.tencentcdb.com', '62768', 'root', 'mojitoVlinked1024', 'share_battery');
$key="c3NrZXl2bGlua2VkMjAxODEwMTg=";
$db_box_status='share_battery_tcp_status';
$db_trans_log='share_battery_tcp_trans_log';

$db_terminal_status='share_battery_tcp_terminal_status';
$SHARE_BATTERY_PORT='1306';


$SHARE_BATTERY_ADDR='111.231.94.130';//主服务器地址
$SHARE_BATTERY_ADDR_BY='182.254.129.182';//备用服务器地址

