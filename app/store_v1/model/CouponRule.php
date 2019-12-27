<?php


namespace app\store_v1\model;


use think\Model;

class CouponRule extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 增加优惠券使用数
     * @param $coupon_id
     * @return int|true
     */
    public static function addUseNum($coupon_id){
        return (new self())->where(['id'=>$coupon_id])->setInc('use_number');
    }

}