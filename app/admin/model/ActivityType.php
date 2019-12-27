<?php


namespace app\admin\model;


use think\Model;

class ActivityType extends Model
{

    public static function add($data){
        return (new self())->insertGetId($data);
    }

    public static function del($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->delete();
    }

    /**
     * 获取当前活动的自定义类别
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityType($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->field('id,type_name')->select();
    }

}