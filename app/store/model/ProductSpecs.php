<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/15
 * Time: 10:06
 */

namespace app\store\model;


use think\Model;

class ProductSpecs extends Model
{

    protected $pk = 'id';

    protected $table = 'product_specs';

    protected $resultSetType = '\think\Collection';

    public function product(){
        return $this->belongsTo('Product','product_id','id');
    }

    /**
     * 获取商品规格
     * @param $product_id
     * @return array
     */
    public static function getProSpecs($product_id){
        return (new self())->where(['product_id'=>$product_id])->field('id as specs_id,price,price as group_buy_price,product_specs')->select()->toArray();
    }

    /**
     * 格式化规格名
     * @param $value
     * @return string
     */
    public function getProductSpecsAttr($value){
        return implode('-',array_values(json_decode($value,true)));
    }

    /**
     * 获取商品最低价
     * @param $product_id
     * @return mixed
     */
    public static function getProMinPrice($product_id){
        return (new self())->where(['product_id'=>$product_id])->order('price','asc')->value('price');
    }

}