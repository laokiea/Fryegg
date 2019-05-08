<?php

namespace EatWhat;

use EatWhat\AppConfig;
use EatWhat\EatWhatStatic;

/**
 * simple jwt generate/verify
 * 
 */
class EatWhatJwt
{
    /**
     * hash algo
     * 
     */
    private $algo;

    /**
     * cipher key
     * 
     */
    private $cipherKey;

    /**
     * extra error message
     * 
     */
    private $extraErrorMsg;

    /**
     * access token
     * 
     */
    private $accessToken;

    /**
     * Constructor!
     * 
     */
    public function __construct($algo = null, $cipherKey = null)
    {
        $this->setAlgo($algo);
        $this->setCipherKey($cipherKey);
    }

    /**
     * set algo
     * 
     */
    private function setAlgo($algo = null) : void
    {
        $this->algo = $algo ?? "sha256";
    }

    /**
     * set cipher key
     * 
     */
    private function setCipherKey($cipherKey = null) : void
    {
        $this->cipherKey = $cipherKey ?? AppConfig::get("cipher_key", "global");
    }

    /**
     * set cipher key
     * 
     */
    public function getExtraErrorMessage() : ?string
    {
        return $this->extraErrorMsg;
    }

    /**
     * set extra error message
     * 
     */
    private function setExtraErrorMessage($message)
    {
        $this->extraErrorMsg = $message;
    }

    /**
     * generate token
     * @param $data  array  ["foo" => bar]
     * 
     */
    public function generate($data)
    {
        $header = base64_encode(json_encode([
            "typ" => "jwt",
            "alg" => $this->algo,
        ]));

        $payload = base64_encode(json_encode([
            "aud" => "eat-what.cn",
            "iat" => $_SERVER["REQUEST_TIME"],
            "exp" => $_SERVER["REQUEST_TIME"] + AppConfig::get("access_token_expire"),
            "data" => $data,
        ]));
        
        $data = $header . '.' . $payload;
        $signature = hash_hmac($this->algo, $data, $this->cipherKey);

        // max size => 2048 / 8 - 11 = 245;
        $jwt = $data . "." . $signature;

        // $pri_key_pem_file = AppConfig::get("pri_key_pem_file", "global");
        // $pri_key = openssl_pkey_get_private($pri_key_pem_file);
        $key = $iv = substr($this->cipherKey, 0, 16);
        if( !($token = openssl_encrypt($jwt, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv)) ) {
            $this->setExtraErrorMessage(openssl_error_string());
            return false;
        }

        $token = base64_encode($token);
        return $token;
    }

    /**
     * verify token
     * 
     */
    public function verify()
    {
        $headers = EatWhatStatic::getallheaders();
        if(!isset($headers["Authorization"]))
            return true;

        list($token) = sscanf($headers["Authorization"], "Bearer %s");
        if(!$token) {
            $this->setExtraErrorMessage("Empty Authorization.");
            return false;
        }

        // $pub_key_pem_file = AppConfig::get("pub_key_pem_file", "global");
        // $pub_key = openssl_pkey_get_public($pub_key_pem_file);
        $key = $iv = substr($this->cipherKey, 0, 16);
        if(!($jwt = openssl_decrypt(base64_decode($token), "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv))) {
            $this->setExtraErrorMessage("Openssl Decrypt Fail: ".openssl_error_string());
            return false;
        }
        
        list($jwtHeader64, $jwtPayload64, $jwtSignature) = explode(".", $jwt);
        $jwtHeader = json_decode(base64_decode($jwtHeader64), 1);
        $jwtPayload = json_decode(base64_decode($jwtPayload64), 1);

        if($jwtHeader["typ"] != "jwt" || $jwtHeader["alg"] != $this->algo || $jwtPayload["aud"] != "eat-what.cn") {
            $this->setExtraErrorMessage("Parameters Error.");
            return false;
        }

        if(!hash_equals(hash_hmac($this->algo, $jwtHeader64.'.'.$jwtPayload64, $this->cipherKey), $jwtSignature)) {
            $this->setExtraErrorMessage("Signature Verify Fail.");
            return false;
        }

        $jwtPayload["data"]["tokenStatus"] = 1;

        // expired
        if($jwtPayload["exp"] <= $_SERVER["REQUEST_TIME"]) {
            $jwtPayload["data"]["tokenStatus"] = -401;
        }

        return [
            "data" => $jwtPayload["data"],
            "token" => $token,
        ];
    }
}