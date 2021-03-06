<?php


namespace app\admin\model;


use think\Model;

class DynamicLookRecord extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取时间区间的量
     * @param $dynamic_id
     * @param $start_time
     * @param $end_time
     * @return int|string
     */
    public static function countByTime($dynamic_id, $start_time, $end_time){
        return (new self())->where(['dynamic_id'=>$dynamic_id, 'create_time'=>['BETWEEN', [$start_time, $end_time]]])->count('id');
    }

    /**
     * 获取动态 总量
     * @param $dynamic_id
     * @return int|string
     */
    public static function countAll($dynamic_id){
        return (new self())->where(['dynamic_id'=>$dynamic_id])->count('id');
    }

}