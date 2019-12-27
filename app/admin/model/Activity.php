<?php


namespace app\admin\model;


use think\Model;

class Activity extends Model
{

    protected $dateFormat = false;

    /**
     * 新增活动
     * @param $data
     * @return int|string
     */
    public static function add($data){
        $data['create_time'] = time();
        return (new self())->insertGetId($data);
    }

    /**
     * 更新活动
     * @param $id
     * @param $data
     * @return Activity
     */
    public static function edit($id, $data){
        return (new self())->where(['id'=>$id])->update($data);
    }

    /**
     * 获取已上线活动数量
     * @return int|string
     */
    public static function getNumHas(){
        return (new self())->where(['status'=>['GT',1],'start_time'=>['GT',time()]])->count('id');
    }

    /**
     * 获取待上线活动数量
     * @return int|string
     */
    public static function getNumWait(){
        return (new self())->where(['status'=>2,'start_time'=>['LT',time()]])->count('id');
    }

    /**
     * 获取本月结束活动数量
     * @return int|string
     */
    public static function getNumDoneThisMonth(){
        $month_start = mktime(0,0,0,date('m'),1,date('Y'));;
        $month_end = mktime(23,59,59,date('m'),date('t'),date('Y'));
        return (new self())->where(['status'=>2,'end_time'=>['LT',$month_end],'end_time'=>['GT',$month_start]])->count('id');
    }

    /**
     * 上线活动
     * @param $id
     * @param $data
     * @return Activity
     */
    public static function onlineActivity($id, $data){
        return (new self())->where(['id'=>$id])->update($data);
    }

    /**
     * 下线活动
     * @param $id
     * @return int
     */
    public static function offLine($id){
        return (new self())->where(['id'=>$id])->setField('status',3);
    }

    /**
     * 暂不上线活动
     * @param $id
     * @return int
     */
    public static function tempNotLineActivity($id){
        return (new self())->where(['id'=>$id])->setField('status',1);
    }

    /**
     * 获取能够绑定推荐tab的活动列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getCanUseAcList(){
        return (new self())->where(['status'=>['IN',[1,2]]])->field('id,title')->select();
    }

}