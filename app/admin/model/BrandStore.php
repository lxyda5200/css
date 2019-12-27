<?php


namespace app\admin\model;


use think\Model;

class BrandStore extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 删除品牌商家绑定
     * @param $brand_id
     * @return int
     */
    public static function delByBrandId($brand_id){
        return self::where(['brand_id'=>$brand_id,'type'=>1])->delete();
    }

    /**
     * 获取店铺品牌名
     * @param $store_id
     * @return string
     */
    public static function getStoreBrandName($store_id){
        $brand_name = (new self())->alias('bs')
            ->join('brand b','b.id = bs.brand_id','LEFT')
            ->where(['store_id'=>$store_id,'is_selected'=>1])
            ->value('brand_name');
        return $brand_name?:"无";
    }

}