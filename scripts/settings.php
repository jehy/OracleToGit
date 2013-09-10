<?
##########  DEFINITIONS
set_time_limit(0);
$CURR_LOG='';
$CURR_SESSION='DEFAULT';
$CONNECT_POOL=array();
$normal_errors=array(24344,955,1917,942);#errors to ignore
$normal_errors[]=20102;
$CONNECTS=array('DEFAULT'=>array('scheme'=>'magic','pass'=>'423826','connect'=>'//192.168.120.142:1521/devel.krk.sma.ora','enc'=>'CL8MSWIN1251','mode'=>OCI_DEFAULT),);#your oracle connection settings
define('VERBOSE',0);
define('DEBUG',1);
$bkp_dir = '/repo/default.git/';#define a directory to store your git repo

?>