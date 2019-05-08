<?php

namespace EatWhat\Api;

use EatWhat\EatWhatLog;
use EatWhat\Base\ApiBase;
use EatWhat\EatWhatStatic;
use EatWhat\AppConfig;

/**
 * Eat Api
 * 
 */
class WebLogicApi extends ApiBase
{
    /**
     * use Trait
     */
    use \EatWhat\Traits\WebLogicTrait,\EatWhat\Traits\CommonTrait;
    use \EatWhat\Traits\UserTrait,\EatWhat\Traits\OrderTrait;
    use \EatWhat\Traits\GoodTrait;

    const DOWNLOAD_TYPES = ["order", "member"];

    /**
     * github Webhook when push event triggered
     * @param void
     * 
     */
    public function githubWebHook() : void
    {
        $verifyResult = $this->verifyGithubWebHookSignature();
        if( $verifyResult ) {
            putenv("HOME=/home/daemon/");
            chdir("/web/www/eat-what/");
            $cmd = "git pull --rebase 2>&1";
            exec($cmd, $o);
            print_r($o);
        } else {
            EatWhatLog::logging("Illegality Github WebHook Request", [
                "ip" => getenv("REMOTE_ADDR"),
            ]);
            echo "Faild";
        }
    }

    /**
     * send verify code by sms
     * @param void
     * 
     */
    public function sendVerifyCode() : void
    {
        $this->checkPost();
        $this->checkParameters(["mobile" => null]);
        if(!$this->checkSmsIpRequestLimit()) {
            $this->generateStatusResult("sendSmsError", -1);
        }

        $mobile = $_GET["mobile"];
        if( !EatWhatStatic::checkMobileFormat($mobile) ) {
            $this->generateStatusResult("wrongMobileFormatOrExists", -2);
        }        

        $type = $_GET["type"] ?? "login"; // login/join/modifyMobile/modifyPassword
        $code = EatWhatStatic::getRandom(4);
        $smsConfig = AppConfig::get("sms", "global");

        $smsParameters = [];
        $smsParameters["mobile"] = $mobile;
        $smsParameters["accessKey"] = $smsConfig["accessKey"];
        $smsParameters["accessSecert"] = $smsConfig["accessSecert"];
        $smsParameters["signName"] = $smsConfig["verifyCode"]["signName"];
        $smsParameters["templateCode"] = $smsConfig["verifyCode"]["templateCode"];
        $smsParameters["params"] = [
            "code" => $code,
        ];
        $result = $this->sendSms($smsParameters);

        if( $result ) {
            $expire = 1 * 60;
            $this->redis->set($mobile . "_" . $type, $code, $expire);
            $this->redis->set(getenv("REMOTE_ADDR") . "_sms_request_time", time(), $expire);
            
            $countKey = getenv("REMOTE_ADDR") . "_sms_request_count";
            $count = $this->redis->get($countKey);
            if(!$count) {
                $this->redis->set($countKey, 1, 24 * 60 * 60);
            } else {
                $this->redis->incr($countKey);
            }

            $this->generateStatusResult("sendSmsSuccess", 1);
            $this->outputResult();
        } else {
            $this->generateStatusResult("sendSmsError", -1);
        }
    }

    /**
     * get province all allowable  
     * @param void
     * 
     */
    public function getProvinceAllowable() : void 
    {
        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "province" => AppConfig::get("province", "city"),
        ]);
    }

    /**
     * get city/county by id
     * @param void
     * 
     */
    public function getSubCity() : void
    {
        $this->checkParameters(["id" => ["int", "nonzero"]]);

        $list = [];
        $id = $_GET["id"];
        $type = $_GET["type"] ?? "city";

        $divisions = $this->redis->get("division_of_china");
        !$divisions && ($divisions = $this->cacheDivisionOfCountry());

        $divisions = json_decode($divisions, true);
        if($type == "county") {
            $provinceId = substr($id, 0, 2);
            foreach($divisions[$provinceId][$id]["county"] as $countyId => $county) {
                $list[] = [
                    "id" => $countyId,
                    "name" => $county,
                ];
            }
        } else if($type == "city") {
            $cities = $divisions[$id];
            foreach($cities as $cityId => $city) {
                $list[] = [
                    "id" => $cityId,
                    "name" => $city["name"],
                ];
            }
        }

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "list" => $list,
        ]);
    }

    /**
     * download orders/members etc, csv format
     * @param void
     *
     */
    public function triggerDownload() : void
    {
        // $this->checkPost();
        $this->checkParameters(["type" => [self::DOWNLOAD_TYPES], "filters" => ["json"]]);

        $downloadType = $_GET["type"];
        if(empty($downloadType)) return;

        $csvHeaders = AppConfig::get("csv_headers", "global");

        $downloadData = $this->redis->get("downloadcsvdata_" . $downloadType);
        if( !$downloadData ) {
            switch( $downloadType ) {
                case "order":
                $_GET["filters"]["manage"] = true;
                extract($this->getOrderList($_GET["filters"], false));
                $downloadData = $orders;
                break;

                case "member":
                extract($this->getMemberList($_GET["filters"], false));
                $downloadData = $members;
                break;
            }
        } 

        /* export data with csv format*/
        $tmpFile = new \SplTempFileObject(10);
        $tmpFile->fwrite(AppConfig::get("csvFileCanBeOpendByExcel", "lang") . PHP_EOL . PHP_EOL);
        $tmpFile->fputcsv($csvHeaders[$downloadType], ",", " ");
        foreach($downloadData as $csvFields) {
            $tmpFile->fputcsv($csvFields, ",", "\"", "\\");
        }

        $contentLength = $tmpFile->ftell();
        header("Content-Disposition: attachment; filename=" . hash("sha256", time()) . ".csv");
        header("Content-Type: application/octet-stream;charset=utf-8");
        header("Content-Length: " . $contentLength);

        $tmpFile->rewind();
        $tmpFile->fpassthru();

        $log = "Download Type: order  Download Length: " . $contentLength;
        EatWhatLog::logging($log, [
            "request_id" => $this->request->getRequestId(),
        ], "file", date("Y-m") . "_download.log");
    }

    /**
     * check order expired, for crontab per 15 mins
     * @param void
     * 
     */
    public function checkOrderExpiredRegularTask() : void
    {
        $result = $this->checkOrderExpired();

        if($result) {
            EatWhatLog::logging($result, [
                "request_id" => $this->request->getRequestId(),
            ], "file", "check_order_expired.log");
        }
    }

    /**
     * crontab task to process user financing income at 0 o'clock everyday
     *
     */
    public function processUserFinancingIncomeTask() : void
    {
        $result = $this->processUserFinancingIncome();

        if($result) {
            file_put_contents(LOG_PATH . "process_user_financing_income_" . date("Y-m") . ".log", $result, FILE_APPEND);
        }
    }

    /**
     * fetch province/city/district info 
     *
     */
    public function fetchDivisionOfCountry() : void
    {
        set_time_limit(0);

        $data = [];
        $provinces = AppConfig::get("province", "city");
        $baseUrl = "compress.zlib://http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2017/";

        foreach ($provinces as $provinceId => $province) {
            $fetchUrl = $baseUrl . $provinceId . ".html";
            $context = stream_context_create([
                "http" => [
                    "header" => "Referer: http://www.stats.gov.cn/\r\nContent-Type: text/html\r\n",
                ],
            ]);

            $content = file_get_contents($fetchUrl, false, $context);
            preg_match_all("/class='citytr'.*?href='(\d+\/(\d+)\.html)'.*?href.*?>([^>]*?)</is", $content, $cityMatches);

            if($cityMatches[1]) {
                $data[$provinceId] = [];
                foreach ($cityMatches[1] as $ckey => $citySubUrl) {
                    $data[$provinceId][$cityMatches[2][$ckey]]["name"] = @iconv("gb2312", "UTF-8//IGNORE", $cityMatches[3][$ckey]);

                    $content = file_get_contents($baseUrl . $citySubUrl, false, $context);
                    preg_match_all("/class='countytr'.*?href='(\d+\/(\d+)\.html)'.*?href.*?>([^>]*?)</is", $content, $countyMatches);

                    if($countyMatches[2]) {
                        $county = [];
                        foreach ($countyMatches[2] as $dkey => $id) {
                            $county[$id] = @iconv("gb2312", "UTF-8//IGNORE", $countyMatches[3][$dkey]);
                        }
                        $data[$provinceId][$cityMatches[2][$ckey]]["county"] = $county;
                    }
                }
            }

            file_put_contents(CONFIG_PATH . "division_of_china.json", json_encode($data));
            echo "$province" . AppConfig::get("fetchFinish", "lang") . PHP_EOL;
            flush();
            ob_flush();
            sleep(1);
        }
    }

    /**
     * cache division info of country 
     *
     */
    public function cacheDivisionOfCountry() : string
    {
        if(file_exists($jsonFile = CONFIG_PATH . "division_of_china.json")) {
            $divisions = file_get_contents($jsonFile);
            $this->redis->set("division_of_china", $divisions);
            $this->redis->persist("division_of_china");
            return $divisions;
        }
    }
}
