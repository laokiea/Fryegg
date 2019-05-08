<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;
use FileUpload\Validator\Simple as ValidatorSimple;
use FileUpload\PathResolver\Simple as PathResolver;
use FileUpload\FileSystem\Simple as FileSystem;
use FileUpload\FileNameGenerator\Custom as FileNameCustom;
use FileUpload\FileUploadFactory;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Common trait
 * 
 */
trait CommonTrait
{
        
    /**
     * set user info
     * 
     */
    public function setUserLogin(array $userData) : void
    {
        $accessToken = $this->request->getAccessTokenAnalyzer()->generate($userData);
        $requestUserController =  $this->request->getUserController();
        $requestUserController->setUserData($userData);
        $requestUserController->setAccessToken($accessToken);
    }

    /**
     * log out
     * 
     */
    public function _logout() : void
    {
        $this->request->getUserController()->logout();
        $this->generateStatusResult("logoutSuccess", 1);
        $this->outputResult();
    }

    /**
     * get all category
     * 
     */
    public function _getAllCategory()
    {
        $categorys = $this->redis->get("all_categorys");
        if(!$categorys) {
            $categorys = $this->mysqlDao->table("good_category")
                             ->select(["id", "name"])
                             ->orderBy(["id" => -1])
                             ->prepare()
                             ->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);
            $this->redis->set("all_categorys", $categorys, 30 * 3600 * 24);
        }
        
        return $categorys;
    }

    /**
     * get category name
     * 
     */
    public function getCategoryNameById(int $categoryId) : string
    {
        $categorys = $this->_getAllCategory();

        if(!empty($categorys)) {
            foreach($categorys as $_c) {
                if($_c["id"] == $categoryId) 
                    return $_c["name"];
            }
        }
        return "";
    }

    /**
     * get all attributes
     * 
     */
    public function _getAllAttributes() 
    {
        $assocAttributes = $this->redis->get("all_attributes");

        if(!$assocAttributes) {
            $attributes = $this->mysqlDao->table("attribute")
                          ->select(["id", "name"])
                          ->orderBy(["id" => -1])
                          ->prepare()
                          ->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);
            $assocAttributes = [];
            foreach($attributes as $p_attribute) {
                $assocAttributes[$p_attribute["id"]] = $p_attribute;
            }
            $this->redis->set("all_attributes", $assocAttributes, 30 * 3600 * 24);
        }
        return $assocAttributes;
    }

    /**
     * get all attributes
     * 
     */
    public function _getAllAttributesValue() 
    {
        $assocAttributesValue = $this->redis->get("all_attributes_value");

        if(!$assocAttributesValue) {
            $attributes_value = $this->mysqlDao->table("attribute_value")
                          ->select(["attr_id", "id", "name"])
                          ->orderBy(["id" => -1])
                          ->prepare()
                          ->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);
            $assocAttributesValue = [];
            foreach($attributes_value as $p_attribute_value) {
                $assocAttributesValue[$p_attribute_value["id"]] = $p_attribute_value;
            }
            $this->redis->set("all_attributes_value", $assocAttributesValue, 30 * 3600 * 24);
        }
        return $assocAttributesValue;
    }

    /**
     * get attribute's values
     * 
     */
    public function _getAttributeValue(int $attrId)
    {
        $accocValue = [];
        $values = $this->mysqlDao->table("attribute_value")
                                ->select(["id", "attr_id", "name"])
                                ->where(["attr_id"])
                                ->orderBy(["id" => -1])
                                ->prepare()
                                ->execute([$attrId], ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach($values as $p_value) {
            $accocValue[$p_value["id"]] = $p_value;
        }
        
        return $accocValue;
    }

    /**
     * get all attribute and attribute value
     * 
     */
    public function getAllAttributesWithValue() : array
    {
        $assocAttributesWithValue = $this->redis->get("all_attributes_with_value");

        if(!$assocAttributesWithValue) {
            $attributes = $this->_getAllAttributes();
            foreach($attributes as &$attribute) {
                $attribute["values"] = $this->_getAttributeValue($attribute["id"]);
            }

            $assocAttributesWithValue = $attributes;
            $this->redis->set("all_attributes_with_value", $assocAttributesWithValue, 30 * 3600 * 24);
        }

        return $assocAttributesWithValue;
    }

    /**
     * check mobile format
     * 
     */
    public function checkMobileFormat(string $mobile) : bool
    {
        return EatWhatStatic::checkMobileFormat($mobile);
    }

    /**
     * check url format
     * 
     */
    public function checkUrlFormat(string $url) : bool
    {
        return EatWhatStatic::checkUrlFormat($url);
    }

    /**
     * upload file
     * 
     */
    public function uploadFile(array $parameters, bool $isEdit = false) : bool
    {
        $uploadError = false;

        if(!$isEdit && !file_exists($parameters["path"])) {
            mkdir($parameters["path"], 0777, true);
        }

        if( $isEdit ) {
            // $files = array_slice(scandir($parameters["path"]), 2);
            // foreach($files as $file) {
            //    $result = unlink($parameters["path"] . DS . $file);
            // }
            if(is_string($parameters["filename"]) && file_exists($target = $parameters["path"] . $parameters["filename"])) {
                unlink($target);   
            }
        }

        $factory = new FileUploadFactory(
            new PathResolver($parameters["path"]),
            new FileSystem(), [
                new ValidatorSimple($parameters["size"] . "M", ["image/png", "image/jpg", "image/jpeg"]),
            ]
        );
        $fileUpload = $factory->create($_FILES[$parameters["uploadname"]], $_SERVER);
        
        $customGenerator = new FileNameCustom($parameters["filename"]);
        $fileUpload->setFileNameGenerator($customGenerator);

        list($files, $headers) = $fileUpload->processAll();

        foreach($files as $file) {
            if( !$file->completed ) {
                $uploadError = true;
                !$isEdit && (EatWhatStatic::rrmdir($parameters["path"]));
                EatWhatLog::logging($file->error, ["request_id" => $this->request->getRequestId()], "file", "uploadFile.log");
            }
        }

        return !$uploadError;
    }

    /**
     * check password is correct
     * 
     */
    public function checkPassword(string $password) : bool
    {
        return boolval(preg_match("/^[\P{Han}^\s]{6,}$/ui", $password));
    }

    /**
     * resize for thumbnail
     * 
     */
    public function resizeImage(array $resizeParameters) : void 
    {
        $driver = extension_loaded('imagick') && class_exists('Imagick') ? "imagick" : "gd";
        Image::configure(array('driver' => $driver));

        $thumb = Image::make($resizeParameters["source"]);
        $thumb->resize($resizeParameters["width"] ?? 330, $resizeParameters["height"] ?? 330)->save($resizeParameters["target"], 100);
    }

    /**
     * check banner limit
     *
     */
    public function checkBannerLimit(string $tableName, ?string $tablePrefix = null, ?string $type = null) : bool
    {
        $count = $this->mysqlDao->table($tableName, $tablePrefix)->select(["count(*) as count"]);

        if(!is_null($type)) {
            $count = $count->where(["type"]);
        }

        $count = ($count->prepare()->execute($type ? [$type] : [], ["fetch", \PDO::FETCH_ASSOC]))["count"];

        if($count == $this->getSetting("bannerCountLimit")) {
            return false;
        }

        return true;
    }

     /**
     * get banner list
     * 
     */
    public function getBannerList(array $filters, bool $count = true) : array
    {
        $binds = [];
        $banners = [];

        $baseSql = "SELECT %s FROM shop_banner WHERE 1 = 1 and";
        $page = 1;
        $size = $filters["size"] ?? $this->getSetting("bannerCountLimit");
        
        if(!isset($filters["status"])) {
            $filters["status"] = 1;
        }

        $baseSql .= " status >= ? and";
        $binds[] = (int)$filters["status"];

        $baseSql = substr($baseSql, 0, -4);

        if( $count ) {
            $countSql = sprintf($baseSql, "count(*) as count");
            $count = ($this->mysqlDao->setExecuteSql($countSql)->prepare()->execute($binds, ["fetch", \PDO::FETCH_ASSOC]))["count"];
        }

        !isset($filters["sort"]) && ($filters["sort"] = "id_desc");
        $baseSql .= " order by " . preg_replace("/_(desc|asc)$/i", " $1", $filters["sort"]) . " limit " .  ($page - 1) * $size . "," . $size;
        $baseSql = sprintf($baseSql, "*");
        
        $banners = $this->mysqlDao->setExecuteSql($baseSql)->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);
        foreach($banners as &$banner) {
            $bannerImage = $this->getBannerImage($banner["id"]);
            $banner["image"] = $bannerImage["banner"];
            $banner["thumbnail"] = $bannerImage["thumb"];
            $banner["create_time"] = date("Y-m-d", $banner["create_time"]);
            $banner["update_time"] = date("Y-m-d", $banner["update_time"]);
            
            if($banner["link_type"] == "good_id") {
                $banner["good_name"] = $this->getGoodBase($banner["link_value"], "name");
            }
        }

        return compact("banners", "count");
    }

    /**
     * get banner thumbnail
     * 
     */
    public function getBannerImage(int $bannerId, bool $withHost = true) : array
    {
        $path = ATTACH_PATH . "banner_image" . DS . chunk_split(sprintf("%06s", $bannerId), 2, DS);
        foreach(["banner", "thumb"] as $filename) {
            ${$filename} = ($withHost ? AppConfig::get("protocol", "global") . AppConfig::get("server_name", "global") . "/" : "/") . "attachment/banner_image/" . chunk_split(sprintf("%06s", $bannerId), 2, "/") . $filename . ".png";
        }

        return compact("banner", "thumb");
    }

    /**
     * common way to insert a object to mysql
     * 
     */
    public function insertOneObject(array $object, string $tableName, ?string $tablePrefix = null, $upsert = false) : int 
    {
        $object = $this->float2string($object);

        $this->mysqlDao->table($tableName, $tablePrefix)->insert(array_keys($object))
             ->prepare()
             ->execute(array_values($object));
        
        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * common way to update a object to mysql by id field
     * 
     */
    public function updateOneObjectById(int $objectId, array $object, string $tableName, ?string $tablePrefix = null) : bool 
    {
        $object = $this->float2string($object);

        $dao = $this->mysqlDao->table($tableName, $tablePrefix)->update(array_keys($object))->where(["id"])->prepare();
        array_push($object, $objectId);
        $dao->execute(array_values($object));

        return $this->mysqlDao->execResult;
    }

    /**
     * common way to delete a object to mysql by id field
     * 
     */
    public function deleteOneObjectById(int $objectId, string $tableName, ?string $tablePrefix = null) : bool 
    {
        $this->mysqlDao->table($tableName, $tablePrefix)
            ->delete()->where(["id"])
            ->prepare()->execute([$objectId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * set attribute of object to string if type is float or double 
     * 
     */
    public function float2string(array $object) : array 
    {
        $object = array_map(function($v){
            if(in_array(gettype($v), ["double", "float"])) {
                return (string)$v;
            }
            return $v;
        }, $object);

        return $object;
    }

    /**
     * get user's increment credit after order complete
     * 
     */
    public function getOrderIncrCredit(string $totalMoney) : int
    {
        return (int)bcdiv($totalMoney, $this->getSetting("creditToMoneyRatio"), 0);
    }

    /**
     * get level name
     * 
     */
    public function getLevelRule(int $level, ?string $prop = null, string $key = "userLevelRules")
    {
        $userLevelRules = (array)$this->getSetting($key);
        foreach($userLevelRules as $rule) {
            if($rule["level"] == $level) {
                if(!is_null($prop)) {
                    return $rule[$prop];
                } else {
                    return $rule;
                }
            }
        }
    }

    /**
     * send sms
     * 
     */
    public function sendSms(array $parameters) : bool
    {
        require_once SDK_PATH . 'aliyun-dysms-php-sdk/api_sdk/vendor/autoload.php'; 
        \Aliyun\Core\Config::load();

        //产品名称:云通信短信服务API产品,开发者无需替换
        $product = "Dysmsapi";
        //产品域名,开发者无需替换
        $domain = "dysmsapi.aliyuncs.com";
        // TODO 此处需要替换成开发者自己的AK (https://ak-console.aliyun.com/)
        $accessKeyId = $parameters["accessKey"]; // AccessKeyId
        $accessKeySecret = $parameters["accessSecert"]; // AccessKeySecret
        // 暂时不支持多Region
        $region = "cn-hangzhou";
        // 服务结点
        $endPointName = "cn-hangzhou";

        //初始化acsClient,暂不支持region化
        $profile = \Aliyun\Core\Profile\DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
        // 增加服务结点
        \Aliyun\Core\Profile\DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);
        // 初始化AcsClient用于发起请求
        $client = new \Aliyun\Core\DefaultAcsClient($profile);

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new \Aliyun\Api\Sms\Request\V20170525\SendSmsRequest();

        // 必填，设置短信接收号码
        $request->setPhoneNumbers($parameters["mobile"]);
        // 必填，设置签名名称，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $request->setSignName($parameters["signName"]);
        // 必填，设置模板CODE，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $request->setTemplateCode($parameters["templateCode"]);
        // 可选，设置模板参数, 假如模板中存在变量需要替换则为必填项
        isset($parameters["params"]) && ($request->setTemplateParam(json_encode($parameters["params"], JSON_UNESCAPED_UNICODE)));

        // 发起访问请求
        $acsResponse = $client->getAcsResponse($request);
        if($acsResponse->Code == "OK") {
            return true;
        } else {
            EatWhatLog::logging("Send sms faild", [
                "request_id" => $this->request->getRequestId(),
                "sms_response" => json_encode($acsResponse),
            ], "file", "sms.log");
            return false;
        }
    }

    /**
     * get path via an unique id, for avatar/banner_image/good_image
     *
     */
    public function getPathById(string $dirName, int $id, bool $url = false) : string
    {
        $ds = $url ? "/" : DS;
        $prefix = $url ? AppConfig::get("protocol", "global") . AppConfig::get("server_name", "global") . "/attachment/" : ATTACH_PATH;

        return $prefix . $dirName . $ds . chunk_split(sprintf("%08s", $id), 2, $ds);
    }

    /**
     * get good image path
     *
     */
    public function getGoodImagePath(int $goodId, bool $url = false) : string
    {
        return $this->getPathById("good_image", $goodId, $url);
    }

    /**
     * upload object single image 
     *
     */
    public function uploadImageSingle(string $pathto, string $uploadFileName)
    {
        $newIndex = count(scandir($pathto)) - 2;
        $fileName = hash("sha256", uniqid() . $newIndex) . ".png";

        $uploadResult = $this->uploadFile([
            "path" => $pathto,
            "size" => 10,
            "uploadname" => $uploadFileName,
            "filename" => $fileName,
        ]);

        if( !$uploadResult ) {
            return false;
        } 

        return compact("newIndex", "fileName");
    }

    /**
     * update good thumbnail
     *
     */
    public function updateGoodThumbnail(int $goodId, int $index = 0) : bool
    {
        $goodImagePath = $this->getGoodImagePath($goodId);
        $sourceBanner = (array_slice(scandir($goodImagePath . "banner_images"), 2))[$index];

        $this->resizeImage([
            "source" => $goodImagePath . "banner_images" . DS . $sourceBanner,
            "target" => $goodImagePath . "thumb.png",
        ]);

        return true;
    }

    /**
     * check user has new message
     * @param void
     * 
     */
    public function checkUserHasNewMessage(int $uid) : bool
    {
        $count = $this->mysqlDao->table("member_message")
                    ->select(["count(*) as count"])->where(["status"])
                    ->prepare()->execute([0], ["fetch", \PDO::FETCH_ASSOC]);
        
        return boolval($count["count"]);
    }
}