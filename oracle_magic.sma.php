<?
foreach ($_COOKIE as $name => $val) $_REQUEST[$name] = $val;

function convert_db_encoding($smth)
{
  if (is_array($smth))
  {
    foreach ($smth as $key => $val)
      $smth[$key] = convert_db_encoding($val);
  }
  else
    $smth = mb_convert_encoding($smth, "utf-8", "Windows-1251");
  #DB in 1251
  return $smth;
}

function chk_auth()
{
  global $users;
  $clogin = $_REQUEST['clogin'];
  $cpassword = $_REQUEST['cpassword'];
  if ($_REQUEST['password'] && $_REQUEST['login'])
  {
    $clogin = $_REQUEST['login'];
    $cpassword = $_REQUEST['password'];
  }
  $sql = "select 1 from magic.users where login=:login and password=magic.get_password_hash(:password)";
  $conn = get_connect();
  $stid = oci_parse($conn, $sql);
  oci_bind_by_name($stid, ":login", $clogin);
  oci_bind_by_name($stid, ":password", $cpassword);
  oci_execute($stid);

  $row = oci_fetch_row($stid);
  oci_free_statement($stid);
  if ($row[0] == 1)
    return true;
  return false;
}


function get_grants_sma($owner = 0, $table = '',$reg = '', $chk_grants = 1)
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

    $tmpl = '/_RU_(.*)_(.*)/';
    #echo "preg_match($tmpl,$row['OWNER'],$matches)";
    if ($chk_grants && preg_match($tmpl, $row['OWNER'], $matches))
    {
      #print_R($matches);
      if ($matches[1] . '_' . $matches[2] != $reg)
      {
        add_log('Shitty GRANT:  ' . $sql . ' (' . $matches[1] . '_' . $matches[2] . '!=' . $reg . ')', 1);
        continue;
      }
    }
    $grants[] = $sql;
  }
  oci_free_statement($stid);
  return $grants;

}

function get_bases()
{
  global $mode, $CURR_SESSION;
  if ($CURR_SESSION == 'FST')
    return array('EAS' => 'FST');
  #test in comments
  ##return array('eas_ru_3_23'=>'test 3_23','eas_ru_1_77'=>'test_moscow',);
  $cache = './cache/' . $mode . '.bases.txt';
  $lastmod = 0;
  $lastmod = @filemtime($cache);
  if (!$lastmod || ((time() - $lastmod) / 60 > 60))
  {
    $sql = "select username from dba_users where username like 'EAS_RU_%' and username not like '%_DISC' order by username";
    $stid = oracle_query($sql, false, 1, 0);
    #$i=0;
    $arr = array();
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      $sql2 = "select name from " . $row['USERNAME'] . ".users where code='admin'";
      $stid2 = oracle_query($sql2, false, 1);
      $row2 = @oci_fetch_array($stid2, OCI_ASSOC + OCI_RETURN_NULLS);
      $arr[$row['USERNAME']] = $row2['NAME'];
      #echo '<input type="checkbox" name="region['.$row['USERNAME'].']" value="1" id="chk'.$i.'">'.$row2['NAME'].' ('.$row['USERNAME'].')<br>';
      #$i++;
    }
    oci_free_statement($stid);
    @mkdir('cache', 0777);
    file_put_contents($cache, serialize($arr));
  }
  else
    $arr = unserialize(file_get_contents($cache));
  return $arr;
}


function get_schemes()
{
  global $base_types, $mode;
  $arr = $base_types;
  $cache = './cache/' . $mode . '.schemes.txt';
  $lastmod = 0;
  if (file_exists($cache))
    $lastmod = filemtime($cache);
  if (!$lastmod || ((time() - $lastmod) / 60 > 60))
  {
    $sql = "select SUBSTR(username,0,instr(username,'_RU_')-1)as x,count(SUBSTR(username,0,instr(username,'_RU_')-1)) as c from dba_users where username like '%\_RU\_%' ESCAPE '\'
and SUBSTR(username,0,instr(username,'_RU_')-1) is not null
 group by SUBSTR(username,0,instr(username,'_RU_')-1)
having count(SUBSTR(username,0,instr(username,'_RU_')-1))>2 order by x";
    $stid = oracle_query($sql, false, 1);
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
    {
      $name = strtolower($row['X']);
      $prefix = $name . '_ru_';
      $found = 0;
      foreach ($arr as $prop)
        if ($prop['prefix'] == $prefix)
          $found = 1;
      if (!$found)
        $arr[$name] = array('prefix' => $prefix, 'def_passwd' => '');
    }
    oci_free_statement($stid);
    if (sizeof($arr))
    {
      if (!file_exists('cache'))
        mkdir('cache', 0777);
      file_put_contents($cache, serialize($arr));
    }
  }
  else
    $arr = unserialize(file_get_contents($cache));
  return $arr;
}


function LoadFiles($dir, $filter = "")
{
  $Files = array();
  $It = opendir($dir);
  if (!$It)
    die('Cannot list files for ' . $dir);
  while ($Filename = readdir($It))
  {
    if ($Filename != '.' && $Filename != '..')
    {
      if (is_dir($dir . $Filename))
      {
        $Files = array_merge($Files, LoadFiles($dir . $Filename . '/'));
      }
      else
        if ($filter == "" || preg_match($filter, $Filename))
        {
          $LastModified = filemtime($dir . $Filename);
          #$Files[] = array($dir .$Filename, $LastModified);
          $Files[] = $dir . $Filename;
        }

        else
          continue;

    }
  }
  return $Files;
}

function DateCmp($a, $b)
{
  return strnatcasecmp($a[1], $b[1]);
}

function SortByDate(&$Files)
{
  usort($Files, 'DateCmp');
}

function show_region_form($format = 'checkbox')
{
  $bases = get_bases();

  if ($format == 'select')
  {
    echo'<select name="target_db">';
    foreach ($bases as $key => $name)
    {
      $name = str_ireplace('Сервер ЕИАС', '', $name);
      $name = str_ireplace(',', '', $name);
      $name = trim($name);
      $key2 = str_ireplace('eas_ru_', '', $key);
      echo '<option value="' . $key . '">' . $name . ' (' . $key2 . ')</a>';
    }
    echo '<option value="fst">FST</option></select>';
  }
  elseif ($format = 'checkbox')
  {
    ?>
  <input type="button" value="Инвертировать" onclick="RecheckAll('chk')">
  <div style="overflow:auto;height:300px;width:80%;background-color:#EEEEEE;border: 1px solid rgb(0, 0, 0);padding:10px;">
    <?
    $i = 0;
    foreach ($bases as $key => $name)
    {
      $name = str_ireplace('Сервер ЕИАС', '', $name);
      $name = str_ireplace(',', '', $name);
      $name = trim($name);
      $key2 = str_ireplace('eas_ru_', '', $key);
      echo '<div style="height:60px;width:300px;float:left;">';
      echo '<input class="checkbox" type="checkbox" value="1" name="region[' . $key . ']" id="chk' . $i . '"';
      if ($_REQUEST['region'][$key])
        echo ' checked';
      echo'>' . $name . ' (' . $key2 . ')</br>';
      echo '</div>';
      $i++;
    }
    ?></div><input type="checkbox" name="region[fst]" value="1"
                   class="checkbox" <?if ($_REQUEST['region']['fst']) echo 'checked';?>>FST<br>
  <!--<input type="checkbox" name="region[fst211]" value="1" class="checkbox">FST 211<br>
  <input type="checkbox" name="region[fst212]" value="1" class="checkbox">FST 212<br>
  <input type="checkbox" name="region[fst213]" value="1" class="checkbox">FST 213<br>--> <?
    ?><br>Так же регионы можно выбрать в виде списка кодов, каждый на новой строчке (Например "4_78,3_23,1_77"...):
  <br><textarea name="regionlist" style="width:100px;height:100px";></textarea><br><?
}
}

function full_rename($source, $target)
{
  if (is_dir($source))
  {
    @mkdir($target, 0777);
    $d = dir($source);
    while (FALSE !== ($entry = $d->read()))
    {
      if ($entry == '.' || $entry == '..')
      {
        continue;
      }
      $Entry = $source . '/' . $entry;
      if (is_dir($Entry))
      {
        full_rename($Entry, $target . '/' . $entry);
        continue;
      }
      rename($Entry, $target . '/' . $entry);
    }
    rmdir($source);

    $d->close();
  }
  else
  {
    rename($source, $target);
  }
}

/*
function Unzip_File_To_Dir($fromfile,$todir)
{
  $zip = zip_open($fromfile);
  mkdir($todir,777,true);
  if ($zip) 
  {
    while ($zip_entry = zip_read($zip)) 
    {
      $fp = fopen($todir.'/'.zip_entry_name($zip_entry), 'w');
      if (zip_entry_open($zip, $zip_entry, 'r')) 
      {
        if(is_dir($zip_entry))
          mkdir()
        $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
        fwrite($fp,$buf);
        zip_entry_close($zip_entry);
        fclose($fp);
      }
    }
    zip_close($zip);
  }
}*/

function Unzip_File_To_Dir($zipfile, $todir)
{
  mkdir(strtolower($todir), 0777, true);
  $zip = zip_open($zipfile);
  while ($zip_entry = zip_read($zip))
  {
    zip_entry_open($zip, $zip_entry);
    if (substr(zip_entry_name($zip_entry), -1) == '/')
    {
      $zdir = substr(zip_entry_name($zip_entry), 0, -1);
      if (file_exists(strtolower($todir . '/' . $zdir)))
      {
        trigger_error('Directory "<b>' . $zdir . '</b>" exists', E_USER_ERROR);
        return false;
      }
      mkdir(strtolower($todir . '/' . $zdir), 0777);
    }
    else
    {
      $name = zip_entry_name($zip_entry);
      if (file_exists(strtolower($todir . '/' . $name)))
      {
        trigger_error('File "<b>' . $name . '</b>" exists', E_USER_ERROR);
        return false;
      }
      $file = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
      file_put_contents(strtolower($todir . '/' . $name), $file);
    }
    zip_entry_close($zip_entry);
  }
  zip_close($zip);
  return true;
}

function list_schemes($base_types, $type = 'checkbox', $fieldname = 'export_scheme', $fieldid = 'schmexp')
{
  $base_types = get_schemes();
  if ($type == 'checkbox')
  {
    ?><input type="button" value="Инвертировать" onclick="RecheckAll('<?=$fieldid;?>')"><br><?
    $i = 0;
    foreach ($base_types as $key => $arr)
    {
      echo '<div style="width:300px;float:left;">';
      if ($arr['def_passwd'])
        echo '<input type="checkbox" class="checkbox" name="' . $fieldname . '[' . $key . ']" value="1" id="schmexp' . $i . '"><font style="font-weight:bolder">' . $key . '</font><br>';
      else
        echo '<input type="checkbox" class="checkbox" name="' . $fieldname . '[' . $key . ']" value="1" id="schmexp' . $i . '">' . $key . '<br>';
      $i++;
      echo '</div>';
    }
    echo '<div style="clear:both;"></div>';
  }
  elseif ($type = 'select')
  {
    echo '<select name="' . $fieldname . '"><option value="">Выберите схему</option>';
    foreach ($base_types as $key => $arr)
      echo '<option value="' . $key . '">' . $key . '</option>';
    echo '</select>';
  }
}


function list_schema_tables($schema)
{
  $sql = "begin
  for rec in (select table_name from dba_tables  where owner='" . $schema . "') loop
    execute immediate ('analyze table " . $schema . ".'||rec.table_name||' compute statistics');
  end loop;
  end;";
  oracle_query($sql, false, 1);
  $sql = "SELECT table_name,NUM_ROWS from dba_tables where owner='" . $schema . "' and num_rows>0 order by num_rows desc";
  $stid = oracle_query($sql, false, 1);
  $s = 0;
  while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS))
  {
    if (!$s)
    {
      ?><input type="button" value="Инвертировать" onclick="RecheckAll('tbl_<?=$schema;?>')"><br><?
    }
    echo '<div style="width:350px;float:left;">';
    echo '<input type="checkbox" class="checkbox" name="table[' . $schema . '][' . $row['TABLE_NAME'] . ']" value="1" id="tbl_' . $schema . $s . '"';
    if (!(
      stripos($row['TABLE_NAME'], 'log') ||
        stripos($row['TABLE_NAME'], 'temp_') ||
        stripos($row['TABLE_NAME'], 'bak_') ||
        stripos($row['TABLE_NAME'], 'audit')
    )
    )
      ;
    #echo ' checked';
    echo '>&nbsp;' . $row['TABLE_NAME'] . '(' . $row['NUM_ROWS'] . ')<br>';
    $s++;
    echo '</div>';
  }
  oci_free_statement($stid);
  echo '<div style="clear:both;"></div>';
}

function load_pages()
{
  $pages = array();
  $d = dir('./pages');
  while (false !== ($entry = $d->read()))
  {
    $e = explode('.', $entry);
    if ($e[1] != 'php')
      continue;
    if ($entry != '.' && $entry != '..')
    {
      $entry = explode('.', $entry);
      $entry = $entry[0];
      $name = GetPageName($entry);
      if ($name)
        $pages[] = array('file' => $entry, 'name' => $name);
    }
  }
  $d->close();
  return $pages;
}

function GetPageName($page)
{
  $filename = './pages/' . $page . '.php';
  $handle = fopen($filename, "r");
  $contents = fread($handle, 255);
  fclose($handle);
  $contents = explode("\n", $contents, 2);
  $contents = explode('#', $contents[0], 2);
  return $contents[1];
}

function load_users()
{
  $users = @file_get_contents('users.inc');
  $users = @unserialize($users);
  $def = array('login' => 'bond', 'password' => '01eea3d08de140d10f208f90a16b712c', 'pages' => array(), 'role' => 'admin');
  if ($users == false)
    $users = array($def);
  return $users;
}

/*
function save_users($users)
{
  $users=serialize($users);
  file_put_contents('users.inc',$users);
}*/
############################## <findme.php>
function read_dir_recursive($path)
{
  $path = trim($path, '/');
  $path = trim($path, '\\'); #'
  $files = array();
  $handle = opendir($path);
  $res = array();
  while (false !== ($file = readdir($handle)))
  {
    if ($file != "." && $file != "..")
    {
      if (is_dir($path . '/' . $file))
      {
        $files = read_dir_recursive($path . '/' . $file);
        foreach ($files as $file) $res[] = $file;
      }
      else
      {
        $res[] = $path . '/' . $file;
      }
    }
  }
  closedir($handle);
  return $res;
}

function find_text_in_files($text, $files)
{
  $result = array();
  foreach ($files as $file)
  {
    #$inside=file_get_contents($file);
    #if(stripos($inside,$text)!==false)$result[]=$file;
    $handle = @fopen($file, "r");
    if ($handle)
    {
      while (!feof($handle))
      {
        $buffer = fgets($handle);
        if (stripos($buffer, $text) !== FALSE)
        {
          $result[] = $file;
          break;
        }
      }
      fclose($handle);
    }

  }
  return $result;
}


function dofind($path, $text)
{
  $files = read_dir_recursive($path);
  $result = '';

  $res = find_text_in_files($text, $files);
  /*foreach($res as $line)
  {
          $result.= '<li>' . $line . '<br>';
  }*/
  return $res;
}

function oracle_import($file, $from = '3_23', $to = '')
{
  #global $counter, $normal_errors;
  if (!$to)
    die('Не установлен параметр конечной базы для смены жёстких идентификаторов - пожалуйста, установите его, или уберите привязки к базе. Скрипт будет терминирован.');

  $result = 1;
  if (is_file($file))
  {
    $f = file_get_contents($file);
    $ext = explode('.', $file);
    $ext = $ext[sizeof($ext) - 1];
    if (!$_REQUEST['select_mode'])
      echo '<br>' . $file;

    $replaced = 0;
    if($to==='fst')
      $to='';
    $f = str_ireplace($from, $to, $f, $replaced);
    if ($replaced)
      echo ' [Hardcode replaced]';
    if (in_array($ext, array('plsql', 'pck', 'trg', 'prc')))
      $type = 'plsql';
    else
      $type = 'sql';
    if (!$_REQUEST['select_mode'])
      echo ' - type ' . $type . '<br>';

    if ($type == 'plsql')
    {
      $f2 = $f;
      do
      {
        $f = $f2;
        $f2 = str_replace(array("\n\t\n", "\n \n"), "\n\n", $f);
        $f2 = str_replace(array(' /', '/ ', "\t" . '/', '/' . "\t"), '/', $f2);
      } while (strcmp($f2, $f) !== 0);
      $f = explode("\r\n/\r\n", $f);
    }

    #make an array. just in case.
    $f = (Array)$f;
    foreach ($f as $text)
    {
      if (!$text)
        continue;
      $text = trim($text);
      if ($type == 'sql')
      {
        $text = explode("\n", $text);
        foreach ($text as $key => $val)
        {
          $val = explode('--', $val);
          if (is_array($val))
          {
            foreach ($val as $key2 => $val2)
              if ($key2 > 0)
                $val[$key2] = str_replace(';', '', $val2);
            $val = implode('--', $val);
          }
          $text[$key] = $val;
        }
        $text = implode("\n", $text);
        $text = explode(';', $text);
      }
      else
      {
        $text = trim($text);
        $text = trim($text, '/');
        $text = trim($text);
        if ($text[strlen($text) - 1] != ';')
          $text .= ';';
      }

      $text = (Array)$text;
      foreach ($text as $sql)
      {
        $x = oracle_query($sql, false, 0);
        if (!$x)
          $result = $x;
        flush();
        ob_flush();
      }
    }
  }
  return $result;
}

############################## </findme.php>

?>
