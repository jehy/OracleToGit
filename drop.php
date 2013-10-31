#!/usr/bin/php
<?
#  
#this script allows to back up and drop invalid objects. Beware!
#
$file=$argv[1];
$file=dirname(__FILE__).'/settings/'.pathinfo ($file,PATHINFO_FILENAME).'.php';

if (!file_exists($file))
  die('Please, set up settings file first!');
include_once($file);
include_once dirname(__FILE__).'/oracle_magic.php';
$session=$CURR_SESSION;
add_log('Using session ' . $session . ', backup dir ' . $bkp_dir, 1);
set_curr_session($session);
$this_update_begin_time = time();
$dt = date('Y-m-d H_i_s');
SET_LOG('git_up_' . $session . $dt);
$cur_time = $dt;
$last_update_time = 0;
$new_last_event_id = 0;
if (!$bkp_dir)
{
  add_log('No backup dir!');
  die();
}
$file_handle = fopen($bkp_dir . 'working_' . md5($session) . '.txt', 'w+');
if (!flock($file_handle, LOCK_EX | LOCK_NB))
{
  add_log('Already exporting, terminating this thread!', 1);
  die();
}
@mkdir($bkp_dir, 0777, 1);
$last_time = @file_get_contents($bkp_dir . 'last_time.txt');
if ($last_time)
{
  $last_time = explode("\n", $last_time);
  $last_update_time = $last_time[1];
  $last_event_id = $last_time[0];
  add_log('Last update time: ' . $last_update_time, 1);
  add_log('Last update event id: ' . $last_event_id, 1);
}
else
{
  $last_update_time = '';
  $last_event_id = 0;
}
$lastuser = false;
$lastaction = false;
$accepted = array('PACKAGE', 'PACKAGE BODY', 'TABLE', 'VIEW', 'SYNONYM', 'JOB', 'FUNCTION', 'VIEW', 'TABLE', 'INDEX',
  'SNAPSHOT', 'SEQUENCE', 'PROCEDURE', 'TRIGGER', 'TYPE','MATERIALIZED VIEW');
$objectsobjects = array('PACKAGE', 'PACKAGE BODY', 'FUNCTION', 'PROCEDURE', 'TRIGGER');

if (!$last_event_id)
{
  /*add_log('Exporting users', 1);
  update_users($bkp_dir);
  add_log('Exporting tablespaces', 1);
  update_tablespaces($bkp_dir);
  add_log('Exporting database links', 1);
  update_dblinks($bkp_dir);*/
}

#update updated
#how many?
if ($last_event_id)
{
  $sql = "select count(1) from dba_objects where status='INVALID' and last_ddl_time>=
(select ddl_timestamp from magic.ddllog where event_id=:id) and owner not in ('SYS','SYSMAN') and last_ddl_time<sysdate-7";
  #if ($test)
  #  $sql .= " where owner='EAS_RU_3_23'";
  add_log($sql, 1);
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":id", $last_event_id);
  oci_execute($stid);
}
else
{
  $sql = "select count(1) as cnt from dba_objects where status='INVALID' and owner not in ('SYS','SYSMAN') and last_ddl_time<sysdate-7";
  #if ($test)
  #  $sql .= " where owner='EAS_RU_3_23'";
  add_log($sql, 1);
  $stid = oracle_query($sql, false, 1);
}
$row = oci_fetch_row($stid);
oci_free_statement($stid);
$total = $row[0];
if (!$total)
  add_log('No objects updated, nothing to do', 1);
else
{
  add_log('Updating ' . $total . ' objects', 1);
  add_log('Start time: ' . date('H:i:s'), 1);
  $primary = 0;

  #update
  $conn = get_connect();
  
  
    $sql = 'select max(event_id) from magic.ddllog';
    $stid = oracle_query($sql, $conn, 1);
    $row = oci_fetch_row($stid);
    $new_last_event_id = $row[0];
  {
    add_log('Logging updates from event id ' . $last_event_id, 1);
    $sql = "select object_type,owner,object_name from dba_objects where status='INVALID' and owner not in ('SYS','SYSMAN')";
    if ($last_event_id)
      $sql.=" and last_ddl_time>=
(select ddl_timestamp from magic.ddllog where event_id=:id) and last_ddl_time<sysdate-7";
    #if ($test)
    #  $sql .= " and owner='EAS_RU_3_23'";
    #$sql .= " order by event_id";
    $stid = oci_parse($conn, $sql);
    if ($last_event_id)
      oci_bind_by_name($stid, ":id", $last_event_id);
    oci_execute($stid);
  }
  $curr = 0; #just step counter to count % complete
  if(!is_array($exclude_drop_schemes))
    $exclude_drop_schemes=array();
  foreach($exclude_drop_schemes as $key=>$val)#in case user put scheme names in lowercase
    $exclude_drop_schemes[$key]=strtoupper($val);
    
  if(!is_array($exclude_drop_types))
    $exclude_drop_types=array();
  foreach($exclude_drop_types as $key=>$val)
    $exclude_drop_types[$key]=strtoupper($val);
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    $curr++;
    if (!in_array($row['OBJECT_TYPE'], $accepted))
      continue;
    if(in_array($row['OWNER'],$exclude_drop_schemes))
    {
      add_log('Not doing anyhing in schema '.$row['OWNER']);
      continue;
    }
    
    if(in_array($row['OBJECT_TYPE'],$exclude_drop_types))
    {
      add_log('Not doing anyhing for object type '.$row['OBJECT_TYPE']);
      continue;
    }

    #$name = strtolower(convert_db_encoding($row['OBJECT_NAME'])); //it will be used in file names, should be UTF.
    $name = strtolower($row['OBJECT_NAME']); //it will be used in file names, should be UTF.
    $type = strtolower($row['OBJECT_TYPE']);
    $owner_dir = $bkp_dir . strtolower($row['OWNER']) . '/';
    $type_dir = $owner_dir . $type . '/';

    add_log('[ ' . round($curr / $total * 100, 4) . ' % ]<br>' . $row['OWNER'] . '.' . $row['OBJECT_NAME'] . ' (' . $row['OBJECT_TYPE'] . ')', 1);
    flush();
    ob_flush();

    ###

    if (strpos($row['OBJECT_NAME'], 'BIN$') !== false)
    {
      add_log('Object already in recycle bin, skipping drop.');
      continue;
    }
    { #any update

      ###
      if ($type == 'database link')
      {
        update_dblink($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP DATABASE LINK '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'index')
      {
        update_indexes($bkp_dir, $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP INDEX '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'type')
      {
        if (strpos($row['OBJECT_NAME'], 'SYS_') !== FALSE)
          continue;
        update_type($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP TYPE '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'job')
      {
        update_job($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP JOB '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'view')
      {
        update_view($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP VIEW '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'synonym')
      {
        update_synonyms($bkp_dir, $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP SYNONYM '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if ($type == 'materialized view')
      {
        update_matview($bkp_dir,$row['OBJECT_NAME'],$row['OWNER'], $this_update_begin_time);
        oracle_query('DROP MATERIALIZED VIEW '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
      if (in_array($row['OBJECT_TYPE'], $objectsobjects))
      {
        update_object($bkp_dir, $row['OBJECT_NAME'], $row['OBJECT_TYPE'], $row['OWNER'], $this_update_begin_time);
        oracle_query('DROP '.$row['OBJECT_TYPE'].' '.$row['OWNER'].'.'.$row['OBJECT_NAME'], $conn, 1);
      }
    }
    if (!$primary)
    {
      #in case update will be interrupted - we need to save our state.
      file_put_contents($bkp_dir . 'last_time.txt', $new_last_event_id . "\n" . $cur_time);
    }
  }
  oci_free_statement($stid);
  $message = 'Update from event '.$new_last_event_id . " to time " . $cur_time;
  send_git($bkp_dir, 'git add .');
  send_git($bkp_dir, 'git commit -am "' . $message . '";');

  if ($curr < $total)
    add_log('Shit happened! Not all exported! Session died? Try one more time!', 1);
  else
  {
    add_log("[ 100 % ]", 1);
    add_log("Base exported!", 1);
  }
}
add_log('Complete time: ' . date('H:i:s'), 1);
if ($new_last_event_id)
  file_put_contents($bkp_dir . 'last_time.txt', $new_last_event_id . "\n" . $cur_time);
fclose($file_handle);
?>