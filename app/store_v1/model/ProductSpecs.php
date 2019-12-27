<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/15
 * Time: 10:06
 */

namespace app\store_v1\model;


use think\Model;

class ProductSpecs extends Model
{

    protected $pk = 'id';

    protected $table = 'product_specs';

    public function product(){
        return $this->belongsTo('Product','product_id','id');
    }

    /**
     * 获取商品规格
     * @param $product_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProSpecs($product_id){
        return (new self())->where(['product_id'=>$product_id])->field('id as specs_id,price')->select();
    }

}