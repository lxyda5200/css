<?php


namespace app\business\model;


use think\Model;

class StoreModel extends Model
{

    protected $pk = 'id';

    protected $table = 'product_order';

    /**
     *  获取指定状态订单列表数据
     * @param $store_id   商铺ID
     * @param int $type   订单类型
     * @param int $page   页数
     * @param int $size   每页条数
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderListByType($store_id, $type = 0, $page = 1, $size = 20){
        $where['store_id'] = $store_id;
        if ($type) $where['order_status'] = $type;
        $dataList = self::where($where) -> select();

        return $dataList;
    }

    /**
     * 获取订单数量根据订单状态
     * @param $store_id  店铺ID
     * @param $status    查询订单状态数组
     * @param $user_info 用户信息
     * @param bool $is_store 是否是总店（true代表首页我的任务页面数据）
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getHomeOrTaskOrderNum($store_id, $status,$user_info,$is_store = false){
        // 代付款、代发货等订单查询条件
        $where = ['p.store_id' => $store_id, 'p.order_status' => ['in', $status]];
        if ($is_store){
            $where = ['p.store_id' => $store_id, 'ob.buniess_id' => $user_info['id'],'p.order_status' => ['in', $status]];
        }
        if ($user_info['is_main_user'] == 1){
            // 商城订单查询条件
            $shopWhere = ['p.store_id' => $store_id, 'p.order_status' => ['egt', 6],'ob.type' => 1];
            // 买单订单查询条件
            $maiWhere = ['p.store_id' => $store_id, 'p.order_status' => ['egt', 6],'ob.type' => 2];
        }else{
            $shopWhere = ['p.store_id' => $store_id, 'ob.buniess_id' => $user_info['id'], 'p.order_status' => ['egt', 6],'ob.type' => 1];
            $maiWhere = ['p.store_id' => $store_id,  'ob.buniess_id' => $user_info['id'], 'p.order_status' => ['egt', 6],'ob.type' => 2];
        }
        // 本月数据条件
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end = strtotime(date('Y-m-d H:i:s'));
        $monthWhere = ['p.create_time' => ['between', "{$start},{$end}"]];
        $shopWhere = array_merge($shopWhere,$monthWhere);
        $maiWhere = array_merge($maiWhere,$monthWhere);

        // 店铺总订单
        $orderList = OrderBusinessModel::getOrderStatusNumByWhere($where);

        // 订单状态重复数组总数统计为数组
        $orderStatusList = array_count_values(array_column($orderList, 'order_status'));

        // 商城订单总数 order_status >= 6
        $shopOrderNum = OrderBusinessModel::getOrderNumByWhere($shopWhere);

        // 到店订单总数
        $maiOrderNum = OrderBusinessModel::getOrderNumByWhere($maiWhere);

        return compact('orderStatusList','shopOrderNum', 'maiOrderNum');
    }

}