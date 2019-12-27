<?php


namespace app\business\model;


use think\Model;

class ProductGoodsModel extends Model
{

    protected $pk = 'id';

    protected $table = 'product';


    /**
     *  商品上下架
     * @param $product_id 商品ID
     * @param $type  类型 1-上架 2-下架
     * @param $store_id 店铺ID
     * @return bool|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function productUpperOrLower($product_id, $type,$store_id){
        //$data = self::where(['id' => $product_id,'store_id' => $store_id]) -> field(['id','total_stocks','status','store_id']) -> find();
        $where['p.store_id'] = $store_id;
        $where['p.id'] = $product_id;
        $data = ProductGoodsModel::where($where)
            ->alias('p')
            ->join('product_specs ps', 'p.id = ps.product_id')
            ->field(['p.product_name','p.status','p.store_id','p.id as id','sum(stock) as total_stocks'])
            ->group('id')
            ->find();
        if (!$data) return '数据检索失败';

        // 上下架数据组装
        if ($type == 1){
            if ($data['status'] == 1) return '已上架';
            if ($data['total_stocks'] <= 0) return '库存不足，无法上架';
            $update = ['status' => 1, 'shangjia_time' => time()];
        }else{
            if ($data['status'] == 0) return '已下架';
            $update = ['status' => 0, 'xiajia_time' => time()];
        }
        // 修改商品状态
        $return = self::where(['id' => $data['id']]) -> update($update);

        return $return ? true : false;
    }
}