<?php

function writeLog($text){
  $log = date('Y-m-d H:i:s') .": ". $text;
  $date = date('Y-m-d');
  file_put_contents($_SERVER["DOCUMENT_ROOT"]."/logs/log_".$date.'.txt', $log . PHP_EOL, FILE_APPEND);
}