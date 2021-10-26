<?php
include_once "ajaxConnect.php";

function encrypt($text, $pass) {
  $etext = "";
  $hashpass = str_split(MD5($pass),1);
  for ($i=0; $i<count($hashpass); $i++){
      $hashpass[$i] = ord($hashpass[$i]);
  }
  $byteText = str_split($text,1);
  for ($i=0; $i<count($byteText); $i++){
      $byteText[$i] = ord($byteText[$i]) * $hashpass[$i % 32];
      $etext .= chr(intdiv($byteText[$i],256));
      $etext .= chr($byteText[$i] % 256);
  }
  return base64_encode($etext);
}

function decrypt($text, $pass) {
  $dtext = "";
  $hashpass = str_split(MD5($pass),1);
  for ($i=0; $i<count($hashpass); $i++){
      $hashpass[$i] = ord($hashpass[$i]);
  }
  $charText = str_split(base64_decode($text),1);
  $byteText = array();
  for ($i=0; $i<count($charText); $i++){
      if ($i % 2 == 1){
          $byteText []= ord($charText[$i-1]) * 256 + ord($charText[$i]);
      }
  }
  for ($i=0; $i<count($byteText); $i++){
      $dtext .= chr($byteText[$i] / $hashpass[$i % 32]);
  }
  return $dtext;
}

writeLogs("==================" . $_SERVER['REMOTE_ADDR'] . "==============", 'Autorization.txt');
// получаем логин запрашиваемого авторизацию
if (isset($_POST['login'])){
  $login = $_POST['login'];
}else{
  if (isset($_GET['login'])){
    $login = $_GET['login'];
  }else{
    return;
  }
}
writeLogs("login = $login", 'Autorization.txt');
// находим пользователя по логину
$stm = $pdo->prepare("SELECT u.ID
, u.PASS
, u.SESSEION_HASH
, u.question
FROM bd_users u
WHERE u.USER_NAME = ?");
$stm->execute(array($login));

if ($row = $stm->fetch()){
  $userId = $row['ID'];
  $userPass = $row['PASS'];
  $userHash = $row['SESSEION_HASH'];
  $userQuestion = $row['question'];
}else{
  return;
}
writeLogs("userId = $userId", 'Autorization.txt');
writeLogs("userPass = $userPass", 'Autorization.txt');
writeLogs("userHash = $userHash", 'Autorization.txt');
writeLogs("userQuestion = $userQuestion", 'Autorization.txt');

if (isset($_POST['answer'])){
  $answer = $_POST['answer'];
  writeLogs("answer = $answer", 'Autorization.txt');
  if ($answer === $userQuestion) {
    $hash_user = md5(microtime().$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
    $stm = $pdo->prepare("UPDATE bd_users SET SESSEION_HASH = ?, IP=? WHERE ID= ?");
    $stm->execute(array($hash_user, $_SERVER['REMOTE_ADDR'], $userId));
    $succesAutorize['token'] = $hash_user;
    $succesAutorize['user'] = $login;
    $succesAutorize['kod_user'] = $userId;
    writeLogs("Выдан токен: $hash_user", 'Autorization.txt');
    setcookie("kod_user" , $userId, time()+36000, "/");//продлеваем сессию на час
    setcookie("USER" , $login, time()+36000, "/");//продлеваем сессию на час
    setcookie("HASHIP", $hash_user, time()+36000, "/");//установка куки с информацией о браузере, адресе и пароле пользователя
    echo json_encode($succesAutorize);
  }else{
    echo "password not authorized!!!";
  }
  return;
}
$testString = md5(microtime() . 'dfe');
writeLogs("testString = $testString", 'Autorization.txt');

$stm = $pdo->prepare("UPDATE bd_users SET question = ? WHERE ID= ?");
$stm->execute(array($testString, $userId));

$question = encrypt($testString,$userPass);
writeLogs("question = $question", 'Autorization.txt');
echo json_encode($question);


