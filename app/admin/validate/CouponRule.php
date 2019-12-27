<?php


namespace app\admin\validate;


use think\Validate;

class CouponRule extends Validate
{

    protected $rule = [

        'status' => 'require|number|min:1',
        'title' => 'require|notEmpty',
        'is_show_coupon_center' => 'require|number',
        'page' => 'number|>=:1',
        'id' => 'require|number|>=:1',
        'check_num|日核销上限' => 'require|number|>=:0',
        'platform_bear|平台承担比例' => 'require|number|>=:0|<=:100'

    ];

    protected $message = [];

    protected $scene = [

        'edit_status' => ['id', 'status'],

        'coupon_rule_add' => ['title'],

        'edit_is_show_coupon_center' => ['id', 'is_show_coupon_center'],

        'offline_coupon_info' => ['id'],

        'coupon_get_list' => ['id', 'page'],

        'edit_offline_coupon' => ['id', 'check_num', 'platform_bear']


    ];

    /**
     * 验证不为空
     * @param $value
     * @return string
     */
    protected function notEmpty($value){
        return !trim($value)?'规则不能为空':true;
    }

}