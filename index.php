<?php
//Release 1.3
//
//file with lang. translations
require_once "lang.php";
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
if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
  $lng=$_SERVER["HTTP_ACCEPT_LANGUAGE"];
}
if (strpos($lng,"uk-UA")) {
  setLang(1);
} elseif (strpos($lng,"ru")) { 
  setLang(2);
} else { 
  setLang(3);
}
//common database config
$mysqli_dsn="mysql:host={$mysqli_host};dbname={$mysqli_db}";
$mysqli_options=[ PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', ];
$mysqli_dbh=new PDO($mysqli_dsn, $mysqli_dbuser, $mysqli_dbpass, $mysqli_options);
$ciphering="AES256";
$iv_length=openssl_cipher_iv_length($ciphering);
$options=0;

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
      $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','expired,unread');";
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
        document.body.classList.add('privacy-hidden');
      });
    });
  }());
</script>
<?php }
//Create link and secure data function
if (isset($_POST['create'])) {
  //check all variables are set and not empty
  if ((!isset($_POST['textMain'])) || (!isset($_POST['timeValid'])) || ((empty($_POST['textMain'])) && ($_FILES['userfile']['size'] == 0)) || (empty($_POST['timeValid']))) {
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
    $mysqli="INSERT INTO `messages` (`created`,`lifetime`,`token`,`link`,`message`,`file`,`file_name`,`psk`) VALUES ('".time()."','".(trim(htmlspecialchars($_POST['timeValid']))*3600)."','".(trim(htmlspecialchars($_POST['csrfToken'])))."','".$link."','".$encrypted_text."','".$dataBase64."','".$_FILES['userfile']['name']."','".$psk."')";
  } else {
    $mysqli="INSERT INTO `messages` (`created`,`lifetime`,`token`,`link`,`message`,`psk`) VALUES ('".time()."','".(trim(htmlspecialchars($_POST['timeValid']))*3600)."','".(trim(htmlspecialchars($_POST['csrfToken'])))."','".$link."','".$encrypted_text."','".$psk."')";
  }
  $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
  ?>
  <div class="bg-light p-5 rounded">
    <div class="col-sm-8 mx-auto">
      <h1><?php echo($lang_cr1);?></h1>
      <p><a href="<?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?link=".$link);?>"><?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?link=".$link);?></a></p>
      <p><?php echo($lang_cr2);?></p>
      <p><a href="<?php echo("https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);?>"><?php echo($lang_cr3);?></a></p>
    </div>
  </div>
  <?php
  die();
}

//Open link function.Has Open button function to make the link unsensible to any messager preview 
if (isset($_GET['link'])) {
  //Doing request to DB with given link
  $mysqli="SELECT * FROM `messages` WHERE link='".(trim(htmlspecialchars($_GET['link'])))."'";
  $data=$mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
  $count=$data->rowCount();
  //Does this link exists at all
  if ($count <= 0){
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
  foreach ($data as $key2 => $result) {
    $encrypted_text=$result['message'];
    $token=$result['token'];
    $lifetime=$result['lifetime'];
    $created=$result['created'];
    $id=$result['id'];
    $link=$result['link'];
    $file=$result['file'];
    $fileName=$result['file_name'];
    $isPsk=$result['psk'];
  }
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
        <?php 
          $mysqli="UPDATE `messages` set message='' WHERE id='".$id."'";
          $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
          $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','".$_SERVER['REMOTE_ADDR']."','text');";
          $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
        } else {
          $mysqli="DELETE FROM `messages` WHERE id='".$id."'";
          $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
          $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','".$_SERVER['REMOTE_ADDR']."','text');";
          $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC); 
        }
        echo("<br><p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."\">".$lang_ret."</a></p>"); ?>
      </div>
    </div>
    <?php
    die();
  }
  //when we open a message with additional password encoding
  if (isset($_POST['open']) && (isset($_POST['pskOpInput']))) {
    $encryption_iv=mb_substr($token,0,6).mb_substr($token,0,6)."1467";
    //now when we got a PSK - we decrypt the attachment from user's password if it exists
    if (!empty($fileName) && (!empty($_POST['pskOpInput']))) {
      $decrypted_file=openssl_decrypt($file,$ciphering,$_POST['pskOpInput'],$options,$encryption_iv);
      $mysqli="UPDATE `messages` SET file='".$decrypted_file."' WHERE id='".$id."';";
      $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
    }
    ?>
    <div class="bg-light p-5 rounded">
      <div class="col-sm-8 mx-auto">
        <h1><?php echo($lang_text);?></h1>  
         <h3><pre class="protected-message" aria-label="<?php echo htmlspecialchars($lang_text, ENT_QUOTES, 'UTF-8'); ?>"><?php
         if (empty($_POST['pskOpInput']))
         {
          echo("<script>alert('".$lang_err8."');</script>");
          echo("<p><font color=\"red\">".$lang_err8."</font></p>");
          echo("<p><a href=\"https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\">".$lang_main10."</a></p>");
          die();
         }
         //if 'message' field in DB is not empty that means the message wasn't viewed 
         if (!empty($encrypted_text)) {
          $decrypted_text=openssl_decrypt($encrypted_text, $ciphering, $_POST['pskOpInput'], $options, $encryption_iv);
          $decrypted_text=openssl_decrypt($decrypted_text, $ciphering, $key, $options, $encryption_iv);
          //if 'decrypted_text' is not empty that means the PSK is correct
          if (!empty($decrypted_text)) {
            echo($decrypted_text); 
          } else {
            //if 'decrypted_text' is empty that means the PSK is incorrect.Show error message.
            echo("<font color=\"red\">".$lang_err9."</font>");
          }
        } else { 
          echo("<font color=\"red\">$lang_err7</font>"); 
        }?>
        </pre></h3>
         <?php if (!empty($fileName)) {?>
         <div><form method="POST" action="">
          <button class="btn-lg btn-primary" style="width1: 95vw;" type="submit" name="dwlAttachment"><?php echo($lang_main6);?></button>
          </form></div>
          <?php
            $mysqli="UPDATE `messages` set message='' WHERE id='".$id."'";
            $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
            $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','".$_SERVER['REMOTE_ADDR']."','text');";
            $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);

          } else {
            $mysqli="DELETE FROM `messages` WHERE id='".$id."'";
            $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC);
            $mysqli="INSERT INTO `msglogs` (`msgid`,`msglink`,`ip`,`type`) VALUES ('".$id."','".$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/?link=".$link."','".$_SERVER['REMOTE_ADDR']."','text');";
            $mysqli_dbh->query($mysqli, PDO::FETCH_ASSOC); 
          }
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
    die();
  }
}
//generating salt and cookie for every new page
$salt=rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
$token=$salt.":".MD5($salt.":".$key);
setcookie("CSRF", $token, time() + 600, "/");
?>
  <div id="global-block" style="width: 99vw; height: 99vh; padding-left: 1rem; float: center; postion: relative;">
    <div id="top-block" style="margin-top: 10px; text-align: center; postion: absolute; padding-right: 1rem;">
      <h1 class="fw-bold lh-1 mb-3"><?php echo($lang_main);?></h1>
      <p class="col-lg-101 fs-51" style="text-align: center;"><?php echo($lang_main2);?></p>
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
  </div>
</body>
</html>
