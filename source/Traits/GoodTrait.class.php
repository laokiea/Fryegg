<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * User Traits For User Api
 * 
 */
trait GoodTrait
{
    /**
     * get good base info
     * 
     */
    public function getGoodBase(int $goodId, ?string $field = null)
    {
        $goodBase = $this->mysqlDao->table("good")
                         ->select(["id","category_id","model","name","description","stock","price","tag","status","salesnum","views","create_time","update_time","props","nodiscount"])
                         ->where(["id"])
                         ->prepare()
                         ->execute([$goodId], ["fetch", \PDO::FETCH_ASSOC]);
        return is_null($field) ? $goodBase : $goodBase[$field];
    }

    /**
     * get segment base
     * 
     */
    public function getSegmentBase(int $segmentId, bool $lock = false, ?string $field = null) 
    {
        $sql = "select SQL_NO_CACHE * from shop_good_segment where id = ?" . ($lock ? " FOR UPDATE" : "");
        $segment = $this->mysqlDao->setExecuteSql($sql)->prepare()->execute([$segmentId], ["fetch", \PDO::FETCH_ASSOC]);

        return is_null($field) ? $segment : $segment[$field];
    }

    /**
     * check good normally
     * 
     */
    public function checkGoodNormally(int $goodId) : bool
    {
        $goodStatus = $this->getGoodBase($goodId, "status");
        return $goodStatus >= 0;
    }

    /**
     * check segment normally
     * 
     */
    public function checkSegmentNormally(int $segmentId, ?int $count = null) : bool
    {
        $segment = $this->getSegmentBase($segmentId, true);
        if($segment["status"] < 0) {
            return false;
        }

        if(!is_null($count) && $segment["stock"] < $count) {
            return false;
        }

        return true;
    }

    /**
     * get good detail by goodid
     * 
     */
    public function _getGoodDetail(int $goodId, bool $showInvalidStatusSegment = false) : array
    {
        $good = [];
        $goodBase = $this->getGoodBase($goodId);

        if( !$goodBase ) {
            return $good;
        }

        $good["base"] = $goodBase;
        
        $goodSegments = $this->mysqlDao->table("good_segment")
                             ->select(["id as segment_id","price","stock","attr_value_ids","attr_ids", "status"])
                             ->where(["good_id", ["status", ">="]])
                             ->prepare()
                             ->execute([$goodId, $showInvalidStatusSegment ? -1 : 0], ["fetchAll", \PDO::FETCH_ASSOC]);

        $attributesWithValue = $this->getAllAttributesWithValue();
        foreach($goodSegments as $i => &$segment) {
            $attrIds = explode("_", $segment["attr_ids"]);
            $attrValuesIds = explode("_", $segment["attr_value_ids"]);
            $attributes = [];
            for($i = 0;$i < count($attrIds);$i++) {
                $attr = [];
                $attr["attr_id"] = $attrIds[$i];
                $attr["attr_name"] = ($attributesWithValue[$attr["attr_id"]])["name"];
                $attr["attr_value_id"] = $attrValuesIds[$i];
                $attr["attr_value_name"] = @($attributesWithValue[$attr["attr_id"]])["values"][$attr["attr_value_id"]]["name"];
                $attributes[] = $attr;
            }
            $segment["attr"] = $attributes;
            unset($segment["attr_ids"], $segment["attr_value_ids"]);
        }

        $good["attributes"] = array_values($goodSegments);
        $good["images"] = $this->getGoodImages($goodId);

        return $good;
    }

    /**
     * get comments of good
     * 
     */
    public function getGoodComments(int $goodId, array $filters = [], bool $count = false) : array
    {
        $binds = [$goodId];
        $comments = [];
        $page = $filters["page"] ?? 1;
        $size = $filters["size"] ?? 10;

        $baseSql = "select %s from shop_good_comment where good_id = ? and";

        foreach(["uid", "segment_id", "status"] as $option) {
            if(isset($filters[$option]) && $filters[$option]) {
                $baseSql .= " status = ? and" . $filters[$option];
                $binds[] = $filters[$option];
            }
        }

        $baseSql = substr($baseSql, 0, -4);
        if(!isset($filters["sort"]) || !$filters["sort"]) {
            $filters["sort"] = "add_time_desc";
        }

        if( $count ) {
            $countSql = sprintf($baseSql, "count(*) as count");
            $count = ($this->mysqlDao->setExecuteSql($countSql)->prepare()->execute($binds, ["fetch", \PDO::FETCH_ASSOC]))["count"];
        }

        $baseSql .= " order by " . preg_replace("/_(desc|asc)$/i", " $1", $filters["sort"]);
        $baseSql .= " limit " . $size * ($page - 1) . "," . $size;
        $baseSql = sprintf($baseSql, "id");
        $execSql = "select comments_primary.*,segment.attr_value_ids,member.username,member.level,_order.order_no from shop_good_comment as comments_primary 
                    inner join ($baseSql) as comments_second using(id) 
                    left join shop_good_segment as segment on segment.id = comments_primary.segment_id 
                    left join shop_member as member on member.id = comments_primary.uid 
                    left join shop_order as _order on _order.id = comments_primary.order_id";

        $comments = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach($comments as &$comment) {
            $comment["add_time"] = date("Y-m-d H:i:s", $comment["add_time"]);
            $comment["attribute"] = $this->getGoodAttributeString(explode("_", $comment["attr_value_ids"]));
            $comment["avatar"] = $this->getUserAvatar($comment["uid"]);
            $comment["levelName"] = $this->getLevelRule($comment["level"], "name");
        }

        return compact("comments", "count");
    }

    /**
     * get good images
     * return not path that is the result with / not DS
     * 
     */
    public function getGoodImages(int $goodId, bool $withHost = true, bool $thumb = false)
    {
        $detail_images = $banner_images = [];
        $path = ATTACH_PATH . "good_image" . DS . chunk_split(sprintf("%08s", $goodId), 2, DS);
        $prefix = ($withHost ? AppConfig::get("protocol", "global") . AppConfig::get("server_name", "global") . "/" : "/") . "attachment/good_image/" . chunk_split(sprintf("%08s", $goodId), 2, "/");

        foreach(["detail_images", "banner_images"] as $subPathName) {
            $files = array_slice(scandir($path . DS . $subPathName), 2);
            foreach($files as $file) {
                ${$subPathName}[] = $prefix . $subPathName . "/" . $file;
            }
        }

        return $thumb ? $prefix . "thumb.png" : compact("detail_images", "banner_images");
    }

    /**
     * get good list by filter
     * 
     */
    public function getGoodList(array $filters, bool $count = false) : array
    {
        $baseSql = "SELECT %s FROM shop_good WHERE 1 = 1 and";
        $page = $filters["page"] ?? 1;
        $size = $filters["size"] ?? 10;

        $selector = "id";
        $binds = [];

        if(isset($filters["keyword"]) && $filters["keyword"] != "") {
            $baseSql .= " concat(name,name_pinyin,if(isnull(description),'',description),if(isnull(description_pinyin),'',description_pinyin),if(isnull(model),'',model)) like ? and";
            $binds[] = "%%" . $filters["keyword"] . "%%";
        }

        if(isset($filters["tag"]) && (int)$filters["tag"]) {
            $baseSql .= " tag = ? and";
            $binds[] = (int)$filters["tag"];
        }

        if(isset($filters["category_id"]) && (int)$filters["category_id"]) {
            $baseSql .= " category_id = ? and";
            $binds[] = (int)$filters["category_id"];
        }

        if(isset($filters["period"])) {
            $timestamp = EatWhatStatic::getPeriodTimestamp((int)$filters["period"]);
            $baseSql .= " create_time > ? and";
            $binds[] = $timestamp;
        }

        if(!isset($filters["listAll"]) || !$filters["listAll"]) {
            $baseSql .= " status >= ? and";
            $binds[] = 0;
        }
        
        $baseSql = substr($baseSql, 0, -4);
        if(!isset($filters["sort"]) || !$filters["sort"]) {
            $filters["sort"] = "id_desc";
        }

        if( $count ) {
            $countSql = sprintf($baseSql, "count(*) as count");
            $count = ($this->mysqlDao->setExecuteSql($countSql)->prepare()->execute($binds, ["fetch", \PDO::FETCH_ASSOC]))["count"];
        }

        $baseSql .= " order by " . preg_replace("/_(desc|asc)$/i", " $1", $filters["sort"]);
        $baseSql .= " limit " . $size * ($page - 1) . "," . $size;
        $baseSql = sprintf($baseSql, $selector);
        $execSql = "select * from shop_good as good_primary inner join ($baseSql) as good_second using(id)";

        $goods = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);
        $goods = $this->fullGoodInfo($goods);

        return compact("goods", "count");
    }

    /**
     * get good list by attribute
     * 
     */
    public function getGoodListByAttribute(array $filters, bool $count = false) : array
    {
        if(!$filters["attr_value_id"]) {
            return [];
        }

        $page = $filters["page"] ?? 1;
        $size = $filters["size"] ?? 10;
        $attrValueId = $filters["attr_value_id"];

        if(!isset($filters["sort"]) || !$filters["sort"]) {
            $filters["sort"] = "id_desc";
        }

        $baseSql = "select %s from shop_good_segment_attribute where attr_value_id = ? group by good_id limit " . $size * ($page - 1) . "," . $size;
        $execSql = "select id,category_id,model,name,description,stock,price,tag,status,salesnum,views,create_time,update_time,props from shop_good as g 
                    inner join 
                    (" . sprintf($baseSql, "good_id as id") . ") as ga using(id)";
        
        if( $count ) {
            $countSql = sprintf($baseSql, "count(*) as count");
            $count = ($this->mysqlDao->setExecuteSql($countSql)->prepare()->execute([$attrValueId], ["fetch", \PDO::FETCH_ASSOC]))["count"];
        }

        $execSql .= " order by " . preg_replace("/_(desc|asc)$/i", " $1", $filters["sort"]);

        $goods = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute([$attrValueId], ["fetchAll", \PDO::FETCH_ASSOC]);
        $goods = $this->fullGoodInfo($goods);

        return compact("goods", "count");
    }

    /**
     * full good info
     * 
     */
    public function fullGoodInfo(array $goods) : array
    {
        $goodTags = AppConfig::get("good_tags", "global");
        foreach($goods as &$p_good) {
            $p_good["tag_name"] = $goodTags[$p_good["tag"]];
            $p_good["category_name"] = $this->getCategoryNameById($p_good["category_id"]);
            $p_good["create_time"] = date("Y-m-d", $p_good["create_time"]);
            $p_good["update_time"] = date("Y-m-d", $p_good["update_time"]);
            $p_good["thumbnail"] = $this->getGoodThumbnail($p_good["id"]);
        }

        return $goods;
    }

    /**
     * get good attribute string
     * 
     */
    public function getGoodAttributeString(array $attr_value_ids) : string
    {
        $allAttributesValue = $this->_getAllAttributesValue();
        $attribute = "";

        foreach( $attr_value_ids as $attrValueId ) {
            $attrValue = $allAttributesValue[$attrValueId]["name"];
            $attribute .= $attrValue . "/";
        }

        return substr($attribute, 0, -1);
    }

    /**
     * get good thumbnail image
     * 
     */
    public function getGoodThumbnail(int $goodId) : string
    {
        return $this->getGoodImages($goodId, true, true);
    }

    /**
     * increment view of good 1 time
     * 
     */
    public function incrGoodView(int $goodId) : bool
    {
        $this->mysqlDao->setExecuteSql("update shop_good set views = views + 1 where id = ?")->prepare()->execute([$goodId]);

        return $this->mysqlDao->execResult;
    }

    /**
     * update segment and good salesnum
     * 
     */
    public function updateSegmentSalesnum(int $segmentId, int $count, ?int $goodId = null) : bool
    {
        if( !$this->mysqlDao->hasTransaction ) {
            $commit = true;
            $this->mysqlDao->beginTransaction();
        } else {
            $commit = false;
        }

        $this->mysqlDao->setExecuteSql("update shop_good_segment set salesnum = salesnum + ? where id = ?")->prepare()->execute([$count, $segmentId]);
        
        if( is_null($goodId) ) {
            $goodId = $this->getSegmentBase($segmengId, false, "good_id");
        }
        $this->mysqlDao->setExecuteSql("update shop_good set salesnum = salesnum + ?, update_time = ? where id = ?")->prepare()->execute([$count, time(), $goodId]);

        $commit && ($this->mysqlDao->commit());

        return $this->mysqlDao->execResult;
    }

    /**
     * update segment count when generate a new order
     * 
     */
    public function updateSegmentStock(int $segmentId, int $count) : bool 
    {
        $sql = "update shop_good_segment set stock = stock " . ($count < 0 ? "-" : "+") . " ? where id = ?";
        $this->mysqlDao->setExecuteSql($sql)->prepare()->execute([abs($count), $segmentId]);

        return $this->mysqlDao->execResult;
    }

    /**
     * update order good info after order edit
     * 
     */
    public function updateOrderGoodInfoAfterGoodEdit(int $goodId, array $newGoodBase)
    {
        $orderGoods = $this->getOrderGoodsByGoodId($goodId);
        if(!empty($orderGoods)) {
            $updateIds = [];
            array_map(function($v) use(&$updateIds){
                $updateIds[] = $v["id"];
            }, $orderGoods);

            $change = [];
            foreach(["model", "name", "description"] as $option) {
                if(isset($newGoodBase[$option]) && $orderGoods[0]["good_" . $option] != $newGoodBase[$option]) {
                    $change["good_" . $option] = $newGoodBase[$option];
                }
            }

            if(!empty($change)) {
                $dao = $this->mysqlDao->table("order_good")->update(array_keys($change))->in("id", count($updateIds))->prepare();
                $change = array_merge($change, $updateIds);
                $dao->execute(array_values($change));
            }
        }
    }
}