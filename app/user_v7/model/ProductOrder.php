<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/20
 * Time: 9:49
 */

namespace app\user_v7\model;


use think\Exception;
use think\Model;
use think\model\relation\HasMany;

class ProductOrder extends Model
{

    protected $table = 'product_order';

    protected $pk = 'id';

    /**
     * 获取用户最近一个月下单的商家信息
     * @param $user_id
     * @return array
     */
    public static function getUserOrderStoreNearMonth($user_id){
        $limit_time = strtotime("-1 month");
        $store_list = (new self())->alias('po')
            ->join('store s','s.id = po.store_id','LEFT')
            ->where([
                'po.user_id' => $user_id,
                'po.create_time' => ['EGT', $limit_time],
                's.sh_status' => 1,
                's.store_status' => 1,
                's.type' => 1
            ])
            ->group('po.store_id')
            ->order('po.create_time','desc')
            ->field('s.id,s.cover,s.address,s.store_name')
            ->select();
        $store_list = json_decode(json_encode($store_list),true);
        return $store_list;

    }

}