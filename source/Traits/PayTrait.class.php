<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * Car Traits For User Api
 * 
 */
trait PayTrait
{
    /**
     * verify webhooks signature
     * 
     */
    public function verifyWebHookSign(string $raw_data) : bool
    {
        $headers = EatWhatStatic::getallheaders();
        $signature = $headers["x-pingplusplus-signature"] ?? NULL;
        $pubkey = file_get_contents( (AppConfig::get("pingpp", "pay"))["webhook_public_key"] );

        return openssl_verify($raw_data, base64_decode($signature), $pubkey, "sha256");
    }

    /**
     * get ping++ channel
     * [["alipay", "wx", "wx_lite"]], // 支付宝app,微信app,微信小程序
     * 
     */
    public function getPingppChannel(int $payChannel, string $source) : string
    {
        if( $source == "app" ) {
            if($payChannel == 1) {
                return "alipay";
            } else if($payChannel == 2) {
                return "wx";
            }
        } else if($source == "wx"){
            if($payChannel == 3) {
                return "wx_lite";   
            }
        }
    }

    /**
     * get ping++ channel id
     * 
     */
    public function getPingppChannelId(string $payChannel, string $source) : int
    {
        if( $source == "app" ) {
            if($payChannel == "alipay") {
                return 1;
            } else if($payChannel == "wx") {
                return 2;
            }
        } else if($source == "wx") {
            if($payChannel == "wx_lite") {
                return 3;
            }
        }
    }

    /**
     * inform admin to process order after user paied
     * 
     */
    public function orderPaiedInform() : bool
    {
        $smsConfig = AppConfig::get("sms", "global");

        // inform admin
        $result = $this->sendSms([
            "mobile" => $this->getSetting("adminMobile"),
            "accessKey" => $smsConfig["accessKey"],
            "accessSecert" => $smsConfig["accessSecert"],
            "signName" => $smsConfig["orderPaiedInform"]["signName"],
            "templateCode" => $smsConfig["orderPaiedInform"]["templateCode"],
        ]);

        // inform last-level user
        $last_level_users = $this->getLastLevelUser($this->uid);
        
        foreach($last_level_users as $user) {
            $rankName = ["asFirst", "asSecond"][$user["relation_level"] - 1];
            $returnRatio = $this->getLevelRule($user["level"], $rankName, "userLevelReturnRatio");
            $returnMoney = bcmul($this->informOrderMoney, $returnRatio);

            $result = $this->sendSms([
                "mobile" => $user["mobile"],
                "accessKey" => $smsConfig["accessKey"],
                "accessSecert" => $smsConfig["accessSecert"],
                "signName" => $smsConfig["informLastLevelUser"]["signName"],
                "templateCode" => $smsConfig["informLastLevelUser"]["templateCode"],
                "params" => [
                    "name" => $user["username"],
                    "good" => $this->informGoodName,
                    "money" => $this->informOrderMoney,
                    "return" => $returnMoney,
                ],
            ]); 
        }

        return $result;
    }
}