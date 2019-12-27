<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2019/6/26
 * Time: 9:45
 */

namespace app\wxapi_test\validate;

use think\Validate;
class User extends Validate
{
    protected $rule =   [
        'mobile'  => 'require|length:11|checkPhone|unique:user',
        'username'   => 'require',
        'password' => 'require',
        'code' => 'require|number|length:4',
        'token' => 'require',
        'user_id' => 'require|number|min:1',
        'type' => 'require|number|min:1|max:2',
        'new_password' =>'require|different:password',
        'form_id' => 'require|unique:user_template,form_id'
    ];

    protected $message = [
        'mobile.require' => '手机号不能为空',
        'mobile.length' => '手机号长度11位',
        'mobile.unique' => '手机号已注册',
        'password.require' => '密码不能为空',
        'code.require' => '验证码不能为空',
        'code.number' => '验证码位数字',
        'code.length' => '验证码长度4位',
        'new_password.require' => '新密码不能为空',
        'new_password.different' => '新密码不能和原密码相同',
        'form_id.require' => 'form_id不能为空'
    ];

    protected $scene = [
        'register'  => ['mobile','password','code'],
        'login' => ['mobile'=>'require|length:11|checkPhone','password'],
        'modifyPwd' => ['password','new_password'],
        'order_coupon_list' => ['user_id', 'token', 'type'],   //订单优惠券列表
        'forgetPwd' => ['mobile'=>'require|length:11|checkPhone','code','password'],
        'keep_form_id' => ['form_id']
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
        $pattern = "/^[1][1,2,3,4,5,6,7,8,9][0-9]{9}$/";
        return preg_match($pattern, $value) ? true : '手机号格式错误';
    }
}