<?php


namespace app\user_v6\validate;


use think\Validate;

class ProductValidate extends Validate
{

    protected $rule = [
        'category_id' => 'require|number',
    ];

    protected $scene = [
        'memberCateProductList' => ['category_id']
    ];

}