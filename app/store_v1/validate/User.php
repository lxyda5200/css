<?php


namespace app\store_v1\validate;


use think\Validate;

class User extends Validate
{
    protected $rule = [
        'user_name'  => 'require|chsDash|length:8,16',
        'email' =>  'require|email',
        'nickname'=>'require|chs|length:2,10',
        'password'=>'alphaNum|length:8,16',
        'mobile'  => 'require|length:11|checkPhone',
        'code' => 'require|number|length:4',
        'emailcode'=>'require|number|length:6',
    ];

    protected $message = [
        'mobile.require'  =>  '手机号必填',
        'mobile.length'  =>  '手机号必须11位',
        'mobile.checkPhone'  =>  '手机号不正确',
        'email' =>  '邮箱格式错误',
        'code.require'  =>  '请填写短信验证码',
        'code.number'  =>  '短信验证码为纯数字',
        'code.length'  =>  '短信验证码位数不对',
        'emailcode.require'  =>  '请填写邮箱验证码',
        'emailcode.number'  =>  '邮箱验证码为纯数字',
        'emailcode.length'  =>  '邮箱验证码位数不对',
    ];

    protected $scene = [
        'register' =>['mobile','email','code','emailcode','password'],
//        'login'   =>  ['name','email'],
//        'edit'  =>  ['email'],
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