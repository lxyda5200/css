<?php


namespace app\store_v1\validate;


use think\Validate;

class BrandDynamicAds extends Validate
{
    protected $rule = [
        'title|广告标题' => 'require',
        'url|图片或者视频地址' => 'require',
        'type|类型' => 'require',
        'link_type|跳转类型' => 'require'
    ];

    protected $scene = [
        'addAds' => ['title', 'url', 'type', 'link_type']
    ];
}