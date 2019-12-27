<?php


namespace app\admin\model;


use think\Model;

class DynamicNavigationRecord extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 计算几天内的量
     * @param $dynamic_id
     * @param $days
     * @return int|string
     */
    public static function countByDays($dynamic_id, $days){
        $limit_time = self::createLimitTime($days);
        return self::countByTime($dynamic_id, $limit_time, $limit_time['end_time']);
    }

    /**
     * 生成限制时间段
     * @param $days
     * @return array
     */
    public static function createLimitTime($days){
        ##结束时间为今天的00:00:00
        $end_time = strtotime(date('Y-m-d') . " 23:59:59") - 24 * 60 * 60;
        $start_time = $end_time - $days * 24 * 60 * 60;
        return compact('start_time','end_time');
    }

    /**
     * 计算某时间段、某动态的量
     * @param $dynamic_id
     * @param $start_time
     * @param $end_time
     * @return int|string
     */
    public static function countByTime($dynamic_id, $start_time, $end_time){
        return (new self())->where(['dynamic_id'=>$dynamic_id,'create_time'=>['BETWEEN', [$start_time, $end_time]]])->count('id');
    }

    /**
     * 获取时间列表的量
     * @param $dynamic_id
     * @param $limit_time
     * @return mixed
     */
    public static function countByTimeList($dynamic_id, $limit_time){
        foreach($limit_time as &$v){
            $v['num'] = self::countByTime($dynamic_id, $v['start_time'], $v['end_time']);
        }
        return $limit_time;
    }

    /**
     * 获取动态总量
     * @param $dynamic_id
     * @return int|string
     */
    public static function countAll($dynamic_id){
        return (new self())->where(['dynamic_id'=>$dynamic_id])->count('id');
    }

}