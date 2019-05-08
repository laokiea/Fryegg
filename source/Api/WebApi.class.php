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
class WebApi extends ApiBase
{
    use \EatWhat\Traits\WebTrait;
    use \EatWhat\Traits\CommonTrait;

    /**
     * list web feeds
     * 获取官网动态列表
     * @param void
     * 
     */
    public function listWebFeed() : void
    {
        $page = $_GET["page"] ?? 1;
        $size = $_GET["size"] ?? 18;

        extract($this->getWebFeedList($page, $size));
        
        $pagemore = $page * $size + count($feeds) < $count ? 1 : 0;

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult(compact(["feeds", "page", "count", "pagemore"]));
    }

    /**
     * get web feedd detail
     * 获取官网动态详情
     * @param void
     * 
     */
    public function webFeedDetail() : void
    {
        $this->checkParameters(["feed_id" => ["int", "nonzero"]]);

        $feed = $this->getWebFeedDetail($_GET["feed_id"]);

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "feed" => $feed,
        ]);
    }

    /**
     * get web feed detail
     * 获取喜勃品牌介绍详情
     * @param void
     * 
     */
    public function webBrandDetail() : void
    {
        $brand = $this->getWebBrandDetail(1);

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "brand" => $brand,
        ]);
    }

    /**
     * get web good detail
     * 获取官网产品详情
     * @param void
     * 
     */
    public function webGoodDetail() : void
    {
        $this->checkParameters(["good_id" => ["int", "nonzero"]]);

        $good = $this->getWebGoodDetail($_GET["good_id"]);

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "good" => $good,
        ]);
    }

    /**
     * list web banners
     * 获取官网banner列表
     * @param void
     * 
     */
    public function listWebBanner() : void
    {
        $banners = $this->getWebBannerList();

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "banners" => $banners,
        ]);
    }

    /**
     * list web good categories
     * 获取官网商品分类列表
     * @param void
     * 
     */
    public function listWebGoodCategory() : void
    {
        $category = $this->getWebGoodCategoryList();

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "category" => $category,
        ]);
    }

    /**
     * list web goods
     * 获取官网good列表
     * @param void
     * 
     */
    public function listWebGood() : void
    {
        $goods = $this->getWebGoodList();

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "goods" => $goods,
        ]);
    }

    /**
     * get web static page content
     * 获取官网静态页面内容
     * @param void
     * 
     */
    public function webStaticContent() : void
    {
        $this->checkParameters(["type" => null]);

        $static = $this->getWebStaticContent($_GET["type"]);

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "static" => $static,
        ]);
    }

    /**
     * get web index page goods
     * 获取官网首页产品
     * @param void
     * 
     */
    public function webIndexGood() : void
    {
        $goods = $this->getWebIndexGoods();

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "goods" => $goods,
        ]);
    }

    /**
     * search web good
     * 关键字搜索产品
     * @param void
     * 
     */
    public function searchWebGood() : void
    {
        $this->checkPost();
        $this->checkParameters(["keyword" => null]);

        $goods = $this->_searchWebGood($_GET["keyword"]);

        $this->generateStatusResult("200 Ok", 1, false);
        $this->outputResult([
            "goods" => $goods,
        ]);
    }
}