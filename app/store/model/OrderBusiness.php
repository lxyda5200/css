<?php


namespace app\store\model;


use think\Model;

class OrderBusiness extends Model
{

    /**
     * 增加订单操作记录
     * @param $order_info
     * @return int|string
     */
    public static function addHandleLog($order_info){
        $data = [
            'order_id' => $order_info['id'],
            'type' => 1,
            'after_send' => 0
        ];
        #获取员工id
        $business_id = Business::getStoreMainBusinessId($order_info['store_id']);
        $data['buniess_id'] = (int)$business_id;
        return self::add($data);
    }

    /**
     * 增加
     * @param $data
     * @return int|string
     */
    protected static function add($data){
        return (new self())->insert($data);
    }

}