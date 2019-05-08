<?php

namespace EatWhat\Api;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\Base\ApiBase;
use EatWhat\EatWhatStatic;
use FileUpload\Validator\Simple as ValidatorSimple;
use FileUpload\PathResolver\Simple as PathResolver;
use FileUpload\FileSystem\Simple as FileSystem;
use FileUpload\FileNameGenerator\Custom as FileNameCustom;
use FileUpload\FileUploadFactory;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * User Api
 * 
 */
class UserApi extends ApiBase
{
    /**
     * use Trait
     */
    use \EatWhat\Traits\UserTrait,\EatWhat\Traits\CommonTrait,\EatWhat\Traits\GoodTrait;

    /**
     * a new user
     * 用户注册
     * @param void
     * 
     */
    public function join() : void
    {
        $this->checkPost();
        $this->checkParameters(["mobile" => null, "verifyCode" => ["int", "nonzero"], "username" => null, "password" => null, "password_repeat" => null]);        
        $this->beginTransaction();

        $mobile = $_GET["mobile"];
        if( !$this->checkMobile($mobile) ) {
            $this->generateStatusResult("wrongMobileFormatOrExists", -2);
        }

        if( !($verifyCode = $_GET["verifyCode"]) || !$this->checkMobileCode($mobile, $verifyCode, "join") ) {
            $this->generateStatusResult("wrongVerifyCode", -3);
        }

        $username = $_GET["username"];
        if( !$this->checkUsername($username) ) {
            $this->generateStatusResult("wrongUsernameFormatOrExists", -4);
        }

        if( !$this->checkPassword($_GET["password"]) ) {
            $this->generateStatusResult("wrongPasswordFormat", -5);
        }

        if(strcmp($_GET["password"], $_GET["password_repeat"])) {
            $this->generateStatusResult("passwordNotSame", -6);
        }
        
        $newUser = [];
        $newUser["mobile"] = $mobile;
        $newUser["username"] = $username;
        $newUser["password"] = password_hash($_GET["password"], PASSWORD_DEFAULT);
        $newUser["create_time"] = time();
        $newUser["last_login_time"] = time();
        isset($_GET["lastUid"]) && (int)$_GET["lastUid"] && ($newUser["lastUid"] = (int)$_GET["lastUid"]);
        $newUserId = $this->createNewUser($newUser);

        // user relation
        if(isset($_GET["lastUid"])) {
            $this->setUserRelation($newUserId, $_GET["lastUid"]);
        }

        $this->setDefaultAvatar($newUserId, hash("sha256", $username));

        $userData = [
            "uid" => $newUserId,
            "username" => $username,
            "avatar" => $this->getUserAvatar($newUserId),
            "tokenType" => "user",
        ];

        $this->initMemberCount($newUserId);
        $this->commit();

        $this->setUserLogin($userData);
        $this->generateStatusResult("registerActionSuccess", 1);
        $this->outputResult();
    }

    /**
     * login
     * 用户登录
     * @param void
     * 
     */
    public function login() : void
    {
        $this->checkPost();
        $this->checkParameters(["mobile" => null]); 

        $mobile = $_GET["mobile"];
        $user = $this->getUserBaseInfoByMobile($mobile);

        if(!$user || $user["status"] < 0) {
            $this->generateStatusResult("userStatusAbnormal", -1); 
        }

        $loginType = $_GET["loginType"] ?? "code";
        
        if($loginType == "code") {
            $this->loginByVerifyCode($user);    
        } else if($loginType == "password") {
            $this->loginByPassword($user);
        }

        $this->setUserLogin([
            "uid" => $user["id"],
            "username" => $user["username"],
            "avatar" =>  $this->getUserAvatar($user["id"]),
            "tokenType" => "user",
        ]);

        $this->updateUserLastLoginTime($user["id"]);
        $this->generateStatusResult("loginActionSuccess", 1);
        $this->outputResult();
    }

    /**
     * login by verify code
     * 用户登录(验证码)
     * @param void
     * 
     */
    public function loginByPassword(array $user) : void
    {
        $this->checkParameters(["password" => null]);

        if(!password_verify($_GET["password"], $user["password"])) {
            $this->generateStatusResult("userVerifyError", -1);
        }
    }

    /**
     * login by verify code
     * 用户登录(验证码)
     * @param void
     * 
     */
    public function loginByVerifyCode(array $user) : void
    {
        $this->checkParameters(["verifyCode" => ["int", "nonzero"]]); 

        $mobile = $_GET["mobile"];

        if( !($verifyCode = $_GET["verifyCode"]) || !$this->checkMobileCode($mobile, $verifyCode, "login") ) {
            // $this->generateStatusResult("wrongVerifyCode", -3);
        }
    }

    /**
     * get user info, include base info and property
     * 用户个人信息，用于个人首页显示，包括基本信息，资产信息
     * @param void
     * 
     */
    public function userInfo() : void
    {
        $userBaseInfo = $this->getUserBaseInfoById($this->uid);

        $userCount = $this->getUserCount($this->uid);
        unset($userCount["id"], $userCount["uid"]);
        $userCount["undeposit"] = $this->getUserUndepositCount($this->uid);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "base" => $userBaseInfo,
            "property" => $userCount,
        ]);
    }

    /**
     * modify user mobile
     * 修改手机号码
     * @param void
     * 
     */
    public function modifyMobile() : void
    {
        $this->checkPost();
        $this->checkParameters(["newmobile" => null]); 

        $newMobile = $_GET["newmobile"];
        if( !$this->checkMobile($newMobile) ) {
            $this->generateStatusResult("wrongMobileFormatOrExists", -2);
        }

        if( !($verifyCode = $_GET["verifyCode"]) || !$this->checkMobileCode($newMobile, $verifyCode, "modifyMobile") ) {
            $this->generateStatusResult("wrongVerifyCode", -3);
        }

        $this->updateUserMobile($newMobile);

        $this->request->getUserController()->setUserField("mobile", $newMobile);
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * modify user mobile
     * 修改手机号码
     * @param void
     * 
     */
    public function modifyPassword() : void
    {
        $this->checkPost();
        $this->checkParameters(["mobile" => null, "verifyCode" => ["int", "nonzero"], "new_password" => null, "new_password_repeat" => null]);

        $user = $this->getUserBaseInfoByMobile($_GET["mobile"]);

        if(!$user || $user["status"] < 0) {
            $this->generateStatusResult("userStatusAbnormal", -1); 
        }

        if( !($verifyCode = $_GET["verifyCode"]) || !$this->checkMobileCode($_GET["mobile"], $verifyCode, "modifyPassword") ) {
            $this->generateStatusResult("wrongVerifyCode", -3);
        }

        if( !$this->checkPassword($_GET["new_password"]) ) {
            $this->generateStatusResult("wrongPasswordFormat", -5);
        }

        if(strcmp($_GET["new_password"], $_GET["new_password_repeat"])) {
            $this->generateStatusResult("passwordNotSame", -6);
        }

        $this->updateUserBaseInfo([
            "password" => password_hash($_GET["new_password"], PASSWORD_DEFAULT),
        ], $user["id"]);

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * log out
     * 登出
     * @param void
     * 
     */
    public function logout() : void
    {
        $this->_logout();
    }

    /**
     * modify user info
     * 修改用户个人头像
     * @param void
     * 
     */
    public function modifyUserAvatar() : void
    {
        $this->checkPost();
        $this->checkParameters(["avatar" => null]);

        $userAvatar = "." . $this->getUserAvatar($this->uid, false);
        $factory = new FileUploadFactory(
            new PathResolver(dirname($userAvatar)),
            new FileSystem(), [
                new ValidatorSimple("2M", ["image/png", "image/jpg", "image/jpeg"]),
            ]
        );
        $fileUpload = $factory->create($_FILES["avatar"], $_SERVER);
        
        $customGenerator = new FileNameCustom("avatar.png");
        $fileUpload->setFileNameGenerator($customGenerator);

        list($files, $headers) = $fileUpload->processAll();
        if( $files[0]->completed ) {
            $this->generateStatusResult("modifyAvatarSuccess", 1);
            $this->outputResult();
        } else {
            $this->generateStatusResult($files[0]->error, -1, false);
        }
    }

    /**
     * modify user base info
     * sex age location
     * 修改用户基本信息
     * @param void
     * 
     */
    public function modifyUserBase() : void
    {
        $this->checkPost();

        $baseInfo = [];
        foreach(["sex", "age", "location"] as $option) {
            isset($_GET[$option]) && ($baseInfo[$option] = $_GET[$option]);
        }

        if(!empty($baseInfo)) {
            $this->updateUserBaseInfo($baseInfo, $this->uid);
        }

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * generate a invitation qrcode
     * 生成邀请二维码
     * @param void
     * 
     */
    public function inviteJoinQrcode() : void
    {
        $inviteJoinUrl = AppConfig::get("protocol", "global") . AppConfig::get("api_server_name", "global") . "/" . "join.html?lastUid=" . $this->uid;
        $this->request->setCORSHeaders();
        $rawContent = EatWhatStatic::getUrlQrcode($inviteJoinUrl);

        header("Content-Type: html/text");
        echo base64_encode($rawContent);
    }

    /**
     * get all distributors of user
     * 获取用户的所有下线
     * @param void
     * 
     */
    public function getAllDistributors() : void
    {
        $page = $_GET["page"] ?? 1;
        $num = $_GET["num"] ?? 10;
        $level = $_GET["level"] ?? 1;

        $distributors = $this->_getAllDistributors($this->uid, $page, $num, $level);
        $pagemore = count($distributors) == $num ? 1 : 0;
        
        $this->generateStatusResult("200 OK", 1, false);
        $result["distributors"] = $distributors;
        $result["count"] = $this->getDistributorsCount();
        $result["pagemroe"] = $pagemore;

        $this->outputResult($result);
    }

    /**
     * add a shipping address
     * 新增收货地址
     * @param void
     * 
     */
    public function addAddress() : void
    {
        $this->checkPost();
        $this->checkParameters(["province" => null, "province_id" => ["int", "nonzero"], "city" => null, "district" => null, "detail" => null, "contact_name" => null, "contact_number" => null]);

        $count = $this->getAddressCount($this->uid);
        if($count == $this->getSetting("addressCountLimit")) {
            $this->generateStatusResult("addessCountOutOfLimit", -1);
        }

        if(!$this->checkUsernameFormat($_GET["contact_name"])) {
            $this->generateStatusResult("wrongContactNameFormat", -1);
        }

        if(!$this->checkMobileFormat($_GET["contact_number"])) {
            $this->generateStatusResult("wrongContactNumberFormat", -2);
        }

        $address = [];
        foreach(["province", "province_id", "city", "district", "detail", "contact_number", "contact_name"] as $option) {
            $address[$option] = $_GET[$option];
        }

        if(isset($_GET["isdefault"]) && $_GET["isdefault"] == 1) {
            $address["isdefault"] = 1;
            $this->setDefaultAddressToNot($this->uid);
        } else {
            $address["isdefault"] = 0;
        }

        $address["uid"] = $this->uid;
        $address["create_time"] = time();
        
        $addressId = $this->_addAddress($address);
        
        $this->generateStatusResult("addAddressSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete user shipping address
     * 删除收货地址
     * @param void
     * 
     */
    public function deleteAddress() : void
    {
        $this->checkPost();
        // $this->checkParameters(["address_ids" => ["array_int", "array_nonzero"]]);
        $this->checkParameters(["address_id" => ["int", "nonzero"]]);
        
        $_GET["address_ids"] = (array)$_GET["address_id"];
        foreach( $_GET["address_ids"] as $addressId ) {
            $address = $this->getAddressInfo($addressId);
            if($this->uid != $address["uid"]) {
                $this->request->generateStatusResult("serverError", -404);
            }
        }

        $this->_deleteAddress($_GET["address_ids"]);
        
        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * Set to the default shipping address
     * 设置用户某一收货地址为默认
     * @param void
     * 
     */
    public function setToDefaultAddress() : void
    {
        $this->checkPost();
        $this->checkParameters(["address_id" => ["int", "nonzero"]]);

        $addressId = (int)$_GET["address_id"];
        $address = $this->getAddressInfo($addressId);
        if($this->uid != $address["uid"]) {
            $this->request->generateStatusResult("serverError", -404);
        }

        $this->setDefaultAddressToNot($this->uid);
        $this->_setToDefaultAddress($addressId);

        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * get user all shipping address
     * 获取用户收货地址列表
     * @param void
     * 
     */
    public function getAddress() : void
    {
        $addresses = $this->getUserAddress($this->uid, @(int)$_GET["default"] == 1);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(["data" => $addresses, "count" => count($addresses)]);
    }

    /**
     * edit user shipping address
     * 编辑收货地址
     * @param void
     * 
     */
    public function editAddress() : void
    {
        $this->checkPost();
        $this->checkParameters(["address_id" => ["int", "nonzero"], "province" => null, "province_id" => ["int", "nonzero"], "city" => null, "district" => null, "detail" => null, "contact_name" => null, "contact_number" => null]);

        if(!$this->checkUsernameFormat($_GET["contact_name"])) {
            $this->generateStatusResult("wrongContactNameFormat", -1);
        }

        if(!$this->checkMobileFormat($_GET["contact_number"])) {
            $this->generateStatusResult("wrongContactNumberFormat", -2);
        }

        $addressId = (int)$_GET["address_id"];
        $addressInfo = $this->getAddressInfo($addressId);
        if($this->uid != $addressInfo["uid"]) {
            $this->request->generateStatusResult("serverError", -404);
        }

        $address = [];
        foreach(["province", "city", "district", "detail", "contact_name", "contact_number"] as $option) {
            $address[$option] = $_GET[$option];
        }

        if(isset($_GET["isdefault"]) && $_GET["isdefault"] == 1) {
            $address["isdefault"] = 1;
            $this->setDefaultAddressToNot($this->uid);
        }

        if( !empty($address) ) {
            $this->editUserAddress($addressId, $address);
        }

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * return - user money-return log
     * 用户返现记录
     * @param void
     * 
     */
    public function moneyReturnLog() : void
    {
        $page = $_GET["page"] ?? 1;
        $size = $_GET["size"] ?? 10;

        $returnLogs = $this->getMoneyReturnLog($this->uid, $page, $size);
        $pagemore = count($returnLogs) == $size ? 1 : 0;
        
        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "logs" => $returnLogs,
            "page" => $page,
            "pagemore" => $pagemore,
        ]);
    }

    /**
     * property financing
     * 开启资产理财
     * @param void
     * check expire: time() >= property_financing_expire
     * check not expire: time() <= property_financing_start 
     *
     */
    public function propertyFinancing() : void
    {
        $this->checkPost();
        $this->checkParameters(["period" => ["int", "nonzero", [10, 30, 90, 180, 360]]]);

        $userCount = $this->getUserCount($this->uid);
        if($userCount["property_financing"]) {
            $this->generateStatusResult("propertyAlreadyFinancing", -1);
        }

        if($userCount["property"] < $this->getSetting("minimumFinancingProperty")) {
            $this->generateStatusResult("lessThanMinimumFinancingProperty", -1);
        }

        $this->userBeginPropertyFinancing($this->uid, $_GET["period"]);

        $this->generateStatusResult("financingSuccess", 1);
        $this->outputResult();
    }

    /**
     * user financing info
     * 我的理财信息，理财收入，剩余天数等等
     * @param void
     * 
     */
    public function myFinancing() : void
    {
        if(!($financingInfo = $this->getUserFinancingInfo($this->uid))) {
            $this->generateStatusResult("userNoFinancingInfo", -1);
        }

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "financing" => $financingInfo,
        ]);
    }

    /**
     * add a user undeposit account
     * 用户绑定收款账户
     * @param void
     * 
     */
    public function addAccount() : void
    {
        $this->checkPost();
        $this->checkParameters(["account" => null]);

        if( ($accountList = $this->getUserAccountList($this->uid)) && count($accountList) >= 1) {
            $this->generateStatusResult("accountLimit", -1);
        }

        $accountId = $this->insertOneObject([
            "uid" => $this->uid,
            "type" => "bank",
            "account" => $_GET["account"],
            "bind_time" => time(),
        ], "member_account");

        $this->generateStatusResult("bindSuccess", 1);
        $this->outputResult([
            "account_id" => $accountId,
        ]);
    }

    /**
     * get user account list
     * 获取用户绑定账户列表
     * @param void
     * 
     */
    public function accountList() : void
    {
        $accountList = $this->getUserAccountList($this->uid);

        $this->generateStatusResult("200 OK", 1);
        $this->outputResult([
            "accounts" => $accountList,
        ]);
    }

    /**
     * delete user account
     * 删除绑定账户
     * @param void
     * 
     */
    public function deleteAccount() : void 
    {
        $this->checkPost();
        $this->checkParameters(["account_id" => ["int", "nonzero"]]);

        $accountId = (int)$_GET["account_id"];
        $account = $this->getAccountInfo($accountId);
        if($account["uid"] != $this->uid) {
            $this->generateStatusResult("serverError", -404);
        }

        $this->deleteUserAccount($accountId);
        
        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * initiate undeposit
     * 发起提现请求
     * @param void
     * 
     */
    public function initiateUndeposit() : void
    {
        $this->checkPost();
        $this->checkParameters(["amount" => ["float", "nonzero"], "account_id" => ["int", "nonzero"]]);
        bcscale($this->getSetting("decimalPlaces"));

        $undepositAmount = $_GET["amount"];
        if($undepositAmount < 1.0) {
            $this->generateStatusResult("undepositMinimumError", -1);
        }

        $userCount = $this->getUserCount($this->uid);
        if($userCount["property_financing"] == 1 && time() < $userCount["property_financing_expire"]) {
            $this->generateStatusResult("propertyFinancingIsNotExpired", -2);
        }
        if(bcsub($userCount["property"], $undepositAmount) < 0.1) {
            $this->generateStatusResult("propertyMoneyLack", -3);
        }

        $accountId = (int)$_GET["account_id"];
        $account = $this->getAccountInfo($accountId);
        if($account["uid"] != $this->uid) {
            $this->generateStatusResult("serverError", -404);
        }

        $logId = $this->insertOneObject([
            "uid" => $this->uid,
            "amount" => $undepositAmount,
            "log_time" => time(),
            "account_id" => $accountId,
        ], "member_log_undeposit");
        $this->updateUserCount($this->uid, "property", -$undepositAmount);

        $this->generateStatusResult("initiateUndepositSuccess", 1);
        $this->outputResult(["logId" => $logId]);        
    }

    /**
     * User Undeposit Logs
     * 用户提现记录
     * @param void
     * 
     */
    public function undepositLog() : void 
    {
        $page = $_GET["page"] ?? 1;
        $size = $_GET["size"] ?? 10;

        $undepositLogs = $this->getUndepositLog([
            "uid" => $this->uid
        ], $page, $size);
        $pagemore = count($undepositLogs) == $size ? 1 : 0;
        
        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "logs" => $undepositLogs,
            "page" => $page,
            "pagemore" => $pagemore,
        ]);
    }

    /**
     * user property log
     * 用户资产变更记录
     * @param void
     * 
     */
    public function propertyLog() : void
    {
        $page = $_GET["page"] ?? 1;
        $size = $_GET["size"] ?? 10;

        $propertyLogs = $this->getPropertyLog($this->uid, $page, $size);
        $pagemore = count($propertyLogs) == $size ? 1 : 0;
        
        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "logs" => $propertyLogs,
            "page" => $page,
            "pagemore" => $pagemore,
        ]);
    }

    /**
     * user messages
     * 用户消息
     * @param void
     * 
     */
    public function userMessage() : void
    {
        $page = $_GET["page"] ?? 1;
        $size = $_GET["size"] ?? 10;

        $userMessages = $this->getUserMessages($this->uid, $page, $size);
        $pagemore = count($userMessages) == $size ? 1 : 0;
        
        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "messages" => $userMessages,
            "page" => $page,
            "pagemore" => $pagemore,
        ]);
    }

    /**
     * mark message read done
     * 标记已读
     * @param void
     * 
     */
    public function messageReadDone() : void
    {
        $this->checkPost();
        $this->checkParameters(["message_id" => ["int", "nonzero"]]);

        $messageId = (int)$_GET["message_id"];
        $this->markMessageReadDone($messageId);

        $this->generateStatusResult("markReadDoneSuccess", 1);
        $this->outputResult();
    }
}
