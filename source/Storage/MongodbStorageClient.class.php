<?php

/**
 * mysql client
 *
 */

namespace EatWhat\Storage;

use EatWhat\EatWhatLog;
use EatWhat\Base\StorageBase;

class MongodbStorageClient extends StorageBase
{
    /**
     * get mysql client obj
     * 
     */
    public static function getClient()
    {
        static::getStorageConfig();
        try {
            $options = [];
            $options["username"] = self::$config["username"];
            $options["password"] = self::$config["password"];
            $options["connectTimeoutMS"] = isset(self::$config["connectTimeoutMS"]) ? self::$config["connectTimeoutMS"] : 60;
            $mongoClient = new \MongoDB\Client(self::$config["uri"], $options);
            return $mongoClient->{self::$config["dbname"]};
        } catch(\MongoDB\Driver\Exception\ConnectionException $exception) {
            if( !DEVELOPMODE ) {
                EatWhatLog::logging($exception, array(
                    "line" => $exception->getLine(),
                    "file" => $exception->getFile(),
                ));
            } else {
                throw $exception;
            }
        }
        
    }
}