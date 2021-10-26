<?php
function getOperator ($param){
  if (is_numeric($param)){
    $param = "" . $param; 
  }
  $sp = explode(":", $param);
  $operator = " = ";
  if (count($sp) === 1) {
      $val = $sp[0];
  } else {
      $operator = " " . $sp[0] . " ";
      $val = $sp[1];
  }
  return [$operator, $val];
}