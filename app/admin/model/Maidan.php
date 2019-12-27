<?php


namespace app\admin\model;


use think\Model;

class Maidan extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time','status','putong_user'];

    /**
     * 获取店铺买单信息
     * @param $store_id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getStoreMaidan($store_id){
        $maidan_info = (new self())->where(['store_id'=>$store_id,'status'=>1])->field('putong_user,member_user')->order('create_time','desc')->find();
        if(!$maidan_info)$maidan_info = ['putong_user'=>10,'member_user'=>10];
        return $maidan_info;
    }

    /**
     * 编辑会员买单折扣
     * @param $store_id
     * @param $member_user
     * @return Maidan|int
     */
    public static function editStoreMaiDanMember($store_id, $member_user){
        if(self::checkStoreMaiDanExist($store_id)){  //修改
            return self::updateStoreMaiDanMember($store_id, $member_user);
        }else{  //新增
            return self::add($store_id, $member_user);
        }
    }

    /**
     * 判断店铺的会员折扣是否存在
     * @param $store_id
     * @return int|string
     */
    public static function checkStoreMaiDanExist($store_id){
        return (new self())->where(['store_id'=>$store_id])->count('id');
    }

    /**
     * 更新会员买单折扣
     * @param $store_id
     * @param $member_user
     * @return int
     */
    public static function updateStoreMaiDanMember($store_id, $member_user){
        return (new self())->where(['store_id'=>$store_id])->setField('member_user',$member_user);
    }

    /**
     * 新增店铺买单折扣
     * @param $store_id
     * @param $member_user
     * @return Maidan
     */
    public static function add($store_id, $member_user){
        $data = compact('store_id','member_user');
        return self::create($data);
    }

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setStatusAttr(){
        return 1;
    }

    protected function setPutongUserAttr(){
        return 10.00;
    }

}