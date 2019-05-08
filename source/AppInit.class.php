<?php

/**
 * App Initial
 *
 */

namespace EatWhat;

use Whoops\Run;
use EatWhat\EatWhatJwt;
use EatWhat\EatWhatStatic;
use EatWhat\EatWhatRequest;
use EatWhat\EatWhatContainer;
use EatWhat\Generator\Generator;
use Whoops\Handler\PrettyPageHandler;
use EatWhat\Exceptions\EatWhatException;

class AppInit
{

	/**
	 * App initalal config
	 *
	 */
	public $initConfig;

	/**
	 * request
	 *
	 */
	public $request;

	/**
	 * Initial app
	 *
	 */
	public function _Init(array $initConfig) : void
	{
		$this->initConfig = $initConfig;

		DEVELOPMODE && $this->setErrorDisplayAndHandle();

		ob_end_clean();
		ob_start("ob_gzhandler");

		session_start();

		date_default_timezone_set("Asia/Shanghai");

		$this->register();
		$this->initInput();
		$this->setGlobal();

		$container = new EatWhatContainer;
		$container->bind("EatWhatJwt", function(){
			return new EatWhatJwt(null, null);
		});
		$container->bind("UserController", "EatWhat\Controller\UserController");

		// create request
		$this->request = new EatWhatRequest();
		$this->request->setAccessTokenAnalyzer($container->make("EatWhatJwt"));
		$this->request->setUserController($container->make("UserController"));

		// verify user
		$this->request->addMiddleWare(Generator::middleware("verifyAccessToken"));
		
		//verify api and method
		$this->request->addMiddleWare(Generator::middleware("verifyApiAndMethod"));

		//verify sign
		if($this->initConfig["api_verify_sign"]) {
			$_GET["paramsSign"] = EatWhatStatic::getParamsSign();
			$this->request->addMiddleWare(Generator::middleware("verifySign"));
		}
		
		// invoke
		$this->request->invoke();
	}

	/**
	 * autoload
	 *
	 */
	public function register(bool $prepend = false) : void
	{
		spl_autoload_register([$this, "autoLoadRegister"], false, $prepend);
	}


	/**
	 * autoload method
	 *
	 */
	public function autoLoadRegister(string $class) : void
	{
		$file = $this->findFile($class);
		if($file && EatWhatStatic::checkFile($file)) 
			require_once $file;
		else 
			throw new EatWhatException($class." class file is not exists.");
	}

	/**
	 * autoload class method
	 *
	 */
	public function findFile(string $class) : ?string
	{
    	$subPath = $class;
    	$suffix  = '';

    	if( isset($this->initConfig['classmap_static'][$class]) ) {
			$file = $this->initConfig['classmap_static'][$class];
			return $file;
		}

		while( false !== $lastPos = strrpos($subPath, '\\') ) {
			$suffix = substr($subPath, $lastPos).$suffix;
			$suffix = str_replace('\\', DS, $suffix);
			$subPath = substr($subPath, 0, $lastPos);
			if ( isset($this->initConfig['classmap_namespace'][$subPath]) ) {
				$file = $this->initConfig['classmap_namespace'][$subPath].$suffix.$this->initConfig['class_file_ext'];
				return $file;
			}
		}
	}

	/**
	 * set error display level and handle
	 * 
	 */
	public function setErrorDisplayAndHandle() : void
	{
		error_reporting(E_ALL);
		ini_set('display_errors','On');
		ini_set('display_startup_errors','On');
		ini_set('log_errors','On');

		$this->registerErrorHandle();
	}

	/**
	 * register error handle
	 * 
	 */
	public function registerErrorHandle() : void
	{
		$whoops = new Run;
		$whoops->pushHandler(new PrettyPageHandler);
		$whoops->register();
	}
	
	/**
	 * init input
	 * 
	 */
	public function initInput() : void
	{
		EatWhatStatic::checkPostMethod() && ($_GET = array_merge($_GET, $_POST));
		EatWhatStatic::trimValue($_GET);
	}

	/**
	 * set some defiend variable etc(ClientFlag)
	 * 
	 */
	public function setGlobal() : void
	{
		define("CLIENT_FLAG", $this->getClientFlag());
		// define();
	}

	/**
	 * get client flag (etc miniapp = 2,web = 1)
	 * 
	 */
	public function getClientFlag() : int
	{
		if(isset($_GET["miniapp"])) {
			return 2;
		}
		return 1;
	}
}