#!/usr/bin/php
<?
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
  'SNAPSHOT', 'SEQUENCE', 'PROCEDURE', 'TRIGGER', 'OBJECT PRIVILEGE', 'SYSTEM PRIVILEGE', 'ROLE PRIVILEGE', 'TYPE', 'GRANT');
$objectsobjects = array('PACKAGE', 'PACKAGE BODY', 'FUNCTION', 'PROCEDURE', 'TRIGGER');

if (!$last_event_id)
{
  add_log('Exporting users', 1);
  update_users($bkp_dir);
  add_log('Exporting tablespaces', 1);
  update_tablespaces($bkp_dir);
  add_log('Exporting database links', 1);
  update_dblinks($bkp_dir);
}

#update updated
#how many?
if ($last_event_id)
{
  $sql = "select count(1) as cnt from magic.ddllog where sysevent in('COMMENT','GRANT','ALTER','CREATE','TRUNCATE','DROP') and event_id>:id and dict_obj_owner not in ('SYS','SYSMAN')";
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
  $sql = 'select count(1) as cnt from all_objects';
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
  if ($last_event_id)
  {
    add_log('Logging updates from event id ' . $last_event_id, 1);
    $sql = "select event_id,dict_obj_type as object_type,dict_obj_owner as owner,dict_obj_name as object_name,host,
    os_user,obj_current_ddl as ddl from magic.ddllog where sysevent in('COMMENT','GRANT','ALTER','CREATE','TRUNCATE','REVOKE','DROP') and dict_obj_owner not in ('SYS','SYSMAN')
     and event_id>:id";
    #if ($test)
    #  $sql .= " and owner='EAS_RU_3_23'";
    $sql .= " order by event_id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":id", $last_event_id);
    oci_execute($stid);
  }
  else
  {
    #need to update all. get current state as event id
    $sql = 'select max(event_id) from magic.ddllog';
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $new_last_event_id = $row[0];
    add_log('Gonna start a brand new DDL versioning. We will export full DB and then start logging updates from event id ' . $new_last_event_id, 1);
    $primary = 1;
    $sql = "select owner,object_type,object_name from all_objects where owner not in ('SYS','SYSMAN')";
    #if ($test)
    #  $sql .= " and owner='EAS_RU_3_23'";
    $sql .= " order by owner,object_name";
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
  }
  $curr = 0; #just step counter to count % complete
  $first_sery_event_id = $last_sery_event_id = 0;
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    $curr++;
    if (!in_array($row['OBJECT_TYPE'], $accepted))
      continue;

    if ($primary)
    {
      $row['OS_USER'] = 'Primary Export, user unknown';
      $row['HOST'] = 'host unknown';
    }
    if (!$primary)
    {
      if (!$row['OS_USER'])
        $row['OS_USER'] = '?';
      if (!$row['HOST'])
        $row['HOST'] = '?';
      $user = $row['OS_USER'] . ' (' . $row['HOST'] . ')';
      $new_last_event_id = $row['EVENT_ID'];


      if ($row['SYSEVENT'] === 'DROP')
        $action = 'Drop';
      else
        $action = 'Update';
      if ($lastuser && ($lastuser != $user) || $lastaction && ($lastaction != $action))
      {
        if ($first_sery_event_id == $last_sery_event_id)
          $message = $action . ' by ' . $lastuser . ', event ' . $first_sery_event_id;
        else
          $message = $action . ' by ' . $lastuser . ', events ' . $first_sery_event_id . '-' . $last_sery_event_id;
        $first_sery_event_id = 0;
        send_git($bkp_dir, 'git add .');
        send_git($bkp_dir, 'git commit -am "' . $message . '";');
      }
      $lastuser = $user;
      $lastaction = $action;

      $last_sery_event_id = $row['EVENT_ID'];
      if ($first_sery_event_id == 0)
        $first_sery_event_id = $row['EVENT_ID'];
    }

    if (!$row['OWNER']) //cascade operation or role grant or some kind of other shit
    {
      if (strpos($row['OBJECT_TYPE'], 'PRIVILEGE') !== FALSE)
      {
        if(stripos($row['OBJECT_NAME'],'SYS_PLSQL_')===0)
          continue;
        //get owner from parsing blob data
        if (is_object($row['DDL']))
        {
          $sql = $row['DDL']->load(); #get blob contents
          $row['OWNER'] = get_owner_from_grant_query($sql);
          if (!$row['OWNER'])
            add_log('Error: can not detect ddl owner from grant query, skipping!', 1);
        }
      }
      elseif ($row['OBJECT_NAME']) # for database links and users
      {
        $row['OWNER'] = $row['OBJECT_NAME']; #yup, owner is in object name
      }
      else
      {
        add_log('Error: can not detect ddl owner, skipping!', 1);
        continue;
      }
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
    if ($row['SYSEVENT'] === 'DROP')
    {
      if ($type == 'user')
      {
        rrmdir($owner_dir);
      }
      if ($type == 'table')
      {
        update_table($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time, 1);
      }
      if ($type == 'index')
      {
        update_indexes($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'sequence')
      {
        @unlink($type_dir . $name . '.sql');
      }
      if ($type == 'type')
      {
        @unlink($type_dir . $name . '.sql');
      }
      if ($type == 'job')
      {
        @unlink($type_dir . $name . '.sql');
      }
      if ($type == 'snapshot') #materialized view
      {
        @unlink($type_dir . $name . '.sql');
      }
      if ($type == 'view')
      {
        @unlink($type_dir . $name . '.sql');
      }
      if ($type == 'synonym')
      {
        update_synonyms($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
      if (in_array($row['OBJECT_TYPE'], $objectsobjects))
      {
        $obj = $type_dir . '/' . $name . '.plsql';
        @unlink($obj);
      }
    }
    else
    { #any update

      ###
      if ($type == 'grant')
      {
        update_grants($bkp_dir, $row['OWNER'], $this_update_begin_time, 1);
      }
      if ($type == 'table')
      {
        update_table($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'database link')
      {
        update_dblink($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'index')
      {
        update_indexes($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'user')
      {
        update_user($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'tablespace')
      {
        update_tablespace($bkp_dir, $row['OBJECT_NAME'], $this_update_begin_time);
      }
      if ($type == 'type')
      {
        if (strpos($row['OBJECT_NAME'], 'SYS_') !== FALSE)
          continue;
        update_type($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'sequence')
      {
        update_sequence($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'job')
      {
        update_job($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'snapshot')
      {
        update_matview($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'view')
      {
        update_view($bkp_dir, $row['OBJECT_NAME'], $row['OWNER'], $this_update_begin_time);
      }
      if ($type == 'synonym')
      {
        update_synonyms($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
      if (in_array($row['OBJECT_TYPE'], $objectsobjects))
      {
        update_object($bkp_dir, $row['OBJECT_NAME'], $row['OBJECT_TYPE'], $row['OWNER'], $this_update_begin_time);
      }
      if (strpos($row['OBJECT_TYPE'], 'PRIVILEGE') !== FALSE)
      {
        update_grants($bkp_dir, $row['OWNER'], $this_update_begin_time);
      }
    }
    if (!$primary)
    {
      #in case update will be interrupted - we need to save our state.
      file_put_contents($bkp_dir . 'last_time.txt', $new_last_event_id . "\n" . $cur_time);
    }
  }
  oci_free_statement($stid);
  if ($primary)
    $message = 'primary export';
  if ($row['SYSEVENT'] === 'DROP')
    $action = 'Drop';
  else
    $action = 'Update';
  if ($first_sery_event_id == $last_sery_event_id)
    $message = $action . ' by ' . $lastuser . ', event ' . $first_sery_event_id;
  else
    $message = $action . ' by ' . $lastuser . ', events ' . $first_sery_event_id . '-' . $last_sery_event_id;
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
add_log("Pushing to remote repo...", 1);
//send_git($bkp_dir,'git push git_ora master');
send_git($bkp_dir, 'git push backup master');
add_log('Complete time: ' . date('H:i:s'), 1);
if ($new_last_event_id)
  file_put_contents($bkp_dir . 'last_time.txt', $new_last_event_id . "\n" . $cur_time);
fclose($file_handle);
?>