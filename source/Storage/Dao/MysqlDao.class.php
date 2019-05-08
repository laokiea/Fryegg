<?php

namespace EatWhat\Storage\Dao;

use EatWhat\Exceptions\EatWhatException;
use EatWhat\AppConfig;
use EatWhat\Generator\Generator;
use EatWhat\EatWhatRequest;
use EatWhat\EatWhatLog;

/**
 * dao of mysql
 * 
 */
class MysqlDao
{
    /**
     * mysql obj
     * 
     */
    public $pdo;

    /**
     * request obj
     * 
     */
    public $request;

    /**
     * the table name
     * 
     */
    public $table;

    /**
     * the last sql
     * 
     */
    public $lastSql;

    /**
     * the executable sql
     * 
     */
    public $executeSql = "";

    /**
     * statment obj
     * 
     */
    public $pdoStatment;

    /**
     * statment obj
     * 
     */
    public $pdoException;

    /**
     * has transaction
     * 
     */
    public $hasTransaction;

    /**
     * last exec result
     * 
     */
    public $execResult;

    /**
     * not interrput program when exception occurate
     *
     */
    public $exceptionNotInterrupt = false;

    /**
     * set it to true if there were an error 
     *
     */
    public $errorBefore = false;

    /**
     * bind vlaue type
     * 
     */
    public $bindTypes = [
        "integer" => \PDO::PARAM_INT,
        "string" => \PDO::PARAM_STR,
    ];

    /**
     * constructor!
     * 
     */
    public function __construct(EatWhatRequest $request)
    {
        $this->request = $request;
        $this->prefix = (AppConfig::get("MysqlStorageClient", "storage"))["prefix"];
        $this->pdo = Generator::storage("StorageClient", "Mysql");
    }

    /**
     * get execute sql
     * 
     */
    public function getExecuteSql() : string
    {
        return $this->executeSql;
    }

    /**
     * set execute sql
     * 
     */
    public function setExecuteSql(string $sql = "") : self
    {
        $this->executeSql = $sql;
        return $this;
    }

    /**
     * ensure table name
     * 
     */
    public function table(string $tableName, ?string $prefix = null) : self
    {
        $this->table = ($prefix ?? $this->prefix) . $tableName;

        return $this;
    }

    /**
     * ensure select section
     * 
     */
    public function select($select) : self
    {
        if( is_array($select) ) {
            $select = implode(",", $select);
        }
        $this->executeSql .= "SELECT $select FROM " . $this->table;

        return $this;
    }

    /**
     * ensure insert section
     * 
     */
    public function insert(array $insert) : self
    {
        $this->executeSql .= "INSERT INTO " . $this->table . "(" . implode(",", $insert) . ")" . " VALUES(" . substr(str_repeat("?,", count($insert)), 0, -1) . ")";

        return $this;
    }

    /**
     * ensure update section
     * 
     */
    public function update(array $update) : self
    {
        $this->executeSql .= "UPDATE " . $this->table . " SET";
        foreach($update as $field) {
            $this->executeSql .= " $field = ? ,";
        }
        $this->executeSql = substr($this->executeSql, 0, -1);

        return $this;
    }

    /**
     * ensure where section
     * 
     */
    public function where($where) : self
    {
        if( !is_array($where) ) {
            $where = explode(",", $where);
        }

        $this->executeSql .= " WHERE";
        foreach($where as $value) {
            if(!is_array($value)) {
                $this->executeSql .= " $value = ? AND";
            } else {
                list($field, $operator) = $value;
                if(in_array($operator, ["<", ">", ">=", "<=", "<>", "=", "!="])) {
                    $this->executeSql .= " " . $field . " " . $operator . " ? AND";
                }
            }
        }
        $this->executeSql = substr($this->executeSql, 0, -4);

        return $this;
    }

    /**
     * in section
     * 
     */
    public function in(string $field, int $inNum) : self
    {
        if(stripos($this->executeSql, "where") !== false) {
            $this->executeSql .= " " . $field . " in (" . str_repeat("?,", $inNum) . ")";
        } else {
            $this->executeSql .= " where " . $field . " in (" . substr(str_repeat("?,", $inNum), 0, -1) . ")";
        }

        return $this;
    }

    /**
     * ensure orderby section
     * ["foo" => -1, "bar" => 1]
     * 
     */
    public function orderBy(array $orderBy) : self
    {
        foreach($orderBy as $field => $sort) {
            $this->executeSql .= " ORDER BY " . $field . " " . ($sort == -1 ? "DESC" : "ASC") . ",";
        }
        $this->executeSql = substr($this->executeSql, 0, -1);

        return $this;
    }

    /**
     * ensure groupby section
     * 
     */
    public function groupBy(string $field) : self
    {
        $this->executeSql .= " GROUP BY $field";

        return $this;
    }

    /**
     * ensure limit section
     * 
     */
    public function limit($page, $num) : self 
    {
        $this->executeSql .= " LIMIT " . ($page - 1) * $num . ",$num";

        return $this;
    }

    /**
     * ensure delete section
     * 
     */
    public function delete() : self
    {
        $this->executeSql .= "DELETE FROM " . $this->table;

        return $this;
    }

    /**
     * left join
     * 
     */
    public function leftJoin(string $tableName, ?string $alias = null) : self
    {
        $this->executeSql .= " LEFT JOIN " . $this->prefix . $tableName . ($alias ? " AS $alias" : "");

        return $this;
    }

    /**
     * as section
     * 
     */
    public function alias($alias) : self
    {
        $this->executeSql .= " AS $alias";

        return $this;
    }

    /**
     * on for join
     * 
     */
    public function on($onsql) : self 
    {
        $this->executeSql .= " ON $onsql";

        return $this;
    }

    /**
     * begin a transaction
     * 
     */
    public function beginTransaction() : self
    {
        $this->pdo->beginTransaction();
        $this->hasTransaction = true;

        return $this;
    }

    /**
     * commit a transaction
     * 
     */
    public function commit() : self
    {
        $this->pdo->commit();
        $this->hasTransaction = false;

        return $this;
    }

    /**
     * commit a transaction
     * 
     */
    public function rollback() : self
    {
        $this->pdo->rollback();
        $this->hasTransaction = false;

        return $this;
    }

    /**
     * get last insert id
     * 
     */
    public function getLastInsertId() : string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Prepares a statement for execution  
     * 
     */
    public function prepare() : self
    {
        try {
            $this->pdoStatment = $this->pdo->prepare($this->getExecuteSql(), [
                \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL,
            ]);
        } catch( \PDOException $exception ) {
            $this->execResult = false;
            if($this->exceptionNotInterrupt) {
                $this->errorBefore = true;
                return $this;
            }

            if( !DEVELOPMODE ) {
                EatWhatLog::logging("DB can not prepare sql: " . (string)$exception . ". ", [
                    "request_id" => $this->request->getRequestId(),
                    "sql" => $this->getExecuteSql(),
                ], "file", "pdo.log");

                $this->pdoException = true;
                $this->request->generateStatusResult("serverError", -404);
                $this->request->outputResult();
            } else {
                throw new EatWhatException((string)$exception);
            }
        }

        return $this;
    }

    /**
     * execute plan
     * 
     */
    public function execute(array $parameters = [], ?array $fetch = null)
    {
        if( isset($this->pdoStatment) ) {
            $placeholdersCount = preg_match_all("/\?/", $this->getExecuteSql()); 
            if($placeholdersCount != count($parameters)) {
                throw new EatWhatException("paratemers count can not matched. ");
            }
            
            try {
                foreach(array_values($parameters) as $index => $parameter) {
                    $parameterType = gettype($parameter);
                    $this->pdoStatment->bindValue($index + 1, $parameter, $this->bindTypes[$parameterType]);
                }
                $execResult = $this->pdoStatment->execute();
                if(!$execResult && $this->hasTransaction) {
                    $this->pdo->rollBack();
                    $this->hasTransaction = false;
                }
                $this->execResult = $execResult;
                $this->setExecuteSql();

                if($fetch) {
                    list($fetchName, $fetchArgs) = $fetch;
                    if( !is_array($fetchArgs) ) {
                        $fetchArgs = (array)$fetchArgs;
                    }
                    return call_user_func_array([$this->pdoStatment, $fetchName], $fetchArgs);
                } else {
                    return $this->pdoStatment;
                } 
            } catch (\PDOException $exception) {
                $this->execResult = false;
                if($this->exceptionNotInterrupt) {
                    $this->errorBefore = true;
                    return $this->execResult;
                }

                if($this->hasTransaction) {
                    $this->pdo->rollBack();
                    $this->hasTransaction = false;
                }

                if( !DEVELOPMODE ) {
                    EatWhatLog::logging((string)$exception, [
                        "request_id" => $this->request->getRequestId(),
                        "sql" => $this->getExecuteSql(),
                    ], "file", "pdo.log");

                    $this->pdoException = true;
                    $this->request->generateStatusResult("serverError", -404);
                    $this->request->outputResult();
                } else {
                    throw new EatWhatException((string)$exception);
                }
            }
        }
    }
}