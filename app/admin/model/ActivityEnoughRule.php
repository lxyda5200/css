<?php


namespace app\admin\model;


use think\Model;

class ActivityEnoughRule extends Model
{

    public static function add($activity_id,$data){
        if(self::del($activity_id) === false)return false;
        return (new self())->insertAll($data);
    }

    public static function del($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->delete();
    }

    /**
     * 获取活动满减规则
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityRule($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->field('id,satisfy_money,discount_money')->select();
    }

}