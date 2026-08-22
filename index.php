<?php
//Release 1.3
//
//file with lang. translations
require_once "lang.php";
$sessionStorage=__DIR__.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'sessions';
if (!is_dir($sessionStorage) && !mkdir($sessionStorage, 0700, true) && !is_dir($sessionStorage)) {
  throw new RuntimeException('Unable to create the protected session directory.');
}
session_save_path($sessionStorage);
$usingHttps=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $usingHttps,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();
//main configuration file
$configFile="config.php";
//psk variable. by default is - means no aditional password for encryption is added
$psk="0";
$token="";
//Include config file - checks, generation if it is absent, and so on.
if (file_exists($configFile) && !empty($configFile)) {
  include_once $configFile;
  //if some variables is not set
  if (empty($key) || empty($mysqli_db) || empty($mysqli_host) || empty($mysqli_dbuser) || empty($mysqli_dbpass)) {
    echo("Some of config.php settings is not set. Please,check it!");
    die();
  }
}
else {
  echo("config.php is not found or it is empty!\n");
  $template="<?php\n//Encryption key.Will be used for message encryption before store in DB.\n\$key=\"\";\n//DB connect settings.\n\$mysqli_db=\"SecureMessage\";\n\$mysqli_host=\"localhost\";\n\$mysqli_dbuser=\"SecureMessage\";\n\$mysqli_dbpass=\"\";\n";
  if (file_put_contents($configFile,$template)) {
    echo("New config.php created successfully!\nPlease, edit the config.php now and set DB credentials + encryption password, then upload content of schema.sql to the newely created DB.");
  } else {
    echo("Error creation of the new config.php! Check user permissions!");
  }
  die();
}
//include language strings for translation
$lng=$_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "";
if (strpos($lng,"uk-UA")) {
  setLang(1);
} elseif (strpos($lng,"ru")) { 
  setLang(2);
} else { 
  setLang(3);
}
//common database config
$mysqli_dsn="mysql:host={$mysqli_host};dbname={$mysqli_db}";
$mysqli_options=[ PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, ];
$mysqli_dbh=new PDO($mysqli_dsn, $mysqli_dbuser, $mysqli_dbpass, $mysqli_options);
$ciphering="AES256";
$iv_length=openssl_cipher_iv_length($ciphering);
$options=0;

//Generate the CSRF token and cookie before any HTML is sent.
$salt=rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
$token=$salt.":".MD5($salt.":".$key);
setcookie("CSRF", $token, [
  'expires' => time() + 600,
  'path' => '/',
  'secure' => $usingHttps,
  'httponly' => true,
  'samesite' => 'Lax'
]);

function csrfIsValid() {
  return isset($_POST['csrfToken'], $_COOKIE['CSRF'])
    && hash_equals($_COOKIE['CSRF'], $_POST['csrfToken']);
}

$authError="";
if (isset($_POST['register'])) {
  if (!csrfIsValid()) {
    $authError=$lang_err2;
  } else {
    $username=trim($_POST['username'] ?? '');
    $password=$_POST['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
      $authError=$lang_auth_username_error;
    } elseif (strlen($password) < 10) {
      $authError=$lang_auth_password_error;
    } else {
      try {
        $register=$mysqli_dbh->prepare("INSERT INTO `users` (`username`,`password_hash`) VALUES (?,?)");
        $register->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$mysqli_dbh->lastInsertId();
        $_SESSION['username']=$username;
        header("Location: ".$_SERVER['PHP_SELF']);
        die();
      } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
          $authError=$lang_auth_exists;
        } else {
          throw $exception;
        }
      }
    }
  }
}

if (isset($_POST['login'])) {
  if (!csrfIsValid()) {
    $authError=$lang_err2;
  } else {
    $username=trim($_POST['username'] ?? '');
    $login=$mysqli_dbh->prepare("SELECT `id`,`username`,`password_hash` FROM `users` WHERE `username`=? LIMIT 1");
    $login->execute([$username]);
    $account=$login->fetch(PDO::FETCH_ASSOC);
    if ($account === false || !password_verify($_POST['password'] ?? '', $account['password_hash'])) {
      $authError=$lang_auth_invalid;
    } else {
      session_regenerate_id(true);
      $_SESSION['user_id']=(int)$account['id'];
      $_SESSION['username']=$account['username'];
      header("Location: ".$_SERVER['PHP_SELF']);
      die();
    }
  }
}

if (isset($_POST['logout']) && csrfIsValid()) {
  $_SESSION=[];
  if (ini_get('session.use_cookies')) {
    $sessionCookie=session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $sessionCookie['path'], $sessionCookie['domain'], $sessionCookie['secure'], $sessionCookie['httponly']);
  }
  session_destroy();
  header("Location: ".$_SERVER['PHP_SELF']);
  die();
}
if (isset($_GET['link']) || isset($_GET['s'])) {
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");
  header("Expires: 0");
}

//daily function to clean all expired data in DB
if (isset($_GET['clean'])) {
  global $mysqli_dbh;
  //getting all records
  $mysqli="SELECT * FROM `messages`";
  $data=$mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
  $count=$data->rowCount();
  //is there any records at all?
  $currtime=time();
  $counter=0;
  //parsing data
  foreach ($data as $key2 => $result) {
    $lifetime=$result['lifetime'];
    $created=$result['created'];
    $id=$result['id'];
    $link=$result['link'];
    //checking exparation and deleting if expired
    if (($created+$lifetime) < $currtime) {
      $mysqli="DELETE FROM `messages` WHERE id='".$id."'";
      $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
      $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','expired,unread','expired');";
      $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
      $counter++;
    }
  }
  echo("Cleaned ".$counter." records.");
  die();
}

function generateRandomString($length = 32) {
  $characters="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
  $charactersLength=strlen($characters);
  $randomString="";
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[random_int(0, $charactersLength - 1)];
  }
  return $randomString;
}

//Atomically reserve one permitted view. Only the request that decrements the
//counter is allowed to receive the decrypted message.
function consumeMessageView($mysqli_dbh, $id) {
  $consume=$mysqli_dbh->prepare("UPDATE `messages` SET `views_remaining`=`views_remaining`-1 WHERE `id`=? AND `views_remaining`>0");
  $consume->execute([$id]);
  return $consume->rowCount() === 1;
}

function finishMessageView($mysqli_dbh, $id, $fileName, $link) {
  $remainingQuery=$mysqli_dbh->prepare("SELECT `views_remaining` FROM `messages` WHERE `id`=?");
  $remainingQuery->execute([$id]);
  $viewsRemaining=$remainingQuery->fetchColumn();

  if ($viewsRemaining !== false && (int)$viewsRemaining <= 0) {
    if (!empty($fileName)) {
      $destroy=$mysqli_dbh->prepare("UPDATE `messages` SET `message`='' WHERE `id`=?");
    } else {
      $destroy=$mysqli_dbh->prepare("DELETE FROM `messages` WHERE `id`=?");
    }
    $destroy->execute([$id]);
  }

  $requestScheme=$_SERVER['REQUEST_SCHEME'] ?? 'https';
  $messageUrl=$requestScheme."://".$_SERVER['SERVER_NAME']."/?link=".$link;
  $log=$mysqli_dbh->prepare("INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES (?,?,?,'text')");
  $log->execute([$id, $messageUrl, $_SERVER['REMOTE_ADDR']]);
  $history=$mysqli_dbh->prepare("UPDATE `message_history` SET `viewed`=1, `viewed_at`=COALESCE(`viewed_at`, CURRENT_TIMESTAMP) WHERE `message_id`=?");
  $history->execute([$id]);
}

if (!isset($_POST['dwlAttachment'])) {?>
<!doctype html>
<html lang="<?php echo($lang_meta);?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo($lang_descr);?>">
    <link rel="icon" type="image/png" href="favicon.png" />
    <title><?php echo($lang_title);?></title>
    <script>
      (function () {
        const savedTheme = localStorage.getItem('sm-theme');
        const dark = savedTheme === 'dark' ||
          (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
      }());
    </script>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
      :root { color-scheme: light; }
      :root[data-theme="dark"] { color-scheme: dark; }
      body {
        background: #f8f9fa;
        color: #212529;
        transition: background-color .2s ease, color .2s ease;
      }
      :root[data-theme="dark"] body { background: #121212; color: #f1f3f5; }
      :root[data-theme="dark"] .bg-light,
      :root[data-theme="dark"] .form-control,
      :root[data-theme="dark"] form.bg-light {
        background-color: #1f2327 !important;
        color: #f1f3f5 !important;
        border-color: #495057 !important;
      }
      :root[data-theme="dark"] .form-control:focus {
        background-color: #252a2f !important;
        color: #fff !important;
      }
      :root[data-theme="dark"] .form-floating > label { color: #adb5bd; }
      :root[data-theme="dark"] a { color: #8bb9fe; }
      :root[data-theme="dark"] hr { border-color: #495057; }
      :root[data-theme="dark"] .table {
        color: #f1f3f5;
        border-color: #495057;
      }
      .theme-toggle {
        position: fixed;
        z-index: 1000;
        top: .75rem;
        right: .75rem;
      }
      .protected-message {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        -webkit-user-select: none;
        user-select: none;
        -webkit-touch-callout: none;
      }
      #privacy-shield {
        position: fixed;
        inset: 0;
        z-index: 2147483647;
        display: none;
        background: #000;
      }
      body.privacy-hidden #privacy-shield { display: block; }
      @media print {
        body.protected-view * { visibility: hidden !important; }
        body.protected-view,
        body.protected-view::before {
          background: #000 !important;
        }
        body.protected-view::before {
          content: "";
          position: fixed;
          inset: 0;
          visibility: visible !important;
        }
      }
    </style>
  </head>
<body>
<button id="theme-toggle" class="btn btn-sm btn-outline-secondary theme-toggle" type="button" aria-pressed="false"></button>
<div id="privacy-shield" aria-hidden="true"></div>
<script>
  (function () {
    const root = document.documentElement;
    const toggle = document.getElementById('theme-toggle');
    const labels = { dark: <?php echo json_encode($lang_theme_dark); ?>, light: <?php echo json_encode($lang_theme_light); ?> };

    function renderThemeToggle() {
      const dark = root.dataset.theme === 'dark';
      toggle.textContent = dark ? labels.light : labels.dark;
      toggle.setAttribute('aria-pressed', String(dark));
    }

    toggle.addEventListener('click', function () {
      const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = theme;
      localStorage.setItem('sm-theme', theme);
      renderThemeToggle();
    });
    renderThemeToggle();

    document.addEventListener('DOMContentLoaded', function () {
      const message = document.querySelector('.protected-message');
      if (!message) return;

      document.body.classList.add('protected-view');
      ['copy', 'cut', 'contextmenu', 'dragstart', 'selectstart'].forEach(function (eventName) {
        message.addEventListener(eventName, function (event) { event.preventDefault(); });
      });
      document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && ['a', 'c', 'x', 'p', 's', 'u'].includes(event.key.toLowerCase())) {
          event.preventDefault();
        }
      });
      document.addEventListener('visibilitychange', function () {
        document.body.classList.toggle('privacy-hidden', document.hidden);
      });
      window.addEventListener('pagehide', function () {
        message.textContent = '';
        document.body.classList.add('privacy-hidden');
      });
    });
  }());
</script>
<?php }
//Create link and secure data function
if (isset($_POST['create'])) {
  if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo("<p>".$lang_auth_required."</p>");
    die();
  }
  //check all variables are set and not empty
  if ((!isset($_POST['textMain'])) || (!isset($_POST['timeValid'])) || (!isset($_POST['viewLimit'])) || ((empty($_POST['textMain'])) && ($_FILES['userfile']['size'] == 0)) || (empty($_POST['timeValid']))) {
    echo("<script>alert('".$lang_err1."');</script>");
    echo("<p>".$lang_err1."</p>");
    echo("<p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."\">".$lang_ret."</a></p>");
    die();
  }
  //checking CSRF
  if ((!isset($_POST['csrfToken'])) || (empty($_POST['csrfToken'])) || (!isset($_COOKIE['CSRF'])) || (empty($_COOKIE['CSRF'])) || ($_POST['csrfToken'] != $_COOKIE['CSRF'])) {
    echo("<script>alert('".$lang_err2."');</script>");
    die();
  }
  $encryption_iv=mb_substr($_POST['csrfToken'],0,6).mb_substr($_POST['csrfToken'],0,6)."1467";
  $link=generateRandomString();
  $shortLink=null;
  if (isset($_POST['shortenUrl'])) {
    do {
      $candidate=generateRandomString(12);
      $shortCheck=$mysqli_dbh->prepare("SELECT 1 FROM `messages` WHERE `short_link`=? LIMIT 1");
      $shortCheck->execute([$candidate]);
    } while ($shortCheck->fetchColumn() !== false);
    $shortLink=$candidate;
  }
  $viewLimit=filter_var($_POST['viewLimit'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 100]]);
  if ($viewLimit === false) {
    echo("<script>alert('".$lang_err1."');</script>");
    die();
  }
  $encryption_key=$key;
  if (!empty($_POST['textMain'])) {
    $encrypted_text=openssl_encrypt(trim(htmlspecialchars($_POST['textMain'])), $ciphering, $encryption_key, $options, $encryption_iv);
    if (!empty($_POST['pskInput'])) {
      $encrypted_text=openssl_encrypt($encrypted_text, $ciphering, $_POST['pskInput'], $options, $encryption_iv);
      $psk="1";
    }
  } else {
    $encrypted_text="-";
  }
  $uploaddir = '/tmp/';
  $uploadfile = $uploaddir.basename($_FILES['userfile']['name']);
  if ($_FILES['userfile']['size'] > 0) {
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
      $data=file_get_contents($uploadfile);
      $dataBase64=openssl_encrypt(base64_encode($data), $ciphering, $encryption_key, $options, $encryption_iv);
      if (!empty($_POST['pskInput'])) {
        $dataBase64=openssl_encrypt($dataBase64, $ciphering, $_POST['pskInput'], $options, $encryption_iv);
        $psk="1";
      }
      unlink($uploadfile);
    }
  }
  if ($_FILES['userfile']['size'] > 0) {
    $messageInsert=$mysqli_dbh->prepare("INSERT INTO `messages` (`user_id`,`created`,`lifetime`,`token`,`link`,`short_link`,`message`,`file`,`file_name`,`psk`,`views_remaining`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $messageInsert->execute([$_SESSION['user_id'], time(), trim($_POST['timeValid'])*3600, trim($_POST['csrfToken']), $link, $shortLink, $encrypted_text, $dataBase64, basename($_FILES['userfile']['name']), $psk, $viewLimit]);
  } else {
    $messageInsert=$mysqli_dbh->prepare("INSERT INTO `messages` (`user_id`,`created`,`lifetime`,`token`,`link`,`short_link`,`message`,`psk`,`views_remaining`) VALUES (?,?,?,?,?,?,?,?,?)");
    $messageInsert->execute([$_SESSION['user_id'], time(), trim($_POST['timeValid'])*3600, trim($_POST['csrfToken']), $link, $shortLink, $encrypted_text, $psk, $viewLimit]);
  }
  $messageId=(int)$mysqli_dbh->lastInsertId();
  $historyInsert=$mysqli_dbh->prepare("INSERT INTO `message_history` (`message_id`,`user_id`) VALUES (?,?)");
  $historyInsert->execute([$messageId, $_SESSION['user_id']]);
  $shareQuery=$shortLink === null ? "link=".$link : "s=".$shortLink;
  $shareUrl="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?".$shareQuery;
  ?>
  <div class="bg-light p-5 rounded">
    <div class="col-sm-8 mx-auto">
      <h1><?php echo($lang_cr1);?></h1>
      <p><a href="<?php echo(htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'));?>"><?php echo(htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'));?></a></p>
      <p><?php echo($lang_cr2);?></p>
      <p><a href="<?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);?>"><?php echo($lang_cr3);?></a></p>
    </div>
  </div>
  <?php
  die();
}

//Open link function.Has Open button function to make the link unsensible to any messager preview 
if (isset($_GET['link']) || isset($_GET['s'])) {
  //Resolve either the standard secret link or its optional built-in alias.
  if (isset($_GET['s'])) {
    $messageLookup=$mysqli_dbh->prepare("SELECT * FROM `messages` WHERE `short_link`=? LIMIT 1");
    $messageLookup->execute([trim($_GET['s'])]);
  } else {
    $messageLookup=$mysqli_dbh->prepare("SELECT * FROM `messages` WHERE `link`=? LIMIT 1");
    $messageLookup->execute([trim($_GET['link'])]);
  }
  $result=$messageLookup->fetch(PDO::FETCH_ASSOC);
  //Does this link exists at all
  if ($result === false){
  ?>
  <div class="bg-light p-5 rounded">
    <div class="col-sm-8 mx-auto">
      <h1><?php echo($lang_err4);?></h1>
      <p><?php echo($lang_err5);?></p>
      <p><a href="<?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);?>"><?php echo($lang_ret);?></a></p>
    </div>
  </div>
  <?php
  die();
  }
  //collecting all necessary data from DB about this link
  $encrypted_text=$result['message'];
  $token=$result['token'];
  $lifetime=$result['lifetime'];
  $created=$result['created'];
  $id=$result['id'];
  $link=$result['link'];
  $file=$result['file'];
  $fileName=$result['file_name'];
  $isPsk=$result['psk'];
  //Checking does the link is still valid
  if (($created+$lifetime) < time()) {
    ?>
    <div class="bg-light p-5 rounded">
      <div class="col-sm-8 mx-auto">
        <h1><?php echo($lang_err4);?></h1>
        <p><?php echo($lang_err6);?></p>
        <p><a href="<?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);?>"><?php echo($lang_ret);?></a></p>
      </div>
    </div>
    <?php
    $mysqli="DELETE FROM `messages` WHERE id='".$id."'";
    $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
    die();
  }
  //If "open" value not sent, just generating Show secure message button 
  if (!isset($_POST['open']) && (!isset($_POST['dwlAttachment']))) {
  ?>
  <div class="bg-light p-5 rounded">
    <div class="col-sm-8 mx-auto">
      <h4><?php echo($lang_conf);?></h4>
        <p>
          <form method="POST" action="">
            <button class="btn-lg btn-primary" style="align: center; width: 300px;" type="submit" name="open"><?php echo($lang_open);?></button>
            <?php if ($isPsk=="1") { ?>
              <p><label for="pskOpInput"><?php echo($lang_main9);?></label>
              <input type="text" style="width: 210px;" class="form-control" id="pskOpInput" value="" name="pskOpInput"></p>
            <?php } ?>
          </form>
        </p>
    </div>
  </div>
  <?php
  die();
  }
  //if "open" value is set, but message is not encrypted by PSK, and this is not download option - showing up encrypted message from this link
  if (isset($_POST['open']) && (!isset($_POST['pskOpInput'])) && (!isset($_POST['dwlAttachment']))) {
    $encryption_iv=mb_substr($token,0,6).mb_substr($token,0,6)."1467";
    if (!consumeMessageView($mysqli_dbh, $id)) {
      echo("<p><font color=\"red\">".$lang_err7."</font></p>");
      die();
    }
    finishMessageView($mysqli_dbh, $id, $fileName, $link);
    ?>
    <div class="bg-light p-5 rounded">
      <div class="col-sm-8 mx-auto">
        <h1><?php echo($lang_text); ?></h1>  
         <h3><pre class="protected-message" aria-label="<?php echo htmlspecialchars($lang_text, ENT_QUOTES, 'UTF-8'); ?>"><?php
        //if 'message' field in DB is not empty
        if (!empty($encrypted_text)) {
          //if 'encrypted_text' is not equal to "-" which means the text message wasn't added while creation of message
          if ($encrypted_text != "-") {
            $decrypted_text=openssl_decrypt($encrypted_text, $ciphering, $key, $options, $encryption_iv);
            echo("$decrypted_text"); 
          }
        } else { 
          echo("<font color=\"red\">$lang_err7</font>"); 
        }?></pre></h3>
        <?php if (!empty($fileName)) {?>
        <div><form method="POST" action="">
        <button class="btn-lg btn-primary" style="width1: 95vw;" type="submit" name="dwlAttachment"><?php echo($lang_main6);?></button>
        </form></div>
        <?php } 
        echo("<br><p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."\">".$lang_ret."</a></p>"); ?>
      </div>
    </div>
    <?php
    die();
  }
  //when we open a message with additional password encoding
  if (isset($_POST['open']) && (isset($_POST['pskOpInput']))) {
    $encryption_iv=mb_substr($token,0,6).mb_substr($token,0,6)."1467";
    if (empty($_POST['pskOpInput'])) {
      echo("<script>alert('".$lang_err8."');</script>");
      echo("<p><font color=\"red\">".$lang_err8."</font></p>");
      echo("<p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\">".$lang_main10."</a></p>");
      die();
    }
    $decrypted_text=openssl_decrypt($encrypted_text, $ciphering, $_POST['pskOpInput'], $options, $encryption_iv);
    $decrypted_text=openssl_decrypt($decrypted_text, $ciphering, $key, $options, $encryption_iv);
    if (empty($decrypted_text)) {
      echo("<p><font color=\"red\">".$lang_err9."</font></p>");
      die();
    }
    if (!consumeMessageView($mysqli_dbh, $id)) {
      echo("<p><font color=\"red\">".$lang_err7."</font></p>");
      die();
    }
    //Decrypt the attachment layer only after the password has been validated.
    if (!empty($fileName)) {
      $decrypted_file=openssl_decrypt($file,$ciphering,$_POST['pskOpInput'],$options,$encryption_iv);
      $updateFile=$mysqli_dbh->prepare("UPDATE `messages` SET `file`=? WHERE `id`=?");
      $updateFile->execute([$decrypted_file, $id]);
    }
    finishMessageView($mysqli_dbh, $id, $fileName, $link);
    ?>
    <div class="bg-light p-5 rounded">
      <div class="col-sm-8 mx-auto">
        <h1><?php echo($lang_text);?></h1>  
         <h3><pre class="protected-message" aria-label="<?php echo htmlspecialchars($lang_text, ENT_QUOTES, 'UTF-8'); ?>"><?php
         echo($decrypted_text);?>
        </pre></h3>
         <?php if (!empty($fileName)) {?>
         <div><form method="POST" action="">
          <button class="btn-lg btn-primary" style="width1: 95vw;" type="submit" name="dwlAttachment"><?php echo($lang_main6);?></button>
          </form></div>
          <?php } 
          echo("<br><p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."\">".$lang_ret."</a></p>"); ?>
          </div>
        </div>
        <?php
        die();
      }
  if (isset($_POST['dwlAttachment'])) {
    $encryption_iv=mb_substr($token,0,6).mb_substr($token,0,6)."1467";
    $decrypted_file=openssl_decrypt($file, $ciphering, $key, $options, $encryption_iv);
    $attachment=base64_decode($decrypted_file);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$fileName.'"');
    echo($attachment);
    $mysqli="DELETE FROM `messages` WHERE id='".$id."'";
    $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
    $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','".$_SERVER['REMOTE_ADDR']."','file');";
    $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
    $history=$mysqli_dbh->prepare("UPDATE `message_history` SET `viewed`=1, `viewed_at`=COALESCE(`viewed_at`, CURRENT_TIMESTAMP) WHERE `message_id`=?");
    $history->execute([$id]);
    die();
  }
}
?>
  <div id="global-block" style="width: 99vw; height: 99vh; padding-left: 1rem; float: center; postion: relative;">
    <div id="top-block" style="margin-top: 10px; text-align: center; postion: absolute; padding-right: 1rem;">
      <h1 class="fw-bold lh-1 mb-3"><?php echo($lang_main);?></h1>
      <p class="col-lg-101 fs-51" style="text-align: center;"><?php echo($lang_main2);?></p>
    </div>
    <?php if (!empty($authError)) { ?>
      <div class="alert alert-danger" role="alert"><?php echo(htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'));?></div>
    <?php } ?>
    <?php if (!empty($_SESSION['user_id'])) { ?>
    <div class="d-flex justify-content-end align-items-center gap-3 mb-3">
      <span><?php echo($lang_signed_in);?> <strong><?php echo(htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'));?></strong></span>
      <form method="POST" class="m-0">
        <input type="hidden" name="csrfToken" value="<?php echo($token);?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit" name="logout"><?php echo($lang_logout);?></button>
      </form>
    </div>
    <div id="bottom-block" style="postion: absolute; padding-right: 1rem;">
      <form class="p-4 p-md-5 border rounded-3 bg-light" enctype="multipart/form-data" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="16535000" />
        <div id="main-text-field" class="form-floating mb-3">
          <textarea style="height: 300px;" class="form-control" id="textInput" name="textMain"></textarea>
          <label for="textInput"><?php echo($lang_main3);?></label>
        </div>
        <div class="form-floating1 mb-1">
          <input type="number" style="width: 210px;" class="form-control" id="hoursInput" min="0" max="24" value="1" name="timeValid">
          <label for="hoursInput"><?php echo($lang_main4);?></label>
          <input type="number" style="width: 210px;" class="form-control" id="viewsInput" min="1" max="100" value="1" name="viewLimit" required>
          <label for="viewsInput"><?php echo($lang_main11);?></label>
          <div class="form-check my-3">
            <input class="form-check-input" id="shortenUrl" name="shortenUrl" type="checkbox" value="1">
            <label class="form-check-label" for="shortenUrl"><?php echo($lang_main12);?></label>
          </div>
          <input type="text" style="width: 210px;" class="form-control" id="pskInput" value="" name="pskInput">
          <label for="pskInput"><?php echo($lang_main7);?></label>
          <input class="form-control" style="width: 210px;" id="userfile" name="userfile" type="file" />
          <label for="userfile"><?php echo($lang_main8);?></label>
        </div>
        <hr class="my-4">
        <button class="btn-lg btn-primary" style="width1: 95vw;" type="submit" name="create"><?php echo($lang_main5);?></button>
        <input type="hidden" name="csrfToken" value="<?php echo($token);?>">
      </form>
    </div>
    <section class="mt-4 mb-4 p-4 border rounded-3 bg-light" aria-labelledby="message-history-heading">
      <h2 id="message-history-heading" class="h4 mb-3"><?php echo($lang_history);?></h2>
      <?php
      $historyQuery=$mysqli_dbh->prepare("SELECT `sent_at`,`viewed` FROM `message_history` WHERE `user_id`=? ORDER BY `sent_at` DESC, `id` DESC");
      $historyQuery->execute([$_SESSION['user_id']]);
      $historyRows=$historyQuery->fetchAll(PDO::FETCH_ASSOC);
      if (empty($historyRows)) { ?>
        <p class="mb-0"><?php echo($lang_history_empty);?></p>
      <?php } else { ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?php echo($lang_history_sent);?></th>
                <th scope="col"><?php echo($lang_history_status);?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($historyRows as $historyRow) { ?>
              <tr>
                <td><time datetime="<?php echo(htmlspecialchars($historyRow['sent_at'], ENT_QUOTES, 'UTF-8'));?>"><?php echo(htmlspecialchars($historyRow['sent_at'], ENT_QUOTES, 'UTF-8'));?></time></td>
                <td>
                  <?php if ((int)$historyRow['viewed'] === 1) { ?>
                    <span class="badge bg-success"><?php echo($lang_history_viewed);?></span>
                  <?php } else { ?>
                    <span class="badge bg-secondary"><?php echo($lang_history_unviewed);?></span>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </section>
    <?php } else { ?>
    <div class="row g-4 justify-content-center">
      <div class="col-md-6 col-lg-5">
        <form class="p-4 border rounded-3 bg-light h-100" method="POST">
          <h2 class="h4 mb-3"><?php echo($lang_login);?></h2>
          <div class="mb-3">
            <label class="form-label" for="login-username"><?php echo($lang_username);?></label>
            <input class="form-control" id="login-username" name="username" type="text" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="login-password"><?php echo($lang_password);?></label>
            <input class="form-control" id="login-password" name="password" type="password" autocomplete="current-password" required>
          </div>
          <input type="hidden" name="csrfToken" value="<?php echo($token);?>">
          <button class="btn btn-primary" type="submit" name="login"><?php echo($lang_login);?></button>
        </form>
      </div>
      <div class="col-md-6 col-lg-5">
        <form class="p-4 border rounded-3 bg-light h-100" method="POST">
          <h2 class="h4 mb-3"><?php echo($lang_register);?></h2>
          <div class="mb-3">
            <label class="form-label" for="register-username"><?php echo($lang_username);?></label>
            <input class="form-control" id="register-username" name="username" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9_.-]+" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="register-password"><?php echo($lang_password);?></label>
            <input class="form-control" id="register-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
          </div>
          <input type="hidden" name="csrfToken" value="<?php echo($token);?>">
          <button class="btn btn-primary" type="submit" name="register"><?php echo($lang_register);?></button>
        </form>
      </div>
    </div>
    <?php } ?>
  </div>
</body>
</html>
