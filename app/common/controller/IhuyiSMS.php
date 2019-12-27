<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/16
 * Time: 15:39
 */

namespace app\common\controller;

use think\Config;
use think\Session;
use think\Db;
class IhuyiSMS {

    /**
     * 获取短信验证码
     * @param $mobile
     * @return bool|string
     */
    public static function getCode($mobile){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回

//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[89])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";

            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $mobile_code = random(4,1);
            Session::set('mobile',$mobile);
            Session::set('mobile_code',$mobile_code);
            Session::set('expire_time',$_SERVER['REQUEST_TIME'] + 60*3);

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

               //已开启
                $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
                $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("您的短信验证码为".$mobile_code."，请勿向任何人提供此验证码。");
                $gets = xml_to_array(Post($post_data, $target));

                if ($gets['SubmitResult']['code'] != 2) {
                    throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
                }

        }catch (\Exception $e){

            return $e->getMessage();

        }
        return true;
    }
    /**
     * 新获取短信验证码
     * @param $mobile
     * @return bool|string
     */
    public static function getCodeNew($mobile){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回

            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if(!preg_match($pattern, $mobile)) {throw new \Exception('手机号格式错误');}

            $mobile_code = random(4,1);

            Session::set($mobile.'mobile',$mobile);
            Session::set($mobile.'mobile_code',$mobile_code);
            Session::set($mobile.'expire_time',$_SERVER['REQUEST_TIME'] + 60*3);

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("您的短信验证码为".$mobile_code."，请勿向任何人提供此验证码。");
            $gets = xml_to_array(Post($post_data, $target));
            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }
    /**s
     * 新验证短信验证码
     * @param $mobile
     * @param $code
     * @return bool
     */
    public static function verifyCodeNew($mobile,$code){
        //短信开关
        $message_tap = Config::get('message_tap');
        if(!$message_tap){return true;}//已关闭直接返回
        if (!Session::has($mobile.'mobile') || !Session::has($mobile.'mobile_code')) {
            return false;
        }
        if (Session::get($mobile.'mobile') != $mobile || Session::get($mobile.'mobile_code') != $code) {
            return false;
        }
        if (Session::get($mobile.'expire_time') < time()) {
            return false;
        }
        return true;
    }
    /**
     * 下单短信提示商家
     * @throws \Exception
     */
    public static function order_code1($mobile){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("	亲，您又收到订单了，赶快发货吧！用户等的喉咙都发炎了！");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }
    
    /**
     * 活动上线提示商家
     * @param $mobile
     * @param $content
     * @return bool|string
     */
    public static function ac_clock($mobile, $content){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";

            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . $content;
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 授权给用户下发优惠码
     * @throws \Exception
     */
    public static function get_coupon_code($user_id,$mobile){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }
            //生成随机数
            $number=mt_rand(0,9);
            $key=$user_id.$number;
            //内容
            $content='【APP新人礼遇】百草味零食礼包6袋19.9。';
            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');
            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("	终于等到你！恭喜小主，成为[超神宿]用户，您的优惠码为".$key."。".$content."爱生活爱实惠，更多惊喜详见APP哦~
http://appwx.supersg.cn/app/download.html");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 提现获取短信验证码
     * @param $mobile
     * @return bool|string
     */
    public static function tixian_code($mobile){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[89])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $mobile_code = random(4,1);

            Session::set('mobile',$mobile);
            Session::set('mobile_code',$mobile_code);
            Session::set('expire_time',$_SERVER['REQUEST_TIME'] + 60*3);

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("您的短信确认码为".$mobile_code."，请勿向任何人提供此确认码。");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }

        }catch (\Exception $e){

            return $e->getMessage();

        }
        return true;
    }

    public static function maidan_code($mobile,$order_no,$user_name,$user_mobile,$price){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";

            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . "订单编号：{$order_no}已付款。收货人：{$user_name}，联系电话：{$user_mobile}，订单金额：{$price}。";
//            echo $post_data;die;
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 验证短信验证码
     * @param $mobile
     * @param $code
     * @return bool
     */
    public static function verifyCode($mobile,$code){
        //短信开关
        $message_tap = Config::get('message_tap');
        if(!$message_tap){return true;}//已关闭直接返回
        if (!Session::has('mobile') || !Session::has('mobile_code')) {
            return false;
        }
        if (Session::get('mobile') != $mobile || Session::get('mobile_code') != $code) {
            return false;
        }

        if (Session::get('expire_time') < time()) {
            return false;
        }

        return true;
    }

    /**
     * 绑定手机号验证两次
     */
    public static function verifymobileCode($mobile,$code){
        //短信开关
        $message_tap = Config::get('message_tap');
        if(!$message_tap){return true;}//已关闭直接返回
        if (!Session::has('mobile'.$mobile) || !Session::has('mobile_code'.$mobile)) {
            return false;
        }
        if (Session::get('mobile'.$mobile) != $mobile || Session::get('mobile_code'.$mobile) != $code) {
            return false;
        }

        if (Session::get('expire_time'.$mobile) < time()) {
            return false;
        }

        return true;
    }

    /**
     * 单商品取消订单短信提示商家
     * @throws \Exception
     */
    public static function order_cancel_product($mobile,$user_name,$order_no,$product_name){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("	亲，用户：".$user_name."已取消了订单号:".$order_no."的".$product_name."商品！");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 取消订单短信提示商家
     * @throws \Exception
     */
    public static function order_cancel($mobile,$user_name,$order_no){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');

            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("	亲，用户：".$user_name."已取消了订单号:".$order_no."的订单！");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }

    /**
     * 1小时未支付短信提醒
     * @throws \Exception
     */
    public static function onehourautosendMessageNotPayOrder($mobile,$pay_order_no,$user_id,$order_id){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            //内容
            $content="您选购的商品正火热抢购中，数量不多啦，小主快快行动吧，进入APP/小程序，马上带它回家！http://appwx.supersg.cn/app/repay.html?order_id={$order_id}。";
            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');
            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("$content");
            $gets = xml_to_array(Post($post_data, $target));

            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }

            $data = [
                'pay_order_no' => $pay_order_no,
                'mobile' => $mobile,
                'user_id' => $user_id,
                'create_time' => time(),
                'content'=>$content
            ];
            $res = Db::table('send_message_record')->insert($data);

            return $res;


        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
    /**
     * 1.5小时未支付短信提醒
     * @throws \Exception
     */
    public static function oneandahalfhoursautosendMessageNotPayOrder($mobile,$pay_order_no,$user_id,$order_id){
        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){return true;}//已关闭直接返回
//            $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                throw new \Exception('手机号格式错误');
            }

            //内容
            $content="还有半小时，为小主保留的特价权益就失效啦！快来看看吧，进入APP/小程序，http://appwx.supersg.cn/app/repay.html?order_id={$order_id}。";
            $account = Config::get('ihuyi_sms.account');
            $password = Config::get('ihuyi_sms.password');
            $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";       //大陆地区短信接口
            $post_data = "account=".$account."&password=".$password."&mobile=" . $mobile . "&content=" . rawurlencode("$content");
            $gets = xml_to_array(Post($post_data, $target));
            if ($gets['SubmitResult']['code'] != 2) {
                throw new \Exception('短信服务内部错误'.$gets['SubmitResult']['code']);
            }
            $data = [
                'pay_order_no' => $pay_order_no,
                'mobile' => $mobile,
                'user_id' => $user_id,
                'create_time' => time(),
                'content'=>$content
            ];
            $res = Db::table('send_message_record')->insert($data);
            return $res;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
    /*
     * 短信code返回值
code    msg
0   提交失败
2   提交成功
400 非法ip访问
401 帐号不能为空
402 密码不能为空
403 手机号码不能为空
4030    手机号码已被列入黑名单
404 短信内容不能为空
405 用户名或密码不正确
4050    账号被冻结
4051    剩余条数不足
4052    访问ip与备案ip不符
406 手机格式不正确
407 短信内容含有敏感字符
4070    签名格式不正确
4071    没有提交备案模板
4072    短信内容与模板不匹配
4073    短信内容超出长度限制
4085    同一手机号验证码短信发送超出5条
408 您的帐户疑被恶意利用，已被自动冻结，如有疑问请与客服联系。
     * */


}