<?php

/**
 * mysql client
 *
 */

namespace EatWhat\Storage;

use EatWhat\EatWhatLog;
use EatWhat\Base\StorageBase;

class RedisStorageClient extends StorageBase
{
    /**
     * get mysql client obj
     * 
     */
    public static function getClient()
    {
        static::getStorageConfig();
        try {
            $redis = new \Redis();
            $redis->connect(self::$config["host"], self::$config["port"], self::$config["timeout"]);
            $redis->setOption(\Redis::OPT_SERIALIZER, self::$config["serialize"]);
            self::$config["prefix"] && ($redis->setOption(\Redis::OPT_PREFIX, self::$config["prefix"]));
            self::$config["auth"] && ($redis->auth(self::$config["auth"]));
            
            return $redis;
        } catch(\RedisException $exception) {
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