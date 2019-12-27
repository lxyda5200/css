<?php


namespace app\store\model;


use think\Model;

class Business extends Model
{

    /**
     * 获取店铺店长员工id
     * @param $store_id
     * @return mixed
     */
    public static function getStoreMainBusinessId($store_id){
        return (new self())->where(['store_id'=>$store_id, 'pid'=>0])->value('id');
    }

}