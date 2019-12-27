<?php


namespace app\user_v5\validate;


use think\Validate;

class CouponValidate extends Validate
{

    protected $rule = [

        'coupon_id' => 'require|number'

    ];

    protected $scene = [

        'coupon_products' => ['coupon_id'],

    ];

}