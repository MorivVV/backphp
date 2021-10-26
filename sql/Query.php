<?php

namespace sql;

class Query
{
  protected ?string $query = "";
  protected ?string $select = "";
  protected ?string $from = "";
  protected ?string $set = "";
  protected ?string $where = "";
  protected ?string $order = "";
  protected ?string $limit = "";
  protected ?string $delete = "";
  protected ?string $insertField = "";
  protected ?string $insertTo = "";
  protected ?string $values = "";
  protected ?string $update = "";
  protected ?array $paramList = array();

  public function __construct($bodyQuery)
  {

    if (isset($bodyQuery->to) && isset($bodyQuery->fields)) {
      $this->setInsert($bodyQuery->fields);
      $this->setInsertTo($bodyQuery->to);
    }

    if (isset($bodyQuery->to) && isset($bodyQuery->set)) {
      $this->setUpdate($bodyQuery->to);
      $this->setUpdateVal($bodyQuery->set);
    }

    if (isset($bodyQuery->from)) {
      if (isset($bodyQuery->join)) {
        $this->setFrom($bodyQuery->from, $bodyQuery->join);
      } else {
        $this->setFrom($bodyQuery->from);
      }
    }

    if (isset($bodyQuery->from) && isset($bodyQuery->fields)) {
      $this->setSelect($bodyQuery->fields);
    }

    if (isset($bodyQuery->filter)) {
      $this->setWhere($bodyQuery->filter);
    }

    if (isset($bodyQuery->sort)) {
      $this->setOrder($bodyQuery->sort);
    }

    if (isset($bodyQuery->limit) && !isset($bodyQuery->page)) {
      $this->setLimit($bodyQuery->limit, 0);
    }
    if (isset($bodyQuery->limit) && isset($bodyQuery->page)) {
      $this->setLimit($bodyQuery->limit, $bodyQuery->page);
    }
  }

  private function getOperator($s)
  {
    if (is_int($s)) {
      $s = "" . $s;
    }
    $sp = array($s);
    if (strpos($s, ":") < 5) {
      $sp = explode(":", $s);
    }
    $operator = "=";
    if (count($sp) === 1) {
      $value = $sp[0];
    } else {
      $operator = "$sp[0]";
      $value = $sp[1];
    }
    // проверяем предопределенные глобальные переменные
    switch ($value) {
      case '${currentUser}':
        $value = 1;
        break;

      case '${currentTimestamp}':
        $value = date("Y-m-d H:i:s");
        break;

      case '${joinFields}':
        $value = "";
        $operator = "";
        break;

      default:
        break;
    }
    $value = preg_replace('/\$\{(.+)\}/', '$1', $value);
    return array($operator, $value);
  }

  private function desk($s)
  {
    if (substr($s, 0, 1) === "-") {
      $res = ltrim($s, "-") . " DESC";
    } else {
      $res = $s . " ASC";
    }
    return $res;
  }

  private function set($s)
  {
    $res = "$s = :set_$s";
    return $res;
  }

  private function aliacer($s)
  {
    $res = str_replace(":", " as ", $s);
    return $res;
  }

  public function getQuery(): string
  {
    $this->query = $this->select . $this->from .  $this->where .  $this->order . $this->limit;
    return $this->query;
  }

  public function checkAuth(): string
  {
    $sql = "SELECT 1
    FROM bd_users u
    WHERE u.SESSEION_HASH = ? 
    AND u.IP = ?";
    return $sql;
  }

  public function getInsert(): string
  {
    $this->query = $this->insertTo . $this->insertField . $this->values . PHP_EOL . $this->getQuery();
    return $this->query;
  }

  public function getUpdate(): string
  {
    $this->query = $this->insertTo . $this->insertField .  $this->where;
    return $this->query;
  }

  public function getDelete(): string
  {
    $this->query = "DELETE " . $this->from .  $this->where;
    return $this->query;
  }

  public function getParams(): array
  {
    return $this->paramList;
  }

  public function setInsert($fields): void
  {
    if (is_object($fields)) {
      $this->setValues($fields);
    } else {
      $this->insertField = "(" . implode(", ", $fields) . ") ";
    }
  }

  public function setInsertTo($table): void
  {
    $this->insertTo = "INSERT INTO $table ";
  }

  public function setUpdate($table): void
  {
    $this->insertTo = "UPDATE $table ";
  }

  public function setValues($values): void
  {
    if (is_object($values)) {
      $insArr = array();
      foreach ($values as $key => $value) {
        $v = $this->getOperator($value);
        $insArr[] = $key;
        $this->paramList["val_$key"] = $v[1];
      }
      $this->insertField = "(" . implode(", ", $insArr) . ") ";
      $this->values = PHP_EOL . "VALUES (:val_" . implode(", :val_", $insArr) . ") ";
    }
  }

  public function setUpdateVal($values): void
  {
    if (is_object($values)) {
      $insArr = array();
      foreach ($values as $key => $value) {
        $v = $this->getOperator($value);
        $insArr[] = $key;
        $this->paramList["set_$key"] = $v[1];
      }
      $this->insertField = PHP_EOL . "SET " . implode(", ", array_map(array($this, "set"), $insArr));
    }
  }

  public function setSelect($fields = array()): void
  {
    $distinct = "";
    foreach ($fields as $f) {
      if ($this->select !== "") {
        $this->select .= ", ";
      }
      if (is_object($f)) {
        foreach ($f as $key => $value) {
          $iquery = new Query($value);
          $execSelect = $iquery->getQuery();
          $paramList = $iquery->getParams();
          $this->select .= "($execSelect) as $key";
          $this->paramList = $this->paramList + $paramList;
        }
      } else {
        if ($f === "DISTINCT") {
          $distinct = "DISTINCT ";
        } else {
          $this->select .= $this->aliacer($f);
        }
      }
    }
    if ($this->select) {
      $this->select = "SELECT $distinct" . $this->select . " ";
    } else {
      $this->select = "SELECT * ";
    }
  }

  public function setFrom($from, $join = ""): void
  {
    if ($join) {
      $tableList = array();
      foreach ($from as $key => $value) {
        $sp = explode(":", $value);
        $tableList[$sp[1]] = array("table" => $sp[0], "alias" => $sp[1], "use" => false);
      }
      $joinList = array();
      foreach ($join as $j) {
        $sp = explode("=", $j);
        $key = $sp[0];
        $element = $sp[1];
        $sp = explode(".", $key);
        $firstTableAlias = $sp[0];
        $firstField = $sp[1];
        $isJoin = "INNER JOIN";
        switch (strpos($element, "(+)")) {
          case false:
            $isJoin = "INNER JOIN";
            break;
          case 0:
            $isJoin = "LEFT JOIN";
            break;
          default:
            $isJoin = "RIGHT JOIN";
            break;
        }
        $sp = explode(".", str_replace("(+)", "", $element));
        $secondTableAlias = $sp[0];
        $secondField = $sp[1];
        $joinList[] = array("firstTableAlias" => $firstTableAlias, "firstField" => $firstField, "isJoin" => $isJoin, "secondTableAlias" => $secondTableAlias, "secondField" => $secondField);
      }
      $firstTableAlias  = array_column($joinList, 'firstTableAlias');
      $secondTableAlias = array_column($joinList, 'secondTableAlias');
      array_multisort($firstTableAlias, SORT_ASC, $secondTableAlias, SORT_ASC, $joinList);
      foreach ($joinList as $i => $e) {
        $table1 = &$tableList[$e["firstTableAlias"]];
        $table2 = &$tableList[$e["secondTableAlias"]];
        if ($i === 0) {
          $table1["use"] = true;
          $table2["use"] = true;
          $this->from =  $table1["table"] . " " . $table1["alias"];
          $this->from .= "\n" . $e["isJoin"] . " ";
          $this->from .=  $table2["table"] . " " . $table2["alias"];
          $this->from .= "\n\tON ";
          $this->from .= $e["firstTableAlias"] . "." . $e["firstField"];
          $this->from .= " = ";
          $this->from .= $e["secondTableAlias"] . "." . $e["secondField"];
        } elseif ($joinList[$i - 1]["firstTableAlias"] === $e["firstTableAlias"] && $joinList[$i - 1]["secondTableAlias"] === $e["secondTableAlias"]) {
          $this->from .= "\n\tAND ";
          $this->from .= $e["firstTableAlias"] . "." . $e["firstField"];
          $this->from .= " = ";
          $this->from .= $e["secondTableAlias"] . "." . $e["secondField"];
        } else {
          if (!$table1["use"]) {
            $this->from .= "\n" . $e["isJoin"] . " ";
            $table1["use"] = true;
            $this->from =  $table1["table"] . " " . $table1["alias"];
            $this->from .= "\n\tON ";
          } else if (!$table2["use"]) {
            $this->from .= "\n" . $e["isJoin"] . " ";
            $table2["use"] = true;
            $this->from .=  $table2["table"] . " " . $table2["alias"];
            $this->from .= "\n\tON ";
          } else {
            $this->from .= "\n\tAND ";
          }
          $this->from .= $e["firstTableAlias"] . "." . $e["firstField"];
          $this->from .= " = ";
          $this->from .= $e["secondTableAlias"] . "." . $e["secondField"];
        }
      }
      foreach ($tableList as $key => $value) {
        if (!$value["use"]) {
          $this->from .= ", " . $value["table"] . " " . $value["alias"];
        }
      }
      $this->from = PHP_EOL . "FROM " . $this->from;
    } else {
      if (is_array($from)) {
        $this->from = PHP_EOL . "FROM " . implode(", ", array_map(array($this, 'aliacer'), $from));
      } else {
        $this->from = PHP_EOL . "FROM " . $this->aliacer($from);
      }
    }
  }

  private function genWhere($filter): array
  {
    $wArr = array();
    foreach ($filter as $key => $value) {
      if (is_object($value)) {
        $iquery = new Query($value);
        $execSelect = $iquery->getQuery();
        $paramList = $iquery->getParams();
        $wArr[] = "$key in ($execSelect)";
        $this->paramList = $this->paramList + $paramList;
      } else {
        $v = $this->getOperator($value);
        if ($value !== preg_replace('/\$\{(.+)\}/', '$1', $value)) {
          $wArr[] = "$key $v[0] $v[1]";
        } else {
          $par = "par_" . str_replace(".", "_", $key);
          $wArr[] = "$key $v[0] :$par";
          $this->paramList[$par] = $v[1];
        }
      }
    }
    return $wArr;
  }

  public function setWhere($filter): void
  {
    if (is_object($filter)) {
      $wArr = $this->genWhere($filter);
      $this->where = PHP_EOL . "WHERE " . implode(" AND ", $wArr);
    } elseif (is_array($filter)) {
      for ($i=0; $i < count($filter); $i++) { 
        $wArr = $this->genWhere($filter[$i]);
        if ($this->where){
          $this->where .= PHP_EOL . " OR (" . implode(" AND ", $wArr) . ")";
        }else{
          $this->where = PHP_EOL . "WHERE (" . implode(" AND ", $wArr) . ")";
        }
      }
    }
  }

  public function setOrder($sort): void
  {
    $this->order = implode(", ", array_map(array($this, 'desk'), $sort));
    if ($this->order) {
      $this->order = PHP_EOL . "ORDER BY " . $this->order;
    }
  }

  public function setLimit($limit, $page = 0): void
  {
    $offset = "";
    if ($page > 1) {
      $offset = " OFFSET " . ($page * $limit - $limit);
    }
    $this->limit = PHP_EOL . "LIMIT " . $limit . $offset;
  }
}
