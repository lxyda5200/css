<?php


namespace app\admin\validate;


use think\Validate;

class CompanyReview extends Validate
{
    protected $rule = [
        'id' => 'require',
        'status' => 'require'
    ];

    protected $scene = [
        'review' => ['id', 'status']
    ];
}