<?php


namespace app\business\model;


use think\Model;

class OrderBusinessModel extends Model
{

    protected $pk = 'id';

    protected $table = 'order_business';

    /**
     *  根据条件获取订单数量
     * @param array $where
     * @return int|string
     */
    public static function getOrderNumByWhere($where = []){
        $num = ProductModel::where($where)
            ->alias('p')
            ->join('order_business ob', 'p.id = ob.order_id','left')
            ->count();
        return $num;
    }
    /**
     *  根据条件获取订单数量
     * @param array $where
     * @return int|string
     */
    public static function getOrderNumByWhereMai($where = []){
        $num = MaiOrderModel::where($where)->count();
        return $num;
    }

    /**
     *  根据条件获取满足条件的订单状态列表 以便统计数据
     * @param $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderStatusNumByWhere($where){
        $data = self::where($where)
            ->alias('ob')
            -> join('product_order p', 'p.id = ob.order_id') -> field(['p.order_status']) -> select();

        return $data;
    }

    /**
     * 获取员工商城订单
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessShopOrder($where = []){
        $data = self::where($where)
            ->alias('ob')
            ->join('product_order o', 'ob.order_id = o.id')
            ->field([
                'ob.order_id',
                'o.pay_time','o.pay_money','o.order_status','o.order_no as order_sn'
            ])
            ->select();
        $totalOrderNum = count($data);

        return compact('data', 'totalOrderNum');
    }

    /**
     *  获取员工到店买单
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessStoreOrder($where = []){
        $data = self::where($where)
            ->alias('ob')
            ->join('maidan_order o', 'ob.order_id = o.id')
            ->field([
                'ob.order_id',
                'o.pay_time','o.price_maidan as pay_money','o.status as order_status','o.order_sn'
            ])
            ->select();

        $totalOrderNum = count($data);

        return compact('data', 'totalOrderNum');
    }
}