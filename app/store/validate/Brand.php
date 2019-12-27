<?php


namespace app\store\validate;


use think\Validate;

class Brand extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'brand_name|品牌名' => 'require',
        'cate_id|品牌分类' => 'require',
        'logo|品牌logo' => 'require',
        'history|品牌历史' => 'require',
        'notion|品牌理念' => 'require',
        'ads|广告位' => 'require',
        'goods|经典款' => 'require',
        'store_id|门店id' => 'require',
        'id|广告id' => 'require',
        'sort|排序' => 'require'
    ];


    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'selfBrandStory' => ['brand_name', 'cate_id', 'logo', 'history', 'notion', 'ads', 'goods'],
        'getFamousBrand' => ['store_id'],
        'sort' => ['id', 'store_id', 'sort'],
        'addBrand' => ['brand_name', 'cate_id', 'logo'] // 添加品牌
    ];
}