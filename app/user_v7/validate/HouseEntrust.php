<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/25
 * Time: 15:25
 */

namespace app\user_v7\validate;


use think\Validate;

class HouseEntrust extends Validate
{

    protected $rule =   [
        'username'  => 'require',
        'mobile'  => 'require|length:11|checkPhone',
        'province' => 'require',
        'city' => 'require',
        'area' => 'require',
        'address' => 'require',
        'description' =>'require'
    ];

    protected $message = [
        'username.require' => '联系人不能为空',
        'mobile.require' => '手机号不能为空',
        'mobile.length' => '手机号长度11位',
        'province.require' => '省份不能为空',
        'city.require' => '城市不能为空',
        'area.require' => '区域不能为空',
        'address.require' => '详细地址不能为空',
        'description.require' => '房屋描述不能为空',
    ];


    protected function checkPhone($value) {
        /**
         * 最新手机号码段
         * 移动号段
         * 134,135,136,137,138,139,147,148,150,151,152,157,158,159,172,178,182,183,184,187,188,198
         * 联通号段
         * 130,131,132,145,146,155,156,166,171,175,176,185,186
         * 电信号段
         * 133,149,153,173,174,177,180,181,189,199
         */
        $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[89])\\d{8}$/";
        return preg_match($pattern, $value) ? true : '手机号格式错误';
    }
}