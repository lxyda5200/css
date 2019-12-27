<?php


namespace app\store_v1\validate;


use think\Validate;

class StoreBanner extends Validate
{
    protected $rule = [
        'id' => 'require',
        'store_id|商户id' => 'require',
        'title|标题' => 'require',
        'banner_type|banner类型' => 'require',
        'cover|封面图' => 'require',
        'type|跳转类型' => 'require',
        'content|内容' => 'requireIf:type, 3',
        'link|H5外链' => 'requireIf:type, 2',
        'sort|排序' => 'require'
    ];


    protected $scene = [
        'addBanner' => ['title', 'banner_type', 'cover', 'type', 'content', 'link'],
        'delBanner' => ['id'],
        'sort' => ['id', 'sort']
    ];
}