<?php


namespace app\store_v1\model;


use think\Db;
use think\Model;

class StoreUser extends Model
{
    /**
     * 账号关联店铺操作
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function up_StoreUser($user_id,$store_id){
        return Db::table('business')->where(['id'=>$user_id])->update(['store_id'=>$store_id,'main_id'=>$store_id]);
    }
}