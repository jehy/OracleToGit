<?
include('ext/geshi/geshi.php');


// Make a new GeSHi object, with the source, language and path set
$language = 'plsql';
$path = 'ext/geshi/geshi/';
?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/html">
<head>
    <meta charset="utf-8">
    <title>Oracle 2 Git</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Le styles -->
    <link href="ext/bootstrap/css/bootstrap.css" rel="stylesheet">
    <style type="text/css">
        body {
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }

    </style>
    <link href="ext/bootstrap/css/bootstrap-responsive.css" rel="stylesheet">

    <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
  <script src="ext/bootstrap/js/html5shiv.js"></script>
    <![endif]-->
</head>

<body>

<div class="container">
    <h2>0. Requirements.</h2>

    <p>You need the following software:
    <ul>
    <li>Linux server with Apache, PHP 5 and git installed. PHP has to be able to connect to
        your Oracle database (set up oci library for php).
    </li>
        <li>Oracle Database server</li>
    </ul>

    Also, you need

    <ul>
        <li>Basic knowledge of Linux, PHP and Git</li>
        <li>DBA priviledges on database in question</li>
    </ul>
    </p>
    <h2>1. Schema.</h2>

    <p>
        First, you will have to run some administrative queries manually. Those are dangerous and can kill kittens - so
        it is better to be careful and examine all the queries output. That is why everything doesn't happen in one
        click.</p>

    <p>Firstly, we will need a separate schema with DBA role (yup, we could grant smth like "select any table"... But it
        isn't really much different from DBA).</p>

    <p>So, let's run</p>
  <?

  $query = file_get_contents('scripts/create_schema.inc');

  $geshi = new GeSHi($query, $language, $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h2>2. Log table.</h2>

    <p>We will use table to store data about changes, happening to your database.</p>

    <p>That's how it looks:</p>
  <?
  $query = file_get_contents('scripts/create_table.inc');
  $geshi = new GeSHi($query, $language, $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>


    <h2>3. DDL trigger.</h2>

    <p>Now we're beginning really dangerous staff - trigger for all DDL operations in database. If this trigger fucks
        up, your ddl operations will too. If you feel that something's off - disable the trigger!</p>
  <?
  $query = file_get_contents('scripts/create_trigger.inc');
  $geshi = new GeSHi($query, $language, $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>
    <h2>4. Test!</h2>

    <p>Seems like you're ready to go! Try making random DDL query, and see how it is reflected in Magic.ddllog
        table.</p>

    <h2>5. Configure script</h2>

    <p>Now we need to set up connection settings for PHP script.</p>

    <p>In this example, all PHP code is extracted to application directory "/web/oracle2git". Copy the following code to
        new file "settings/default.php" and change default connect settings to the ones for your
        database with the newly created "magic" schema.</p>
  <?
  $query = file_get_contents('scripts/settings.php');
  $geshi = new GeSHi($query, "php", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h2>6. Set up Git repo for your database</h2>

    <p>Now you need to associate your backup directory with the git repository. Init repository, make first clean commit
        and push it to remote server. Please, use remote repository (github, bitbucket or any other) with "master"
        branch. Remote repo should be called "backup". Also, add there ".gitignore" file with the following lines:
      <?
      $query = file_get_contents('scripts/ignore_repo.inc');
      $geshi = new GeSHi($query, "bash", $path);
      $geshi->enable_keyword_links(false);
// and simply dump the code!
      echo $geshi->parse_code();
      ?>
        Also, make sure that your push can be done without entering login and password. To accomplish it, you can store
        login and password locally, or use SSH keys (that's the best).
    </p>


    <h2>7. Set up permissions</h2>

    <p>Allow PHP to write to "logs" directory and to backup directory (which you chose in settings.php file).</p>


    <h2>8. Primary export</h2>

    <p>Yup, at last we're ready to export your database source code!</p>

    <p>Open your server console and launch backup.php script. It may take long for your first export.</p>
  <?
  $query = 'php "/web/oracle2git/backup.php" "default"';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h2>9. Secondary export</h2>

    <p>Secondary export should be quick but you need to check if it works okay.</p>

    <p>Just repeat the same command you used for primary export.</p>


    <h2>10. Push your repo to remote repositary</h2>

  <?
  $query = 'php "/web/oracle2git/push.php" "default"';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <p>It should work okay if you correctly completed step 6.</p>

    <h2>11. Set up regular export</h2>

    <p>Now you just need to set up a crontab script to regularly launch secondary export script and push script. Those
        are represented as two different scripts because obviously you don't need to sync as often as you update your
        local repo. It would just make unneccessary traffic. I'd recommend to set it backup to every five minutes and
        push to every hour. You need to make two bash scripts with backup and push commands, for example,</p>

    <p><b>/root/backup_default.sh</b></p>
  <?
  $query = '#!/bin/bash
php /web/oracle2git/backup.php default';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>
    <p>and</p>

    <p><b>/root/push_default.sh</b></p>
  <?
  $query = '#!/bin/bash
php /web/oracle2git/push.php default';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <p>Make sure that your bash and php files have right permissions for execution. And, the last thing - make it a <a
            href="https://en.wikipedia.org/wiki/Cron">cron</a> job:</p>
  <?
  $query = '*/5 * * * * php /root/backup_default.sh
00 * * * * php /root/push_default.sh';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h2>Ready!</h2>
</div>

</body>
</html>
