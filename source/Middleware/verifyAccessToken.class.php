<?php

namespace EatWhat\Middleware;

use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;
use EatWhat\EatWhatRequest;
use EatWhat\Base\MiddlewareBase;
use EatWhat\Exceptions\EatWhatException;

/**
 * verify user access token
 * 
 */
class verifyAccessToken extends MiddlewareBase
{
    /**
     * return a callable function
     * 
     */
    public static function generate()
    {
        return function(EatWhatRequest $request, callable $next)
        {
            $analyzer = $request->getAccessTokenAnalyzer();
            $userController = $request->getUserController();
            $verifyResult = $analyzer->verify();

            if(!$verifyResult) {
                $extraErrorMessage = $analyzer->getExtraErrorMessage();
                if( !DEVELOPMODE ) {
                    EatWhatLog::logging("Illegality Access Token.", [
                        "ip" => getenv("REMOTE_ADDR"),
                        "extra_error_message" => $extraErrorMessage,
                        "request_id" => $request->getRequestId(),
                    ],
                    "file",
                    "access_token.log"
                    );
                    EatWhatStatic::illegalRequestReturn();
                } else {
                    throw new EatWhatException("Illegality Access Token, Check it. ".$extraErrorMessage);
                }
            } else {
                if(is_array($verifyResult)) {
                    $userController->setUserData($verifyResult["data"]);
                    $userController->setAccessToken($verifyResult["token"]);
                }   
                $next($request);
            }
        };
    }
}