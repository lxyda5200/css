<?php


namespace app\user_v7\common;


use think\Db;
use think\Exception;

class StaffLogic
{

    /**
     * 添加员工收益记录 -- 单条
     * @param $data
     * @throws Exception
     */
    public static function addProfitRecord($data){
        $res = Db::name('bussiness_profit')->insert($data);
        if($res === false)throw new Exception('员工收益记录创建失败');
    }

    /**
     * 添加员工收益记录 -- 多条
     * @param $data
     * @param $maidan_order_id
     * @throws Exception
     */
    public static function addProfitRecords($data, $maidan_order_id){
        foreach($data as $v)$v['maidan_order_id'] = $maidan_order_id;
        $res = Db::name('bussiness_profit')->insertAll($data);
        if($res === false)throw new Exception('员工收益记录创建失败');
    }

}