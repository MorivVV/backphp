<?php
date_default_timezone_set('Europe/Samara');
function writeLogs ($text, $file){
    if (isset($text)) {
        file_put_contents('logs/'.$file, $_SERVER['SERVER_NAME'].date(" Y-m-d H:i:s: ").$text. PHP_EOL, FILE_APPEND);
    }
  }
