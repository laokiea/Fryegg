<?php

namespace EatWhat\Traits;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\EatWhatStatic;

/**
 * Car Traits For User Api
 * 
 */
trait CarTrait
{
        /**
     * check car good exists
     * 
     */
    public function checkCarGoodExists(int $uid, int $segmentId, ?int $goodId = null)
    {
        $where = ["uid", "segment_id"];
        $binds = [$uid, $segmentId];

        if( !is_null($goodId) ) {
            array_push($where, "good_id");
            array_push($binds, $goodId);
        }

        $carGood = $this->mysqlDao->table("car")
                        ->select(["*"])
                        ->where($where)
                        ->prepare()
                        ->execute($binds, ["fetch", \PDO::FETCH_ASSOC]);

        return $carGood;
    }

    /**
     * add shop car good
     * [unique -> good_id|uid|segment_id]
     * 
     */
    public function _addCarGood(array $carGood) : int
    {
        $this->mysqlDao->table("car")
             ->insert(array_keys($carGood));
        
        $this->mysqlDao->executeSql .= " ON DUPLICATE KEY UPDATE good_count = good_count + VALUES(good_count)";
        $this->mysqlDao->prepare()->execute(array_values($carGood));

        return $this->mysqlDao->getLastInsertId();
    }

    /**
     * get car good info
     * 
     */
    public function getCarGoodInfo(int $carGoodId)
    {
        $carGood = $this->mysqlDao->table("car")
                        ->select("*")->where([["id", "="]])
                        ->prepare()
                        ->execute([$carGoodId], ["fetch", \PDO::FETCH_ASSOC]);

        return $carGood;
    }

    /**
     * edit car good count
     * 
     */
    public function _editCarGoodCount(int $carGoodId, int $count) : bool 
    {
        $this->mysqlDao->table("car")
             ->update(["good_count"])
             ->where(["id"])->prepare()
             ->execute([$count, $carGoodId]);

        return $this->mysqlDao->execResult;
    }

    /**
     * get all car good
     * 
     */
    public function getUserAllCarGood(int $uid) : array
    {
        $carGood = [];
        $selector = ["cargood.*", "good.name", "good.description", "good.model", "good.status as good_status", "segment.status as segment_status", "segment.price", "segment.stock", "segment.price", "segment.attr_value_ids"];
        $carGoods = $this->mysqlDao->table("car")
                         ->select($selector)->alias("cargood")
                         ->leftJoin("good", "good")->on("cargood.good_id = good.id")
                         ->leftJoin("good_segment", "segment")->on("cargood.segment_id = segment.id")
                         ->where(["cargood.uid"])
                         ->prepare()
                         ->execute([$uid], ["fetchAll", \PDO::FETCH_ASSOC]);
        
        foreach($carGoods as &$p_cargood) {
            $p_cargood["thumbnail"] = $this->getGoodThumbnail($p_cargood["good_id"]);
            $p_cargood["add_time"] = date("Y-m-d H:i:s", $p_cargood["add_time"]);
            $p_cargood["attribute"] = $this->getGoodAttributeString(explode("_", $p_cargood["attr_value_ids"]));
        }

        return $carGoods;
    }

    /**
     * delete car good
     * 
     */
    public function _deleteCarGood($carGoodIds) : bool 
    {
        if( !is_array($carGoodIds) ) {
            $carGoodIds = (array)$carGoodIds;
        }

        $this->mysqlDao->table("car")->delete()->in("id", count($carGoodIds))->prepare()->execute($carGoodIds);

        return $this->mysqlDao->execResult;
    }
}