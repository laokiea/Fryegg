<?php

/**
 * some static operation in app
 *
 */

namespace EatWhat;

use EatWhat\AppConfig;

class EatWhatStatic
{
    /**
     * check file exists and can be read
     *
     */
    public static function checkFile($file)
    {
        return file_exists($file) && is_readable($file);
    }

    /**
     * check a variable is empty except the val in the array exclude
     *
     */
    public static function checkEmpty($value, $exclude = [])
    {
        $exclude && !is_array($exclude) && ($exclude = [$exclude]);

        if( $exclude && in_array($value, $exclude, true) )
            return false;

        if( empty($value) ) {
            return true;
        }

        return false;
    }

    /**
     * check post request
     * 
     */
    public static function checkPost()
    {
        return self::checkPostMethod() && 1;
    }

    /**
     * check http method is post
     * 
     */
    public static function checkPostMethod()
    {
        return getenv("REQUEST_METHOD") == "POST";
    }

    /**
     * get passed params sign
     * 
     */
    public static function getParamsSign()
    {
        $data = json_encode($_GET);
        $pri_key_pem_file = AppConfig::get("pri_key_pem_file", "global");
        $pri_key = openssl_pkey_get_private($pri_key_pem_file);

        openssl_sign($data, $signature, $pri_key, "sha256");
        return $signature;
    }

    /**
     * get GP value
     * 
     */
    public static function getGPValue($key)
    {
        if(isset($_GET[$key])) {
            return $_GET[$key];
        }
        return "";
    }

    /**
     * illegal return
     * 
     */
    public static function illegalRequestReturn()
    {
        $output = <<<EATWHAT
------------------------------------------------------------
|        _                _           _                     |
|        ___  __ _| |_    __      _| |__   __ _| |_         |
|       / _ \/ _` | __|___\ \ /\ / / '_ \ / _` | __|        |
|      |  __/ (_| | ||_____\ V  V /| | | | (_| | |_         |
|       \___|\__,_|\__|     \_/\_/ |_| |_|\__,_|\__|        |
|                                                           | 
------------------------------------------------------------
                Oops! Some Thing Wrong.
                       A start
                       B select
EATWHAT;
        http_response_code(500);
        exit($output);
    }

    /**
     * convert a number to a specify base, $int is a decimal number
     * 
     */
    public static function convertBase(int $int, $tobase) : string
    {
        if($tobase <= 32 && $tobase >= 2) {
            return base_convert($int, 10, $tobase);
        } else if($tobase > 32 && $tobase <= 62) {
            $mod = self::numberToAscii($int % $tobase);
            $div = (int)($int / $tobase);
            if($div >= $tobase) {
                return self::convertBase($div, $tobase) . $mod;
            } else {
                return self::numberToAscii($div) . $mod;
            }
        }
    }

    /**
     * multiple band and ascii convert by bit
     * 
     */
    public static function numberToAscii(int $num) : string
    {
        if($num >= 10 && $num <= 35) {
            $num = chr($num + 55);
        } else if($num > 35 && $num <= 61) {
            $num = chr($num + 61);
        }
        return $num;
    }

    /**
     * trim value recursive
     * 
     */
    public static function trimValue(array &$value) : void
    {
        foreach($value as &$v) {
            if( is_array($v) ) {
                self::trimValue($v);
            } else {
                $v = trim($v);
            }
        }
    }

    /**
     * emulate a async request
     * 
     */
    public static function asyncRequestWithoutOutput($url)
    {
        $cwd = getcwd();
        chdir("/tmp");
        $cmd = "nohup curl -so /dev/null " . str_replace(["&", " "], ["\&", "%20"], addslashes($url)) . " 2>&1 &";
        pclose(popen($cmd, "r"));
        chdir($cwd);
    }

    /**
     * check mobile format
     * 
     */
    public static function checkMobileFormat(string $mobile) : bool
    {
        if( !preg_match("/^(13|14|15|16|18|17|19)[0-9]{9}$/", $mobile) ) {
            return false;
        }
        return true;
    }

    /**
     * get url qrcode
     * 
     */
    public static function getUrlQrcode(string $url) : string
    {
        require_once LIB_PATH . "phpqrcode.php";
        \QRcode::png($url, false, QR_ECLEVEL_L, 4, 6);
        return ob_get_clean();
    }

    /**
     * get random number/char
     * 
     */
    public static function getRandom(int $count, string $type = "number") : string
    {
        $random = "";
        if($type == "number") {
            for($i = 0;$i < $count;$i++) {
                $random .= mt_rand(1,9);
            }
        } else if($type == "char") {
            $baseString = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $random = substr(str_shuffle($baseString), 0, $count);
        }

        return $random;
    }

    /**
     * get period timestamp
     * 
     */
    public static function getPeriodTimestamp(int $days, string $op = "sub") : int
    {
        if($days == 0) return 0;

        $date = new \DateTime(date("Y-m-d"));
        $date->{$op}(new \DateInterval("P".$days."D"));
        $timestamp = $date->getTimestamp();
        
        return $timestamp;
    }

    /**
     * check url format
     * 
     */
    public static function checkUrlFormat(string $url) : bool
    {
        return boolval(preg_match("/^(https?:\/\/)?([\w\-\_\+]+\.)+.*\/?$/i", $url));
    }

    /**
     * base64 encode
     * 
     */
    public static function base64encode(string $string, bool $safe = false) : string
    {
        $code = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
        $encode = "";
        
        $chunks = str_split($string, 3);
        foreach($chunks as $chunk) {
            $group = "";
            for($i = 0;$i < strlen($chunk);$i++) {
                $group .= sprintf("%08s", decbin(ord($chunk{$i})));
            }
    
            $encode .= implode("", array_map(function($v) use($code){
                if(strlen($v) == 6) {
                    return $code{bindec("00" . $v)};
                } else if(strlen($v) == 4) {
                    return $code{bindec("00" . $v . "00")};
                } else if(strlen($v) == 2) {
                    return $code{bindec("00" . $v . "0000")};
                }
            },str_split($group, 6))) . str_repeat("=", strlen($group) % 3);
        }
    
        return $safe ? str_replace(["+", "/"], ["-", "_"], $encode) : $encode;
    }

    /**
     * get all headers
     * 
     */
    public static function getallheaders() : array
    {
        if( !function_exists("getallheaders")) {
            $getheaders = function() {
                $headers = [];
                foreach ($_SERVER as $name => $value) { 
                    if (substr($name, 0, 5) == 'HTTP_') { 
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
                    } 
                } 
                return $headers;
            };
            return $getheaders();
        } else {
            return getallheaders();
        }
    }

    /**
     * rmdir loop
     * 
     */
    public static function rrmdir(string $src) : void
    {
        if (file_exists($src)) {
            $dir = opendir($src);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                     $full = $src . DS . $file;
                    if (is_dir($full)) {
                        self::rrmdir($full);
                    } else {
                        unlink($full);
                    }
                }
            }
            closedir($dir);
            rmdir($src);
        }
    } 
}