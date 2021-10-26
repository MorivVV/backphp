<?php
namespace sql;
include_once $_SERVER["DOCUMENT_ROOT"]."/functions/writeLog.php";
// include_once $_SERVER["DOCUMENT_ROOT"]."/functions/restApi.php";
include_once "sql/DataBase.php";
include_once "sql/Query.php";
$param;
$param['SERVER'] = $_SERVER;
$param['GET'] = $_GET;
$param['POST'] = $_POST;
$param['JSON'] = json_decode(file_get_contents('php://input'));
$param['headers'] = getallheaders();

$db = new DataBase;
$query = new Query($param['JSON']);
if ($conn = $db->getConnect()){
}else{
  echo "NOT connect";
}
if (isset($param['headers']["V-Token"])){
  $token = $param['headers']["V-Token"];
  $ip = $_SERVER['REMOTE_ADDR'];
  writeLog("Используется токен: $token, $ip");
  $stm = $conn->prepare($query->checkAuth());
  $stm->execute(array($token, $ip));
  if ($row = $stm->fetch()){
    writeLog("Авторизация активна: $token");
  }else{
    echo "Нет авторизации";
    return;
  }
}else{
  echo "Нет авторизации";
  return;
}

$execSelect = $query->getDelete();
$paramList = $query->getParams();
writeLog($execSelect);
$stm = $conn->prepare($execSelect);
writeLog(json_encode($paramList));
$stm->execute($paramList);
writeLog(json_encode($stm->errorInfo()));
$row;
$row["count"] = $stm->rowCount();
echo json_encode($row);

