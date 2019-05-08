<?php

namespace EatWhat\Api;

use EatWhat\EatWhatLog;
use EatWhat\Base\ApiBase;

/**
 * Eat Api
 * 
 */
class EatWhatApi extends ApiBase
{
    /**
     * use Trait
     */
    use \EatWhat\Traits\EatWhatTrait;
    use \EatWhat\Traits\CommonTrait;

    /**
     * method return a rand decision that you eat
     * 
     */
    public function EatWhat()
    {
        $this->default();
    }
}
