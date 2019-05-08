<?php

namespace EatWhat\MiddleWare;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;
use EatWhat\EatWhatRequest;
use EatWhat\Base\MiddlewareBase;
use EatWhat\Exceptions\EatWhatException;

/**
 * check request sign middleware
 * 
 */
class verifySign extends MiddlewareBase
{
    /**
     * return a callable handler
     * 
     */
    public static function generate()
    {
        return function(EatWhatRequest $request, callable $next) 
        {
            $signature = EatWhatStatic::getGPValue("signature");
            $verifyResult = static::verify($signature);

            if( !$verifyResult ) {
                if( !DEVELOPMODE ) {
                    EatWhatLog::logging("Illegality Request With Wrong Sinature.", [
                        "ip" => getenv("REMOTE_ADDR"),
                        "request_id" => $request->getRequestId(),
                    ]);
                    EatWhatStatic::illegalRequestReturn();
                } else {
                    throw new EatWhatException("Sign is incorrect, Check it.");
                }
            } else {
                $next($request);
            }
        };
    }

    /**
     * verify sign
     * 
     */
    public static function verify($signature)
    {
        $pub_key_pem_file = AppConfig::get("pub_key_pem_file", "global");
        $pub_key = openssl_pkey_get_public($pub_key_pem_file);
        $data = EatWhatStatic::getGPValue("paramsSign");

        return openssl_verify($data, $signature, $pub_key, "sha256");
    }
}