<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/7
 * Time: 14:56
 */

namespace app\store\model;
use think\Model;

class Store extends Model
{

    /**
     * 获取一个店铺的坐标(经纬度)
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getStorePosition($id){
        return (new self())->where(compact('id'))->field('lng,lat')->find();
    }


    public function bussinessReward() {
        return $this->hasMany(BussinessReward::class, 'store_id', 'id');
    }


    /**
     * 修改到店买单员工提成比例
     * @param $store_id
     * @param $deduct
     * @return Store
     */
    public function changeDeduct($store_id, $deduct) {
        return $this->where(['id' => $store_id])->update(['bussiness_deduct' => $deduct]);
    }


    /**
     * 获取到店买单员工提成比例
     * @param $store_id
     * @return mixed
     */
    public function getDeduct($store_id) {
        return $this->where(['id' => $store_id])->value('bussiness_deduct');
    }
}