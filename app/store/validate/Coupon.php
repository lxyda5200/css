<?php


namespace app\store\validate;


use think\Validate;

class Coupon extends Validate
{

    protected $rule = [
        'coupon_code' => 'require|length:10'
    ];

    protected $scene = [

        'validate_coupon' => ['coupon_code']

    ];

}