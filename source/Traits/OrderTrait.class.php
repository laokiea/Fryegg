<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * Car Traits For User Api
 * 
 */
trait OrderTrait
{
    /**
     * generate a unique order no
     * 
     */
    public function generateOrderNo() : string
    {
        $this->mysqlDao->table("orderno_unique")->insert([])->prepare()->execute([]);
        $uniqueId = $this->mysqlDao->getLastInsertId();

        list($usec,) = explode(" ", microtime());
        $orderNo = date("Ym") . substr((string)$usec, -4) . substr(sprintf("%010s", $this->uid), -4) . $uniqueId;

        return $orderNo;
    }

    /**
     * get postage by province
     * 
     */
    public function getPostageByProvince(int $provinceId) : float
    {
        $provincePostage = $this->getSetting("provincePostage");
        if( $provincePostage && isset($provincePostage[$provinceId]) ) {
            $postage = $provincePostage[$provinceId];
        } else {
            $postage = $this->getSetting("defaultPostage");
        }

        return $postage;
    }
    
    /**
     * get base info of a order
     * 
     */
    public function getOrderBaseInfo($orderId, $field = null, bool $isOrderNo = false) 
    {
        $orderBase = $this->mysqlDao->table("order")
                          ->select(["*"])->where([!$isOrderNo ? "id" : "order_no"])
                          ->prepare()->execute([$orderId], ["fetch", \PDO::FETCH_ASSOC]);

        $orderBase["create_time_stamp"] = $orderBase["create_time"];
        $orderBase["create_time"] = date("Y-m-d H:i:s", $orderBase["create_time"]);
        $orderBase["pay_time"] && ($orderBase["pay_time"] = date("Y-m-d H:i:s", $orderBase["pay_time"]));
        $orderBase["order_status_flag"] = (AppConfig::get("order_status", "global"))[$orderBase["order_status"]];
        $orderBase["pay_channel_flag"] = @(AppConfig::get("pay_channel", "global"))[$orderBase["pay_channel"]];

        if(!is_null($field)) {
            if(is_array($field)) {
                $return = [];
                foreach($field as $f) {
                    $return[$f] = $orderBase[$f];
                }
                return $return;
            } else {
                return $orderBase[$field];
            }
        }

        return $orderBase;
    }

    /**
     * check order expired
     * 
     */
    public function checkOrderExpired() : string
    {
        $orders = $this->mysqlDao->table("order")
                       ->select(["o.id", "o.create_time","og.good_count","og.segment_id"])->alias("o")
                       ->leftJoin("order_good", "og")->on("o.id = og.order_id")
                       ->where(["o.order_status"])
                       ->prepare()->execute([0], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        if( $orders ) {
            $orderids = [];
            foreach($orders as $order) {
                if($order["create_time"] + 15 * 60 <= time()) {
                    $this->updateOrderStatus($order["id"], -4);
                    $this->updateSegmentStock($order["segment_id"], $order["good_count"]);
                    $orderids[] = $order["id"];
                }
            }
            
            if($orderids) {
                return "expired order: " . implode(", ", array_unique($orderids));
            }
        }

        return "";
    }

    /**
     * check order correct
     * 
     */
    public function checkOrderBeforePay(string $orderNo)
    {
        $orderInfo = $this->getOrderBaseInfoByOrderNo($orderNo, null, true);

        if( !$orderInfo ) {
            return false;
        }

        if($orderInfo["uid"] != $this->uid) {
            return false;
        }

        if($orderInfo["order_status"] != 0) {
            return false;
        }

        if($orderInfo["create_time_stamp"] + 15 * 60 <= time()) {
            return false;
        }

        return $orderInfo;
    }

    /**
     * get goods of an order by order id
     * 
     */
    public function getOrderGoods(int $orderId) : array
    {
       return $this->getOrderGoodsByField("order_id", $orderId);
    }

    /**
     * get goods of an order by good id
     * 
     */
    public function getOrderGoodsByGoodId(int $goodId) : array
    {
        return $this->getOrderGoodsByField("good_id", $goodId);
    }

    /**
     * get goods of an order by one significance field
     * 
     */
    public function getOrderGoodsByField(string $field, $fieldValue) : array
    {
        $orderGoods = $this->mysqlDao->table("order_good")
                          ->select("*")->where([$field])
                          ->prepare()->execute([$fieldValue], ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach($orderGoods as &$good) {
            $good["thumbnail"] = $this->getGoodThumbnail($good["good_id"]);
            $good["good_attribute"] = $this->getGoodAttributeString(explode("_", $good["attr_value_ids"]));
        }
       
        return $orderGoods;
    }

    /**
     * get order status
     * 
     */
    public function getOrderStatus(int $orderId) : int
    {
        return ($this->getOrderBaseInfo($orderId, "order_status"))["order_status"];
    }

    /**
     * get order by order no
     * 
     */
    public function getOrderBaseInfoByOrderNo(string $orderNo, $field = null) 
    {
        return $this->getOrderBaseInfo($orderNo, $field, true);
    }

    /**
     * update order status
     * 
     */
    public function updateOrderStatus(int $orderId, int $status) : bool
    {
        $this->mysqlDao->table("order")
            ->update(["order_status", "update_time"])->where(["id"])
            ->prepare()->execute([$status, time(), $orderId]);
        
        return $this->mysqlDao->execResult;
    }

    /**
     * check order status pass by is correctly
     * 
     */
    public function checkOrderStatusAvaliable(int $orderStatus) : bool 
    {
        $orderStatusFlags = AppConfig::get("order_status", "global");

        if(!in_array($orderStatus, array_keys($orderStatusFlags))) {
            return false;
        }

        return true;
    }

    /**
     * get orders by filter
     * 
     */
    public function getOrderList(array $filters, bool $count = false)
    {
        $binds = [];
        $selector = "id";
        $page = $filters["page"] ?? 1;
        $size = $filters["size"] ?? 10;

        $baseSql = "SELECT %s FROM shop_order WHERE 1 = 1 and";

        if(isset($filters["order_status"])) {
            $baseSql .= " order_status = ? and";
            $binds[] = (int)$filters["order_status"];
        }

        if(isset($filters["period"])) {
            $timestamp = EatWhatStatic::getPeriodTimestamp((int)$filters["period"]);
            $baseSql .= " create_time > ? and";
            $binds[] = $timestamp;
        }

        if(isset($filters["uid"])) {
            $baseSql .= " uid = ? and";
            $binds[] = (int)$filters["uid"];
        }

        if(isset($filters["order_no"])) {
            $baseSql .= " order_no = ? and";
            $binds[] = $filters["order_no"];
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
        $execSql = $baseSql;

        $orders = $this->mysqlDao->setExecuteSql($execSql)->prepare()->execute($binds, ["fetchAll", \PDO::FETCH_ASSOC]);

        foreach( $orders as &$order ) {
            $orderId = $order["id"];
            if(!isset($filters["manage"])) {
                $order["base"] = $this->getOrderBaseInfo($orderId);
                $order["goods"] = $this->getOrderGoods($orderId);   
                unset($order["id"]); 
            } else {
                $order = $this->getOrderBaseInfo($orderId);
            }
        }
        
        return compact("orders", "count");
    }

    /**
     * get single order detail
     *
     */
    public function getOrderDetail(int $orderId) : array
    {
        $base = $this->getOrderBaseInfo($orderId);
        $goods = $this->getOrderGoods($orderId);
        $address = $this->getAddressInfo($base["address_id"]);

        return compact("base", "goods", "address");
    }

    /**
     * update order pay time
     * 
     */
    public function updateOrderInfo(int $orderId, array $change) : bool
    {
        $dao = $this->mysqlDao->table("order")
               ->update(array_keys($change))->where(["id"]);

        array_push($change, $orderId);
        $dao->prepare()->execute(array_values($change));
        
        return $this->mysqlDao->execResult;
    }

    /**
     * get user discount of an order
     * 
     */
    public function getUserDiscount(int $uid, array $goods) : string
    {
        bcscale($this->getSetting("decimalPlaces"));

        $discount = "0.0";
        $discountRatio = $this->getUserLevelDiscountRatio($uid);

        foreach( $goods as $good ) {
            $orderGood = [];
            $goodInfo = $this->getGoodBase($good["good_id"]);

            if( !$goodInfo["nodiscount"] ) {
                $segmentInfo = $this->getSegmentBase($good["segment_id"], true);
                $goodMoney = bcmul($segmentInfo["price"], $good["good_count"]);
                $discount = bcadd($discount, bcmul($goodMoney, bcsub("1.0", $discountRatio)));
            }
        }

        return $discount;
    }
}