<?php


namespace app\admin\model;


use think\Model;

class ProductSpecs extends Model
{

    public static function getBaseInfo($product_ids){
        return (new self())->where(['product_id'=>['IN',$product_ids]])->field('id,price')->select();
    }

    public static function updateActivityPrice($id, $activity_price){
        return (new self())->where(['id'=>$id])->setField('price_activity_temp',$activity_price);
    }

}