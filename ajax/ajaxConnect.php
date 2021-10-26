<?php
include_once 'functions.php';
$config = include_once 'configs/localhost.php';
// $serverConfFile = str_replace(".", "", $_SERVER['SERVER_NAME']);
// $config = include_once "configs/$serverConfFile.php";
// writeLogs("Используем настройки из файла 'configs/$serverConfFile.php'", 'Axios.txt');
try {
    $dsn = 'mysql:host='.$config['host'].';dbname='.$config['db_name'].';charset='.$config['charset'];
    $pdo = new PDO($dsn, $config['user'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    writeLogs("Подключение успешно ". $config['host']. ' ' . $config['db_name'], 'Axios.txt');
  } catch (PDOException $e) {
    print "Error!: " . $e->getMessage();
    die();
  }