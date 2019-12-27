<?php


namespace app\store_v1\model;


use think\Model;

class BrandDynamic extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'brand_dynamic';


    /**
     * 查询是否存在自有品牌时尚动态
     * @param $store_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exitsBrandDynamic($store_id) {
        return $this->alias('bd')
            ->join(['brand_store' => 'bs'], 'bs.brand_id=bd.brand_id')
            ->where(['bs.store_id' => $store_id, 'bs.type' => 2])
            ->field('bd.id as dynamic_id, bs.brand_id')
            ->find();
    }


    /**
     * 添加品牌时尚动态关系表
     * @param $brand_id
     * @return int|string
     */
    public function addRelation($brand_id) {
        return $this->insertGetId(['brand_id' => $brand_id, 'create_time' => time()]);
    }


    /**
     * 获取自有品牌时尚动态id
     * @param $store_id
     * @return mixed
     */
    public function getDynamicId($store_id) {
        return $this->alias('bd')
            ->join(['brand_store' => 'bs'], 'bs.brand_id=bd.brand_id')
            ->where(['bs.store_id' => $store_id, 'bs.type' => 2])
            ->value('bd.id');
    }
}