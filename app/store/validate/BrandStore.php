<?php


namespace app\store\validate;


use think\Validate;

class BrandStore extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'store_id|门店id' => 'require',
        'brand_id|知名品牌id' => 'require',
        'is_show_story|展示品牌故事' => 'require',
        'is_show_dynamic|展示时尚动态' => 'require'
    ];


    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'add' => ['store_id', 'brand_id', 'is_show_story', 'is_show_dynamic']
    ];
}