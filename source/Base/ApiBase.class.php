<?php

namespace EatWhat\Base;

use EatWhat\EatWhatRequest;
use EatWhat\EatWhatBase;
use EatWhat\AppConfig;
use EatWhat\EatWhatStatic;
use EatWhat\Generator\Generator;
use EatWhat\Storage\Dao\MysqlDao;

/**
 * Api Base
 * 
 */
class ApiBase extends EatWhatBase
{
    /**
     * pdo connection obj
     * 
     */
    protected $pdo;

    /**
     * redis connection obj
     * 
     */
    protected $redis;

    /**
     * mongodb connection obj
     * 
     */
    protected $mongodb;

    /**
     * request obj
     * 
     */
    protected $request;

    /**
     * request obj
     * 
     */
    protected $userData;

    /**
     * request obj
     * 
     */
    protected $uid;

    /**
     * Api Constructor!
     * 
     */
    public function __construct(EatWhatRequest $request)
    {
        $this->request = $request;
        $this->userData = $request->getUserData();
        $this->uid = $this->userData["uid"];

        $this->mysqlDao = new MysqlDao($request);
        $this->redis = Generator::storage("StorageClient", "Redis");
        $this->mongodb = Generator::storage("StorageClient", "Mongodb");
    }

    /**
     * output result
     * 
     */
    public function outputResult($result = []) {
        if( $this->uid ) {
            $newMessage = (int)$this->checkUserHasNewMessage($this->uid);
            $this->request->getUserController()->setUserField("new_message", $newMessage);
        }

        $this->request->outputResult($result);
    }

    /**
     * generate an array that includ a note and acode
     * 
     */
    public function generateStatusResult(string $langName, int $code, bool $isLang = true) : array
    {
        return $this->request->generateStatusResult($langName, $code, $isLang);
    }

    /**
     * check post request
     * 
     */
    public function checkPost() : void
    {
        if( !EatWhatStatic::checkPost() ) {
            $this->generateStatusResult("illegalRequest", -1);
        }
    }

    /**
     * 
     * check float
     */
    public function checkFloat($value) : bool
    {
        if(is_numeric($value)) {
            if( (false !== $pos = strpos($value, ".")) && strlen(substr($value, $pos + 1)) > $this->getSetting("decimalPlaces")) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * 
     * check int
     */
    public function checkInt($value) : bool
    {
        return boolval(preg_match("/^[0-9]+$/", $value));
    }

    /**
     * get setting value
     * 
     */
    public function getSetting(string $settingKey)
    {
        $setting = $this->mongodb->setting->findOne(["key" => $settingKey]);
        return $setting["value"];
    }

    /**
     * set value
     * 
     */
    public function setSetting(string $key, $value)
    {
        $this->mongodb->setting->updateOne([
            "key" => $key,
        ], ['$set' => ["value" => $value]], ["upsert" => true]);
    }

    /**
     * begin a transaction
     * 
     */
    public function beginTransaction() : void
    {
        $this->mysqlDao->beginTransaction();
    }

    /**
     * commit a transaction
     * 
     */
    public function commit() : void
    {
        $this->mysqlDao->commit();
    }

    /**
     * rollback a transaction
     * 
     */
    public function rollback() : void
    {
        $this->mysqlDao->rollback();
    }

    /**
     * check request parameters
     * 
     */
    public function checkParameters(array $options) : void
    {
        foreach($options as $option => $types) {
            if(!isset($_GET[$option]) && !isset($_FILES[$option])) {
                $this->generateStatusResult("parameterError", -1);  
            } else if(!is_null($types)) {
                !is_array($types) && ($types = (array)$types);
                foreach($types as $type) {
                    if(is_array($type)) {
                        if(!in_array($_GET[$option], $type)) {
                            $this->generateStatusResult("parameterError", -1);
                        }
                    } else {
                        switch($type) {
                            case "float":
                            if(!$this->checkFloat($_GET[$option])) {
                                $this->generateStatusResult("parameterError", -1);
                            }
                            break;
        
                            case "int":
                            if(!$this->checkInt($_GET[$option])) {
                                $this->generateStatusResult("parameterError", -1);
                            }
                            break;
        
                            case "array_int":
                            foreach($_GET[$option] as $value) {
                                if(!$this->checkInt($value)) {
                                    $this->generateStatusResult("parameterError", -1);
                                }
                            }
                            break;
        
                            case "array_float":
                            foreach($_GET[$option] as $value) {
                                if(!$this->checkFloat($value)) {
                                    $this->generateStatusResult("parameterError", -1);
                                }
                            }
                            break;
    
                            case "nonzero":
                            if($_GET[$option] == 0) {
                                $this->generateStatusResult("parameterError", -1);
                            }
                            break;
    
                            case "array_nonzero":
                            foreach($_GET[$option] as $value) {
                                if($value == 0) {
                                    $this->generateStatusResult("parameterError", -1);
                                }
                            }
                            break;

                            case "json":
                            $_GET[$option] = json_decode($_GET[$option], true);
                            if(!$_GET[$option]) {
                                $this->generateStatusResult("parameterError", -1);
                            }
                            break;

                            case "url":
                            if(!$this->checkUrlFormat($_GET[$option])) {
                                $this->generateStatusResult("parameterError", -1);
                            }
                            break;
                        }
                    }
                }
            }
        }
    }
}