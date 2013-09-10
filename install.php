<?
include('ext/geshi/geshi.php');


// Make a new GeSHi object, with the source, language and path set
$language = 'plsql';
$path = 'ext/geshi/geshi/';
?>
<!DOCTYPE html>
<html lang="en">
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

        .form-signin {
            max-width: 300px;
            padding: 19px 29px 29px;
            margin: 0 auto 20px;
            background-color: #fff;
            border: 1px solid #e5e5e5;
            -webkit-border-radius: 5px;
            -moz-border-radius: 5px;
            border-radius: 5px;
            -webkit-box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
            -moz-box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
        }

        .form-signin .form-signin-heading,
        .form-signin .checkbox {
            margin-bottom: 10px;
        }

        .form-signin input[type="text"],
        .form-signin input[type="password"] {
            font-size: 16px;
            height: auto;
            margin-bottom: 15px;
            padding: 7px 9px;
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
    <h1>0. Requirements.</h1>

    <p>You need the following software:
    <ul>
        <li>Linux server in local network with Apache, PHP 5 and git installed. PHP will need to be able to connect to
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
    <h1>1. Schema.</h1>

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

    <h1>2. Log table.</h1>

    <p>We will use table to store data about changes, happening to your database.</p>

    <p>That's how it looks:</p>
  <?
  $query = file_get_contents('scripts/create_table.inc');
  $geshi = new GeSHi($query, $language, $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>


    <h1>3. DDL trigger.</h1>

    <p>Now we're beginning really dangerous staff - trigger for all DDL operations in database. If this trigger fucks
        up, your ddl operations will too. If you feel that something's off - disable the trigger!</p>
  <?
  $query = file_get_contents('scripts/create_trigger.inc');
  $geshi = new GeSHi($query, $language, $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>
    <h1>4. Test!</h1>

    <p>Seems like you're ready to go! Try making random DDL query, and see how it is reflected in Magic.ddllog
        table.</p>

    <h1>5. Configure script</h1>

    <p>Now we need to set up connection settings for PHP script.</p>

    <p>Copy the following code to new file "settings/default.php" and change default connect settings to the ones for your
        database with the newly created "magic" schema.</p>
  <?
  $query = file_get_contents('scripts/settings.php');
  $geshi = new GeSHi($query, "php", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h1>6. Set up Git repo for your database</h1>

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

    </p>


    <h1>7. Set up permissions</h1>

    <p>Allow PHP to write to "logs" directory and to backup directory (which you chose in settings.php file).</p>


    <h1>8. Primary export</h1>

    <p>Yup, at last we're ready to export your database source code!</p>

    <p>Open your server console and launch backup.php script. It may take long for your first export.</p>
  <?
  $query = 'php "/web/oracle2git/backup.php" "default"';
  $geshi = new GeSHi($query, "bash", $path);
  $geshi->enable_keyword_links(false);
// and simply dump the code!
  echo $geshi->parse_code();
  ?>

    <h1>9. Secondary export</h1>

    <p>Secondary export should be quick but you need to check if it works okay.</p>

    <p>Just repeat the same command you used for primary export.</p>


    <h1>10. Set up regular export</h1>

    <p>Now you just need to set up a crontab script to regularly launch secondary export script. I'd recommend to set it
        to every five minutes. It would look like

      <?
      $query = '*/5 * * * * php "/web/oracle2git/backup.php" "default" >/dev/null 2>&1';
      $geshi = new GeSHi($query, "bash", $path);
      $geshi->enable_keyword_links(false);
// and simply dump the code!
      echo $geshi->parse_code();
      ?>
    </p>


    <h1>Ready!</h1>
</div>

</body>
</html>
