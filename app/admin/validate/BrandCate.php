<?php


namespace app\admin\validate;


use think\Validate;

class BrandCate extends Validate
{
    protected $rule = [
        'id' => 'require',
        'title' => 'require',
        'pid' => 'require'
    ];


    protected $scene = [
        'save' => ['title', 'pid'],
        'del' => ['id'],
        'edit' => ['id']
    ];
}