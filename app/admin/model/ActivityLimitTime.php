<?php


namespace app\admin\model;


use think\Model;

class ActivityLimitTime extends Model
{

    public static function add($activity_id, $data){
        if(self::del($activity_id) === false)return false;
        return (new self())->insertAll($data);
    }

    public static function del($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->delete();
    }

    public static function getActivityLimitTime($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->field('start_time,end_time,week_day')->select();
    }

}