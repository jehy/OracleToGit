<?
if (!defined('TRY_RESUME_ON_NO_DB'))
  define('TRY_RESUME_ON_NO_DB', 0);

if (!defined('DEBUG'))
  define('DEBUG', 0);

if (!defined('NOLOG'))
  define('NOLOG', 1);

if (!defined('MAX_CONNECT_TIME'))
  define('MAX_CONNECT_TIME', 0);

function set_log($fname)
{
  global $CURR_LOG;
  if ($fname)
  {
    $CURR_LOG = date('Y-m-d H-i-s ') . $fname . '.html';
    add_log('Пользователь: ' . $_REQUEST['clogin'] . ' (' . $_SERVER['REMOTE_ADDR'] . '), ' . date('Y-m-d H:i:s'), 0);
  }
  else
    $CURR_LOG = '';
}

if (!function_exists('convert_db_encoding'))
{
  function convert_db_encoding($smth)
  {
    return $smth;
  }
}

function add_log($log, $show = 0)
{
  global $CURR_LOG;
  if (!$log)
    return;
  if ($CURR_LOG && (@constant('NOLOG') != 1))
  {
    $dir = './logs/' . date('Y-m-d');
    @mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . $CURR_LOG, "\n<br>" . $log, FILE_APPEND);
  }
  if ($show)
    echo "\n<br>" . $log;
}

function get_failover_connect($id)
{
  global $CONNECTS;
  $time1 = time();
  $res = false;
  $i = 0;
  $c = $CONNECTS[$id];
  add_log('<div>Устанавливаем коннект к ' . $c['tns'] . $c['connect'] . '.</div>', 0);
  while ($res == false && $i < 10)
  {
    if ($c['tns'])
      $res = @oci_connect($c['scheme'], $c['pass'], $c['tns'], $c['enc'], $c['mode']);
    else
      $res = @oci_connect($c['scheme'], $c['pass'], $c['connect'], $c['enc'], $c['mode']);
    if ($res == false)
    {
      $i++;
      $e = oci_error();
      add_log('<br>Коннект не удался (' . htmlentities($e['message'], ENT_QUOTES) . '). Обождём и попытаем счастья снова (' . $c['scheme'] . '@' . $c['connect'] . ')', DEBUG);
      if (MAX_CONNECT_TIME && ((time() - $time1) > MAX_CONNECT_TIME))
      {
        add_log('<div class="error">Исчерпано максимальное время на подключение.</div>', DEBUG);
        break;
      }
      flush();
      ob_flush();
      sleep(1);
    }
    else
      return $res;
  }
  add_log('<div class="error">Исчерпан временной интервал подключения.</div>', DEBUG);
  if (!TRY_RESUME_ON_NO_DB)
  {
    add_log('<div class="error">Терминация</div>', DEBUG);
    die('Ведутся работы на сервисе, он будет вновь доступен в кратчайшее время.');
  }
  else
  {
    add_log('<div class="error">Пробуем работать, несмотря ни на что.</div>', DEBUG);
    return false;
  }
}

function get_connect()
{
  global $CURR_SESSION, $CONNECT_POOL;
  if (!$CONNECT_POOL[$CURR_SESSION])
  {
    $CONNECT_POOL[$CURR_SESSION] = get_failover_connect($CURR_SESSION);
  }
  return $CONNECT_POOL[$CURR_SESSION];
}


function set_curr_session($s)
{
  global $CURR_SESSION;
  $CURR_SESSION = $s;
}

function oracle_execute($stid, $mode = OCI_COMMIT_ON_SUCCESS)
{
  $result = @oci_execute($stid, $mode);
  $counter = 0;
  if (!$result)
  {
    $counter++;
    $m = oci_error($stid);
    #echo '!!';print_R($m);echo '!!';
    $message = '<ul><div id="err_' . $counter . '" class="error"';
    #if(in_array((int)$m['code'],$normal_errors))
    #	$message.=' style="display:none"';
    $message .= '>' . $m['message'] . '<br><pre>' . htmlentities($m['sqltext']) . '</pre></div></ul>';
    #if(in_array((int)$m['code'],$normal_errors))
    #	$message='<ul><font color="orange" onclick="ToggleVisible('."'".'err_'.$counter."'".');">Ooooh</font></ul>'.$message;
    add_log($message, DEBUG);
    return $result;
  }
  return $stid;
}

function oracle_query($sql, $conn = false, $noecho = 0, $log = 1, $count = 0)
{
  global $normal_errors, $counter;
  $x1 = stripos($sql, 'update');
  $x2 = stripos($sql, 'delete');
  if ($x1 !== FALSE && ($x1 < 5) || $x2 !== FALSE && ($x2 < 5))
    $count = 1;
  // нужно считать количество затронутых строк
  if ($log)
    add_log($sql, 0);
  if (!$conn)
    $conn = get_connect();
  if (!$conn && TRY_RESUME_ON_NO_DB)
    return false;
  $stid = oci_parse($conn, $sql);
  if (!$stid)
  {
    $message = '<ul><div id="err_' . $counter . '" class="error"><pre>' . htmlentities($sql) . '</pre></div></ul>';
    add_log($message, DEBUG);
    return $stid;
  }
  $result = @oci_execute($stid);
  if (!$result && (@constant('HIDE_ERRS') <> 1))
  {
    $counter++;
    $m = oci_error($stid);
    #echo '!!';print_R($m);echo '!!';
    $message = '<ul><div id="err_' . $counter . '" class="error"';
    #if(in_array((int)$m['code'],$normal_errors))
    #	$message.=' style="display:none"';
    $message .= '>' . $m['message'] . '<br><pre>' . htmlentities($m['sqltext']) . '</pre></div></ul>';
    #if(in_array((int)$m['code'],$normal_errors))
    #	$message='<ul><font color="orange" onclick="ToggleVisible('."'".'err_'.$counter."'".');">Ooooh</font></ul>'.$message;
    add_log($message, DEBUG);
    return $result;
  }
  elseif (!$noecho)
  {
    #echo '<ul><font color="green">Done: '.$sql.'</font></ul>';
    show_query_result($stid, 'table', $count);
    oci_free_statement($stid);
    return $stid;
  }
  return $stid;
}

function set_grants($grants)
{
  global $normal_errors;
  $normal_errors[] = 942;
  foreach ($grants as $val)
    oracle_query($val);
  foreach ($normal_errors as $key => $val)
    if ($val == 942)
      unset($normal_errors[$key]);
}

function get_user_objects($type, $owner = 0)
{
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
    oci_free_statement($stid);
  }
  $binds = array();
  if (!is_array($owner))
    $owner = array($owner);
  $i = 0;
  foreach ($owner as $val)
  {
    $binds[] = array('name' => ':var' . $i, 'val' => $val);
    $i++;
  }
  $arr = array();
  $sql = "select object_name from all_objects where object_type = :type";
  if (sizeof($owner) == 1)
    $sql .= ' and owner=:var0';
  else
  {
    $vars = array();
    foreach ($binds as $arrs)
      $vars[] = $arrs['name'];
    $sql .= ' and owner in (' . implode(',', $vars) . ')';
  }
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  foreach ($binds as $key => $arrs)
    oci_bind_by_name($stid, $binds[$key]['name'], $binds[$key]['val']);
  oci_bind_by_name($stid, ":type", $type);
  oci_execute($stid);
  while ($row = oci_fetch_row($stid))
    $arr[] = $row[0];
  oci_free_statement($stid);
  return $arr;
}

function get_constraints($owner = 0)
{
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
    oci_free_statement($stid);
  }
  $owner2 = $owner;

  $sql = "select constraint_name,constraint_type from all_constraints where owner =:owner and generated='USER NAME' and  constraint_name not like 'BIN$%' order by constraint_type,constraint_name"; //not from recycle bin
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":owner", $owner2);
  oci_execute($stid);
  $arr = array();
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    if ($row['CONSTRAINT_TYPE'] == 'R')
      $sql = "select  dbms_metadata.get_ddl ('REF_CONSTRAINT', :name,:owner) from dual";
    else
      $sql = "select  dbms_metadata.get_ddl ('CONSTRAINT', :name,:owner) from dual";

    $stid2 = oci_parse($conn, $sql);
    oci_bind_by_name($stid2, ":owner", $owner2);
    oci_bind_by_name($stid2, ":name", $row['CONSTRAINT_NAME']);
    oci_execute($stid2);

    $row = oci_fetch_row($stid2);
    if (is_object($row[0]))
    {
      $c = trim($row[0]->load());
      #$c = str_replace('"' . $owner . '".', '', $c);
      $arr[] = $c;
    }
    oci_free_statement($stid2);
  }
  oci_free_statement($stid);
  return $arr;
}

function get_indexes($owner = 0)
{
  $arr = get_user_objects('INDEX', $owner);
  $arr2 = array();
  foreach ($arr as $name)
    $arr2[$name] = get_object_with_metadata('INDEX', $name, $owner);
  return $arr2;
}

/*
function get_objects2($type,$owner=0)
{
  $type2view=array('index'=>'all_indexes','synonym'=>'all_synonyms','dblink'=>'dba_db_link');
  $type2name=array('index'=>'index_name','synonym'=>'synonym_name','db_link'=>'db_link');
  $type2metadata=array('index'=>'INDEX','synonym'=>'SYNONYM','dblink'=>'DB_LINK');
  if(strpos($owner,',')!==false)
  {
    $owner=explode(',',$owner);
  }
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
  }
  $binds=array();
  if(!is_array($owner))
    $owner=array($owner);
  $i=0;
  foreach($owner as $key=>$val)
  {
    $binds[]=array('name'=>':var'.$i,'val'=>$val);
    $i++;
  }

  $from=$type2view[$type];
  if(!$from)
  {
    add_log('Error: Unknown type '.$type.'!',1);
    return false;
  }
  $col=$type2name[$type];
  $meta_type=$type2metadata[$type];
  $sql='select owner,'.$col.' from '.$from.' where';
  if(sizeof($owner)==1)
    $sql.=' owner=:var0';
  else
  {
    $vars=array();
    foreach ($binds as $arrs)
      $vars[]=$arrs['name'];
    $sql.='owner in ('.implode(',',$vars).')';
  }
   foreach ($binds as $key=>$arrs)
     oci_bind_by_name($stid, $binds[$key]['name'], $binds[$key]['val']);
  add_log($sql,1);
  oci_execute($stid);
  $res=array();
  while($row = oci_fetch_row($stid))
    $res[]=get_object_with_metadata($meta_type,$row[1],$row[0]);
  return $res;
}
*/
function get_job($name, $owner = 0)
{
  return get_object_with_metadata('PROCOBJ', $name, $owner);
}


function get_matview($name, $owner = 0)
{
  return get_object_with_metadata('MATERIALIZED_VIEW', $name, $owner);
}

function get_table($name, $owner = 0)
{
  if (stripos($name, 'ORA_TMP') !== FALSE)
  {
    add_log('Temporary table, skipping', 1);
    return false;
  }
  if (strpos($name, 'DBMS_TABCOMP') !== FALSE)
  {
    add_log('Temporary table, skipping', 1);
    return false;
  }

  if (strpos($name, '==') !== FALSE)
  {
    add_log('System oracle table, skipping', 1);
    return false;
  }
  global $GET_TABLES_PREPARED;
  if (!$GET_TABLES_PREPARED)
  {
    $sql = "
begin
dbms_metadata.set_transform_param( DBMS_METADATA.SESSION_TRANSFORM, 'REF_CONSTRAINTS', false );
dbms_metadata.set_transform_param( DBMS_METADATA.SESSION_TRANSFORM, 'CONSTRAINTS', false );
end;";
    oracle_query($sql, false, 1);
    $GET_TABLES_PREPARED = 1;
  }

  return get_object_with_metadata('TABLE', $name, $owner);
}

function get_tables()
{
  $arr = get_user_objects('TABLE');
  $arr2 = array();
  foreach ($arr as $name)
    if ($val = get_table($name))
      $arr2[$name] = $val;
  return $arr2;
}

function get_views()
{
  $arr = get_user_objects('VIEW');
  $arr2 = array();
  foreach ($arr as $name)
    $arr2[$name] = get_view($name);
  return $arr2;
}

function get_sequences()
{
  $arr = get_user_objects('SEQUENCE');
  $arr2 = array();
  foreach ($arr as $name)
    $arr2[$name] = get_sequence($name);
  return $arr2;
}


function get_types()
{
  $arr = get_user_objects('TYPE');
  $arr2 = array();
  foreach ($arr as $name)
    if ($val = get_type($name))
      $arr2[$name] = $val;
  return $arr2;
}

function get_view($name, $owner = 0)
{
  return get_object_with_metadata('VIEW', $name, $owner);
}

function get_object_with_metadata($type, $name, $owner = 0)
{
  if (constant('VERBOSE'))
    add_log('Getting object (' . $type . ' ' . $owner . '.' . $name . ') source', 1);
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
  }
  $owner2 = $owner;
  $sql = "select  dbms_metadata.get_ddl (:type,:name,:owner2) from dual";

  $conn = get_connect();
  if (constant('VERBOSE'))
    add_log('Parsing', 1);
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":type", $type);
  oci_bind_by_name($stid, ":owner2", $owner2);
  oci_bind_by_name($stid, ":name", $name);
  if (constant('VERBOSE'))
    add_log('Executing', 1);
  oci_execute($stid);

  $row = oci_fetch_row($stid);
  #$name = strtolower($name);
  $blo = false;
  if (constant('VERBOSE'))
    add_log('Reading blob', 1);
  if (is_object($row[0]))
  {
    $blo = $row[0]->read(1024 * 1024); #1 MB object. Should not be more.
    #$blo = str_replace('"' . $owner . '".', '', $blo);
    while ($blo != ($blo2 = trim($blo)))
      $blo = $blo2;
  }
  oci_free_statement($stid);
  if (constant('VERBOSE'))
    add_log('Got source', 1);
  return $blo;
}

function get_type($name, $owner = 0)
{
  if (strpos($name, 'SYS_PLSQL') !== FALSE)
  {
    add_log('Ignoring system type "' . $name . '"', 1);
    return false;
  }
  return get_object_with_metadata('TYPE', $name, $owner);
}

function get_sequence($name, $owner = 0)
{
  return get_object_with_metadata('SEQUENCE', $name, $owner);
}


function get_objects($name = '', $type = '', $owner = 0)
{

  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
    oci_free_statement($stid);
  }
  $binds = array();
  if (!is_array($owner))
    $owner = array($owner);
  $i = 0;
  foreach ($owner as $val)
  {
    $binds[] = array('name' => ':var' . $i, 'val' => $val);
    $i++;
  }

  $arr = array();
  #user_source_current is a custom view!
  $sql = "select name,text,type from all_source where type<>'TYPE' ";
  if ($name)
    $sql .= " and name = :name";
  if ($type)
    $sql .= " and type = :type";

  if (sizeof($owner) == 1)
    $sql .= ' and owner=:var0';
  else
  {
    $vars = array();
    foreach ($binds as $arrs)
      $vars[] = $arrs['name'];
    $sql .= ' and owner in (' . implode(',', $vars) . ')';
  }
  $sql .= " order by line";

  $conn = get_connect();
  $stid = oci_parse($conn, $sql);

  foreach ($binds as $key => $arrs)
    oci_bind_by_name($stid, $binds[$key]['name'], $binds[$key]['val']);
  if ($name)
    oci_bind_by_name($stid, ':name', $name);
  if ($type)
    oci_bind_by_name($stid, ':type', $type);
  oci_execute($stid);
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    $row['TYPE'] = strtolower($row['TYPE']);
    $row['NAME'] = strtolower($row['NAME']);
    if (!$arr[$row['TYPE']][$row['NAME']])
      $arr[$row['TYPE']][$row['NAME']] = 'CREATE OR REPLACE ';
    if (strlen(trim($row['TEXT']))) #fuck the empty rows
      $arr[$row['TYPE']][$row['NAME']] .= $row['TEXT'];
  }
  oci_free_statement($stid);
  return $arr;
}


function get_invalid_objects($owner = '', $extra = 1)
{
  # $sql="SELECT owner,'' as sqlq
  $sql = "SELECT owner,object_type,owner,object_name
  FROM   dba_objects
  WHERE  status = 'INVALID' and object_type in('PACKAGE','PROCEDURE','FUNCTION','TRIGGER','VIEW','PACKAGE BODY','SYNONYM','TYPE BODY')";
  if ($owner)
    $sql .= " and owner like :owner";
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  if ($owner)
  {
    $owner = '%' . strtoupper($owner) . '%';
    oci_bind_by_name($stid, ":owner", $owner);
  }
  oci_execute($stid);
  $arr = array();
  $i = 0;
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    if ($row['OBJECT_TYPE'] == 'SYNONYM')
    {
      if ($extra)
        add_log('Внимание: синоним-инвалид: ' . $row['OWNER'] . '.' . $row['OBJECT_NAME'] . '<br>', 1);
    }
    if (in_array($row['OBJECT_TYPE'], array('PACKAGE BODY', 'TYPE BODY')))
    {
      $o = explode(' ', $row['OBJECT_TYPE']);
      $sql = 'ALTER ' . $o[0] . ' ' . $row['OWNER'] . '.' . $row['OBJECT_NAME'] . ' compile body';
    }
    else
      $sql = 'ALTER ' . $row['OBJECT_TYPE'] . ' ' . $row['OWNER'] . '.' . $row['OBJECT_NAME'] . ' compile';
    $i++;
    if ($sql)
      $arr[$row['OWNER']][] = $sql;
  }
  $arr['obj_count'] = $i;
  oci_free_statement($stid);
  return $arr;
}

function get_dblink($name, $owner = 0)
{
  return get_object_with_metadata('DB_LINK', $name, $owner);
}

function get_dba_dblinks()
{
  $arr = array();
  $sql = "select owner,db_link from dba_db_links"; //"all_db_links" only gets current user's dblinks, need to USE DBA
  $stid = oracle_query($sql, false, 1);
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    $arr[$row['OWNER']][$row['DB_LINK']] = get_dblink($row['DB_LINK'], $row['OWNER']);
  oci_free_statement($stid);
  return $arr;
}

function get_all_tablespaces()
{
  $arr = array();
  $sql = 'select tablespace_name from dba_tablespaces';
  $stid = oracle_query($sql, false, 1);
  while ($row = oci_fetch_row($stid))
    $arr[$row[0]] = get_tablespace($row[0]);
  oci_free_statement($stid);
  return $arr;
}

function get_tablespace($name)
{
  $res = false;
  $sql = "select DBMS_METADATA.get_ddl('TABLESPACE',:name) from dual";
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":name", $name);
  oci_execute($stid);

  $row = oci_fetch_row($stid);
  if (is_object($row[0]))
    $res = $row[0]->load();
  oci_free_statement($stid);
  return $res;
}

function get_all_dblinks($owner = 0)
{
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
  }
  $owner2 = $owner;
  $arr = array();
  $sql = "select db_link from dba_db_links where owner = :owner  order by db_link"; //"all_db_links" only gets current user's dblinks, need to USE DBA
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":owner", $owner2);
  oci_execute($stid);
  while ($row = oci_fetch_row($stid))
    $arr[] = get_dblink($row[0], $owner2);
  oci_free_statement($stid);
  return $arr;
}

function get_all_users()
{
  $sql = 'select username from dba_users';
  $stid = oracle_query($sql, false, 1);
  $arr = array();
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    $arr[$row['USERNAME']] = get_user($row['USERNAME']);
  oci_free_statement($stid);
  return $arr;
}

function get_user($owner = 0)
{
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    oci_free_statement($stid);
    $owner = $row[0];
  }
  $owner2 = $owner;
  $res = false;
  $sql = "select DBMS_METADATA.get_ddl ('USER',:owner2) from dual";
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":owner2", $owner2);
  oci_execute($stid);

  $row = oci_fetch_row($stid);
  if (is_object($row[0]))
    $res = $row[0]->load();
  oci_free_statement($stid);
  return $res;
}

function get_synonyms($owner = 0)
{
  if ($owner === 'PUBLIC')
  {
    add_log('Skipping PUBLIC synonyms', 1); //too fucken many of them
    return false;
  }
  if (constant('VERBOSE'))
    add_log('Getting user objects (synonyms)', 1);
  $arr = get_user_objects('SYNONYM', $owner);
  $arr2 = array();
  if (constant('VERBOSE'))
    add_log('Got user objects (synonyms), getting their source', 1);
  foreach ($arr as $name)
    $arr2[$name] = get_synonym($name, $owner);
  if (constant('VERBOSE'))
    add_log('Got user objects (synonyms) source', 1);
  return $arr2;
}


function get_synonym($name = 0, $owner = 0)
{
  return get_object_with_metadata('SYNONYM', $name, $owner);
}

function get_owner_from_grant_query($sql)
{
  $sql = explode('\n', $sql);
  foreach ($sql as $key => $val)
  {
    while (($v2 = trim($val)) != $val)
      $val = $v2;
    $pos = strpos($val, '--');
    if ($pos == 0)
    {
      unset($sql[$key]);
      continue;
    }
    else
      $val = substr($val, 0, $pos);
    $sql[$key] = $val;
  }
  $sql = implode(' ', $sql);
  $sql2 = explode($sql, ' TO '); #for GRANT query
  if (sizeof($sql2) != 2)
    $sql2 = explode($sql, ' FROM ');
  #for REVOKE query
  if (sizeof($sql2) != 2)
    return FALSE;
  $owner = $sql2[1];
  while (($o = trim($owner)) != $owner)
    $owner = $o;
  return $o;
}

function get_grants($owner = 0, $table = '')
{
  if (!$owner)
  {
    $sql = "select sys_context( 'userenv', 'current_schema') from dual";
    $stid = oracle_query($sql, false, 1);
    $row = oci_fetch_row($stid);
    $owner = $row[0];
  }
  $owner2 = $owner;
  $conn = get_connect();

  $grants = array();
  if (!$table)
  {
    $sql = "select PRIVILEGE,GRANTEE,admin_option from dba_sys_privs where grantee=:owner2 order by grantee,privilege";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":owner2", $owner2);
    oci_execute($stid);

    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      $sql = 'GRANT ' . $row['PRIVILEGE'] . ' TO ' . $row['GRANTEE'];
      if ($row['ADMIN_OPTION'] === 'YES')
        $sql .= ' WITH ADMIN OPTION';
      $grants[] = $sql;
    }
    oci_free_statement($stid);
    $sql = "select GRANTED_ROLE,GRANTEE,admin_option from  dba_role_privs where grantee=:owner2 order by grantee,granted_role";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":owner2", $owner2);
    oci_execute($stid);
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      $sql = 'GRANT ' . $row['GRANTED_ROLE'] . ' TO "' . $row['GRANTEE'] . '"';
      if ($row['ADMIN_OPTION'] === 'YES')
        $sql .= ' WITH ADMIN OPTION';
      $grants[] = $sql;
    }
    oci_free_statement($stid);
  }
  $sql = "select PRIVILEGE,OWNER,TABLE_NAME,GRANTEE,grantable from DBA_TAB_PRIVS t where grantee=:owner2";
  if ($table)
    $sql .= " and table_name=:tbl";
  $sql .= ' order by grantee,owner,table_name,privilege';
  #add_log($sql,1);
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":owner2", $owner2);
  #add_log('binding :owner2 to '.$owner2,1);
  if ($table)
  {
    $t = strtoupper($table);
    oci_bind_by_name($stid, ":tbl", $t);
    #add_log('binding :tbl to '.$t,1);
  }
  oci_execute($stid);

  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    $sql = 'GRANT ' . $row['PRIVILEGE'] . ' ON "' . $row['OWNER'] . '"."' . $row['TABLE_NAME'] . '" TO "' . $row['GRANTEE'] . '"';
    if ($row['GRANTABLE'] === 'YES')
      $sql .= ' WITH GRANT OPTION';
    $grants[] = $sql;
  }
  oci_free_statement($stid);
  return $grants;
}

function show_query_result($stid, $format = 'table', $count = 0)
{
  $title = 0;
  $rows = '';
  if ($count)
  {
    $rows = oci_num_rows($stid);
    if ($rows !== false)
      $rows = $rows . ' строк затронуто';
  }
  if ($format == 'table')
  {
    $r = '';
    while ($row = @oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      if ($title == 0)
      {
        $r = '<table border="1px" cellpadding="2px" cellspacing="0">';
        $r .= '<tr>';
        foreach ($row as $key => $item)
          $r .= '<td>' . str_replace('"', '', $key) . '</td>';
        $r .= '</tr>';
        $title = 1;
      }
      $r .= "<tr>\n";
      foreach ($row as $item)
      {
        if (!$item)
          $item = '&nbsp;';
        if (is_object($item))
        {
          $o = var_export($item, 1);
          $r .= '<td>' . $o . '</td>';
        }
        else
          $r .= '<td>' . $item . "</td>\n";
      }
      $r .= "</tr>\n";
    }
    if ($title)
      $r .= '</table>';
    $r = $rows . $r;
    add_log($r, 1);
  }
  elseif ($format == 'csv')
  {
    $r = '';
    while ($row = @oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      $row2 = array();
      if ($title == 0)
      {
        foreach ($row as $key => $item)
          $row2[] = '"' . $key . '"';
        $title = 1;
        $r .= implode(',', $row2);
        $r .= "\n";
        $row2 = array();
      }
      foreach ($row as $item)
      {
        $item = str_replace(array("\n", "\r"), '', $item);
        $item = str_replace('"', "", $item);
        $item = '"' . $item . '"';
        $row2[] = $item;
      }
      $r .= implode(',', $row2);
      $r .= "\n";
    }
    $r = $rows . $r;
    add_log($r, 1);
  }
  #return $r;
}


function alter_constraints($owner, $mode)
{
  if ($mode)
    $status = 'ENABLE';
  else
    $status = 'DISABLE';
  $sql = "BEGIN
  FOR c IN
  (SELECT c.owner, c.table_name, c.constraint_name
   FROM dba_constraints c, dba_tables t
   WHERE c.table_name = t.table_name
   AND c.owner = '" . $owner . "'
   AND t.owner='" . $owner . "'
   )
  LOOP
    begin
      dbms_utility.exec_ddl_statement('alter table ' || c.owner || '.' || c.table_name || ' " . $status . " constraint ' || c.constraint_name";
  if (!$mode)
    $sql .= " ||' cascade'";
  $sql .= ");
      exception
      when others then
        null;
    end;
  END LOOP;
END;";
  oracle_query($sql);
}

function alter_triggers($owner, $mode)
{
  if ($mode)
    $status = 'ENABLE';
  else
    $status = 'DISABLE';
  $sql = "BEGIN
  FOR c IN
  (SELECT c.owner, c.table_name,c.trigger_name
   FROM dba_triggers c, dba_tables t
   WHERE c.table_name = t.table_name
   AND c.owner = '" . $owner . "'
   AND t.owner='" . $owner . "'
   )
  LOOP
    dbms_utility.exec_ddl_statement('alter trigger ' || c.owner || '.' || c.trigger_name || ' " . $status . "');
  END LOOP;
END;";
  oracle_query($sql);
}


# recursively remove a directory
function rrmdir($dir)
{
  foreach (glob($dir . '/*') as $file)
  {
    if (is_dir($file))
      rrmdir($file);
    else
      unlink($file);
  }
  rmdir($dir);
}


function update_object($bkp_dir, $name, $type, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . strtolower($type) . '/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.plsql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    $src = get_objects($name, $type, $owner);
    foreach ($src as $arr)
      foreach ($arr as $text)
        file_put_contents($file, $text);
  }
}

#several funcs
function update_grants($bkp_dir, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'grants.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if (sizeof($f = get_grants($owner)))
      file_put_contents($file, implode(";\n", $f) . ';'); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function update_indexes($bkp_dir, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'indexes.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if (sizeof($f = get_indexes($owner)))
      file_put_contents($file, implode(";\n", $f) . ';'); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function update_table($bkp_dir, $name, $owner, $this_update_begin_time, $delete = 0)
{
  if (in_array($name, array('DBMS_TABCOMP_TEMP_UNCMP', 'DBMS_TABCOMP_TEMP_CMP')))
  {
    add_log('Skipping segment advisor table');
    return;
  }
  if (strpos($name, 'ORA_TEMP_') !== FALSE)
  {
    add_log('Skipping temporary tables');
    return;
  }

  if (strpos($name, 'SYS_IOT') !== FALSE)
  {
    add_log('Skipping abother system shit');
    return;
  }

  if (strpos($name, 'DBMS_TABCOMP') !== FALSE)
  {
    add_log('Skipping abother system shit');
    return;
  }
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'table/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';

  if ($delete)
  {
    @unlink($file);
    update_grants($bkp_dir, $owner, $this_update_begin_time);
    update_indexes($bkp_dir, $owner, $this_update_begin_time);
    update_synonyms($bkp_dir, $owner, $this_update_begin_time);
    update_constraints($bkp_dir, $owner, $this_update_begin_time);
  }
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    update_grants($bkp_dir, $owner, $this_update_begin_time);
    update_indexes($bkp_dir, $owner, $this_update_begin_time);
    update_synonyms($bkp_dir, $owner, $this_update_begin_time);
    update_constraints($bkp_dir, $owner, $this_update_begin_time);

    if ($f = get_table($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}


function update_sequence($bkp_dir, $name, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'sequence/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_sequence($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}


function update_job($bkp_dir, $name, $owner, $this_update_begin_time)
{
  if ($owner === 'SYS') #sys job source is not available
    return;
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'job/' . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_job($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}


function update_dblink($bkp_dir, $name, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'dblink/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_dblink($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}


function update_type($bkp_dir, $name, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'type/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_type($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function update_view($bkp_dir, $name, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'view/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_view($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}


function update_matview($bkp_dir, $name, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  $file_dir = $owner_dir . 'materialized view/';
  @mkdir($file_dir, 0777, 1);
  $file = $file_dir . $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_matview($name, $owner))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function update_synonyms($bkp_dir, $owner, $this_update_begin_time)
{
  if (constant('VERBOSE'))
    add_log('Updating synonyms', 1);
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'synonyms.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if (sizeof($f = get_synonyms($owner)))
      file_put_contents($file, implode(";\n", $f) . ';'); //update file with all of them for current user
    else
      @unlink($file);
  }
  if (constant('VERBOSE'))
    add_log('Synonums updated', 1);
}

function update_constraints($bkp_dir, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'constraints.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if (sizeof($f = get_constraints($owner)))
      file_put_contents($file, implode(";\n", $f) . ';'); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function send_git($bkp_dir, $command)
{

  $message = 'cd ' . $bkp_dir . '&&' . $command;
  $message = convert_db_encoding($message);
  add_log($message, 1);
  exec($message, $output);
  add_log(implode("\n<br>", $output), 1);
}

function update_user($bkp_dir, $owner, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/';
  @mkdir($owner_dir, 0777, 1);
  $file = $owner_dir . 'user.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_user($owner))
    {
      file_put_contents($file, $f); //update file with all of them for current user
      update_grants($bkp_dir, $owner, $this_update_begin_time);
    }
    else
      @unlink($file);
  }
}


function update_tablespace($bkp_dir, $name, $this_update_begin_time)
{
  $owner_dir = $bkp_dir . 'tablespace/';
  @mkdir($owner_dir, 0777, 1);
  $file = $name . '.sql';
  if (@filemtime($file) < $this_update_begin_time) //if no file or it is older then current session
  {
    if ($f = get_tablespace($name))
      file_put_contents($file, $f); //update file with all of them for current user
    else
      @unlink($file);
  }
}

function update_users($bkp_dir)
{
  $users = get_all_users();
  $t = time();
  foreach ($users as $name => $val)
  {
    $owner_dir = $bkp_dir . 'data/' . strtolower($name) . '/';
    @mkdir($owner_dir, 0777, 1);
    $file = $owner_dir . 'user.sql';
    file_put_contents($file, $val);
    update_grants($bkp_dir, $name, $t);
  }
}

function update_tablespaces($bkp_dir)
{
  $tablespaces = get_all_tablespaces();
  foreach ($tablespaces as $name => $val)
  {
    $dir = $bkp_dir . 'tablespace/';
    @mkdir($dir, 0777, 1);
    $file = $dir . $name . '.sql';
    file_put_contents($file, $val);
  }
}

/*
 *
 */
function update_dblinks($bkp_dir)
{
  $dblinks = get_dba_dblinks();
  foreach ($dblinks as $owner => $arr)
    foreach ($arr as $name => $val)
    {
      $owner_dir = $bkp_dir . 'data/' . strtolower($owner) . '/database link/';
      @mkdir($owner_dir, 0777, 1);
      $file = $owner_dir . $name . '.sql';
      ;
      file_put_contents($file, $val);
    }
}

?>
