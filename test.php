<?php
namespace sql;
// use sql\Query;
include_once "sql/Query.php";
include_once "sql/DataBase.php";
$test = new Query(json_decode('{"to":"temp","toFields":["firdt","kod_user","ident"],"fields":["SCHEMA_NAME:nspname"],"from":"INFORMATION_SCHEMA.SCHEMATA","sort":["nspname"]}'));
$ins = new Query(json_decode('{"fields":["TABLE_NAME:tablename","ENGINE:tableowner","TABLE_CATALOG:tablespace","INDEX_LENGTH:hasindexes","TABLE_COMMENT:hasrules","TABLE_ROWS:hastriggers"],"from":"INFORMATION_SCHEMA.tables","filter":{"table_schema":"checkroom"},"sort":["table_name"]}'));

$db = new \DataBase;
if ($conn = $db->getConnect()){
  echo "connect Ok<br>";
}else{
  echo "NOT connect";
}

// $ins->setInsertTo("b32_18009385_users.admin_bbcode");
// $ins->setInsert(array("b32_","e18009385_","users","admin","bbcode"));
// $fields = array("TABLE_NAME:tablename","ENGINE:tableowner","TABLE_CATALOG:tablespace","INDEX_LENGTH:hasindexes","TABLE_COMMENT:hasrules","TABLE_ROWS:hastriggers");
// $ins->setSelect($fields);
// // $ins->setFrom("INFORMATION_SCHEMA.tables");
// $ins->setWhere(json_decode('{"table_schema":"checkroom"}'));
// // $ins->setLimit(10);
// // $ins->setOrder(array("table_name"));
// $ins->setValues(json_decode('{"table_schema":"checkroom","schema":12,"table":"14"}'));
echo $ins->getInsert();
echo "<br>";
echo json_encode($ins->getParams());
echo "<br>";
// {"fields":["TABLE_NAME:tablename","ENGINE:tableowner","TABLE_CATALOG:tablespace","INDEX_LENGTH:hasindexes","TABLE_COMMENT:hasrules","TABLE_ROWS:hastriggers"],"from":"INFORMATION_SCHEMA.tables","filter":{"table_schema":"checkroom"},"sort":["table_name"]}
// $fields = array("TABLE_NAME:tablename","ENGINE:tableowner","TABLE_CATALOG:tablespace","INDEX_LENGTH:hasindexes","TABLE_COMMENT:hasrules","TABLE_ROWS:hastriggers");
// $test->setSelect($fields);
// $test->setFrom("INFORMATION_SCHEMA.tables");
// $test->setWhere(json_decode('{"table_schema":"checkroom"}'));
// $test->setLimit(10);
// $test->setOrder(array("table_name"));

echo $test->getQuery();
echo "<br>";
echo json_encode($test->getParams());

$stm = $conn->prepare($test->getQuery());
$stm->execute($test->getParams());
$row = $stm->fetchAll();
echo "<br>";
echo json_encode($row);