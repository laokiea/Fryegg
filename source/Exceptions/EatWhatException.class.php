<?php

namespace EatWhat\Exceptions;

/**
 * eat what exception
 * 
 */

class EatWhatException extends \Exception
{
    /**
     * Constructor!
     * 
     */
    public function __construct($message = "", $code = 0)
    {
        $message = " EATWHAT ERROR: " . PHP_EOL . $message;
        parent::__construct($message, $code);
    }
}