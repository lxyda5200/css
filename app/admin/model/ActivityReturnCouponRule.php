<?php


namespace app\admin\model;


use think\Model;

class ActivityReturnCouponRule extends Model
{

    public static function add($activity_id, $data){
        if(self::del($activity_id) === false)return false;
        return (new self())->insertAll($data);
    }

    public static function del($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->delete();
    }

    public static function getActivityRtnCoupon($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->field('id,satisfy_money,coupon_id')->select();
    }

    public static function getActivityRtnCouponInfo($activity_id){
        return (new self())->alias('arc')
            ->join('coupon_rule cr','arc.coupon_id = cr.id','LEFT')
            ->where(['arc.activity_id'=>$activity_id])
            ->field('arc.id,arc.satisfy_money,arc.coupon_id,cr.coupon_name')
            ->select();
    }

}