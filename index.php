<?
header("Content-Type: text/html; charset=windows-1251");

define('TRY_RESUME_ON_NO_DB', 1);
define('DEBUG', 1);
define('MAX_CONNECT_TIME', 0);
define('NOLOG', 0);

if (
  ($_REQUEST['action'] == 'fstats' && $_REQUEST['step'] == 2)
  ||
  ($_REQUEST['action'] == 'autocomplete')
)
  ob_start();

$mode = 'dev';
if ($_SERVER['HTTP_HOST'] == 'dev.eias-support.ru')
{
  $mode = 'dev';
  include_once('oracle_dev_sett.php');
}
elseif ($_SERVER['HTTP_HOST'] == 'magic.eias-support.ru')
{
  $mode = 'war';
  include_once('oracle_war_sett.php');
}
elseif ($_SERVER['HTTP_HOST'] == 'localhost')
{
  $mode = 'war';
  include_once('oracle_war_sett.php');
}
else
  die('No way from ' . $_SERVER['HTTP_HOST']);

include_once('oracle_magic.sma.php');
include_once('oracle_magic.php');

$action = $_REQUEST['action'];
if ($_REQUEST['login'] && $_REQUEST['password'])
{
  if (chk_auth())
  {
    setcookie('clogin', $_REQUEST['login']);
    setcookie('cpassword', $_REQUEST['password']);
    #$action='';
  }
  else die('BAD LAMA');
}

if (!chk_auth())
  $action = 'login';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<html>
<head>
    <script type="text/javascript" language="JavaScript" src="js/scripts.js"></script>
    <LINK REL="StyleSheet" HREF="css/oracle.magic.css?v3" TYPE="text/css"/>
  <?if ($action == 'update_not_common')
{
  ?>
    <link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet"
          type="text/css"/>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
  <?
}?>
</head>
<body>
<?
if ($mode == 'dev')
  echo '<div align="center" class="error">Вы работаете с базой для тестов</div>';
else
  echo '<div align="center" class="error">Вы работаете с боевой базой! Будьте осторожны!</div>';
?>
<?
if (!$action)
{
  ?>
<div class="option" style="float:right">Oracle Magic. Выберите действие:</div>
<div style="clear:both"></div> </div>
<div class="main"><?

  $pages = load_pages();
  foreach ($pages as $page)
    if (!in_array($page['file'], $hidden_plugins))
      echo'<div class="y yy"><a href="index.php?action=' . $page['file'] . '">' . $page['name'] . '</a></div>';

  ?></div><div class="mainbg">
<?
}
else
{
  ?>
    <div class="option"><a href="javascript:history.back();">Назад</a></div><br><br><?
  $pages = load_pages();

  foreach ($pages as $page)
    if ($action == $page['file'])
      include_once('./pages/' . $page['file'] . '.php');
}
?>
</body>
</html>
