<?php


namespace app\business\model;


use think\Model;

class OrderDetailsModel extends Model
{

    protected $pk = 'id';

    protected $table = 'product_order_detail';

    public function getProductSpecsAttr($value)
    {
        return json_decode($value, true);
    }
}