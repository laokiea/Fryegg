<?php

namespace EatWhat\Traits;


use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * Management trait
 * 
 */
trait ManageTrait
{
    /**
     * get management user by name
     * 
     */
    public function getManageUserByName(string $username)
    {
        $user = $this->mysqlDao->table("manage_member")
                     ->select("*")
                     ->where(["username"])
                     ->prepare()
                     ->execute([$username], ["fetch", \PDO::FETCH_ASSOC]);

        return $user;
    }

    /**
     * get good attribute via name
     * 
     */
    public function getCategoryByName(string $categoryName) 
    {
        $category = $this->mysqlDao->table("good_category")
                          ->select("*")
                          ->where(["name"])
                          ->prepare()
                          ->execute([$categoryName], ["fetch", \PDO::FETCH_ASSOC]);
        
        return $category;
    }

    /**
     * get good attribute via name
     * 
     */
    public function getAttributeByName(string $attributeName) 
    {
        $attribute = $this->mysqlDao->table("attribute")
                          ->select("*")
                          ->where(["name"])
                          ->prepare()
                          ->execute([$attributeName], ["fetch", \PDO::FETCH_ASSOC]);
        
        return $attribute;
    }

    /**
     * add good attribute
     * 
     */
    public function _addAttribute(array $attribute) : int
    {
        $this->mysqlDao->table("attribute")
             ->insert(array_keys($attribute))
             ->prepare()
             ->execute(array_values($attribute));
        $this->redis->del("all_attributes");
        $this->redis->del("all_attributes_with_value");
        
        return (int)$this->mysqlDao->getLastInsertId();
    }

    /**
     * edit an attributeq
     * 
     */
    public function _editAttribute(int $attrId, string $attributeName) : bool
    {
        $this->mysqlDao->table("attribute")->update(["name"])->where("id")->prepare()->execute([$attributeName, $attrId]);

        $this->redis->del("all_attributes");
        $this->redis->del("all_attributes_with_value");
        return $this->mysqlDao->execResult;
    }

    /**
     * add attribute value
     * 
     */
    public function _addAttributeValue(int $attrId, array $attrValue) : bool
    {
        foreach($attrValue as $value) {
            $attr_value_id = $this->mysqlDao->table("attribute_value")
                                  ->insert(["attr_id", "name", "create_time"])
                                  ->prepare()
                                  ->execute([$attrId, $value, time()]);
        }
        
        $this->redis->del("all_attributes_value");
        $this->redis->del("all_attributes_with_value");
        return $this->mysqlDao->execResult;
    }

    /**
     * edit ad attribute value
     * 
     */
    public function _editAttributeValue(int $attrValueId, string $attrValue) : bool
    {
        $this->mysqlDao->table("attribute_value")
             ->update(["name"])
             ->where("id")->prepare()
             ->execute([$attrValue, $attrValueId]);

        $this->redis->del("all_attributes_value");
        $this->redis->del("all_attributes_with_value");
        return $this->mysqlDao->execResult;
    }

    /**
     * check good name, 2 - 10characters
     * 
     */
    public function checkGoodName(string $name, int $categoryId, string $model = "") : bool
    {
        return $this->checkGoodNameFormat($name) && !$this->checkGoodNameExists($name, $categoryId, $model);
    }

    /**
     * check good name, 2 - 10characters
     * 
     */
    public function checkGoodNameFormat(string $name) : bool
    {
        return boolval(preg_match("/^[\d\w\p{Han}\-\+\!\=\*\(\)\"\'\[\]\,\?\:]{1,40}$/iu", $name));
    }

    /**
     * check good name exists
     * return true when exists
     * 
     */
    public function checkGoodNameExists(string $name, int $categoryId, string $model = "", bool $_bool = true)
    {
        $result = $this->mysqlDao->table("good")
                                 ->select(["id"])
                                 ->where(["name", "category_id", "model"])
                                 ->prepare()
                                 ->execute([$name, $categoryId, $model], ["fetch", \PDO::FETCH_ASSOC]);
        return $_bool ? boolval($result) : ($result ? $result["id"] : 0);
    }

    /**
     * add a good base 
     * [unique: name-model-category_id]
     * 
     */
    public function addGoodBase(array $good) : int
    {
        $this->mysqlDao->table("good")
             ->insert(array_keys($good))
             ->prepare()
             ->execute(array_values($good));

        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * add good segment
     * [unique: attr_value_ids]
     * 
     */
    public function addGoodSegment(array $segment) : int
    {
        $this->mysqlDao->table("good_segment")
             ->insert(array_keys($segment))
             ->prepare()
             ->execute(array_values($segment));

        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * add good segment attr
     * 
     */
    public function addGoodSegmentAttr(array $segmentAttr) : int
    {
        $this->mysqlDao->table("good_segment_attribute")
             ->insert(array_keys($segmentAttr))
             ->prepare()
             ->execute(array_values($segmentAttr));

        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * update segment attr value
     * 
     */
    public function updateSegmentAttr(int $segmentId, string $attrIds, string $attrValueIds) : bool
    {
        $this->mysqlDao->table("good_segment")
             ->update(["attr_ids", "attr_value_ids"])->where(["id"])
             ->prepare()->execute([$attrIds, $attrValueIds, $segmentId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * delete segment attributes
     * 
     */
    public function deleteSegmentAttr(int $segmentId) : bool
    {
        $this->mysqlDao->table("good_segment_attribute")
                    ->delete()->where(["segment_id"])
                    ->prepare()->execute([$segmentId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * edit good base
     * 
     */
    public function editGoodBase(int $goodId, array $goodBase) : bool
    {
        $dao = $this->mysqlDao->table("good")->update(array_keys($goodBase))->where(["id"])->prepare();
        array_push($goodBase, $goodId);
        $dao->execute(array_values($goodBase));

        return $this->mysqlDao->execResult;
    }

    /**
     * edit good segment
     * 
     */
    public function editGoodSegment(int $segmentId, array $segment) : bool
    {
        $dao = $this->mysqlDao->table("good_segment")->update(array_keys($segment))->where(["id"])->prepare();
        array_push($segment, $segmentId);
        $dao->execute(array_values($segment));

        return $this->mysqlDao->execResult;
    }

    /**
     * set good status
     * 
     */
    public function setGoodStatus(array $goodIds, int $status) : bool
    {
        $dao = $this->mysqlDao->table("good")->update(["status"])->in("id", count($goodIds))->prepare();
        array_unshift($goodIds, $status);
        $dao->execute($goodIds);

        return $this->mysqlDao->execResult;
    }

    /**
     * add banner
     * 
     */
    public function _addBanner(array $banner) : int
    {
        $this->mysqlDao->table("banner")->insert(array_keys($banner))->prepare()->execute(array_values($banner));

        return (int)$this->mysqlDao->getLastInsertId();
    }

    /**
     * edit banner
     * 
     */
    public function _editBanner(int $bannerId, array $banner) : bool
    {
        $dao = $this->mysqlDao->table("banner")->update(array_keys($banner))->where(["id"])->prepare();
        array_push($banner, $bannerId);
        $dao->execute(array_values($banner));

        return $this->mysqlDao->execResult;
    }

    /**
     * delete banner
     * 
     */
    public function _deleteBanner(array $bannerIds) : bool
    {
        $dao = $this->mysqlDao->table("banner")->delete()->in("id", count($bannerIds))->prepare();
        array_unshift($bannerIds, -1);
        $dao->execute($bannerIds);

        return $this->mysqlDao->execResult;
    }

    /**
     * set banners show position
     * 
     */
    public function setBannerPosition(int $bannerId, int $position, string $tableName = "banner", ?string $tablePrefix = null) : bool
    {
        $this->mysqlDao->table($tableName, $tablePrefix)
             ->update(["status", "position"])->where(["id"])
             ->prepare()->execute([1, $position, $bannerId]);
             
        return $this->mysqlDao->execResult;
    }

    /**
     * get today/all statistics information. include new members count, sales and orders count
     * 
     */
    public function getStatisticsInfo(bool $statToday = false) : array
    {
        $statisticsInfo = [];
        $todayTimeStamp = (new \DateTime(date("Y-m-d"), new \DateTimeZone('Asia/Shanghai')))->getTimeStamp();

        $orderExecuteSql = "select count(*) as orders, if(isnull(sum(order_total_money)), 0.0 , sum(order_total_money)) as sales from shop_order where order_status >= ?
                       union all
                       (select count(*) as orders, if(isnull(sum(order_total_money)), 0.0 , sum(order_total_money)) as sales from shop_order where order_status >= ? and create_time >= ?)";
        $orderStatistics = $this->mysqlDao->setExecuteSql($orderExecuteSql)->prepare()->execute([1, 1, $todayTimeStamp], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        $memberExecuteSql = "select count(*) as members from shop_member where status >= ?
                       union all
                       (select count(*) as members from shop_member where status >= ? and create_time >= ?)";
        $memberStatistics = $this->mysqlDao->setExecuteSql($memberExecuteSql)->prepare()->execute([0, 0, $todayTimeStamp], ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach(["all", "today"] as $index => $option) {
            $statisticsInfo[$option] = [
                "orders" => $orderStatistics[$index]["orders"],
                "sales" => $orderStatistics[$index]["sales"],
                "members" => $memberStatistics[$index]["members"],
            ];
        }

        return $statisticsInfo;
    }

    /**
     * get period statistics information. include new members count, sales and orders count
     * 
     */
    public function getPeriodStatisticsInfo(int $start_time, int $end_time) : array
    {
        $orderExecuteSql = "select count(*) as orders, if(isnull(sum(order_total_money)), 0.0 , sum(order_total_money)) as sales from shop_order where order_status >= ? and create_time between ? and ?";
        $orderStatistics = $this->mysqlDao->setExecuteSql($orderExecuteSql)->prepare()->execute([1, $start_time, $end_time], ["fetch", \PDO::FETCH_ASSOC]);
        
        $memberExecuteSql = "select count(*) as members from shop_member where status >= ? and create_time between ? and ?";
        $memberStatistics = $this->mysqlDao->setExecuteSql($memberExecuteSql)->prepare()->execute([0, $start_time, $end_time], ["fetch", \PDO::FETCH_ASSOC]);

        $statisticsInfo = [
            "orders" => $orderStatistics["orders"],
            "sales" => $orderStatistics["sales"],
            "members" => $memberStatistics["members"],
        ];

        return $statisticsInfo;
    }

    /**
     * delete a good comment
     * 
     */
    public function _deleteComment(int $commentId) : bool 
    {
        $this->mysqlDao->table("good_comment")
            ->delete()->where(["id"])
            ->prepare()->execute([$commentId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * update undeposit log status
     *
     */
    public function updateUndepositLogStatus(int $logId, int $status) : bool
    {
        $this->mysqlDao->table("member_log_undeposit")
            ->update(["status"])->where(["id"])
            ->prepare()->execute([$status, $logId]);

        return $this->mysqlDao->execResult;
    }

    /**
     * upload web feed cover
     *
     */
    public function uploadWebFeedCover(int $feedId) : bool
    {
        $feedCoverPathTo = $this->getPathById("web_feed_cover", $feedId);
        $uploadResult = $this->uploadFile([
                "path" => $feedCoverPathTo,
                "size" => 20,
                "uploadname" => "cover",
                "filename" => "cover.png",
        ]);

        if(!$uploadResult) {
            return false;
        } 
        // else {
        //     $this->resizeImage([
        //         "width" => 512,
        //         "height" => 512,
        //         "source" => $feedCoverPathTo . "cover.png",
        //         "target" => $feedCoverPathTo . "thumb.png",
        //     ]);
        //     return true;
        // }
    }

    /**
     * upload web good images
     *
     */
    public function uploadWebGoodBannerImage(int $goodId) : bool
    {
        $bannerPathTo = $this->getPathById("web_good_image", $goodId);
        $fileGenerator = function($source_name, $type, $tmp_name, $index, $content_range, \FileUpload\FileUpload $upload) {
            return hash("sha256", uniqid() . $index) . ".png";
        };

        $bannerUploadResult = $this->uploadFile([
            "path" => $bannerPathTo . "banner_images",
            "size" => 10,
            "uploadname" => "banner_images",
            "filename" => $fileGenerator,
        ]);

        if(!$bannerUploadResult) {
            return false;
        } else {
            if(isset($_FILES["good_thumb"]) && !empty($_FILES["good_thumb"])) {
                $thumbUploadResult = $this->uploadFile([
                    "path" => $bannerPathTo,
                    "size" => 10,
                    "uploadname" => "good_thumb",
                    "filename" => "thumb.png",
                ]);

                if(!$thumbUploadResult) {
                    return false;
                }
                $thumbSource = $bannerPathTo . "thumb.png";
            } else {
                $thumbSource = $bannerPathTo . "banner_images" . DS . (array_slice(scandir($bannerPathTo . "banner_images"), 2))[0];
            }
            // $this->resizeImage([
            //     "width" => 512,
            //     "height" => 512,
            //     "source" => $thumbSource,
            //     "target" => $bannerPathTo . "thumb.png",
            // ]);

            if(isset($_FILES["good_hover_thumb"]) && !empty($_FILES["good_hover_thumb"])) {
                $thumbHoverUploadResult = $this->uploadFile([
                    "path" => $bannerPathTo,
                    "size" => 10,
                    "uploadname" => "good_hover_thumb",
                    "filename" => "hover_thumb.png",
                ]);

                if(!$thumbHoverUploadResult) {
                    return false;
                }

                // $this->resizeImage([
                //     "width" => 512,
                //     "height" => 512,
                //     "source" => $bannerPathTo . "hover_thumb.png",
                //     "target" => $bannerPathTo . "hover_thumb.png",
                // ]);
            }

            if(isset($_FILES["good_detail_image"]) && !empty($_FILES["good_detail_image"])) {
                $goodDetailUploadResult = $this->uploadFile([
                    "path" => $bannerPathTo,
                    "size" => 10,
                    "uploadname" => "good_detail_image",
                    "filename" => "detail.png",
                ]);

                if(!$goodDetailUploadResult) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * get web good category by assign field
     *
     */
    public function getWebGoodCategoryByField(string $fieldName, $fieldValue) : array
    {
        $category = $this->mysqlDao->table("good_category", "web_")->select(["*"])->where([$fieldName])->prepare()->execute([$fieldValue], ["fetch", \PDO::FETCH_ASSOC]);

        if($category) {
            $category["add_time"] = date("Y-m-d H:i:s", $category["add_time"]);
        } else {
            $category = [];
        }

        return $category;
    }

    /**
     * upsert a web static html
     *
     */
    public function _upsertWebStatic(string $type, string $content) : bool
    {
        $execSql = "INSERT INTO web_static (type,content,update_time) VALUES(?,?,?) ON DUPLICATE KEY UPDATE content = VALUES(content)";
        $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute([$type, $content, time()]);

        return $this->mysqlDao->execResult;
    }

    /**
     * delete single image by url
     *
     */
    public function _deleteSingleImage(string $url) : bool 
    {
        if(strpos($url, "attachment") !== false) {
            preg_match("/attachment\/(.*)$/i", $url, $match);
            if(file_exists($filename = ATTACH_PATH . str_replace("/", DS, $match[1]))) {
                unlink($filename);
                return true;
            }
        }

        return false;
    }
}
