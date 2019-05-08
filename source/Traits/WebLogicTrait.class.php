<?php

namespace EatWhat\Traits;

/**
 * Eat Traits For Eat Api
 * 
 */
trait WebLogicTrait
{
    /**
     * verify the request of github webhook by signature
     * 
     */
    public function verifyGithubWebHookSignature() : bool
    {
        $headers = getallheaders();
        $payloadBody = file_get_contents("php://input");

        $signature = $headers["X-Hub-Signature"];
        $secretToken = getenv("SECRET_TOKEN");

        $verifyHashHex = "sha1=" . hash_hmac("sha1", $payloadBody, $secretToken);
        
        return hash_equals($signature, $verifyHashHex);
    }

    /**
     * check ip request limit
     * 
     */
    public function checkSmsIpRequestLimit(int $count = 5) : bool
    {
        $ip = getenv("REMOTE_ADDR");
        $requestCount = $this->redis->get($ip . "_sms_request_count");
        $requestTime = $this->redis->get($ip . "_sms_request_time");

        if($requestTime || $requestCount > $count) {
            return false;
        }

        return true;
    }
}