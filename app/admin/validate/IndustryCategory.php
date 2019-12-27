<?php


namespace app\admin\validate;


use think\Validate;

class IndustryCategory extends Validate
{
    protected $rule = [
        'pid|父级id' => 'require',
        'cate_name|分类名称' => 'require',
        'id' => 'require'
    ];

    protected $scene = [
        'add' => ['pid', 'cate_name'],
        'del' => ['id']
    ];
}