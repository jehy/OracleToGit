<?
##########  DEFINITIONS
set_time_limit(0);
$CURR_SESSION='';
$CURR_LOG='';
if(!$CURR_SESSION)
  $CURR_SESSION='SMA';
$CONNECT_POOL=array();
$normal_errors=array(24344,955,1917,942);
$normal_errors[]=20102;#������������ ��� ����������
$hidden_plugins=array('login','manage','part','svn','full','customexp','adduser','invalid','autocomplete');
#include_once('oracle_magic.php');
$CONNECTS=array(
  #'SMA'=>array('scheme'=>'sys','pass'=>'614087','connect'=>'SMA','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  #'FST'=>array('scheme'=>'sys','pass'=>'ybrjve','connect'=>'FST_RAC_FAILOVER','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  
  #'SMA'=>array('scheme'=>'sys','pass'=>'614087','connect'=>'//192.168.120.141:1521/SMA3','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA,),
  #'SMA'=>array('scheme'=>'sys','pass'=>'423826','connect'=>'172.16.0.27/devel.krk.sma.ora','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  'SMA'=>array('scheme'=>'sys','pass'=>'423826','connect'=>'//192.168.120.142:1521/devel.krk.sma.ora','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  #'SMA'=>array('scheme'=>'sys','pass'=>'pifagor','connect'=>'//172.16.0.25:1521/devel','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA, 'tns'=>'devel2'),
  #'FST211'=>array('scheme'=>'sys','pass'=>'ybrjve','connect'=>'//10.77.15.211:1521/eias','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  #'FST212'=>array('scheme'=>'sys','pass'=>'ybrjve','connect'=>'//10.77.15.212:1521/eias','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  #'FST213'=>array('scheme'=>'sys','pass'=>'ybrjve','connect'=>'//10.77.15.213:1521/eias','enc'=>'CL8MSWIN1251','mode'=>OCI_SYSDBA),
  );
#$CONNECTS['FST']=$CONNECTS['FST211'];

$base_types=array(
	'eas'=>array('prefix'=>'eas_ru_','def_passwd'=>'pifagor'),
	'reporting'=>array('prefix'=>'eias_reporting_ru_','def_passwd'=>'pifagor'),
	'reporting admin'=>array('prefix'=>'eias_reporting_admin_ru_','def_passwd'=>'eias_reporting_admin'),
	'reporting server'=>array('prefix'=>'eias_reporting_server_ru_','def_passwd'=>'pifagor'),
	'ds2'=>array('prefix'=>'ds2_ru_','def_passwd'=>'ds2'),
	'proxy'=>array('prefix'=>'eas_proxy_ru_','def_passwd'=>'pifagor'),
	'monitoring'=>array('prefix'=>'monitoring_ru_','def_passwd'=>'monitoring'),
	'monitoring_client'=>array('prefix'=>'monitoring_client_ru_','def_passwd'=>'monitoring'),
	'srt'=>array('prefix'=>'srt_ru_','def_passwd'=>'srtmewdull'),
	'srt_client'=>array('prefix'=>'srt_client_ru_','def_passwd'=>'srt_client'),
	'audit_client'=>array('prefix'=>'audit_client_ru_','def_passwd'=>'audit'),
	'audit'=>array('prefix'=>'audit_ru_','def_passwd'=>'audit'),
	'analytic'=>array('prefix'=>'analytic_ru_','def_passwd'=>'analytic'),
	'f46'=>array('prefix'=>'f46_ru_','def_passwd'=>'Z1s2e3D4'),
	'tarcom'=>array('prefix'=>'tarcom_ru_','def_passwd'=>'tarcom'),
	'ri_ru'=>array('prefix'=>'ri_ru_','def_passwd'=>'Z1s2e3D4'),
	'p48_ru'=>array('prefix'=>'p48_ru_','def_passwd'=>'Z1s2e3D4'),
   'gisee'=>array('prefix'=>'gisee_ru_','def_passwd'=>'Z1s2e3D4'),
   'montarcom'=>array('prefix'=>'montarcom_ru_','def_passwd'=>'montarcom'),
   'filesync'=>array('prefix'=>'filesync_ru_','def_passwd'=>'filesync'),
   'office'=>array('prefix'=>'office_ru_','def_passwd'=>'office'),
   'office_client'=>array('prefix'=>'office_client_ru_','def_passwd'=>'office_client'),
   'uac_client'=>array('prefix'=>'uac_client_ru_','def_passwd'=>'uac_client'),
   'uac'=>array('prefix'=>'uac_ru_','def_passwd'=>'uac'),
);
$sample_db='3_23';
###########################################
?>