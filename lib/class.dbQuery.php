<?php
$host="localhost";
$port="3306";
$db_name="solr_demo";
$user="user";
$password="password";
// Connect to server and select database.
try {
    $db = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db_name . ';charset=utf8;', $user, $password, [PDO::MYSQL_ATTR_LOCAL_INFILE => true]);
} catch (PDOException $e) {
    error_log("Error occurred while connecting to DB" . $e);
    die("Error connecting to DB");
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class DbQuery
{
    private $paramList = array();
    private $conditionList = array();
    private $conditionGroupList = array();
    private $joinList = array();
    private $orderBy = array();
    private $groupBy = "";
    private $limit = 0;
    private $isDistinct = false;
    private $lastInsertId = 0;
    private $errorMessage;
    private $affectedRows = 0;
    private $db;
    private $showSql = false;
    private $bindParamList = array();
    private $throwExceptions = true;
    private $tableName;

    function __construct($tableName = null)
    {
        global $db;
        $this->db = $db;
        $this->tableName = $tableName;
    }

    public function addParam($field, $value = null, $dataType = PDO::PARAM_STR)
    {
        if (empty($value)) {
            $dataType = PDO::PARAM_NULL;
        }
        $param = array(
            "field" => $field,
            "value" => $value,
            "type" => $dataType
        );

        array_push($this->paramList, $param);
    }

    public function addParamList(array $paramNameList)
    {
        foreach ($paramNameList as $paramName) {
            $this->addParam($paramName);
        }
    }

    public function addCondition($field, $value, $dataType = PDO::PARAM_INT, $comparison = "=", $join = "AND", $group = 0)
    {
        if (!($dataType > 0)) {
            $dataType = PDO::PARAM_INT;
        }
        if (empty($comparison)) {
            $comparison = "=";
        }
        if (empty($join)) {
            $join = "AND";
        }

        $condition = array(
            "field" => $field,
            "value" => $value,
            "type" => $dataType,
            "comparison" => $comparison,
            "join" => $join,
            "group" => $group
        );

        array_push($this->conditionList, $condition);
    }

    public function updateCondition($field, $value)
    {
        foreach ($this->conditionList as $index => $condition) {
            if ($condition["field"] == $field) {
                $this->conditionList[$index]["value"] = $value;
                break;
            }
        }
    }

    public function removeCondition($field)
    {
        $conditionIndex = $this->getConditionIndex($field);
        $bindParamIndex = $this->getBindParamIndex($field);
        if ($conditionIndex > -1) {
            array_splice($this->conditionList, $conditionIndex, 1);
        }
        if ($bindParamIndex > -1) {
            array_splice($this->bindParamList, $bindParamIndex, 1);
        }
    }

    public function addConditionGroup($groupId, $join = "AND")
    {
        $this->conditionGroupList[$groupId] = $join;
    }

    public function addJoin($type = "LEFT", $tableLeft, $columnLeft, $tableRight, $columnRight)
    {
        $join = array(
            "type" => $type,
            "tableLeft" => $tableLeft,
            "columnLeft" => $columnLeft,
            "tableRight" => $tableRight,
            "columnRight" => $columnRight
        );

        array_push($this->joinList, $join);
    }

    public function addOrderBy($columnName, $order = "ASC")
    {
        if (strtoupper($order) == "RAND()") {
            array_push($this->orderBy, "RAND()");
        } else {
            array_push($this->orderBy, $columnName . " " . $order);
        }
    }

    public function addGroupBy($columnName)
    {
        $this->groupBy = $columnName;
    }

    public function addLimit($limit)
    {
        $this->limit = $limit;
    }

    public function addDistinct($isDistinct = false)
    {
        $this->isDistinct = $isDistinct;
    }


    //retrieve data
    public function retrieve($tableName = null, $fetchType = PDO::FETCH_ASSOC, $acquireLock = false)
    {
        if (empty($tableName) && empty($tableName = $this->tableName)) {
            return false;
        }

        $sql = "SELECT ";

        if ($this->isDistinct == true) {
            $sql .= "DISTINCT ";
        }

        if (!empty($this->paramList)) {
            $fieldNames = array();
            foreach ($this->paramList as $param) {
                array_push($fieldNames, $param["field"]);
            }
            $sql .= implode(",", $fieldNames);
        } else {
            $sql .= "*";
        }

        $sql .= " FROM " . $tableName;

        //add joins
        if (!empty($this->joinList)) {
            foreach ($this->joinList as $join) {
                $sql .= " " . strtoupper($join["type"]) . " JOIN " . $join["tableRight"];
                $sql .= " ON " . $join["tableLeft"] . "." . $join["columnLeft"] . "=" . $join["tableRight"] . "." . $join["columnRight"];
            }
        }

        //add conditions
        $sql .= $this->appendConditions();

        //add group by
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . $this->groupBy;
        }

        //add order by
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(',', $this->orderBy);
        }

        //add limit
        if (!empty($this->limit)) {
            $sql .= " LIMIT " . $this->limit;
        }

        //acquire lock if required
        if ($acquireLock) {
            $sql .= " FOR UPDATE";
        }


        if ($this->showSql == true) {
            $generatedSql = $sql;
            foreach ($this->bindParamList as $bindParam) {
                $generatedSql = str_replace($bindParam["key"], $bindParam["value"], $generatedSql);
            }
            error_log("SQL: " . $generatedSql);
        }

        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        try {
            $pdoStatement = $this->db->prepare($sql);

            foreach ($this->bindParamList as $bindParam) {
                $pdoStatement->bindParam($bindParam["key"], $bindParam["value"], $bindParam["type"]);
            }

            $pdoStatement->execute();

            return $pdoStatement->fetchAll($fetchType);
        } catch (PDOException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->errorMessage = $e->getMessage();
            error_log("mysql error occurred : " . $e->getMessage());
            error_log("mysql query: " . $sql);
            return false;
        }

    }

    //insert data
    public function insert($tableName = null)
    {

        if ((empty($tableName) && empty($tableName = $this->tableName)) || empty($this->paramList)) {
            return false;
        }

        $sql = "INSERT INTO " . $tableName;

        $columns = array();
        $values = array();

        foreach ($this->paramList as $param) {

            $columns[] = $param["field"];
            $values[] = ":" . ($param["field"]);

            $this->addBindParam(":" . $param["field"], $param["value"], $param["type"]);
        }

        $sql .= "(" . implode(",", $columns) . ") VALUES(" . implode(",", $values) . ")";

        //remove condition list to avoid bind parameter exception
        $backupConditionList = $this->conditionList;
        $this->conditionList = array();

        $result = $this->prepareAndExecute($sql);

        //reassign condition list
        $this->conditionList = $backupConditionList;

        return $result;
    }

    //update data
    public function update($tableName = null)
    {
        //validate for parameters and conditions to avoid full table update
        if ((empty($tableName) && empty($tableName = $this->tableName)) || empty($this->paramList) || empty($this->conditionList)) {
            return false;
        }

        $sql = "UPDATE " . $tableName . " SET ";

        foreach ($this->paramList as $index => $param) {

            $uniqueId = rand(0, 10000);

            $sql .= $param["field"] . "=:" . $param["field"] . $uniqueId;

            if (($index + 1) < count($this->paramList)) {
                $sql .= ", ";
            } else {
                $sql .= " ";
            }

            $this->addBindParam(":" . $param["field"] . $uniqueId, $param["value"], $param["type"]);
        }

        $sql .= $this->appendConditions($sql);

        return $this->prepareAndExecute($sql);
    }

    //delete data
    public function delete($tableName = null)
    {
        if ((empty($tableName) && empty($tableName = $this->tableName)) || empty($this->conditionList)) {
            return false;
        }

        $sql = "DELETE FROM " . $tableName;

        $sql .= $this->appendConditions($sql);

        return $this->prepareAndExecute($sql);
    }

    //count total rows
    public function getRowCount($tableName, $columnName)
    {
        $sql = "SELECT count(DISTINCT " . $columnName . ") FROM " . $tableName . " use index(PRIMARY)";

        //add joins
        if (!empty($this->joinList)) {
            foreach ($this->joinList as $join) {
                $sql .= " " . strtoupper($join["type"]) . " JOIN " . $join["tableRight"];
                $sql .= " ON " . $join["tableLeft"] . "." . $join["columnLeft"] . "=" . $join["tableRight"] . "." . $join["columnRight"];
            }
        }
        //add conditions
        $sql .= $this->appendConditions();

        //replace bind params(no prepare required since it decrease performance)
        //NOTE: parameter type is required except for integer
        foreach ($this->bindParamList as $bindParam) {
            $value = $bindParam["type"] == PDO::PARAM_INT ? $bindParam["value"] : "'" . $bindParam["value"] . "'";
            $sql = str_replace($bindParam["key"], $value, $sql);
        }

        if ($this->showSql == true) {
            error_log("SQL: " . $sql);
        }

        try {
            $pdoStatement = $this->db->query($sql);
            return $pdoStatement->fetchColumn();
        } catch (PDOException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->errorMessage = $e->getMessage();
            error_log("mysql error occurred : " . $e->getMessage());
            error_log("mysql query : " . $sql);
            return false;
        }

    }

    public function uploadCSVRecords($databasetable, $csv_path, $fieldseparator = ",", $lineseparator = "\n", $table_fields = null, $custom_value_fields = null)
    {
        $responseArray = array();

        try {
            $affectedRows = $this->db->exec
            (
                $this->prepareCsvLoadSqlStmt($databasetable, $csv_path, $fieldseparator, $lineseparator, $table_fields, $custom_value_fields)
            );

            $responseArray["state"] = "success";
            $responseArray["affectedRows"] = $affectedRows;

            return json_encode($responseArray);
        } catch (PDOException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->errorMessage = $e->getMessage();
            error_log("mysql error occurred : " . $e->getMessage());

            $responseArray["state"] = "error";
            $responseArray["reason"] = $e->getMessage();

            return json_encode($responseArray);
        }
    }

    private function prepareCsvLoadSqlStmt($databasetable, $csv_path, $fieldseparator, $lineseparator, $table_fields, $custom_value_fields)
    {
        $stmt = null;

        if ($table_fields == null && $custom_value_fields == null) {
            $stmt = "LOAD DATA LOCAL INFILE "
                . $this->db->quote($csv_path)
                . " INTO TABLE `$databasetable` FIELDS TERMINATED BY "
                . $this->db->quote($fieldseparator)
                . " ENCLOSED BY '\"'"
                . "LINES TERMINATED BY "
                . $this->db->quote($lineseparator);
        } else if ($table_fields != null && $custom_value_fields == null) {
            $stmt_phase_1 = "LOAD DATA LOCAL INFILE "
                . $this->db->quote($csv_path)
                . " INTO TABLE `$databasetable` FIELDS TERMINATED BY "
                . $this->db->quote($fieldseparator)
                . " ENCLOSED BY '\"'"
                . "LINES TERMINATED BY "
                . $this->db->quote($lineseparator);

            $stmt_phase_1 = $stmt_phase_1 . ' (';

            foreach ($table_fields as $table_field) {
                $stmt_phase_1 = $stmt_phase_1 . $table_field . ',';
            }

            $stmt = rtrim($stmt_phase_1, ",") . ')';
        } else if ($table_fields != null && $custom_value_fields != null) {
            $stmt_phase_1 = "LOAD DATA LOCAL INFILE "
                . $this->db->quote($csv_path)
                . " INTO TABLE `$databasetable` FIELDS TERMINATED BY "
                . $this->db->quote($fieldseparator)
                . " ENCLOSED BY '\"'"
                . "LINES TERMINATED BY "
                . $this->db->quote($lineseparator);

            $stmt_phase_1 = $stmt_phase_1 . '(';

            foreach ($table_fields as $table_field) {
                $stmt_phase_1 = $stmt_phase_1 . $table_field . ',';
            }
            $stmt_phase_2 = rtrim($stmt_phase_1, ",") . ') SET ';

            foreach ($custom_value_fields as $valuesIndex => $value) {
                $stmt_phase_2 = $stmt_phase_2 . $valuesIndex . '=' . $value . ',';
            }
            $stmt = rtrim($stmt_phase_2, ",");
        }

        if ($this->showSql == true) {
            error_log("SQL: " . $stmt);
        }

        return $stmt;
    }

    private function prepareAndExecute($sql)
    {
        $result = false;
        if ($this->showSql == true) {
            $generatedSql = $sql;
            foreach ($this->bindParamList as $bindParam) {
                $generatedSql = str_replace($bindParam["key"], $bindParam["value"], $generatedSql);
            }
            error_log("SQL: " . $generatedSql);
        }
        try {
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdoStatement = $this->db->prepare($sql);

            foreach ($this->bindParamList as $bindParam) {
                $pdoStatement->bindParam($bindParam["key"], $bindParam["value"], $bindParam["type"]);
            }

            $result = $pdoStatement->execute();
            $this->lastInsertId = $this->db->lastInsertId();
            $this->affectedRows = $pdoStatement->rowCount();
        } catch (PDOException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->errorMessage = $e->getMessage();
            $generatedSql = $sql;
            foreach ($this->bindParamList as $bindParam) {
                $generatedSql = str_replace($bindParam["key"], $bindParam["value"], $generatedSql);
            }
            error_log("mysql error occurred: " . $e->getMessage());
            error_log("mysql query: " . $generatedSql);
            /*error_log("mysql error occurred : " . $e->getMessage());
            error_log("mysql query : " . $sql);
            error_log("params : " . json_encode($this->paramList));
            error_log("conditions : " . json_encode($this->conditionList));*/
        }

        return $result;
    }

    private function getConditionIndex($field)
    {
        $conditionIndex = -1;
        foreach ($this->conditionList as $index => $condition) {
            if ($condition["field"] == $field) {
                $conditionIndex = $index;
                break;
            }
        }

        return $conditionIndex;
    }

    private function getBindParamIndex($keyName)
    {
        $bindParamIndex = -1;
        foreach ($this->bindParamList as $index => $bindParam) {
            if ($bindParam["key"] == ":" . str_replace(".", "", $keyName)) {
                $bindParamIndex = $index;
                break;
            }
        }

        return $bindParamIndex;
    }


    private function appendConditions()
    {
        $sql = "";
        if (!empty($this->conditionList)) {
            $sql = " WHERE ";

            //process single conditions
            $groupedConditions = array();
            foreach ($this->conditionList as $index => $condition) {
                if ($condition["group"] == 0) {

                    if ($condition["comparison"] == "IN" || $condition["comparison"] == "NOT IN") {
                        $sql .= $condition["field"] . " " . $condition["comparison"] . " (";
                        $valueList = array();
                        foreach ($condition["value"] as $valuesIndex => $value) {
                            $valueList[] = ":" . str_replace(".", "", $condition["field"]) . $valuesIndex;
                            $this->addBindParam(":" . str_replace(".", "", $condition["field"]) . $valuesIndex, $value, $condition["type"]);
                        }
                        $sql .= implode(",", $valueList);
                        $sql .= ")";
                    } else {
                        //change field name for concat
                        if (strpos($condition["field"], "CONCAT") !== FALSE) {
                            $sql .= $condition["field"] . " " . $condition["comparison"] . " :concat" . $index;
                            $this->addBindParam(":concat" . $index, $condition["value"], $condition["type"]);
                        } else {
                            // . character in field name is removed to avoid sql errors
                            $sql .= $condition["field"] . " " . $condition["comparison"] . " :" . str_replace(".", "", $condition["field"]);
                            $this->addBindParam(":" . str_replace(".", "", $condition["field"]), $condition["value"], $condition["type"]);
                        }
                    }

                    if ((($index + 1) < count($this->conditionList)) && $this->conditionList[$index + 1]["group"] == 0) {
                        $sql .= " " . $this->conditionList[$index + 1]["join"] . " ";
                    }

                } else {
                    $groupedConditions[$condition["group"]][] = $condition;
                }
            }

            //process grouped conditions
            foreach ($groupedConditions as $group => $groupConditionList) {
                $groupJoin = (!empty($this->conditionGroupList[$group]) ? $this->conditionGroupList[$group] : "AND");
                $sql .= " " . $groupJoin . " ";
                $sql .= " (";
                foreach ($groupConditionList as $index => $condition) {
                    if ($condition["comparison"] == "IN" || $condition["comparison"] == "NOT IN") {
                        $sql .= $condition["field"] . " " . $condition["comparison"] . " (";
                        $valueList = array();
                        foreach ($condition["value"] as $valuesIndex => $value) {
                            $valueList[] = ":" . str_replace(".", "", $condition["field"]) . $valuesIndex;
                            $this->addBindParam(":" . str_replace(".", "", $condition["field"]) . $valuesIndex, $value, $condition["type"]);
                        }
                        implode(",", $valueList);
                        $sql .= ")";
                    } else {
                        if (strpos($condition["field"], "CONCAT") !== FALSE) {
                            $sql .= $condition["field"] . " " . $condition["comparison"] . " :concat" . $index;
                            $this->addBindParam(":concat" . $index, $condition["value"], $condition["type"]);
                        } else {
                            // . character in field name is removed to avoid sql errors
                            $sql .= $condition["field"] . $condition["comparison"] . ":" . str_replace(".", "", $condition["field"]);
                            $this->addBindParam(":" . str_replace(".", "", $condition["field"]), $condition["value"], $condition["type"]);
                        }
                    }

                    if (($index + 1) < count($groupConditionList)) {
                        $sql .= " " . $groupConditionList[$index + 1]["join"] . " ";
                    }

                }
                $sql .= ")";
            }
        }
        return $sql;
    }

    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollBack()
    {
        return $this->db->rollBack();
    }

    public function reset()
    {
        $this->paramList = array();
        $this->conditionList = array();
        $this->conditionGroupList = array();
        $this->joinList = array();
        $this->orderBy = array();
        $this->groupBy = "";
        $this->limit = 0;
        $this->isDistinct = false;
        $this->bindParamList = array();
        $this->lastInsertId = 0;
        $this->boundParams = array();
    }

    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function setShowSql($showSql)
    {
        $this->showSql = $showSql;
    }

    /**Set 'true' to throw exceptions instead processing exception within this class
     * @param boolean $throwExceptions
     */
    public function setThrowExceptions($throwExceptions)
    {
        $this->throwExceptions = $throwExceptions;
    }

    private function addBindParam($key, $value, $type)
    {
        $this->bindParamList[] = array(
            "key" => $key,
            "value" => $value,
            "type" => $type
        );
    }

    /**
     * Use this function to directly execute a mysql query
     *
     * @param $sqlQuery
     * @param array $variables
     * @param int $fetchType
     * @return array
     */
    public function executeSqlQuery($sqlQuery, $variables = [], $fetchType = PDO::FETCH_ASSOC)
    {
        $pdoStatement = $this->db->prepare($sqlQuery);
        $pdoStatement->execute($variables);
        return $pdoStatement->fetchAll($fetchType);
    }

    /**
     * Use this function to directly execute a mysql update query
     *
     * @param $sqlQuery
     * @param array $variables
     * @param int $fetchType
     * @return array
     */
    public function executeUpdateSqlQuery($sqlQuery, $variables = [])
    {
        $pdoStatement = $this->db->prepare($sqlQuery);
        $pdoStatement->execute($variables);
    }

    //retrieve data
    public function checkExists($tableName)
    {
        if (empty($tableName)) {
            return false;
        }

        $sql = "SELECT EXISTS(SELECT 1 FROM " . $tableName;

        //add joins
        if (!empty($this->joinList)) {
            foreach ($this->joinList as $join) {
                $sql .= " " . strtoupper($join["type"]) . " JOIN " . $join["tableRight"];
                $sql .= " ON " . $join["tableLeft"] . "." . $join["columnLeft"] . "=" . $join["tableRight"] . "." . $join["columnRight"];
            }
        }

        //add conditions
        $sql .= $this->appendConditions();

        $sql .= ") as result";

        if ($this->showSql == true) {
            $generatedSql = $sql;
            foreach ($this->bindParamList as $bindParam) {
                $generatedSql = str_replace($bindParam["key"], $bindParam["value"], $generatedSql);
            }
            error_log("SQL: " . $generatedSql);
        }

        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        try {
            $pdoStatement = $this->db->prepare($sql);

            foreach ($this->bindParamList as $bindParam) {
                $pdoStatement->bindParam($bindParam["key"], $bindParam["value"], $bindParam["type"]);
            }

            $pdoStatement->execute();


            $result = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
            return $result[0]["result"] == 1 ? true : false;
        } catch (PDOException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->errorMessage = $e->getMessage();
            error_log("mysql error occurred : " . $e->getMessage());
            error_log("mysql query: " . $sql);
            return false;
        }

    }
}