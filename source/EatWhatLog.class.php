<?php

namespace EatWhat;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

/**
 * app log process
 * 
 */
class EatWhatLog
{

    /**
     * one static logger
     * 
     */
    public static $logger = null;

    /**
     * log to file simplely
     * 
     */
    public static function logging(string $message, $extra = [], $target = "file", $fileName = "eat_what.log")
    {
        $logger = self::getLogger();
        ($target == "file") && ($logger->pushHandler(new StreamHandler(LOG_PATH . $fileName, Logger::DEBUG)));
        $logger->pushHandler(new FirePHPHandler());

        $logger->info($message, $extra);
    }

    /**
     * get single logger obj
     * 
     */
    public static function getLogger()
    {
        if(!self::$logger) {
            self::$logger = new Logger("eatwhat");
        }
        return self::$logger;
    }
}