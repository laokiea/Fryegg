<?php

namespace EatWhat\Base;

use EatWhat\EatWhatBase;

/**
 * Middleware Base
 * 
 */

abstract class MiddlewareBase extends EatWhatBase
{
    /**
     * generate a cloursor obj
     * 
     */
    abstract public static function generate();
    
}