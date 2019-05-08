<?php

namespace EatWhat\Middleware;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;
use EatWhat\EatWhatRequest;
use EatWhat\Generator\Generator;
use EatWhat\Base\MiddlewareBase;
use EatWhat\Exceptions\EatWhatException;

/**
 * check request api and mtd legality
 * 
 */
class verifyManageGroup extends MiddlewareBase
{
    /**
     * return a callable function
     * 
     */
    public static function generate()
    {
        return function(EatWhatRequest $request, callable $next) 
        {
            $api = $request->getApi();
            $method = $request->getMethod();
            $userData = $request->getUserController()->getUserData();

            if($userData["tokenType"] != "manage") {
                $request->generateStatusResult("actionWithLogIn", -400);
            } 

            $next($request);
        };
    }

}