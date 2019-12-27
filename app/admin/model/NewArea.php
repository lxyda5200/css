<?php


namespace app\admin\model;


use think\Model;

class NewArea extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取省列表
     * @return array
     */
    public static function getProvinceList(){
        return (new self())->where(['level'=>1])->field('id,name')->select()->toArray();
    }

    /**
     * 获取市列表
     * @param $pid
     * @return array
     */
    public static function getCityList($pid){
        return (new self())->where(['pid'=>$pid,'level'=>2])->field('id,name')->select()->toArray();
    }

    /**
     * 获取县列表
     * @param $pid
     * @return array
     */
    public static function getCountyList($pid){
        return (new self())->where(['pid'=>$pid,'level'=>3])->field('id,name')->select()->toArray();
    }

    /**
     * 获取地址名
     * @param $id
     * @return mixed
     */
    public static function getRegionName($id){
        return (new self())->where(['id'=>$id])->value('name');
    }

}