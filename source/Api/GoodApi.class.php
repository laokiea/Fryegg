<?php

namespace EatWhat\Api;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\Base\ApiBase;
use EatWhat\EatWhatStatic;

/**
 * User Api
 * 
 */
class GoodApi extends ApiBase
{
    /**
     * use Trait
     */
    use \EatWhat\Traits\GoodTrait,\EatWhat\Traits\CommonTrait,\EatWhat\Traits\UserTrait;
    use \EatWhat\Traits\OrderTrait;

    /**
     * get index banner
     * 获取轮播图
     * @param void
     * 
     */
    public function listBanner() : void
    {
        $bannerCountLimit = $this->getSetting("bannerCountLimit");
        extract($this->getBannerList([
            "size" => $bannerCountLimit,
            "sort" => "position_asc",
        ], true));

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact(["banners", "bannerCountLimit"]));
    }

    /**
     * get goods by filters
     * 按照条件筛选商品
     * @param void
     * 
     */
    public function listGood() : void
    {
        $filters = [];
        
        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;

        foreach(["keyword", "tag", "period", "category_id"] as $option) {
            if(isset($_GET[$option])) {
                $filters[$option] = $_GET[$option];
            }
        }

        extract($this->getGoodList($filters, true));

        if(!empty($goods)) {
            $pagemore = ($page - 1) * $size  + count($goods) == $count ? 0 : 1;
        } else if($page == 1) {
            $recommendGoods = ($this->getGoodList([
                "sort" => "salesnum_desc",
                "size" => 20,
            ], false))["goods"];
            $goods = $recommendGoods;
            $pagemore = 0;
        }

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("goods", "pagemore", "page"));
    }

    /**
     * get goods by attribute
     * 按照属性筛选商品,比如按照颜色-黄色筛选，传入属性值(黄色)id
     * @param void
     * 
     */
    public function listGoodByAttribute() : void
    {
        $this->checkParameters(["attr_value_id" => ["int", "nonzero"]]);

        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;
        $filters["attr_value_id"] = (int)$_GET["attr_value_id"];

        extract($this->getGoodListByAttribute($filters, true));

        if(!empty($goods)) {
            $pagemore = ($page - 1) * $size  + count($goods) == $count ? 0 : 1;
        }

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult(compact("goods", "pagemore", "page"));
    }

    /**
     * get good detail
     * 商品详情
     * @param void
     * 
     */
    public function getGoodDetail() : void
    {   
        $this->checkParameters(["good_id" => ["int", "nonzero"]]);

        $goodId = (int)$_GET["good_id"];

        $this->incrGoodView($goodId);
        $good = $this->_getGoodDetail($goodId, true);
        $attributes = $this->_getAllAttributes();

        $this->generateStatusResult("200 OK", 1);
        $this->outputResult([
            "good" => $good,
            "attributes" => $attributes,
        ]);
    }

    /**
     * add good comment
     * 添加商品评价
     * @param void
     * 
     */
    public function addGoodComment() : void
    {
        $this->checkPost();
        $this->checkParameters(["order_id" => ["int", "nonzero"],"good_id" => ["int", "nonzero"], "segment_id" => ["int", "nonzero"],"status" => ["int", [1, 2, 3]],"comment" => null, "score" => [1, 2, 3, 4, 5]]);

        $orderId = (int)$_GET["order_id"];
        $orderInfo = $this->getOrderBaseInfo($orderId);
        if(!$orderInfo || $orderInfo["uid"] != $this->uid || $orderInfo["order_status"] != 3) {
            $this->generateStatusResult("serverError", -404);
        }

        $comment = $_GET["comment"];
        if( ($length = mb_strlen($comment)) > 255 && $length < 2 ) {
            $this->generateStatusResult("commentLengthWrong", -1);
        }

       $commentId =  $this->insertOneObject([
            "uid" => $this->uid,
            "add_time" => time(),
            "comment" => $comment,
            "order_id" => $orderId,
            "good_id" => (int)$_GET["good_id"],
            "segment_id" => (int)$_GET["segment_id"],
            "status" => (int)$_GET["status"],
            "score" => (int)$_GET["score"],
        ], "good_comment");

        $this->generateStatusResult("addCommentSuccess", 1);
        $this->outputResult([
            "comment_id" => $commentId,
        ]);
    }

    /**
     * get comments of good
     * 获取商品评价
     * @param void
     * 
     */
    public function goodComments() : void
    {
        $this->checkParameters(["good_id" => ["int", "nonzero"]]);

        $filters["page"] = $page = $_GET["page"] ?? 1;
        $filters["size"] = $size = $_GET["size"] ?? 10;
        if(isset($_GET["status"]) && $_GET["status"]) {
            $filters["status"] = (int)$_GET["status"];
        }

        $goodId = $_GET["good_id"];
        extract($this->getGoodComments($goodId, $filters, true));
        $pagemore = ($page - 1) * $size  + count($comments) == $count ? 0 : 1;

        $this->generateStatusResult("200 OK", 1, false);
        $this->outputResult([
            "comments" => $comments,
            "page" => $page,
            "pagemore" => $pagemore,
        ]);
    }
}
