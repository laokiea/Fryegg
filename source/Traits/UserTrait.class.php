<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use EatWhat\EatWhatStatic;

use function GuzzleHttp\Promise\unwrap;

/**
 * User Traits For User Api
 * 
 */
trait UserTrait
{

    /**
     * get request action uid
     * 
     */
    public function getActionUid()
    {
        if(isset($_GET["uid"]) && (int)$_GET["uid"]) {
            $user = $this->getUserBaseInfoById((int)$_GET["uid"]);
            if(empty($user)) {
                $this->generateStatusResult("UserNotExists", -1); 
            }
            return (int)$_GET["uid"];
        } else {
            return $this->uid;
        }
    }

    /**
     * check mobile format and is that exists
     * 
     */
    public function checkMobile(string $mobile) : bool
    {
        return $this->checkMobileFormat($mobile) && !$this->checkMobileExists($mobile);
    }

    /**
     * check username format and is that exists
     * 
     */
    public function checkUsername(string $username) : bool
    {
        return $this->checkUsernameFormat($username) && !$this->checkUsernameExists($username);
    }

    /**
     * check mobile is exists
     * return true when exists
     * 
     */
    public function checkMobileExists(string $mobile) : bool
    {
        $dao = $this->mysqlDao->table("member")
                    ->select(["id"])
                    ->where(["mobile"])
                    ->prepare()
                    ->execute([$mobile]);

        $result = $dao->fetch(\PDO::FETCH_ASSOC);
        return boolval($result);
    }

    /**
     * check mobile code
     * 
     */
    public function checkMobileCode(string $mobile, string $code, string $action = "login") : bool
    {
        $codeKey = $mobile . "_" . $action;
        $verifyCode = $this->redis->get($codeKey);

        if(!$verifyCode || $verifyCode != $code) {
            return false;
        }

        return true;
    }

    /**
     * check username format
     * 
     */
    public function checkUsernameFormat(string $username) : bool
    {
        return boolval(preg_match("/^[\d\w\p{Han}]{2,12}$/iu", $username));
    }

    /**
     * check username format
     * 
     */
    public function checkUsernameExists(string $username) : bool
    {
        $dao = $this->mysqlDao->table("member")
                    ->select(["id"])
                    ->where(["username"])
                    ->prepare()
                    ->execute([$username]);

        $result = $dao->fetch(\PDO::FETCH_ASSOC);
        return boolval($result);
    }

    /**
     * create a new user
     * 
     */
    public function createNewUser(array $newUser) : int
    {
        $this->mysqlDao->table("member")
             ->insert(array_keys($newUser))
             ->prepare()
             ->execute(array_values($newUser));

        return (int)$this->mysqlDao->getLastInsertId();
    }

    /**
     * initial member count
     * 
     */
    public function initMemberCount(int $uid) : int
    {
        $this->mysqlDao->table("member_count")
             ->insert(["uid"])
             ->prepare()->execute([$uid]);
        
        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * update user last login time
     * 
     */
    public function updateUserLastLoginTime(int $uid) : bool 
    {
        // $this->mysqlDao->table("member")->update(["last_login_time"])->prepare()->execute([time()]);
        $this->updateMemberField($uid, "last_login_time", time());

        return $this->mysqlDao->execResult;
    }

    /**
     * get user status
     * 
     */
    public function getUserBaseInfoByMobile(string $mobile, ?string $field = null)
    {
        $dao = $this->mysqlDao->table("member")
                    ->select(["*"])
                    ->where(["mobile"])
                    ->prepare()
                    ->execute([$mobile]);

        $user = $dao->fetch(\PDO::FETCH_ASSOC);
        if(!$user) return [];
        
        $this->fullUserInfo($user);

        return is_null($field) ? $user : $user[$field];
    }

    /**
     * get user status
     * 
     */
    public function getUserBaseInfoById(int $uid, ?string $field = null)
    {
        $dao = $this->mysqlDao->table("member")
                    ->select(["*"])
                    ->where(["id"])
                    ->prepare()
                    ->execute([$uid]);

        $user = $dao->fetch(\PDO::FETCH_ASSOC);
        $this->fullUserInfo($user);

        return is_null($field) ? $user : $user[$field];
    }

    /**
     * full user array 
     * 
     */
    public function fullUserInfo(&$user) : void
    {  
        $user["levelName"] = $this->getLevelRule($user["level"], "name");
        $user["create_time"] = date("Y-m-d", $user["create_time"]);
        $user["last_login_time"] = date("Y-m-d H:i:s", $user["last_login_time"]);
    }

    /**
     * set a default gravatar for user
     * 
     */
    public function setDefaultAvatar(int $uid, ?string $hashString = null) : void
    {
        if( is_null($hashString) ) {
            $hashString = hash("sha256", $uid);
        }

        $avatarPath = ATTACH_PATH . "avatar" . DS . chunk_split(sprintf("%08s", $uid), 2, DS);
        if( !file_exists($avatarPath) ) {
            mkdir($avatarPath, 0777, true);
        }

        try {
            $retry = true;
            $Client = new GuzzleClient([
                "base_uri" => "http://www.gravatar.com/avatar/" . $hashString,
                "time_out" => 6.0,
            ]);

            GRAVATAR_RETRY:
            $promises = [
                "big" => $Client->getAsync("?s=200&d=identicon"),
            ];
            $results = unwrap($promises); 
            $response = $results["big"];

            if($response->getStatusCode() == 200 && $response->getHeader('Content-Length') > 1024) {
                $avatarContent = $response->getBody()->getContents();
                $avatarFile = $avatarPath . "avatar.png";
                file_put_contents($avatarFile, $avatarContent);
            } else if( $retry ) {
                $retry = false;
                goto GRAVATAR_RETRY;
            }   
        } catch( RequestException $exception ) {
            EatWhatLog::logging((string)$exception, [
                "request_id" => $this->request->getRequestId(),
            ], "file", "gravatar.log");
        }
    }

    /**
     * get user avatar
     * 
     */
    public function getUserAvatar(int $uid, bool $withHost = true) : string
    {
        $path = "attachment/avatar/" . chunk_split(sprintf("%08s", $uid), 2, "/") . "avatar.png";
        return ($withHost ? AppConfig::get("protocol", "global") . AppConfig::get("server_name", "global") . "/" : "/") . $path;
    }

    /**
     * modify user mobile
     * 
     */
    public function updateUserMobile(string $newMobile) : bool
    {
        $values = [$newMobile, $this->userData["mobile"]];

        $this->mysqlDao->table("member")
             ->update(["mobile"])
             ->where(["mobile"])
             ->prepare()
             ->execute($values);

        return $this->mysqlDao->execResult;
    }

    /**
     * update user base info
     * 
     */
    public function updateUserBaseInfo(array $baseInfo, int $uid) : bool 
    {
        $dao = $this->mysqlDao->table("member")
                         ->update(array_keys($baseInfo))
                         ->where(["id"])
                         ->prepare();

        array_push($baseInfo, $uid);
        $dao->execute($baseInfo);

        return $this->mysqlDao->execResult;
    }

    /**
     * set user relation
     * 
     */
    public function setUserRelation(int $uid, int $lastUid)
    {
        // user relation
        $this->insertOneObject([
            "uid" => $uid,
            "related_uid" => $lastUid,
            "level" => 1,
        ], "user_relation");

        if($last2User = $this->getLastUser($lastUid)) {
            $this->insertOneObject([
                "uid" => $uid,
                "related_uid" => $last2User["id"],
                "level" => 2,
            ], "user_relation");
        }
    }

    /**
     * get all distributors of user
     * 
     */
    public function _getAllDistributors(int $uid, int $page, int $num, int $level = 1) : array
    {
        $statment = $this->mysqlDao->table("user_relation")->select("member.*, relation.uid, relation.related_uid, mcount.consume_money")->alias("relation")
                         ->leftJoin("member", "member")->on("member.id = relation.uid")
                         ->leftJoin("member_count", "mcount")->on("member.id = mcount.uid")
                         ->where(["relation.related_uid", "relation.level"])
                         ->orderBy(["relation.id" => -1])
                         ->limit($page, $num)
                         ->prepare()
                         ->execute([$uid, $level]);

        $distributors = $statment->fetchAll(\PDO::FETCH_ASSOC);

        foreach($distributors as &$_p_d) {
            $_p_d["levelName"] = $this->getLevelRule($_p_d["level"], "name");
            $_p_d["avatar"] = $this->getUserAvatar($_p_d["uid"]);
            $_p_d["create_time"] = date("Y-m-d", $_p_d["create_time"]);
        }

        return $distributors;
    }

    /**
     * get distributors count
     * 
     */
    public function getLastLevelUser(int $uid) : array
    {
        $statment = $this->mysqlDao->table("user_relation")->select("member.*,relation.level as relation_level")->alias("relation")
                         ->leftJoin("member", "member")->on("member.id = relation.related_uid")
                         ->where(["relation.uid"])
                         ->prepare()
                         ->execute([$uid]);
        $lastLevelUsers = $statment->fetchAll(\PDO::FETCH_ASSOC);

        foreach($lastLevelUsers as &$_p_d) {
            $_p_d["levelName"] = $this->getLevelRule($_p_d["level"], "name");
            $_p_d["avatar"] = $this->getUserAvatar($_p_d["id"]);
            $_p_d["create_time"] = date("Y-m-d", $_p_d["create_time"]);
        }

        return $lastLevelUsers;
    }

    /**
     * get distributors count
     * 
     */
    public function getDistributorsCount() : array
    {
        $executeSql = "select count(*) as count from shop_user_relation as relation left join shop_member member on member.id = relation.uid where relation.related_uid = " . $this->uid . " and relation.level = %d";

        $level_1_count = ($this->mysqlDao->setExecuteSql(sprintf($executeSql, 1))->prepare()->execute([], ["fetch", \PDO::FETCH_ASSOC]))["count"];
        $level_2_count = ($this->mysqlDao->setExecuteSql(sprintf($executeSql, 2))->prepare()->execute([], ["fetch", \PDO::FETCH_ASSOC]))["count"];

        return compact("level_1_count", "level_2_count");
    }

    /**
     * add a shipping address
     * 
     */
    public function _addAddress(array $address) : int
    {
        $this->mysqlDao->table("address")
             ->insert(array_keys($address))
             ->prepare()
             ->execute(array_values($address));
        
        return (int)$this->mysqlDao->getLastInsertId();
    }

    /**
     * get address info
     * 
     */
    public function getAddressInfo(int $addressId) 
    {
        $address = $this->mysqlDao->table("address")
                        ->select(["SQL_CACHE *"])->where(["id"])
                        ->prepare()->execute([$addressId], ["fetch", \PDO::FETCH_ASSOC]);

        return $address;
    }

    /**
     * get user address count
     * 
     */
    public function getAddressCount(int $uid) : int
    {
        $statment = $this->mysqlDao->table("address")
                         ->select("COUNT(*) as count")
                         ->where(["id"])
                         ->prepare()
                         ->execute([$uid]);
        $count = $statment->fetch(\PDO::FETCH_ASSOC);
        
        return $count ? $count["count"] : 0;
    }

    /**
     * delete user shipping address
     * 
     */
    public function _deleteAddress(array $addressIds) : bool
    {
        $sql = "DELETE FROM shop_address WHERE id in (" . implode(",", $addressIds) . ")";
        $this->mysqlDao->setExecuteSql($sql)->prepare()->execute();
        
        return $this->mysqlDao->execResult;
    }

    /**
     * set a user's default address to not
     * 
     */
    public function setDefaultAddressToNot(int $uid) : bool
    {
        $this->mysqlDao->table("address")
             ->update(["isdefault"])
             ->where(["uid", "isdefault"])
             ->prepare()
             ->execute([0, $uid, 1]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * Set to the default address
     * 
     */
    public function _setToDefaultAddress(int $addressId) : bool
    {
        $this->mysqlDao->table("address")
             ->update(["isdefault"])
             ->where(["id"])
             ->prepare()
             ->execute([1, $addressId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * get user address
     * 
     */
    public function getUserAddress(int $uid, bool $default = false)
    {
        $where = ["uid"];
        $value = [$uid];
        $default && ($where[] = "isdefault") && ($value[] = 1);

        $statment = $this->mysqlDao->table("address")->select("*")->where($where)->orderBy(["id" => -1])->prepare()->execute($value);
        $addresses = $statment->fetchAll(\PDO::FETCH_ASSOC);

        foreach($addresses as &$_p_a) {
            $_p_a["create_time"] = date("Y-m-d", $_p_a["create_time"]);
        }

        return $addresses;
    }

    /**
     * edit user address
     * 
     */
    public function editUserAddress(int $addressId, array $address) : bool
    {
        $dao = $this->mysqlDao->table("address")
                    ->update(array_keys($address))
                    ->where(["id"])
                    ->prepare();

        $address["id"] = $addressId;
        $dao->execute(array_values($address));
        
        return $this->mysqlDao->execResult;
    }

    /**
     * get user count
     * 
     */
    public function getUserCount(int $uid, ?string $field = null)
    {
        $memberCount = $this->mysqlDao->table("member_count")
                            ->select(["*"])->where(["uid"])
                            ->prepare()->execute([$uid], ["fetch", \PDO::FETCH_ASSOC]);
        
        if(!is_null($field)) {
            if(is_array($field)) {
                $return = [];
                foreach($field as $f) {
                    $return[$f] = $memberCount[$f];
                }
                return $return;
            } else {
                return $memberCount[$field];
            }
        }

        return $memberCount;
    }

    /**
     * get user undeposit count
     * 
     */
    public function getUserUndepositCount(int $uid) : float
    {
        $count = $this->mysqlDao->setExecuteSql("select if(isnull(sum(amount)), 0, sum(amount)) as count from shop_member_log_undeposit where uid = ? and status = ?")->prepare()->execute([$uid, 1], ["fetch", \PDO::FETCH_ASSOC]);
        
        return $count["count"];
    }

    /**
     * update user credit
     * 
     */
    public function updateUserCount(int $uid, string $filed, $count) : bool
    {
        $sql = "update shop_member_count set $filed = $filed " . ($count < 0 ? "-" : "+") . " ? where uid = ?";
        
        $count = abs($count);
        if(in_array(gettype($count), ["float", "double"])) {
            $count = (string)$count;
        }

        $this->mysqlDao->setExecuteSql($sql)->prepare()->execute([$count, $uid]);

        return $this->mysqlDao->execResult;
    }

    /**
     * check user level up
     * 
     */
    public function checkUserLevelUp(int $uid) : bool
    {
        $execSql = "update shop_member as m left join shop_member_count as mc on m.id = mc.uid set level = (case ";

        $levelRules = (array)$this->getSetting("userLevelRules");
        foreach( array_reverse($levelRules) as $rule ) {
            $execSql .= "when mc.consume_money >= " . $rule["consume_money"] . " then " . $rule["level"] . " ";
        }
        $execSql .= " end) where uid = ?";
        
        $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute([$uid]);

        return $this->mysqlDao->execResult;
    }

    /**
     * get user money-return logs
     * 
     */
    public function getMoneyReturnLog(int $uid, int $page = 1, int $size = 10) : array
    {
        $logs = [];

        $logs = $this->mysqlDao->table("member_log_return")
                ->select(["log.*", "_order.order_no"])->alias("log")
                ->leftJoin("order", "_order")->on("log.order_id = _order.id")
                ->where(["log.uid"])
                ->orderBy(["id" => -1])->limit($page, $size)
                ->prepare()->execute([$uid], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        foreach($logs as &$log) {
            $log["log_time"] = date("Y-m-d H:i:s", $log["log_time"]);
        }

        return $logs;
    }

    /**
     * get user undeposit logs
     * 
     */
    public function getUndepositLog(array $filters, int $page = 1, int $size = 10) : array
    {
        $logs = $binds = $where = [];

        if(isset($filters["uid"]) && $filters["uid"]) {
            $binds[] = (int)$filters["uid"];
            $where[] = "log.uid";
        }

        if(isset($filters["status"])) {
            $binds[] = (int)$filters["status"];
            $where[] = "log.status";
        }

        $logs = $this->mysqlDao->table("member_log_undeposit")
                ->select(["log.*", "account.account", "account.type", "member.username"])->alias("log")
                ->leftJoin("member_account", "account")->on("log.account_id = account.id")
                ->leftJoin("member", "member")->on("log.uid = member.id")
                ->where($where)
                ->orderBy(["log.id" => -1])->limit($page, $size)
                ->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);
        
        $undepositLogStatus = AppConfig::get("undeposit_log_status");
        foreach($logs as &$log) {
            $log["log_time"] = date("Y-m-d H:i:s", $log["log_time"]);
            $log["status"] = $undepositLogStatus[$log["status"]];
        }

        return $logs;
    }

    /**
     * get user property log
     * 
     */
    public function getPropertyLog(int $uid, int $page = 1, int $size = 10) : array
    {
        $logs = [];

        $logs = $this->mysqlDao->table("member_log_property")
                ->select(["*"])
                ->where(["uid"])
                ->orderBy(["id" => -1])->limit($page, $size)
                ->prepare()->execute([$uid], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        foreach($logs as &$log) {
            $log["log_time"] = date("Y-m-d H:i:s", $log["log_time"]);
        }

        return $logs;
    }

    /**
     * get user account info
     * 
     */
    public function getAccountInfo(int $accountId) 
    {
        $account = $this->mysqlDao->table("member_account")
                        ->select(["*"])->where(["id"])
                        ->prepare()->execute([$accountId], ["fetch", \PDO::FETCH_ASSOC]);
        
        return $account;
    }

    /**
     * get uses account list
     * 
     */
    public function getUserAccountList(int $uid)
    {
        $accountList = $this->mysqlDao->table("member_account")
                        ->select(["*"])->where(["uid"])
                        ->prepare()->execute([$uid], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        foreach($accountList as &$account) {
            $account["bind_time"] = date("Y-m-d H:i:s", $account["bind_time"]);
        }
        
        return $accountList;
    }

    /**
     * get user level discount ratio
     * @param int uid
     * 
     */
    public function getUserLevelDiscountRatio(int $uid) : float
    {
        $level = $this->getUserBaseInfoById($uid, "level");
        return $this->getLevelRule($level, "discount");
    }

    /**
     * delete user account
     * 
     */
    public function deleteUserAccount(int $accountId) : bool
    {
        $this->mysqlDao->table("member_account")
                ->delete()->where(["id"])
                ->prepare()->execute([$accountId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * user begin property financing
     * 
     */
    public function userBeginPropertyFinancing(int $uid, int $period) : bool
    {
        $startTimeStamp = EatWhatStatic::getPeriodTimestamp(1, "add") - 1;
        $endTimeStamp = EatWhatStatic::getPeriodTimestamp($period + 1, "add") - 1;

        $dao = $this->mysqlDao->table("member_count")
            ->update(["property_financing", "property_financing_start", "property_financing_expire", "property_financing_remain"]);
        $dao->executeSql .= ", property_financing_basemoney = property";
       
        $dao->where(["uid"])
            ->prepare()->execute([1, $startTimeStamp, $endTimeStamp, $period, $uid]);

        return $this->mysqlDao->execResult;
    }

    /**
     * get user financing info
     * 
     */
    public function getUserFinancingInfo(int $uid) 
    {
        $financingInfo = $this->mysqlDao->table("member_count")
                         ->select(["property_financing_income", "property_financing", "property_financing_start", "property_financing_remain", "property_financing_expire"])
                         ->where(["uid"])
                         ->prepare()->execute([$uid], ["fetch", \PDO::FETCH_ASSOC]);

        if(!$financingInfo["property_financing"]) {
            return false;
        }

        $financingInfo["property_financing_ratio"] = $this->getSetting("propertyFinancingRatio");
        foreach (["property_financing_start", "property_financing_expire"] as $option) {
            $financingInfo[$option] = date("Y-m-d H:i:s", $financingInfo[$option]);
        }

        return $financingInfo;
    }

    /**
     * get user property log
     * 
     */
    public function getUserMessages(int $uid, int $page = 1, int $size = 10) : array
    {
        $messages = [];

        $messages = $this->mysqlDao->table("member_message")
                ->select(["*"])
                ->where(["uid"])
                ->orderBy(["id" => -1])->limit($page, $size)
                ->prepare()->execute([$uid], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        foreach($messages as &$message) {
            $message["message_time"] = date("Y-m-d H:i:s", $message["message_time"]);
        }

        return $messages;
    }

    /**
     * mark message read done
     * 
     */
    public function markMessageReadDone(int $messageId) : bool
    {
        $this->mysqlDao->table("member_message")
                ->update(["status"])
                ->where(["id"])
                ->prepare()->execute([1, $messageId]);

        return $this->mysqlDao->execResult;
    }

    /**
     * get user financing info
     * 
     */
    public function getLogBaseInfo(int $logId, string $logTable) : array
    {
        $selector = ["uid", "id", "amount", "log_time"];
        ($logTable == "member_log_undeposit") && ($selector[] = "status");
            
        $logInfo = $this->mysqlDao->table($logTable)
                        ->select($selector)->where(["id"])
                        ->prepare()->execute([$logId], ["fetch", \PDO::FETCH_ASSOC]);

        return $logInfo;
    }

    /**
     * update user financing data when expire
     *
     */
    public function financingExpireReset(int $uid) : bool 
    {
        $this->mysqlDao->table("member_count")->update([
            "property_financing", "property_financing_start", "property_financing_expire", "property_financing_remain", "property_financing_basemoney"
        ])->prepare()->execute([0, 0, 0, 0, 0]);

        return $this->mysqlDao->execResult;
    }

    /**
     * user financing income processor for crontab task
     * 
     */
    public function processUserFinancingIncome() : string
    {
        $taskLogs = date("Y-m-d") . " | process begin at " . date("Y-m-d H:i:s") . PHP_EOL;

        $taskBeginTime = time();
        bcscale($this->getSetting("decimalPlaces"));
        $this->mysqlDao->exceptionNotInterrupt = true;

        $financingUsers = $this->mysqlDao->table("member_count")
                            ->select(["uid", "property_financing_income", "property_financing", "property_financing_start", "property_financing_remain", "property_financing_expire", "property_financing_ratio", "property_financing_basemoney"])
                            ->where(["property_financing"])
                            ->prepare()->execute([1]);

        while( $user = $financingUsers->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT) ) {
            $uid = $user["uid"];
            $this->beginTransaction();

            if( $taskBeginTime >= $user["property_financing_expire"] ) {
                $this->financingExpireReset($uid);
                $this->rollback();
                continue;
            }

            if( $taskBeginTime >= $user["property_financing_start"] ) {
                $propertyDayIncome = bcmul($user["property_financing_basemoney"], $this->getSetting("propertyFinancingRatio"));

                $this->mysqlDao->setExecuteSql("update shop_member_count set property = property + ?, property_financing_income = property_financing_income + ?, property_financing_remain = property_financing_remain - ? where uid = ?")
                               ->prepare()->execute([$propertyDayIncome, $propertyDayIncome, 1, $uid]);

                $propertyLogId = $this->insertOneObject([
                    "uid" => $uid,
                    "amount" => $propertyDayIncome,
                    "log_time" => time(),
                    "description" => AppConfig::get("financingIncome", "lang"),
                ], "member_log_property");

                if($this->getUserCount($uid, "property_financing_remain") == 0) {
                    $this->financingExpireReset($uid);
                }

                $taskLogs .= "Process Uid: $uid,  Process Time: " . date("Y-m-d H:i:s") . ",  Process Status: ";
                if($this->mysqlDao->errorBefore) {
                    $this->rollback();
                    $this->mysqlDao->errorBefore = false;
                    $taskLogs .= "Faild" . PHP_EOL;
                } else {
                    $this->commit();
                    $taskLogs .= "Success" . PHP_EOL;
                }
            }

            $this->rollback();
        }

        return $taskLogs . PHP_EOL . PHP_EOL;
    }

    /**
     * get last user info
     *
     */
    public function getLastUser(int $uid) : array
    {
        $user = $this->mysqlDao->table("member")->select(["member_addition.*"])->alias("member")
                    ->leftJoin("member", "member_addition")->on("member.lastUid = member_addition.id")
                    ->where(["member.id"])->prepare()->execute([$uid], ["fetch", \PDO::FETCH_ASSOC]);

        if( $user["id"] ) {
            $this->fullUserInfo($user);
            return $user;
        }

        return [];
    }

    /**
     * update last user return money after order paid
     * 订单完成后，计算并更新上级用户的佣金收入
     * 
     */
    public function updateLastUserReturnAfterPaied(int $uid, string $calculateMoney, int $orderId) : void
    {   
        $this->lastUserReturnProcess($uid, $calculateMoney, $orderId, 1);
    }

    /**
     * process last user return
     *
     */
    public function lastUserReturnProcess(int $uid, string $calculateMoney, int $orderId, int $rank) : void
    {
        bcscale($this->getSetting("decimalPlaces"));

        $lastUser = $this->getLastUser($uid);
        if( $lastUser ) {
            $rankName = ["asFirst", "asSecond"][$rank - 1];
            $returnRatio = $this->getLevelRule($lastUser["level"], $rankName, "userLevelReturnRatio");
            $returnMoney = bcmul($calculateMoney, $returnRatio);

            $this->userNewReturnRecord($lastUser["id"], $returnMoney, $orderId);

            if(++$rank > 2) {
                return;
            }
            $this->lastUserReturnProcess($lastUser["id"], $calculateMoney, $orderId, $rank);
        }
    }    

    /**
     * user has a new money-return record
     * 用户有一条新的佣金记录
     * 
     */
    public function userNewReturnRecord(int $uid, string $returnMoney, int $orderId) : bool
    {
        if( !$this->mysqlDao->hasTransaction ) {
            $commit = true;
            $this->mysqlDao->beginTransaction();
        } else {
            $commit = false;
        }

        $this->updateUserCount($uid, "return_money", $returnMoney);
        $this->updateUserCount($uid, "property", $returnMoney);

        $returnLogId = $this->insertOneObject([
            "uid" => $uid,
            "amount" => $returnMoney,
            "log_time" => time(),
            "order_id" => $orderId,
        ], "member_log_return");

        $propertyLogId = $this->insertOneObject([
            "uid" => $uid,
            "amount" => $returnMoney,
            "log_time" => time(),
            "description" => AppConfig::get("orderMoneyReturn", "lang"),
            "extra" => $orderId,
        ], "member_log_property");

        $commit && ($this->mysqlDao->commit());

        return $this->mysqlDao->execResult;
    }

    /**
     * rollback last-user return record in case of money-return
     * 订单退款后，回滚上级用户的佣金和记录
     * 
     */
    public function rollbackUserReturn(int $orderId) : bool
    {
        $logs = $this->mysqlDao->table("member_log_return")
                ->select(["*"])->where(["order_id"])
                ->prepare()->execute([$orderId]);

        if(!$logs) return false;

        if( !$this->mysqlDao->hasTransaction ) {
            $commit = true;
            $this->mysqlDao->beginTransaction();
        } else {
            $commit = false;
        }

        foreach($logs as $log) {
            $this->updateUserCount($log["uid"], "return_money", -$log["amount"]);
            $this->updateUserCount($log["uid"], "property", -$log["amount"]);

            $this->mysqlDao->table("member_log_return")->delete()->where(["id"])->prepare()->execute([$log["id"]]);
            $this->mysqlDao->table("member_log_property")->delete()->where(["uid", "extra"])->prepare()->execute([$log["uid"], $orderId]);
        }

        $commit && ($this->mysqlDao->commit());

        return $this->mysqlDao->execResult;
    }

    /**
     * get member list
     * 
     */
    public function getMemberList(array $filters, bool $count = false) : array
    {
        $binds = [];
        $selector = "id";
        $page = $filters["page"] ?? 1;
        $size = $filters["size"] ?? 10;

        $baseSql = "SELECT %s FROM shop_member WHERE 1 = 1 and";

        foreach( ["status", "id", "level", "isDistributor"] as $option ) {
            if(isset($filters[$option])) {
                $baseSql .= " $option = ? and";
                $binds[] = (int)$filters[$option];
            }
        }

        if(isset($filters["period"])) {
            $timestamp = EatWhatStatic::getPeriodTimestamp((int)$filters["period"]);
            $baseSql .= " create_time > ? and";
            $binds[] = $timestamp;
        }

        if(isset($filters["mobile"])) {
            $baseSql .= " mobile = ? and";
            $binds[] = $filters["mobile"];
        }

        if(isset($filters["namekey"]) && $filters["namekey"] != "") {
            $baseSql .= " username like ? and";
            $binds[] = "%%" . $filters["namekey"] . "%%";
        }

        if(!isset($filters["sort"]) || !$filters["sort"]) {
            $filters["sort"] = "id_desc";
        }

        $baseSql = substr($baseSql, 0, -4);
        if( $count ) {
            $countSql = sprintf($baseSql, "count(*) as count");
            $count = ($this->mysqlDao->setExecuteSql($countSql)->prepare()->execute($binds, ["fetch", \PDO::FETCH_ASSOC]))["count"];
        }
        
        $baseSql .= " order by " . preg_replace("/_(desc|asc)$/i", " $1", $filters["sort"]);
        $baseSql .= " limit " . $size * ($page - 1) . "," . $size;
        $baseSql = sprintf($baseSql, $selector);
        $execSql = "select member_primary.*,member_addition.username as LastUsername,mc.property,mc.consume_money from shop_member as member_primary inner join ($baseSql) as member_second using(id) left join shop_member as member_addition on member_primary.lastUid = member_addition.id left join shop_member_count as mc on member_primary.id = mc.uid";

        $members = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach( $members as &$member ) {
            $member["create_time"] = date("Y-m-d", $member["create_time"]);
            $member["last_login_time"] = date("Y-m-d", $member["last_login_time"]);
            $member["level_name"] = $this->getLevelRule($member["level"], "name");
            $member["status"] = AppConfig::get($member["status"] >= 0 ? "normal" : "abnormal", "lang");
            $member["avatar"] = $this->getUserAvatar($member["id"]);
        }

        return compact("members", "count");
    }

    /**
     * update member level
     *
     */
    public function updateMemberLevel(int $uid, int $level) : bool
    {
        $this->mysqlDao->table("member")
            ->update(["level"])->where(["id"])
            ->prepare()->execute([$level, $uid]);

        return $this->mysqlDao->execResult;
    }

    /**
     * update member info
     *
     */
    public function updateMemberField(int $uid, string $field, $value) : bool
    {
        $this->mysqlDao->table("member")
            ->update([$field])->where(["id"])
            ->prepare()->execute([$value, $uid]);

        return $this->mysqlDao->execResult;
    }
}