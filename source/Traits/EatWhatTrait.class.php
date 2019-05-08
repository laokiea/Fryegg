<?php

namespace EatWhat\Traits;

use EatWhat\EatWhatStatic;

/**
 * Eat Traits For Eat Api
 * 
 */
trait EatWhatTrait
{
    public function default()
    {
        require_once ROOT_PATH . "index.html";
    }
}