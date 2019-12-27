<?php


namespace app\store\validate;


use think\Validate;

class BrandDynamicAds extends Validate
{
    protected $rule = [
        'url|图片或者视频地址' => 'require',
        'type|类型' => 'require',
        'link_type|跳转类型' => 'require'
    ];

    protected $scene = [
        'addAds' => ['url', 'type', 'link_type']
    ];
}