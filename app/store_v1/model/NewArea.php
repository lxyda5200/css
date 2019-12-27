<?php


namespace app\store_v1\model;


use think\Db;
use think\Model;

class NewArea extends Model
{
    /**
     * 获取地区信息
     * PID 上级iD 默认 1
     */
    public function get_list($pid=1){
        $data = Db::table('new_area')->where(['pid'=>$pid])->select();
        return $data;
    }

    /**
     * 获取地区名
     */
    public function get_name($ids){
        $res = Db::table('new_area')->where(['id'=>$ids])->field('id,name')->find();
        if(empty($res)){
            return null;
        }
        return $res;
    }
}