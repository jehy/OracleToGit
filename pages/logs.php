<?#Просмотр логов
if($_REQUEST['log'])
{
  echo str_replace("\n",'<br>',file_get_contents($log));
}
elseif($_REQUEST['date'])
{
  echo '<div class="option">'.$_REQUEST['date'].'</div>';
  $dir='./logs/'.$_REQUEST['date'];
  $entries=array();
  $d = dir($dir);
  while (false !== ($entry = $d->read())) 
    if($entry!='.' && $entry!='..' && $entry!='.svn')
    $entries[]=$entry;
  sort($entries);
  error_reporting(E_ALL);
  foreach($entries as $entry)
  {
    $file='logs/'.$_REQUEST['date'].'/'.$entry;
    $handle = fopen($file, "r");
    if(!$handle)
      echo 'oops';
    fgets($handle, 255);
    $buffer = fgets($handle, 255);
    fclose($handle);
    #$buffer=strstr($buffer,'Пользователь',0);
    #&Iuml;&icirc;&euml;&uuml;&ccedil;&icirc;&acirc;&agrave;&ograve;&aring;&euml;&uuml;: bond, 
    echo '<a href="'.$file.'">'.strip_tags($buffer,'<br>').'</a>';
  }

}
elseif($_REQUEST['find'])
{
  echo 'Выполняется поиск...<br>';flush();ob_flush();
  #include 'findme.php';
  $res = dofind('logs',$_REQUEST['find']);
  sort($res);
  #echo $res . '<hr>';
  #foreach($res as $line)
  foreach($res as $file)
  #  echo '<a href="index.php?action=logs&log='.$line.'">'.$line.'</a></br>';
  {
  
    $handle = fopen($file, "r");
    if(!$handle)
      echo 'oops';
    fgets($handle, 255);
    $buffer = fgets($handle, 255);
    fclose($handle);
    #$buffer=strstr($buffer,'Пользователь',0);
    #&Iuml;&icirc;&euml;&uuml;&ccedil;&icirc;&acirc;&agrave;&ograve;&aring;&euml;&uuml;: bond, 
    echo '<a href="'.$file.'">'.strip_tags($buffer,'<br>').'</a>';
  }     
  flush();ob_flush();
   /*
  $res = dofind('archive',$_REQUEST['find']);
  sort($res);
  foreach($res as $line)
    echo '<a href="index.php?action=logs&log='.$line.'">'.$line.'</a></br>';*/
}
else
{
 ?><div class="option">Поиск по тексту:</div><br>
   <form action="index.php">
   <input type="text" name="find"><Br>
   <input type="hidden" name="action" value="logs"><Br>
   <input type="submit" value="Искать"></form><Br><Br>
  
  ИЛИ<br><br>
  
  <div class="option">Выберите дату:</div><?
  $d = @dir('./logs');
  $f=array();
  $entries=array();
  while (false !== ($entry = @$d->read())) 
    if(is_dir('./logs/'.$entry) && $entry!='.' && $entry!='..' && $entry!='.svn')
    {
      $entry=explode('-',$entry);
      $entries[$entry[0]][$entry[1]][$entry[2]]=1;
    }
  ksort($entries);
  foreach ($entries as $year=>$arr)
  {
    echo '<div class="year"><div class="title">'.$year.'<hr align="left" style="width:300px;"></div>';
    ksort($arr);
    foreach($arr as $month=>$arr2)
    {
      echo '<div class="month"><div class="title">'.$month.'</div>'; 
      ksort($arr2);     
      foreach($arr2 as $day=>$one)
      {
        if(date('Y-m-d')==$year.'-'.$month.'-'.$day)
        	echo '<div class="curday">';
        else
        	echo '<div class="day">';
        echo '<a href="index.php?action=logs&date='.$year.'-'.$month.'-'.$day.'">'.$day.'</a></div>';
      }
      echo '</div>';
    }
    echo '</div>';
  }
    
}
?>