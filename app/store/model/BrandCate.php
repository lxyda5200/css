<?php


namespace app\store\model;


use think\Model;

class BrandCate extends Model
{
    /**
     * 品牌分类
     * @var string
     */
    protected $table = 'brand_cate';


    /**
     * 获取品牌分类
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandCate() {
        return $this->where(['status' => 1])->field('id, title')->select();
    }
}