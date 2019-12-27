<?php


namespace app\admin\model;


use think\Model;

class CouponValidate extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取优惠券的总核销数
     * @param $coupon_rule_id
     * @return int|string
     */
    public static function getCouponValidateNum($coupon_rule_id){
        return (new self())->where(['coupon_rule_id'=>$coupon_rule_id])->count('id');
    }

    /**
     * 获取优惠券的平台补贴总金额
     * @param $coupon_rule_id
     * @return float|int
     */
    public static function getPlatformSubsidy($coupon_rule_id){
        return (new self())->where(['coupon_rule_id'=>$coupon_rule_id])->sum('platform_price');
    }

}