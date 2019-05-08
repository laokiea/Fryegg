<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * Web Traits For User Api
 * 
 */
trait WebTrait
{
    /**
     * get web feed list
     *
     */
    public function getWebFeedList(int $page, int $size) : array
    {
        $feeds = [];
        $count = ($this->mysqlDao->table("feed", "web_")->select(["count(*) as count"])->prepare()->execute([], ["fetch", \PDO::FETCH_ASSOC]))["count"];

        $feeds = $this->mysqlDao->table("feed", "web_")->select(["id", "title"])->orderBy(["id" => -1])->limit($page, $size)->prepare()->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach($feeds as &$feed) {
            $feed["thumbnail"] =  $this->getPathById("web_feed_cover", $feed["id"], true) . "thumb.png";
        }

        return compact(["count", "feeds"]);
    }

    /**
     * get web feed detail
     *
     */
    public function getWebFeedDetail(int $feedId) : array
    {
        $feed = [];
        $feed = $this->mysqlDao->table("feed", "web_")->select(["*"])->where(["id"])->prepare()->execute([$feedId], ["fetch", \PDO::FETCH_ASSOC]);
        $feed["cover"] =  $this->getPathById("web_feed_cover", $feed["id"], true) . "cover.png";
        $feed["add_time"] = date("Y-m-d H:i:s", $feed["add_time"]);

        return $feed ?? [];
    }

    /**
     * get web brand detail
     *
     */
    public function getWebBrandDetail(int $brandId) : array
    {
        $brand = $this->mysqlDao->table("brand", "web_")->select(["*"])->where(["id"])->prepare()->execute([$brandId], ["fetch", \PDO::FETCH_ASSOC]);
        $brand["update_time"] = date("Y-m-d H:i:s", $brand["update_time"]);

        return $brand ?? [];
    }

    /**
     * get web banner list
     *
     */
    public function getWebBannerList() : array
    {  
        $banners = $this->mysqlDao->table("banner", "web_")->select(["*"])
                    ->orderBy(["position" => 1])
                    ->prepare()->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach($banners as &$banner) {
            $banner["image"] = $this->getPathById("web_banner_image", $banner["id"], true) . "banner.png";
            $banner["add_time"] = date("Y-m-d H:i:s", $banner["add_time"]);
            if(strpos($banner["url"], "http") === false) {
                $banner["url"] = "http://" . $banner["url"];
            }
        }

        return $banners ?? [];
    }

    /**
     * get web good list
     * 
     */
    public function getWebGoodCategoryList() : array
    {
        $categories = $this->mysqlDao->table("good_category", "web_")->select(["id", "name", "status"])->prepare()->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);
        foreach ($categories as &$category) {
            $urlTo = $this->getPathById("web_good_category_image", $category["id"], true);
            
            if(file_exists($this->getPathById("web_good_category_image", $category["id"], false) . "category.png")) {
                $category["image"] = $urlTo . "category.png";
            }
            if(file_exists($this->getPathById("web_good_category_image", $category["id"], false) . "category_hover.png")) {
                $category["hover_image"] = $urlTo . "category_hover.png";
            }
        }

        return $categories ?? [];
    }

    /**
     * get web good list
     * 
     */
    public function getWebGoodList() : array
    {
        $goods = $this->mysqlDao->table("good", "web_")->select(["id", "name", "price", "category_id"])->orderBy(["category_id" => 1])->prepare()->execute([], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        if( $goods ) {
            $lastCategoryId = -1;
            $goodList = [];

            foreach($goods as &$good) {
                // if($good["category_id"] != $lastCategoryId) {
                //     $goodList[] = [
                //         "id" => -1,
                //         "name" => "",
                //         "thumbnail" => $this->getPathById("web_good_category_image", $good["category_id"], true) . "category.png",
                //     ];
                //     $lastCategoryId = $good["category_id"];
                // }

                $UrlTo = $this->getPathById("web_good_image", $good["id"], true);

                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "thumb.png")) {
                    $good["thumbnail"] = $UrlTo . "thumb.png";
                }

                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "hover_thumb.png")) {
                    $good["thumbnail_hover"] = $UrlTo . "hover_thumb.png";
                }
                $goodList[] = $good;
            }
        } else {
            $goodList = [];
        }

        return $goodList;
    }

    /**
     * get web good detail
     *
     */
    public function getWebGoodDetail(int $goodId) : array
    {
        $good = [];
        $good = $this->mysqlDao->table("good", "web_")->select(["*"])->where(["id"])->prepare()->execute([$goodId], ["fetch", \PDO::FETCH_ASSOC]);

        $bannerImages = [];
        $pathTo = $this->getPathById("web_good_image", $good["id"]);
        $files = array_slice(scandir($pathTo . "banner_images"), 2);

        $UrlTo = $this->getPathById("web_good_image", $good["id"], true);
        foreach($files as $file) {
            $bannerImages[] = $UrlTo . "banner_images/" . $file;
        }

        $good["banner_images"] = $bannerImages;
        if(file_exists($pathTo . "detail.png")) {
            $good["detail_image"] = $UrlTo . "detail.png";
        }
        $good["add_time"] = date("Y-m-d H:i:s", $good["add_time"]);

        $UrlTo = $this->getPathById("web_good_image", $good["id"], true);

        if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "thumb.png")) {
            $good["thumbnail"] = $UrlTo . "thumb.png";
        }
        
        if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "hover_thumb.png")) {
            $good["thumbnail_hover"] = $UrlTo . "hover_thumb.png";
        }

        return $good ?? [];
    }

    /**
     * get web good list
     * 
     */
    public function getWebStaticContent(string $type) : array
    {
        $static = $this->mysqlDao->table("static", "web_")->select(["*"])->where(["type"])->prepare()->execute([$type], ["fetch", \PDO::FETCH_ASSOC]);
        if($static) {
            $static["update_time"] = date("Y-m-d H:i:s", $static["update_time"]);
        } else {
            $static = [];
        }

        return $static;
    }  

    /**
     * get web good list
     * 
     */
    public function getWebIndexGoods() : array
    { 
        $goodIds = (array)$this->getSetting("web_index_good");
        if(!$goodIds) {
            return [];
        }

        $goods = $this->mysqlDao->table("good", "web_")->select(["id", "name", "price", "category_id"])->in("id", count($goodIds))->prepare()->execute($goodIds, ["fetchAll", \PDO::FETCH_ASSOC]);

        if( $goods ) {
            foreach($goods as &$good) {
                $UrlTo = $this->getPathById("web_good_image", $good["id"], true);
                
                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "thumb.png")) {
                    $good["thumbnail"] = $UrlTo . "thumb.png";
                }

                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "hover_thumb.png")) {
                    $good["thumbnail_hover"] = $UrlTo . "hover_thumb.png";
                }
            }
        }

        return $goods ?? [];
    }

    /**
     * search web good
     * 
     */
    public function _searchWebGood(string $keyWord) : array
    { 
        if(!$keyWord) return [];

        $execSql = "select * from web_good where concat(name,if(isnull(model),'',model), if(isnull(description),'',description)) like ? order by id desc";
        $goods = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute(["%" . $keyWord . "%"], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        if( $goods ) {
            foreach($goods as &$good) {
                $UrlTo = $this->getPathById("web_good_image", $good["id"], true);
                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "thumb.png")) {
                    $good["thumbnail"] = $UrlTo . "thumb.png";
                }
                if(file_exists($this->getPathById("web_good_image", $good["id"], false) . "hover_thumb.png")) {
                    $good["thumbnail_hover"] = $UrlTo . "hover_thumb.png";
                }
            }
        }

        return $goods ?? [];
    }
}