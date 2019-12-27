<?php


namespace app\business\model;


use think\Model;

class MaiOrderModel extends Model
{

    protected $pk = 'id';

    protected $table = 'maidan_order';

    /**
     *  根据where查询收益数据并统计总金额
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderDetailsByWhere($where = []){
        $data = self::where($where)
            ->field(['price_maidan','order_sn','id as order_id','FROM_UNIXTIME(pay_time,\'%Y-%m-%d %H:%i\') as pay_time','status'])
            ->select();
        // 统计总金额
        $totalPrice = number_format(array_sum(array_column($data,'price_maidan')),2);
        return compact('totalPrice', 'data');
    }
}