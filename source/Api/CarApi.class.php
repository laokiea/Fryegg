<?php

namespace EatWhat\Api;

use EatWhat\AppConfig;
use EatWhat\EatWhatLog;
use EatWhat\Base\ApiBase;
use EatWhat\EatWhatStatic;

/**
 * Car Api
 * 
 */
class CarApi extends ApiBase
{
    /**
     * use Trait
     */
    use \EatWhat\Traits\CarTrait,\EatWhat\Traits\CommonTrait,\EatWhat\Traits\GoodTrait;
    use \EatWhat\Traits\UserTrait,\EatWhat\Traits\OrderTrait;

    /**
     * add to shop car
     * 添加商品至购物车
     * @param void
     * 
     */
    public function addCarGood() : void
    {
        $this->checkPost();
        $this->checkParameters(["good_id" => ["int", "nonzero"], "count" => ["int", "nonzero"], "segment_id" => ["int", "nonzero"]]);

        $goodId = (int)$_GET["good_id"];
        $count = (int)$_GET["count"];
        $segmentId = (int)$_GET["segment_id"];
        $existsCarGood = $this->checkCarGoodExists($this->uid, $segmentId, $goodId);

        if(($this->getSegmentBase($segmentId))["good_id"] != $goodId) {
            $this->generateStatusResult("serverError", -404);
        }

        if(!$this->checkGoodNormally($goodId)) {
            $this->generateStatusResult("goodWithWrongStatus", -3);
        }

        if(!$this->checkSegmentNormally($segmentId, !$existsCarGood ? $count : $count + $existsCarGood["good_count"])) {
            $this->generateStatusResult("goodUnderStock", -2);
        }

        $carGood = [];
        $carGood["uid"] = $this->uid;
        $carGood["good_id"] = $goodId;
        $carGood["good_count"] = $count;
        $carGood["segment_id"] = $segmentId;
        $carGood["add_time"] = time();

        $carGoodId = $this->_addCarGood($carGood);

        $this->generateStatusResult("addSuccess", 1);
        $this->outputResult([
            "carGoodId" => $carGoodId,
        ]);
    }

    /**
     * edit car good count
     * 编辑购物车商品数量
     * @param void
     * 
     */
    public function editCarGoodCount() : void 
    {
        $this->checkPost();       
        $this->checkParameters(["cargood_id" => ["int", "nonzero"], "count" => ["int", "nonzero"]]);

        $carGoodId = (int)$_GET["cargood_id"];
        $count = (int)$_GET["count"];
        $carGoodInfo = $this->getCarGoodInfo($carGoodId);

        if($this->uid != $carGoodInfo["uid"]) {
            $this->request->generateStatusResult("serverError", -404);
        }

        if(!$this->checkSegmentNormally($carGoodInfo["segment_id"], $count)) {
            $this->generateStatusResult("goodUnderStock", -2);
        }

        $this->_editCarGoodCount($carGoodId, $count);

        $this->generateStatusResult("updateSuccess", 1);
        $this->outputResult();
    }

    /**
     * delete car good
     * 删除购物车商品
     * @param void
     * 
     */
    public function deleteCarGood() : void 
    {
        $this->checkPost();
        $this->checkParameters(["cargood_ids" => ["array_int", "array_nonzero"]]);

        foreach( $_GET["cargood_ids"] as $carGoodId ) {
            $carGoodInfo = $this->getCarGoodInfo($carGoodId);
            if($this->uid != $carGoodInfo["uid"]) {
                $this->request->generateStatusResult("serverError", -404);
            }
        }

        $this->_deleteCarGood($_GET["cargood_ids"]);

        $this->generateStatusResult("deleteSuccess", 1);
        $this->outputResult();
    }

    /**
     * get all car good
     * 获取所有购物车商品
     * @param void
     * 
     */
    public function listAllCarGood() : void
    {
        $carGoods = $this->getUserAllCarGood($this->uid);
        
        $this->generateStatusResult("200 ok", 1, false);
        $this->outputResult([
            "cargoods" => $carGoods,
        ]);
    }
}