<?php
include_once "ajaxConnect.php";
$axiosLog = date("Y-m-d") . "Axios.txt";
writeLogs("==================" . $_SERVER['REMOTE_ADDR'] . "==============", $axiosLog);
// получаем параметр запрашиваемого запроса
$paramData = json_decode(file_get_contents('php://input'), true);
$headers = getallheaders();
if (isset($_POST['sqlname'])){
  $paramData = $_POST;
}
if (isset($_GET['sqlname'])){
  $paramData = $_GET;
}

if (isset($paramData['sqlname'])){
  $sqlname = $paramData['sqlname'];
}else{
  return;
}
writeLogs("получаем параметр запрашиваемого запроса\n". json_encode($paramData), $axiosLog);
writeLogs("Запрос sqlname = $sqlname", $axiosLog);
// получаем из БД текст запроса и параметры
$stm = $pdo->prepare("SELECT 
ssq.sql_query QUERY
, ssq.sql_params PARAMS
, ssq.result RESULT
, ssq.need_token AUTH
FROM sys_sql_query ssq
WHERE ssq.sql_name = ?");
$stm->execute(array($sqlname));
if ($row = $stm->fetch()){
  $sqlQuery = $row['QUERY'];
  $sqlParams = $row['PARAMS'];
  $sqlResult = $row['RESULT'];
  $sqlAUTH = $row['AUTH'];
}else{
  writeLogs("Ошибка:".json_encode($stm->errorInfo()), $axiosLog);
  return;
}
// writeLogs("sqlAUTH = $sqlAUTH", $axiosLog);
if ($sqlAUTH == 1) {
  writeLogs("Требуется авторизация", $axiosLog);
  $userHash = $headers["V-Token"];
  $login = $_SERVER['REMOTE_ADDR'];
  $stm = $pdo->prepare("SELECT count(u.ID) CNT
  FROM bd_users u
  WHERE u.ip = ?
  AND u.SESSEION_HASH = ?");
  $stm->execute(array($login, $userHash));
  $res = 0;
  if ($row = $stm->fetch()){
    $res = $row['CNT'];
  }
  if ($res != 1){
    writeLogs("Авторизация не пройдена", $axiosLog);
    return;
  }
  writeLogs("Авторизация успешно пройдена", $axiosLog);
}else{
  writeLogs("Авторизация не требуется", $axiosLog);
}

writeLogs("Получили запрос\n$sqlQuery", $axiosLog);
// извлекаем из параметов имена переменных, с которыми нам нужно выполнить запрос
if ($sqlParams === ''){
  $paramList = array();
}else {
  $paramList = array_map('trim', explode(',', $sqlParams));
}
$bindParams = array();

// получаем нужные параметры из запроса пост
foreach ($paramList as $sourceParam) {
  writeLogs("sourceParam= $sourceParam", $axiosLog);
  writeLogs("mb_strpos(sourceParam, ':')= ". mb_strpos($sourceParam, ':'), $axiosLog);
  if (mb_strpos($sourceParam, ':') === 1){
    $param = mb_substr($sourceParam, 2);
  }else{
    $param = $sourceParam;
  }
  if (isset($paramData[$param])){
    writeLogs("Параметр $param =" . $paramData[$param], $axiosLog);
    $postValue = $paramData[$param];
    // проверяем наличие списка параметров для in в запросах SELECT
    if (substr($sqlQuery,0,6) !== "SELECT" ||  strpos($postValue,',') === false ){
      $bindParams[$sourceParam] = $postValue;
    }else{
      $postValue = explode(',', $postValue);
      // если нашли запятую, то создаем несколько переменных с индесом
      $inParams = '';
      foreach ($postValue as $key => $value) {
        $bindParams[$sourceParam . $key] = $value;
        // in (:kod_group) OR :kod_group = -2
        if ($inParams !== ''){
          $inParams .= ', ';
        }
        $inParams .= ":" . $param . $key;
      }
      writeLogs("Список параметров по разрбору $inParams", $axiosLog);
      $sqlQuery = str_replace("in (:$param)", "in ($inParams)", $sqlQuery);
      $bindParams[$sourceParam] = -1;
    }
  }else{
    $bindParams[$sourceParam] = -2;
  }
}

writeLogs(json_encode($bindParams), $axiosLog);

$stm = $pdo->prepare($sqlQuery);
foreach($bindParams as $key => $value){
  $pdoParam = PDO::PARAM_STR;
  if (strpos($key,':') === 1 ){
    $t = mb_substr($key, 0, 1);
    switch ($t) {
      case 's':
        $pdoParam = PDO::PARAM_STR;
        break;
      case 'i':
        $pdoParam = PDO::PARAM_INT;
        break;
      case 'l':
        $pdoParam = PDO::PARAM_LOB;
        break;
      default:
        $pdoParam = PDO::PARAM_STR;
        break;
    }
    $key = mb_substr($key, 2);
  }
  ${$key} = $value;
  $stm->bindParam("$key", ${$key}, $pdoParam);
  writeLogs("\$stm->bindParam('$key', ${$key}, PDO::$pdoParam)", $axiosLog);
}

$stm->execute();
// $stm->execute($bindParams);

// проверяем наличие ошибок
$arr = $stm->errorInfo();
if ($arr[0] !== "00000"){
  writeLogs(json_encode($arr), 'AxiosErrors.txt');
}
writeLogs(json_encode($arr), $axiosLog);

$dataAudit = array();
if (substr($sqlQuery,0,6) === 'SELECT' || substr($sqlQuery,0,4) === 'WITH'){
    while ($row = $stm->fetch()){
      if (isset($row['ID'])){
        $dataAudit[$row['ID']] = $row;
      }else{
        $dataAudit []= $row;
      }
  }
}else{
  $dataAudit["count"] = $stm->rowCount();
}
writeLogs(json_encode($dataAudit), $axiosLog);
if ($sqlResult === 'json') {
  echo json_encode($dataAudit);
  
}else{
  echo $dataAudit[0][$sqlResult];
}
