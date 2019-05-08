<?php

namespace EatWhat\Base;

use EatWhat\AppConfig;
use EatWhat\EatWhatBase;

/**
 * Middleware Base
 * 
 */
abstract class StorageBase extends EatWhatBase
{
    /**
     * config
     * 
     */
    public static $config;

    /**
     * get storage obj config
     * 
     */
    public static function getStorageConfig()
    {
        $classname = static::className(true);
        self::$config = AppConfig::get($classname, "storage");
    } 

    /**
     * get client
     * 
     */
    abstract static public function getClient();
}