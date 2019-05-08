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
use Overtrue\Pinyin\Pinyin;

/**
 * Manage Api
 * 
 */
class ManageApi extends ApiBase
{
    use \EatWhat\Traits\ManageTrait,\EatWhat\Traits\CommonTrait,\EatWhat\Traits\GoodTrait;
    use \EatWhat\Traits\OrderTrait;
    use \EatWhat\Traits\UserTrait;

    /**
     * manage login
     * 登录
     * @param void
     * 
     */
    public function login() : void
    {
        $this->checkPost();
        $this->checkParameters(["username" => null, "password" => null]);

        $username = $_GET["username"];
        $user = $this->getManageUserByName($username);

        if(!$user || $user["status"] < 0) {
            $this->generateStatusResult("userStatusAbnormal", -1); 
        }

        if(!password_verify($_GET["password"], $user["password"])) {
            $this->generateStatusResult("userVerifyError", -2);
        }

        $this->setUserLogin([
            "uid" => $user["id"],
            "role" => $user["role"],
            "username" => $user["username"],
            "tokenType" => "manage",
        ]);

        $this->generateStatusResult("loginActionSuccess", 1);
        $this->outputResult();
    }

    /**
     * log out
     * 新增管理员账号
     * @param void
     * 
     */
    public function newManager() : void
    {
        $this->checkPost();
        $this->checkParameters(["username" => null, "password" => null]);

        $role = $_GET["role"] ?? 0;
        $username = $_GET["username"];
        $password = $_GET["password"];
        if( !$this->checkUsername($username) ) {
            $this->generateStatusResult("wrongUsernameFormatOrExists", -4);
        }

        if( !$this->checkPassword($password) ) {
            $this->generateStatusResult("wrongPasswordFormat", -1);
        }

        $newManagerId = $this->insertOneObject([
            "username" => $username,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "role" => $role,
            "create_time" => time(),
        ], "manage_member");

        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "id" => $newManagerId,
        ]);
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
     * set global parameter
     * 设置全局参数
     * json
     * @param void
     * 
     */
    public function setGlobal() : void 
    {
        $this->checkPost();
        $this->checkParameters(["setting" => ["json"]]);

        $setting = $_GET["setting"];

        foreach ($setting as $_setting) {
            $this->setSetting($_setting["key"], $_setting["value"]);
        }
        

        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * set global parameter
     * 设置全局参数
     * json
     * @param void
     * 
     */
    public function getGlobal() : void 
    {
        $this->checkPost();
        if(isset($_GET["key"])) {
            $result = [
                "value" => $this->getSetting($_GET["key"]),
            ];
        } else {
            $result = [];
            $settings = $this->mongodb->setting->find()->toArray();
            foreach($settings as $setting) {
                $result[] = [
                    "key" => $setting["key"],
                    "value" => $setting["value"],
                ];
            }
        }

        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult($result);
    }

    /**
     * edit an category
     * 编辑商品分类
     * @param void
     * 
     */
    public function editCategory() : void
    {
        $this->checkPost();
        $this->checkParameters(["name" => null, "id" => ["int", "nonzero"]]);

        $categoryId = $_GET["id"];
        $categoryName = $_GET["name"];
        if( ($category = $this->getCategoryByName($categoryName)) && $category["id"] != $categoryId) {
            $this->generateStatusResult("categoryExists", -1);
        }

        $this->updateOneObjectById($categoryId, [
            "name" => $categoryName,
        ], "good_category");

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * add an category
     * 新增商品分类
     * @param void
     * 
     */
    public function addCategory() : void
    {
        $this->checkPost();
        $this->checkParameters(["name" => null]);

        $categoryName = $_GET["name"];
        if( $this->getCategoryByName($categoryName) ) {
            $this->generateStatusResult("categoryExists", -1);
        }

        $categoryId = $this->insertOneObject([
            "name" => $categoryName,
            "create_time" => time(),
        ], "good_category");

        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "categoryId" => $categoryId,
        ]);
    }

    /**
     * add a attribute for good
     * 新增商品(规格/属性)
     * @param void
     * 
     */
    public function addAttribute() : void
    {
        $this->checkPost();
        $this->checkParameters(["attrName" => null]);

        $attributeName = $_GET["attrName"];
        if( $this->getAttributeByName($attributeName) ) {
            $this->generateStatusResult("attributeExists", -1);
        }

        $attrId = $this->_addAttribute([
            "name" => $attributeName,
            "create_time" => time(),
        ]);

        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "attrId" => $attrId,
        ]);
    }

    /**
     * edit an attribute
     * 编辑商品(规格/属性)
     * @param void
     * 
     */
    public function editAttribute() : void
    {
        $this->checkPost();
        $this->checkParameters(["attrId" => "int", "attrName" => null]);

        $attrId = $_GET["attrId"];
        $attributeName = $_GET["attrName"];

        $this->_editAttribute($attrId, $attributeName);

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * get all attributes
     * 获取所有商品(规格/属性)
     * @param void
     * 
     */
    public function getAllAttributes() : void 
    {
        $attributes = $this->_getAllAttributes();

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "attributes" => $attributes,
        ]);
    }

    /**
     * get all attributes and value
     * 获取所有商品(规格/属性),附带属性值列表
     * @param void
     * 
     */
    public function allAttributesWithValue() : void
    {
        $attributes = $this->getAllAttributesWithValue();

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "attributes" => $attributes,
        ]);
    }

    /**
     * add attribute value
     * 新增一个规格值
     * @param void
     * 
     */
    public function addAttributeValue() : void
    {
        $this->checkPost();
        $this->checkParameters(["attrId" => "int", "attrValue" => null]);

        $attrId = $_GET["attrId"];
        $attrValue = $_GET["attrValue"];
        if( !is_array($attrValue) ) {
            $attrValue = explode(",", $attrValue);
        }

        $this->_addAttributeValue($attrId, $attrValue);

        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult();
    }

    /**
     * edit attribute value
     * 编辑规格值
     * @param void
     * 
     */
    public function editAttributeValue() : void
    {
        $this->checkPost();
        $this->checkParameters(["attrValueId" => "int", "attrValue" => null]);

        $attrValueId = $_GET["attrValueId"];
        $attrValue = $_GET["attrValue"];
        $this->_editAttributeValue($attrValueId, $attrValue);

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * get attribute values
     * 获取所有的规格值
     * @param void
     * 
     */
    public function getAttributeValue() : void
    {
        $this->checkParameters(["attrId" => "int"]);

        $attrId = (int)$_GET["attrId"];
        $attrValues = $this->_getAttributeValue($attrId);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "attrValues" => $attrValues,
        ]);
    }

    /**
     * get all category
     * 获取所有分类
     * @param void
     * 
     */
    public function getAllCategory() : void
    {
        $category = $this->_getAllCategory();

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "category" => $category,
        ]);
    }

    /**
     * add a good
     * 新增商品
     * [{"attr":["1_1","2_3","7_4"],"price":99.99,"stock":10},{"attr":["1_2","2_3","7_6"],"price":119.00,"stock":10}]
     * @param void
     * 
     */
    public function addGood() : void
    {
        $this->checkPost();
        $this->checkParameters([
            "name" => null, 
            "category_id" => "int", 
            "price" => "float", 
            "stock" => "int", 
            "attributes" => ["json"], 
            "tag" => "int", 
            "detail_images" => null, 
            "banner_images" => null
        ]);
        $this->beginTransaction();
        $pinyin = new Pinyin();

        $goodAttributes = $_GET["attributes"];

        $goodBase = [];
        foreach(["price", "stock", "tag"] as $option) {
            $goodBase[$option] = $_GET[$option];
        }

        $goodName = $_GET["name"];
        $categoryId = (int)$_GET["category_id"];
        if( !$this->checkGoodName($goodName, $categoryId, $_GET["model"] ?? "") ) {
            $this->generateStatusResult("goodNameError", -1);
        }

        $goodBase["name"] = $goodName;
        $goodBase["category_id"] = $categoryId;
        $goodBase["name_pinyin"] = $pinyin->permalink($goodName, "");

        if(isset($_GET["description"]) && !empty($_GET["description"])) {
            $goodBase["description"] = $_GET["description"];
            $goodBase["description_pinyin"] = $pinyin->permalink($_GET["description"], "");
        }

        foreach(["model", "props"] as $option) {
            if(isset($_GET[$option]) && !empty($_GET[$option])) {
                $goodBase[$option] = $_GET[$option];
            }
        }

        if(isset($_GET["nodiscount"])) {
            $goodBase["nodiscount"] = (int)$_GET["nodiscount"];
        }

        $goodBase["create_time"] = $goodBase["update_time"] = time();
        $goodId = $this->addGoodBase($goodBase);

        foreach($goodAttributes as $attribute) {
            $segment = [];
            $segment["good_id"] = $goodId;
            $segment["price"] = (string)$attribute["price"];
            $segment["stock"] = $attribute["stock"];
            $segment["create_time"] = $segment["update_time"] = time();
            $segment["cost_price"] = $attribute["cost_price"] ?? "0.0";
            $segmentId = $this->addGoodSegment($segment); 

            $attrValueIds = $attrIds = [];
            foreach(array_unique($attribute["attr"]) as $attr) {
                list($attrId, $attrValueId) = explode("_", $attr);
                $attrIds[] = $attrId;
                $attrValueIds[] = $attrValueId;

                $segmentAttribute = [];
                $segmentAttribute["good_id"] = $goodId;
                $segmentAttribute["segment_id"] = $segmentId;
                $segmentAttribute["attr_id"] = $attrId;
                $segmentAttribute["attr_value_id"] = $attrValueId;
                $segmentAttrId = $this->addGoodSegmentAttr($segmentAttribute);
            }

            $this->updateSegmentAttr($segmentId, implode("_", $attrIds),  implode("_", $attrValueIds));         
        }

        $uploadResult = $this->uploadGoodImage($goodId);
        if(!$uploadResult) {
            $this->generateStatusResult("uploadError", -3);
        }

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult();
    }

    /**
     * upload good image
     * 批量上传商品banner/细节图 
     * @param void
     * 
     */
    public function uploadGoodImage(int $goodId, bool $isEdit = false) : bool
    {
        $goodImagePath = $this->getGoodImagePath($goodId);
        $fileGenerator = function($source_name, $type, $tmp_name, $index, $content_range, \FileUpload\FileUpload $upload) {
            return hash("sha256", uniqid() . $index) . ".png";
        };

        if(!empty($_FILES["detail_images"])) {
            $uploadResult = $this->uploadFile([
                "path" => $goodImagePath . "detail_images",
                "size" => 20,
                "uploadname" => "detail_images",
                "filename" => $fileGenerator,
            ], $isEdit);

            if(!$uploadResult) {
                return false;
            }
        }

        if(!empty($_FILES["banner_images"])) {
            $bannerUploadResult = $this->uploadFile([
                "path" => $goodImagePath . "banner_images",
                "size" => 10,
                "uploadname" => "banner_images",
                "filename" => $fileGenerator,
            ], $isEdit);

            if(!$bannerUploadResult) {
                return false;
            }

            if(isset($_FILES["good_thumb"]) && !empty($_FILES["good_thumb"])) {
                $thumbUploadResult = $this->uploadFile([
                    "path" => $goodImagePath,
                    "size" => 10,
                    "uploadname" => "good_thumb",
                    "filename" => "thumb.png",
                ]);

                if(!$thumbUploadResult) {
                    return false;
                }
                $thumbSource = $goodImagePath . "thumb.png";
            } else {
                $thumbSource = $goodImagePath . "banner_images" . DS . (array_slice(scandir($goodImagePath . "banner_images"), 2))[0];
            }

            $this->resizeImage([
                "source" => $thumbSource,
                "target" => $goodImagePath . "thumb.png",
            ]);
        }

        return true;
    }

    /**
     * get edit good info
     * 获取商品详情
     * @param void
     * 
     */
    public function getGoodDetail() : void
    {   
        $this->checkParameters(["good_id" => ["int", "nonzero"]]);
        $good = $this->_getGoodDetail((int)$_GET["good_id"], true);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "good" => $good,
        ]);
    }

    /**
     * edit good
     * 编辑商品
     * [{"segment_id":48,"attr":["1_1","2_3"],"price":10.99,"stock":10},{"segment_id":49,"attr":["1_2","2_3"],"price":19.99,"stock":10, "status":-1},{"segment_id":0,"attr":["1_1"],"price":10,"stock":10}]
     * @param void
     * 
     */
    public function editGood() : void
    {
        $this->checkPost();
        $this->checkParameters([
            "good_id" => ["int", "nonzero"], 
            "name" => null, 
            "category_id" => ["int", "nonzero"], 
            "price" => "float", 
            "stock" => ["int"], 
            "attributes" => ["json"], 
            "tag" => ["int", "nonzero"]
        ]);
        $this->beginTransaction();
        $pinyin = new Pinyin();

        $goodAttributes = $_GET["attributes"];

        $goodBase = [];
        $goodId = (int)$_GET["good_id"];
        foreach(["price", "stock", "tag"] as $option) {
            $goodBase[$option] = $_GET[$option];
        }

        $goodName = $_GET["name"];
        $categoryId = (int)$_GET["category_id"];

        if( !$this->checkGoodNameFormat($goodName) || ( ($existsId = $this->checkGoodNameExists($goodName, $categoryId, $_GET["model"] ?? "", false)) && $existsId !=  $goodId) ) {
            $this->generateStatusResult("goodNameError", -2);
        }

        $goodBase["name"] = $goodName;
        $goodBase["category_id"] = $categoryId;
        $goodBase["name_pinyin"] = $pinyin->permalink($goodName, "");

        if(isset($_GET["description"]) && !empty($_GET["description"])) {
            $goodBase["description"] = $_GET["description"];
            $goodBase["description_pinyin"] = $pinyin->permalink($_GET["description"], "");
        }

        if(isset($_GET["model"])) {
            $goodBase["model"] = $_GET["model"];
        }

        if(isset($_GET["props"])) {
            $goodBase["props"] = $_GET["props"];
        }

        if(isset($_GET["nodiscount"])) {
            $goodBase["nodiscount"] = (int)$_GET["nodiscount"];
        }

        $goodBase["update_time"] = time();
        $this->editGoodBase($goodId, $goodBase);

        foreach($goodAttributes as $attribute) {
            $segment = [];
            $segment["good_id"] = $goodId;
            $segment["price"] = (string)$attribute["price"];
            $segment["stock"] = $attribute["stock"];
            $segment["update_time"] = time();
            $segment["status"] = $attribute["status"] ?? 0; // delete segment, set to -1
            $segment["cost_price"] = $attribute["cost_price"] ?? "0.0";

            $segmentId = $attribute["segment_id"] ?? false;
            if( $segmentId ) {
                $this->deleteSegmentAttr($segmentId);
                $this->editGoodSegment($segmentId, $segment);
            } else {
                $segment["create_time"] = time();
                $segmentId = $this->addGoodSegment($segment);
            }  

            $attrValueIds = $attrIds = [];
            foreach(array_unique($attribute["attr"]) as $attr) {
                list($attrId, $attrValueId) = explode("_", $attr);
                $attrIds[] = $attrId;
                $attrValueIds[] = $attrValueId;

                $segmentAttribute = [];
                $segmentAttribute["good_id"] = $goodId;
                $segmentAttribute["segment_id"] = $segmentId;
                $segmentAttribute["attr_id"] = $attrId;
                $segmentAttribute["attr_value_id"] = $attrValueId;
                $segmentAttrId = $this->addGoodSegmentAttr($segmentAttribute);
            }

            $this->updateSegmentAttr($segmentId, implode("_", $attrIds),  implode("_", $attrValueIds));         
        }

        $this->updateOrderGoodInfoAfterGoodEdit($goodId, $goodBase);
        $this->updateGoodThumbnail($goodId, (int)($_GET["thumb_index"] ?? 0));

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * add good image in editing case
     * 编辑商品时新增图片(banner/detail)
     * @param void
     * 
     */
    public function addGoodImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzeros"], "good_image" => null, "type" => [["detail", "banner"]]]);

        $goodId = (int)$_GET["good_id"];
        $goodImagePath = $this->getGoodImagePath($goodId) . $_GET["type"] . "_images";

        $uploadResult = $this->uploadImageSingle($goodImagePath, "good_image");

        if($uploadResult === false) {
            $this->generateStatusResult("uploadError", -1);
        } else {
            extract($uploadResult);
        }
        
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "pic" => $this->getGoodImagePath($goodId, true) . $_GET["type"] . "_images" . "/" . $fileName,
            "pic_index" => $newIndex,
        ]);
    }

    /**
     * delete good image in editing case
     * pic_index starting from 0
     * 编辑商品时删除图片(banner/detail)
     * @param void
     * 
     */
    public function deleteGoodImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzeros"], "pic_index" => ["int"], "type" => [["detail", "banner"]]]);

        $goodId = (int)$_GET["good_id"];
        $picIndex = (int)$_GET["pic_index"];
        $goodImagePath = $this->getGoodImagePath($goodId) . $_GET["type"] . "_images";

        $files = array_slice(scandir($goodImagePath), 2);
        if(count($files) <= $picIndex) {
            $this->generateStatusResult("deleteNotExistsImageIndex", -1);
        } 

        if(!unlink($goodImagePath . DS . $files[$picIndex])) {
            $this->generateStatusResult("deleteFaild", -2);
        }

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * set good status,up/down
     * 上架商品
     * @param void
     * 
     */
    public function upShelf() : void
    {
        $this->upDownShelf(0);
    }

    /**
     * set good status,up/down
     * 下架商品
     * @param void
     * 
     */
    public function downShelf() : void
    {
        $this->upDownShelf(-1);
    }

    /**
     * good up/down shelf
     * 更新商品状态表示上架和下架
     * @param void
     * 
     */
    public function upDownShelf(int $status) : void
    {
        $this->checkPost();
        $this->checkParameters(["good_ids" => ["array_int", "array_nonzero"]]);

        $goodIds = $_GET["good_ids"];
        if( !is_array($goodIds) ) {
            $goodIds = [$goodIds];
        }
        $this->setGoodStatus($goodIds, $status);
        
        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * get good list
     * 按照筛选条件获取所有商品
     * @param void
     * 
     */
    public function listGood() : void
    {
        $filters = [];
        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $_GET["size"] ?? 1000;

        foreach(["keyword", "tag", "period"] as $option) {
            if(isset($_GET[$option])) {
                $filters[$option] = $_GET[$option];
            }
        } 
        $filters["listAll"] = true;

        extract($this->getGoodList($filters, true));

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("goods", "count", "page"));
    }

    /**
     * delete good comment
     * 删除商品评价
     * @param void
     * 
     */
    public function deleteGoodComment() : void
    {
        $this->checkPost();
        $this->checkParameters(["comment_id" => ["int", "nonzero"]]);

        $this->_deleteComment($_GET["comment_id"]);

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * add banner
     * 添加一个banner
     * @param void
     * 
     */
    public function addBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["link_type" => null, "link_value" => null, "banner_image" => null]);
        $this->beginTransaction();
    
        if($_GET["link_type"] == "url" && !$this->checkUrlFormat($_GET["link_value"])) {
            $this->generateStatusResult("urlFormatError", -1);
        }

        if(!$this->checkBannerLimit("banner")) {
            $this->generateStatusResult("bannerAddCountError", -2);
        }

        $banner = [];
        $banner["link_type"] = $_GET["link_type"];
        $banner["link_value"] = $_GET["link_value"];
        $banner["create_time"] = time();
        $bannerId = $this->_addBanner($banner);

        $uploadParameters = [];
        $uploadParameters["path"] = ATTACH_PATH . "banner_image" . DS . chunk_split(sprintf("%06s", $bannerId), 2, DS);
        $uploadParameters["size"] = 20;
        $uploadParameters["uploadname"] = "banner_image";
        $uploadParameters["filename"] = "banner.png";
        $uploadResult = $this->uploadFile($uploadParameters);

        if($uploadResult) {
            $this->resizeImage([
                "source" => $uploadParameters["path"] . "banner.png",
                "target" => $uploadParameters["path"] . "thumb.png",
            ]);
        } else {
            $this->generateStatusResult("uploadError", -3);
        }

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult();
    }

    /**
     * edit banner
     * 编辑某一banner
     * @param void
     * 
     */
    public function editBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["link_type" => null, "link_value" => null, "banner_id" => "int"]);
        $this->beginTransaction();
    
        if($_GET["link_type"] == "url" && !$this->checkUrlFormat($_GET["link_value"])) {
            $this->generateStatusResult("urlFormatError", -1);
        }

        $bannerId = $_GET["banner_id"];
        $banner = [];
        $banner["link_type"] = $_GET["link_type"];
        $banner["link_value"] = $_GET["link_value"];
        $banner["update_time"] = time();
        $this->_editBanner($bannerId, $banner);

        if(!empty($_FILES["banner_image"])) {
            $uploadParameters = [];
            $uploadParameters["path"] = ATTACH_PATH . "banner_image" . DS . chunk_split(sprintf("%06s", $bannerId), 2, DS);
            $uploadParameters["size"] = 20;
            $uploadParameters["uploadname"] = "banner_image";
            $uploadParameters["filename"] = "banner.png";
            $uploadResult = $this->uploadFile($uploadParameters, true);

            if($uploadResult) {
                $this->resizeImage([
                    "source" => $uploadParameters["path"] . "banner.png",
                    "target" => $uploadParameters["path"] . "thumb.png",
                ]);
            } else {
                $this->generateStatusResult("uploadError", -3);
            }
        }

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete banner
     * 删除某一banner
     * @param void
     * 
     */
    public function deleteBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["banner_ids" => ["array_int", "array_nonzero"]]);

        $bannerIds = $_GET["banner_ids"];

        foreach($bannerIds as $bannerId) {
            $this->deleteOneObjectById($bannerId, "banner");
            EatWhatStatic::rrmdir(ATTACH_PATH . "banner_image" . DS . chunk_split(sprintf("%06s", $bannerId), 2, DS));
        }

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * set banner position
     * 设置banner显示和位置
     * @param banner_setting [{"bannerid":1,"position":1},{"bannerid":3,"position":2}]
     * @param void
     * 
     */
    public function setBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["banner_setting" => ["json"]]);
        $this->beginTransaction();

        $bannerSetting = $_GET["banner_setting"];
        if(count($bannerSetting) > $this->getSetting("bannerCountLimit")) {
            $this->generateStatusResult("bannerShowCountError", -1);
        }

        foreach($bannerSetting as $banner) {
            $this->setBannerPosition($banner["bannerid"], $banner["position"]);
        }

        $this->commit();
        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * statistics information
     * 首页统计信息, 今日和所有
     * @param void
     * 
     */
    public function statisticsInfo() : void 
    {
        $statisticsInfo = $this->getStatisticsInfo();

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "statistics" => $statisticsInfo,
        ]);
    }

    /**
     * statistics information
     * 首页统计信息, 按照时间段
     * @param void
     * 
     */
    public function periodStatisticsInfo() : void 
    {
        $this->checkPost();
        $this->checkParameters(["start_time" => ["int", "nonzero"], "end_time" => ["int", "nonzero"]]);

        $statisticsInfo = $this->getPeriodStatisticsInfo($_GET["start_time"], $_GET["end_time"]);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "statistics" => $statisticsInfo,
        ]);
    }

    /**
     * agree undeposit request
     * 获取所有的待处理提现请求
     * @param void
     * 
     */
    public function pendingUndepositLog() : void
    {
        $logs = $this->getUndepositLog([
            "status" => 0,
        ]);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "logs" => $logs,
        ]);
    }

    /**
     * agree undeposit request
     * 同意提现请求
     * @param void
     * 
     */
    public function agreeUndeposit() : void
    {
        $this->checkPost();
        $this->checkParameters(["log_id" => ["int", "nonzero"]]);
        $this->beginTransaction();

        $logId = (int)$_GET["log_id"];
        $logInfo = $this->getLogBaseInfo($logId, "member_log_undeposit");
        if($logInfo["status"] != 0) {
            $this->generateStatusResult("serverError", -404);
        }

        $this->updateUndepositLogStatus($logId, 1);
        $propertyLogId = $this->insertOneObject([
            "uid" => $logInfo["uid"],
            "amount" => -$logInfo["amount"],
            "log_time" => time(),
            "description" => AppConfig::get("undepositDescription", "lang"),
        ], "member_log_property");

        $messageId = $this->insertOneObject([
            "uid" => $logInfo["uid"],
            "message" => AppConfig::get("message_tpl", "global", "agreeUndeposit"),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("agreeUndepositRequest", 1);
        $this->outputResult();
    }

    /**
     * reject undeposit request
     * 拒绝提现请求
     * @param void
     * 
     */
    public function rejectUndeposit() : void
    {
        $this->checkPost();
        $this->checkParameters(["log_id" => ["int", "nonzero"], "reason" => null]);
        $this->beginTransaction();

        $logId = (int)$_GET["log_id"];
        $logInfo = $this->getLogBaseInfo($logId, "member_log_undeposit");
        if($logInfo["status"] != 0) {
            $this->generateStatusResult("serverError", -404);
        }

        $this->updateUndepositLogStatus($logId, -1);
        $this->updateUserCount($logInfo["uid"], "property", $logInfo["amount"]);

        $messageId = $this->insertOneObject([
            "uid" => $logInfo["uid"],
            "message" => sprintf(AppConfig::get("message_tpl", "global", "rejectUndeposit"), $_GET["reason"]),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("rejectUndepositRequest", 1);
        $this->outputResult();
    }

    /**
     * list orders by filter
     * 获取订单列表
     * @param void
     *
     */
    public function listOrder() : void
    {
        $filters = [];
        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;

        if(isset($_GET["order_status"])) {
            !$this->checkOrderStatusAvaliable($_GET["order_status"]) && $this->generateStatusResult("parameterError", -1);
            $filters["order_status"] = (int)$_GET["order_status"];
        }

        if(isset($_GET["period"]) && (int)$_GET["period"]) {
            $filters["period"] = (int)$_GET["period"];
        }

        if(isset($_GET["order_no"])) {
            $filters["order_no"] = $_GET["order_no"];
        }

        $filters["manage"] = true;
        extract($this->getOrderList($filters, true));

        //down load csv data
        $this->redis->set("downloadcsvdata_order", $orders, 24 * 3600);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("orders", "count", "page"));
    }

    /**
     * get single order detail
     * 获取单个订单详情
     * @param void
     * 
     */
    public function orderDetail() : void
    {
        $this->checkParameters(["order_id" => ["int", "nonzero"]]);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult($this->getOrderDetail($_GET["order_id"]));
    }

    /**
     * set order tracking number 
     * 设置订单运单号，表示已发货
     * @param void
     * 
     */
    public function setOrderTrackNumber() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"], "track_no" => null]);
        $this->beginTransaction();

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);
        if($orderInfo["order_status"] != 1) {
            $this->generateStatusResult("orderCantSetTrackNo", -1);
        }

        $this->updateOrderInfo($_GET["order_id"], [
            "order_status" => 2,
            "track_no" => $_GET["track_no"],
        ]);

        $messageId = $this->insertOneObject([
            "uid" => $orderInfo["uid"],
            "message" => sprintf(AppConfig::get("message_tpl", "global", "orderDeliverd"), $_GET["track_no"]),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * agree good-return request
     * 同意退货请求（先不做）
     * @param void
     * 
     */
    public function agreeGoodReturn() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"]]);
        $this->beginTransaction();

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);

        $this->updateOrderStatus($orderId, -2);
        $messageId = $this->insertOneObject([
            "uid" => $orderInfo["uid"],
            "message" => AppConfig::get("message_tpl", "global", "goodReturnSteps"),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("agreeGoodReturn", 1);
        $this->outputResult();
    }

    /**
     * reject good-return request
     * 拒绝退货请求（先不做）
     * @param void
     * 
     */
    public function rejectGoodReturn() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"], "reason" => null]);
        $this->beginTransaction();

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);

        $this->updateOrderStatus($orderId, 3);
        $messageId = $this->insertOneObject([
            "uid" => $orderInfo["uid"],
            "message" => sprintf(AppConfig::get("message_tpl", "global", "goodReturnReject"), $_GET["reason"]),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("rejectGoodReturn", 1);
        $this->outputResult();
    }

    /**
     * agree money-return order
     * 同意退款请求
     * @param void
     * 
     */
    public function agreeMoneyReturn() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"]]);
        $this->beginTransaction();

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);
        if($orderInfo["order_status"] != -5) {
            $this->generateStatusResult("orderStatusWrong", -1);
        }

        $orderGoods = $this->getOrderGoods($orderId);

        $uid = $orderInfo["uid"];
        // 状态
        $this->updateOrderInfo($orderId, [
            "order_status" => -6,  
            "update_time" => time(),
        ]);

        // 佣金记录
        $this->rollbackUserReturn($orderId);

        // 积分
        $incrCredit = $this->getOrderIncrCredit($orderInfo["order_total_money"]);
        $this->updateUserCount($uid, "credit", -$incrCredit);

        // 消费金额, 等级
        $this->updateUserCount($uid, "consume_money", -$orderInfo["order_total_money"]);
        $this->checkUserLevelUp($uid);

        //库存 销量
        foreach($orderGoods as $orderGood) {
            $this->updateSegmentStock($orderGood["segment_id"], $orderGood["good_count"]);
            $this->updateSegmentSalesnum($orderGood["segment_id"], -$orderGood["good_count"], $orderGood["good_id"]);
        }
        // 通知
        $messageId = $this->insertOneObject([
            "uid" => $uid,
            "message" => AppConfig::get("message_tpl", "global", "moneyReturnAgree"),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("agreeMoneyReturn", 1);
        $this->outputResult();
    }

    /**
     * reject money-return request
     * 拒绝退款请求
     * @param void
     * 
     */
    public function rejectMoneyReturn() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"], "reason" => null]);
        $this->beginTransaction();

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);

        $this->updateOrderStatus($orderId, 1);
        $messageId = $this->insertOneObject([
            "uid" => $orderInfo["uid"],
            "message" => sprintf(AppConfig::get("message_tpl", "global", "moneyReturnReject"), $_GET["reason"]),
            "message_time" => time(),
        ], "member_message");

        $this->commit();
        $this->generateStatusResult("rejectMoneyReturn", 1);
        $this->outputResult();
    }

    /**
     * get member list
     * 获取用户列表
     * @param void
     * 
     */
    public function listMember() : void
    {
        $filters = [];
        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;

        foreach(["status", "id", "period", "level", "namekey", "mobile", "isDistributor"] as $option) {
            if(isset($_GET[$option])) {
                $filters[$option] = $_GET[$option];
            }
        }

        extract($this->getMemberList($filters, true));

        //down load csv data
        $this->redis->set("downloadcsvdata_member", $members, 24 * 3600);

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("members", "count", "page"));
    }

    /**
     * get member order list
     * 获取用户订单列表
     * @param void
     * 
     */
    public function listMemberOrder() : void 
    {
        $this->checkParameters(["uid" => ["int", "nonzero"]]);

        $filters = [];
        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;

        $uid = (int)$_GET["uid"];
        $filters["uid"] = $uid;

        extract($this->getOrderList($filters, true));

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("orders", "count", "page"));
    }

    /**
     * set member level
     * 设置用户等级
     * @param void
     * 
     */
    public function setMemberLevel() : void
    {
        $this->checkPost();
        $this->checkParameters(["uid" => ["int", "nonzero"], "level" => ["int", [1, 2, 3, 4]]]);

        $this->updateMemberLevel($_GET["uid"], $_GET["level"]);
        
        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * set member distributor
     * 设置用户为分销商
     * @param void
     * 
     */
    public function setDistributor() : void
    {
        $this->checkPost();
        $this->checkParameters(["uid" => ["int", "nonzero"]]);

        $status = $_GET["status"] ?? 1;
        $this->updateMemberField($_GET["uid"], "isDistributor", $status);

        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * send message to user
     * 给用户发一条站内信
     * @param void
     * 
     */
    public function sendMessageToUser() : void
    {
        $this->checkPost();
        $this->checkParameters(["uid" => ["int", "nonzero"], "message" => null]);

        $messageId = $this->insertOneObject([
            "uid" => (int)$_GET["uid"],
            "message" => $_GET["message"],
            "message_time" => time(),
        ], "member_message");

        $this->generateStatusResult("sendSuccess", 1);
        $this->outputResult();
    }

    /**
     * add a web feed
     * 添加一条官网动态
     * @param void
     * 
     */
    public function addWebFeed() : void
    {
        $this->checkPost();
        $this->checkParameters(["title" => null, "content" => null, "cover" => null]);
        $this->beginTransaction();

        $feedId = $this->insertOneObject([
            "title" => $_GET["title"],   
            "content" => $_GET["content"],   
            "add_time" => time(),   
        ], "feed", "web_");

        if(!$this->uploadWebFeedCover($feedId)) {
            $this->generateStatusResult("uploadError", -1);
        }

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "feed_id" => $feedId,
        ]);
    }

    /**
     * edit a web feed
     * 编辑一条官网动态
     * @param void
     * 
     */
    public function editWebFeed() : void
    {
        $this->checkPost();
        $this->checkParameters(["feed_id" => ["int", "nonzero"], "title" => null, "content" => null]);
        $this->beginTransaction();

        $feedId = $_GET["feed_id"];
        $this->updateOneObjectById($feedId, [
            "title" => $_GET["title"],   
            "content" => $_GET["content"],
        ], "feed", "web_");

        if( isset($_FILES["cover"]) && !empty($_FILES["cover"]) ) {
            if(!$this->uploadWebFeedCover($feedId)) {
                $this->generateStatusResult("uploadError", -1);
            }
        }

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete a web feed
     * 删除一条官网动态
     * @param void
     * 
     */
    public function deleteWebFeed() : void
    {
        $this->checkPost();
        $this->checkParameters(["feed_id" => ["int", "nonzero"]]);

        $feedId = (int)$_GET["feed_id"];
        $this->deleteOneObjectById($feedId, "feed", "web_");
        EatWhatStatic::rrmdir($this->getPathById("web_feed_cover", $feedId));

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * edit brand description
     * 编辑喜勃品牌介绍
     * @param void
     * 
     */
    public function editWebBrand() : void
    {
        $this->checkPost();
        $this->checkParameters(["description" => null]);

        $this->updateOneObjectById(1, [   
            "description" => $_GET["description"],
            "update_time" => time(),
        ], "brand", "web_");

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * edit static html
     * 创建和编辑品牌、首页、联络、合作等静态页面
     * @param void
     * 
     */
    public function upsertWebStatic() : void
    {
        $this->checkPost();
        $this->checkParameters(["type" => null, "content" => ""]);

        $this->_upsertWebStatic($_GET["type"], $_GET["content"]);

        $this->generateStatusResult("operateSuccess", 1);
        $this->outputResult();
    }

    /**
     * upload static html image
     * 上传静态页面的图片
     * @param void
     * 
     */
    public function uploadWebStaticImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["static_image" => null]);

        $filename = hash("sha256", uniqid() . time()) . ".png";
        $pathSuffix = "static_image" . DS . date("Ym");
        $uploadResult = $this->uploadFile([
            "path" => ATTACH_PATH . $pathSuffix,
            "size" => 20,
            "uploadname" => "static_image",
            "filename" => $filename,
        ]);

        if(!$uploadResult) {
            $this->generateStatusResult("uploadError", -1);
        }

        $this->generateStatusResult("uploadSuccess", 1);
        $this->outputResult([
            "url" => AppConfig::get("protocol") . AppConfig::get("server_name") . "/attachment/static_image/" . date("Ym") . "/$filename",
        ]);
    }

    /**
     * add a web banner
     * 添加一条官网banner
     * @param void
     * 
     */
    public function addWebBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["url" => ["url"], "banner_image" => null, "type" => null]);
        $this->beginTransaction();

        // if(!$this->checkBannerLimit("banner", "web_", $_GET["type"])) {
        //     $this->generateStatusResult("bannerAddCountError", -2);
        // }

        $bannerId = $this->insertOneObject([
            "url" => $_GET["url"],
            "add_time" => time(),
            "type" => $_GET["type"],
        ], "banner", "web_");

        $bannerPathTo = $this->getPathById("web_banner_image", $bannerId);
        $uploadResult = $this->uploadFile([
            "path" => $bannerPathTo,
            "size" => 20,
            "uploadname" => "banner_image",
            "filename" => "banner.png",
        ]);

        if(!$uploadResult) {
            $this->generateStatusResult("uploadError", -1);
        }

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "banner_id" => $bannerId,
        ]);
    }

    /**
     * edit a web banner
     * 编辑一条官网banner
     * @param void
     * 
     */
    public function editWebBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["banner_id" => ["int", "nonzero"], "url" => ["url"], "type" => null]);
        $this->beginTransaction();

        $bannerId = $_GET["banner_id"];
        $this->updateOneObjectById($bannerId, [
            "url" => $_GET["url"],
            "type" => $_GET["type"],
        ], "banner", "web_");

        if( isset($_FILES["banner_image"]) && !empty($_FILES["banner_image"]) ) {
            $bannerPathTo = $this->getPathById("web_banner_image", $bannerId);
            $uploadResult = $this->uploadFile([
                "path" => $bannerPathTo,
                "size" => 20,
                "uploadname" => "banner_image",
                "filename" => "banner.png",
            ], true);

            if(!$uploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
        }

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete a web banner
     * 删除一条官网banner
     * @param void
     * 
     */
    public function deleteWebBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["banner_id" => ["int", "nonzero"]]);

        $bannerId = (int)$_GET["banner_id"];
        $this->deleteOneObjectById($bannerId, "banner", "web_");
        EatWhatStatic::rrmdir($this->getPathById("web_banner_image", $bannerId));

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

     /**
     * set web banner position
     * 设置官网banner显示和位置
     * @param banner_setting [{"bannerid":1,"position":1},{"bannerid":3,"position":2}]
     * @param void
     * 
     */
    public function setWebBanner() : void
    {
        $this->checkPost();
        $this->checkParameters(["banner_setting" => ["json"]]);
        $this->beginTransaction();

        $bannerSetting = $_GET["banner_setting"];
        foreach($bannerSetting as $banner) {
            $this->setBannerPosition($banner["bannerid"], $banner["position"], "banner", "web_");
        }

        $this->commit();
        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }

    /**
     * add web good category
     * 新增官网商品分类
     * @param void
     *
     */
    public function addWebGoodCategory() : void
    {
        $this->checkPost();
        $this->checkParameters(["category_name" => null, "category_image" => null]);
        $this->beginTransaction();

        if($this->getWebGoodCategoryByField("name", $_GET["category_name"])) {
            $this->generateStatusResult("categoryExists", -1);
        }

        $categoryId = $this->insertOneObject([
            "name" => $_GET["category_name"],
            "add_time" => time(),
        ], "good_category", "web_");


        $categoryPathTo = $this->getPathById("web_good_category_image", $categoryId);
        $uploadResult = $this->uploadFile([
            "path" => $categoryPathTo,
            "size" => 20,
            "uploadname" => "category_image",
            "filename" => "category.png",
        ]);

        if(!$uploadResult) {
            $this->generateStatusResult("uploadError", -1);
        }

        if(isset($_FILES["category_image_hover"]) && !empty($_FILES["category_image_hover"])) {
            $hoverUploadResult = $this->uploadFile([
                "path" => $categoryPathTo,
                "size" => 20,
                "uploadname" => "category_image_hover",
                "filename" => "category_hover.png",
            ]);

            if(!$hoverUploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
        } 

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "category_id" => $categoryId,
        ]);
    }

    /**
     * edit web good category
     * 编辑官网商品分类
     * @param void
     *
     */
    public function editWebGoodCategory() : void
    {
        $this->checkPost();
        $this->checkParameters(["category_name" => null, "category_id" => ["int", "nonzero"]]);
        $this->beginTransaction();

        $categoryId = $_GET["category_id"];
        $this->updateOneObjectById($categoryId, [
            "name" => $_GET["category_name"],
        ], "good_category", "web_");

        $categoryPathTo = $this->getPathById("web_good_category_image", $categoryId);
        if( isset($_FILES["category_image"]) && !empty($_FILES["category_image"]) ) {
            $uploadResult = $this->uploadFile([
                "path" => $categoryPathTo,
                "size" => 20,
                "uploadname" => "category_image",
                "filename" => "category.png",
            ], true);

            if(!$uploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
        }

        if(isset($_FILES["category_image_hover"]) && !empty($_FILES["category_image_hover"])) {
            $hoverUploadResult = $this->uploadFile([
                "path" => $categoryPathTo,
                "size" => 20,
                "uploadname" => "category_image_hover",
                "filename" => "category_hover.png",
            ], true);

            if(!$hoverUploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
        } 

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * add a web good
     * 新增一条官网good
     *
     * 图片相关参数
     * banner_images : banner图,多张
     * good_thumb : 封面图(单张，可以不传，用banner的第一张作为封面)
     * good_hover_thumb : 鼠标滑过图(单张，可以不传，没有就不显示)
     * good_detail_image : 详情图(单张)
     * @param void
     * 
     */
    public function addWebGood() : void
    {
        $this->checkPost();
        $this->checkParameters([
            "name" => null,
            "price" => ["float"],
            "banner_images" => null,
            "category_id" => ["int", "nonzero"],
        ]);
        $this->beginTransaction();

        $goodBase = [];
        $goodBase["add_time"] = time();
        $goodBase["category_id"] = (int)$_GET["category_id"];
        foreach(["price", "name"] as $option) {
            $goodBase[$option] = $_GET[$option];
        }

        foreach(["model", "props", "description"] as $option) {
            if(isset($_GET[$option]) && !empty($_GET[$option])) {
                $goodBase[$option] = $_GET[$option];
            }
        }

        $goodId = $this->insertOneObject($goodBase, "good", "web_");
        $uploadResult = $this->uploadWebGoodBannerImage($goodId);
        if(!$uploadResult) {
            $this->generateStatusResult("uploadError", -1);
        }

        $this->commit();
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "good_id" => $goodId,
        ]);
    }

    /**
     * edit a web good
     * 编辑一条官网good
     *
     * 图片相关参数
     * banner_images : 单独接口上传和删除
     * good_thumb : 封面图(单张，可以不传，用banner的第一张作为封面)
     * good_hover_thumb : 鼠标滑过图(单张，可以不传，没有就不显示)
     * good_detail_image : 详情图(单张)
     * @param void
     * 
     */
    public function editWebGood() : void
    {
        $this->checkPost();
        $this->checkParameters([
            "good_id" => ["int", "nonzero"],
            "name" => null,
            "price" => ["float"],
        ]);
        $this->beginTransaction();

        $goodId = (int)$_GET["good_id"];
        $goodBase = [];
        foreach(["price", "name"] as $option) {
            $goodBase[$option] = $_GET[$option];
        }

        foreach(["model", "props", "description"] as $option) {
            if(isset($_GET[$option]) && !empty($_GET[$option])) {
                $goodBase[$option] = $_GET[$option];
            }
        }

        if(isset($_GET["category_id"]) && !empty($_GET["category_id"])) {
            $goodBase["category_id"] = (int)$_GET["category_id"];
        }

        $this->updateOneObjectById($goodId, $goodBase, "good", "web_");

        // upload
        $bannerPathTo = $this->getPathById("web_good_image", $goodId);
        if(isset($_FILES["good_thumb"]) && !empty($_FILES["good_thumb"])) {
            $thumbUploadResult = $this->uploadFile([
                "path" => $bannerPathTo,
                "size" => 10,
                "uploadname" => "good_thumb",
                "filename" => "thumb.png",
            ], true);

            if(!$thumbUploadResult) {
                $this->generateStatusResult("uploadError", -1);
            } 
            // else {
            //     $this->resizeImage([
            //         "width" => 512,
            //         "height" => 512,
            //         "source" => $bannerPathTo . "thumb.png",
            //         "target" => $bannerPathTo . "thumb.png",
            //     ]);
            // } 
        }

        if(isset($_FILES["good_hover_thumb"]) && !empty($_FILES["good_hover_thumb"])) {
            $thumbUploadResult = $this->uploadFile([
                "path" => $bannerPathTo,
                "size" => 10,
                "uploadname" => "good_hover_thumb",
                "filename" => "hover_thumb.png",
            ], true);

            if(!$thumbUploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
            //  else {
            //     $this->resizeImage([
            //         "width" => 512,
            //         "height" => 512,
            //         "source" => $bannerPathTo . "hover_thumb.png",
            //         "target" => $bannerPathTo . "hover_thumb.png",
            //     ]);
            // } 
        }

        if(isset($_FILES["good_detail_image"]) && !empty($_FILES["good_detail_image"])) {
            $goodDetailUploadResult = $this->uploadFile([
                "path" => $bannerPathTo,
                "size" => 10,
                "uploadname" => "good_detail_image",
                "filename" => "detail.png",
            ], true);

            if(!$goodDetailUploadResult) {
                $this->generateStatusResult("uploadError", -1);
            }
        }

        $this->commit();
        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete a web good
     * 删除一条官网good
     * @param void
     * 
     */
    public function deleteWebGood() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzero"]]);

        $goodId = (int)$_GET["good_id"];
        $this->deleteOneObjectById($goodId, "good", "web_");
        EatWhatStatic::rrmdir($this->getPathById("web_good_image", $goodId));

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * add web good image in editing case
     * 编辑商品时新增图片
     * @param void
     * 
     */
    public function addWebGoodImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzeros"], "banner_image" => null]);

        $goodId = (int)$_GET["good_id"];
        $bannerPathTo = $this->getPathById("web_good_image", $goodId) . "banner_images";

        $uploadResult = $this->uploadImageSingle($bannerPathTo, "banner_image");

        if($uploadResult === false) {
            $this->generateStatusResult("uploadError", -1);
        } else {
            extract($uploadResult);
        }
        
        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "pic" => $this->getPathById("web_good_image", $goodId, true) . "banner_images/" . $fileName,
            "pic_index" => $newIndex,
        ]);
    }

    /**
     * delete web good image in editing case
     * pic_index starting from 0
     * 编辑官网商品时删除图片
     * @param void
     * 
     */
    public function deleteWebGoodImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzeros"], "pic_index" => ["int"]]);

        $goodId = (int)$_GET["good_id"];
        $picIndex = (int)$_GET["pic_index"];
        $bannerPathTo = $this->getPathById("web_good_image", $goodId) . "banner_images";

        $files = array_slice(scandir($bannerPathTo), 2);
        if(count($files) <= $picIndex) {
            $this->generateStatusResult("deleteNotExistsImageIndex", -1);
        } 

        if(!unlink($bannerPathTo . DS . $files[$picIndex])) {
            $this->generateStatusResult("deleteFaild", -2);
        }

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete single image
     * 删除单张图片
     * @param void
     * 
     */
    public function deleteSingleImage() : void
    {
        $this->checkPost();
        $this->checkParameters(["url" => ["url"]]);

        $url = $_GET["url"];
        if(!$this->_deleteSingleImage($url)) {
            $this->generateStatusResult("deleteFaild", -1);
        }

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * Set the products displayed on the home page
     * 设置首页显示的产品
     * @param void
     * 
     */
    public function setIndexGood() : void
    {
        $this->checkPost();
        $this->checkParameters(["position" => ["json"]]);

        $this->setSetting("web_index_good", $_GET["position"]);

        $this->generateStatusResult("setSuccess", 1);
        $this->outputResult();
    }
}