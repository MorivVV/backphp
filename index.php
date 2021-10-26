<?php
include_once $_SERVER["DOCUMENT_ROOT"]."/functions/writeLog.php";
include_once $_SERVER["DOCUMENT_ROOT"]."/functions/restApi.php";
include_once "sql/DataBase.php";
$param;
$param['SERVER'] = $_SERVER;
$param['GET'] = $_GET;
$param['POST'] = $_POST;
$param['JSON'] = json_decode(file_get_contents('php://input'));
$param['headers'] = getallheaders();

// echo json_encode($param);

// echo '$_GET', '<br>',json_encode($_GET), '<br>';
// echo '$_POST', '<br>',json_encode($_POST), '<br>';
// echo '$GLOBALS ', '<br>',json_encode($GLOBALS), '<br>';
// echo '$getallheaders()  ', '<br>',json_encode(getallheaders()), '<br>';
// echo phpinfo();
$db = new DataBase;
if ($conn = $db->getConnect()){
}else{
  echo "NOT connect";
}
if (isset($param['headers']["V-Token"])){
  writeLog(json_encode($param['headers']));
}else{
  echo "Нет авторизации";
  return;
}
if (isset($param['JSON']->from)){
  writeLog(json_encode($param['JSON']));
  $table = str_replace(":"," as ",$param['JSON']->from);
}else{
  return;
}
$select = "";
$paramList = array();
if (isset($param['JSON']->fields)){
  $fields = $param['JSON']->fields;
  foreach ($fields as $f) {
    if ($select !== ""){
      $select .= ", ";
    }
    $select .= str_replace(":"," as ",$f);
  }
}
if ($select === ""){
  $select = "*";
}
$where = "";
if (isset($param['JSON']->filter)){
  $filter = $param['JSON']->filter;
  foreach ($filter as $k => $v) {
    writeLog('$k='.$k);
    writeLog('$v='.$v);
    if ($where !== ""){
      $where .= " AND ";
    }
    $op = getOperator($v);
    $paramList[] = $op[1];
    $where .= $k . $op[0] . " ?" ;
  }
}
if ($where !== ""){
  $where = "WHERE $where";
}
function desk($s)
{
  if (substr($s, 0, 1) === "-"){
    $res = ltrim($s, "-") . " DESC";
  }else{
    $res = $s . " ASC";
  }
  return $res;
}
$order  = "";
if (isset($param['JSON']->sort)){
  $order = "ORDER BY " . implode(", ", array_map('desk', $param['JSON']->sort));
}
$limit  = 100;
if (isset($param['JSON']->limit)){
  $limit = $param['JSON']->limit;
}

$execSelect = "SELECT $select FROM $table $where $order LIMIT $limit";
writeLog($execSelect);
$stm = $conn->prepare($execSelect);
writeLog(json_encode($paramList));
$stm->execute($paramList);
writeLog(json_encode($stm->errorInfo()));
$row = $stm->fetchAll();
echo json_encode($row);

