<?php
namespace sql;
class DataBase {
    private $pdo = null;

    public function getConnect(){
        return $this->pdo;
    }

    public function __construct()
    {
        $config = include_once $_SERVER["DOCUMENT_ROOT"]."/config/localhost.php";
        $dsn = 'mysql:host='.$config['host'].';dbname='.$config['db_name'].';charset='.$config['charset'];
        $this->pdo = new \PDO($dsn, $config['user'], $config['password']);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $this;
    }
}


