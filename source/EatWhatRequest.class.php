<?php

/**
 * bulid request, route
 *
 */

namespace EatWhat;

use EatWhat\AppConfig;
use Ramsey\Uuid\Uuid;
use EatWhat\EatWhatStatic;
use EatWhat\EatWhatContainer;
use EatWhat\Exceptions\EatWhatException;

class EatWhatRequest
{
    /**
     * userid
     * 
     */
    private $userData = [];

    /**
     * middlewares 
     * 
     */
    private $middlewares = [];

    /**
     * api
     * 
     */
    private $api;

    /**
     * method 
     * 
     */
    private $method;

    /**
     * method args
     * 
     */
    private $args = [];

    /**
     * access token analyzer
     * 
     */
    private $accessTokenAnalyzer;

    /**
     * user data manager
     * 
     */
    private $userController;

    /**
     * request status
     * 
     */
    private $requestStatus;

    /**
     * request id
     * 
     */
    public $requestId;

    /**
     * for static use
     * 
     */
    public static $staticRequestId;

    /**
     * route 
     * 
     */
    public function __construct()
    {
        $this->setApi($_GET["api"] ?? "EatWhat");
        $this->setMethod($_GET["mtd"] ?? "EatWhat");
        $this->setRequestId();
    }

    /**
     * set api
     * 
     */
    private function setApi($api)
    {   
        $this->api = ucfirst($api);
    }

    /**
     * set method
     * 
     */
    private function setMethod($method)
    {   
        $this->method = $method;
    }

    /**
     * set method
     * 
     */
    private function setArgs($args)
    {   
        $this->args = $args;
    }

    /**
     * set method
     * 
     */
    private function setRequestStatus($status)
    {   
        $this->requestStatus = $status;
    }

    /**
     * set access token analyzer
     * 
     */
    public function setAccessTokenAnalyzer($analyzer)
    {
        $this->accessTokenAnalyzer = $analyzer;
    }

    /**
     * set user controller
     * 
     */
    public function setUserController($controller)
    {
        $this->userController = $controller;
    }

    /**
     * set user controller
     * 
     */
    public function setRequestId()
    {
        // $requestId = Uuid::uuid5(Uuid::NAMESPACE_DNS, "eatwhat");
        $requestId = Uuid::uuid4();
        $this->requestId = $requestId->toString();
        self::$staticRequestId = $this->requestId;
    }

    /**
     * get api args
     * 
     */
    public function getArgs()
    {
        $args = $_GET;
        unset($args["api"], $args["mtd"]);
        $this->setArgs($args);
    }

    /**
     * get api
     * 
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * get method
     * 
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * get access token analyzer obj
     * 
     */
    public function getAccessTokenAnalyzer()
    {
        return $this->accessTokenAnalyzer;
    }

    /**
     * get user controller
     * 
     */
    public function getUserController()
    {
        return $this->userController;
    }

    /**
     * get user controller
     * 
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * get user controller
     * 
     */
    public function getRequestStatus()
    {
        return $this->requestStatus;
    }

    /**
     * get user data
     * 
     */
    public function getUserData()
    {
        return $this->getUserController()->getUserData();
    }

    /**
     * add a request filter
     * 
     */
    public function addMiddleWare(callable $middleware)
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * invoke
     * 
     */
    public function invoke() 
    {
        if(!empty($this->middlewares)) {
            $handle = array_reduce(array_reverse($this->middlewares), function($next, $middleware){
                return function($request) use($next, $middleware) {
                    $middleware($request, $next);
                };
            }, [$this, "call"]);

            $handle($this);
        } else {
            $this->call();
        }
    }

    /**
     * call after middleware filter
     * 
     */
    public function call()
    {
        $container = new EatWhatContainer;
        $container->bind("EatWhatRequest", function(){return $this;});

        $instanceName = "EatWhat\\Api\\" . ucfirst($this->api) . "Api";
        if(class_exists($instanceName) && method_exists($instanceName, $this->method) && is_callable([$instanceName, $this->method])) {
            $methodObj = new \ReflectionMethod($instanceName, $this->method);
            if($methodObj->getParameters()) {
                $this->getArgs();
            }
            $api = new $instanceName($container->make("EatWhatRequest"));
            call_user_func_array([$api, $this->method], $this->args);
        }
    }

    /**
     * generate an array that includ a note and acode
     * 
     */
    public function generateStatusResult(string $langName, int $code, bool $isLang = true) : array
    {
        $result = [
            "note" => $isLang ? AppConfig::get($langName, "lang") : $langName,
            "code" => $code,
        ];
        $this->setRequestStatus($result);

        ($code < 0) && ($this->outputResult());

        return $result;
    }


    /**
     * set CORS headers before output
     * 
     */
    public function setCORSHeaders() : void 
    {
        header("Access-Control-Allow-Headers: authorization, Content-Type");
        // header("Access-Control-Allow-Origin: " . AppConfig::get("protocol") . AppConfig::get("server_name"));
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Max-Age: 3600");
    }

    /**
     * out put result with json format
     * 
     */
    public function outputResult($result = []) : void
    {
        $output = [];
        $output["request_id"] = $this->getRequestId();
        $output["auth_token"] = $this->getUserController()->getAccessToken();
        $output["status"] = $this->getRequestStatus();

        $userData = $this->getUserController()->getUserData();
        if(!empty($userData) && !in_array($output["status"]["code"], array_values(AppConfig::get("global_status", "global")))) {
            $output["user"] = $userData;
        }

        $output["result"] = $result;

        $this->setCORSHeaders();
        header("Content-Type: application/json;charset=utf-8");
        
        echo json_encode($output);
        exit();
    }
}