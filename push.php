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
if (!$bkp_dir)
{
  add_log('No backup dir!');
  die();
}
add_log("Pushing to remote repo...", 1);
//send_git($bkp_dir,'git push git_ora master');
send_git($bkp_dir, 'git push backup master');

?>