<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/6
 * Time: 16:31
 */

namespace app\user_v3\model;


use think\Model;

class GoodsOrder extends Model
{
    protected $resultSetType = "collection";

   # protected $hidden = ['pay_time,confirm_time,cancel_time'];

    public function orderDetail(){
        return $this->hasMany('GoodsOrderDetail');
    }

}