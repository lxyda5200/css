<?php


namespace app\store_v1\model;


use think\Model;

class BrandProduct extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'brand_product';


    /**
     * 添加品牌经典款设置
     * @param $data
     * @return int|string
     */
    public static function addGoods($data) {
        return self::insertAll($data);
    }


    /**
     * 删除品牌经典款设置
     * @param $brand_id
     * @return int
     */
    public static function delGoods($brand_id) {
        $res = self::where(['brand_id' => $brand_id])->select();
        if(!$res)
            return true;
        return self::where(['brand_id' => $brand_id])->delete();
    }


    /**
     * 获取经典款商品
     * @param $brand_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoods($brand_id) {
        return self::alias('bp')
            ->join(['product' => 'p'], 'bp.product_id=p.id')
            ->join(['product_img' => 'pi'], 'pi.product_id=p.id')
            ->join(['product_specs' => 'ps'], 'ps.product_id=p.id')
            ->join(['store' => 's'], 's.id=p.store_id')
            ->group('ps.product_id')
            ->field('ps.product_name, p.id as product_id, ps.cover, min(ps.price) as price, s.store_name, s.address')
            ->where(['bp.brand_id' => $brand_id])->select();
    }
}