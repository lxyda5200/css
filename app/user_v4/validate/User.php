<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/17
 * Time: 9:45
 */

namespace app\user_v4\validate;

use think\Validate;
class User extends Validate
{
    protected $rule =   [
        'mobile'  => 'require|length:11|checkPhone|unique:user',
        'username'   => 'require',
        'password' => 'require',
        'code' => 'require|number|length:4',
        'new_password' =>'require|different:password',
        'user_id' => 'require|number|min:1',
        'coupon_id' => 'require|number|min:1',
        'store_id' => 'require|number|min:1',
        'price_yj' => 'require|pay_money',
        'pay_type' => 'require',
        'coupon_code' => 'require|length:12',
        'token' => 'require',
        'msg_id' => 'require|number|min:1',
        'type' => 'require|number|min:1|max:2',
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
        'price_yj.pay_money' => '买单金额最多2位小数'
    ];

    protected $scene = [
        'register'  => ['mobile','password','code'],
        'login' => ['mobile'=>'require|length:11|checkPhone','password'],
        'modifyPwd' => ['password','new_password'],
        'forgetPwd' => ['mobile'=>'require|length:11|checkPhone','code','password'],
        'bind_mobile' => ['mobile', 'password', 'code', 'user_id'],
        'get_coupon' => ['user_id', 'coupon_id'],
        'maidan' => ['user_id', 'price_yj', 'pay_type'],     //买单
        'exchange_code' => ['coupon_code'],  //兑换优惠券
        'delete_msg' => ['user_id', 'token', 'msg_id'],  //删除消息通知
        'order_coupon_list' => ['user_id', 'token', 'type'],   //订单优惠券列表
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
        $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
        return preg_match($pattern, $value) ? true : '手机号格式错误';
    }

    /**
     * 买单金额
     * @param $value
     * @return bool|string
     */
    protected function pay_money($value){
        $value = explode('.',$value);
        if(count($value)==1)return true;
        return strlen($value[1])>2 ? false : true;
    }
}