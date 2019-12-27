<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/7
 * Time: 10:04
 */
namespace app\mainstore\validate;

use think\Validate;

class Store extends Validate
{
    protected $rule =   [
        'user_name' => 'require',
        'store_name' => 'require',
        'mobile'  => 'require|length:11|checkPhone|unique:store',
        'telephone' => 'require',
        'brand_name' => 'require',
        'business_start_time' => 'require',
        'business_end_time' => 'require',
        'is_ziqu' => 'require|number|length:1',
        'is_tixian' => 'require|number|length:1',
        'province' => 'require',
        'city' => 'require',
        'address' => 'require',
        'category_id' => 'require',
        'lng' => 'require',
        'lat' => 'require',
        'description' => 'require',
        #'code' => 'require|number|length:4',
        'password' => 'require',
        'new_password' =>'require|different:password'
    ];

    protected $message = [
        /*'mobile.require' => '手机号不能为空',
        'mobile.length' => '手机号长度11位',
        'mobile.unique' => '手机号已注册',
        'password.require' => '密码不能为空',
        'code.require' => '验证码不能为空',
        'code.number' => '验证码位数字',
        'code.length' => '验证码长度4位',
        'new_password.require' => '新密码不能为空',
        'new_password.different' => '新密码不能和原密码相同'*/
    ];

    protected $scene = [
        'register'  => ['user_name','mobile','store_name','telephone','brand_name',
            'business_start_time','business_end_time','is_ziqu','is_tixian','province','city','address','category_id','lng','lat','description','code'
        ],
        'login' => ['mobile'=>'require|length:11|checkPhone','password'],
        'modifyPwd' => ['password','new_password'],
        'forgetPwd' => ['mobile'=>'require|length:11|checkPhone','code','password']
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
//        $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
        $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
        return preg_match($pattern, $value) ? true : '手机号格式错误';
    }
}