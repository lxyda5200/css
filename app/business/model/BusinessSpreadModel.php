<?php


namespace app\business\model;


use think\Model;

class BusinessSpreadModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_spread_details';

    /**
     *  获取员工推广数据
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessSpreadList($where = []){
        $list = self::where($where)
            ->alias('s')
            ->join('maidan_order o', 's.order_id = o.id')
            ->field([
                's.order_id', 's.status', 's.money', 's.create_time', 's.statements_time', 'note',
                'o.price_maidan',
            ])
            ->select();
        $totalMoney = array_sum(array_column($list, 'money'));
        return compact('list', 'totalMoney');
    }

    /**
     *  获取推广详情
     * @param $spread_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessSpreadDetails($spread_id){
        $details = self::where(['s.id' => $spread_id])
            ->alias('s')
            ->join('maidan_order o', 's.order_id = o.id')
            ->field([
                's.order_id', 's.status', 's.money', 's.create_time', 's.statements_time', 'note',
                'o.price_maidan',
            ])
            ->find();

        return $details;
    }
}