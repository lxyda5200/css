<?php
namespace app\mainstore\common;

use think\Db;
class Store
{

    /**
     * token令牌生成
     * @return array
     */
    public static function setToken(){

        $str = md5(uniqid(md5(microtime(true)),true));  //生成一个不会重复的字符串

        $token = sha1($str);  //加密  token字符串

        $token_expire_time = strtotime("+7 days");  //token过期时间

        return ['token'=>$token,'token_expire_time'=>$token_expire_time];

    }

    /**
     * token验证
     * @return array|false|\PDOStatement|string|\think\Model|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkToken($store_id = '',$token = ''){

        if (empty($store_id)) {
            $store_id = request()->post('store_id');
        }

        if (empty($token)) {
            $token = request()->post('token');
        }

        if (empty($store_id) || !intval($store_id) || empty($token) || strlen($token) != 40) {
            return json(['status'=>0,'msg'=>'需要用户验证','data'=>[]]);
        }

        $store_info = Db::name('store')->where('id',$store_id)->find();

        if (!empty($store_info)) {

            /*if ($store_info['token'] != $token) {
             //token错误验证失败
                return json(['status'=>1000,'msg'=>'账号在其他地方登陆','data'=>[]]);
            }*/

            if (time() - $store_info['token_expire_time'] > 0) {

                 //token长时间未使用而过期，需重新登陆
                return json(['status'=>1000,'msg'=>'登录已失效，请重新登录','data'=>[]]);

            }

            $new_expire_time = time() + 604800; //604800是七天 token七天保留时间

            if ($store_info['store_status'] != 1) {
                return json(['status'=>-1,'msg'=>'账号已被禁用，请联系管理员','data'=>[]]);
            }

            Db::name('store')->where('token','eq',$token)->setField('token_expire_time',$new_expire_time);

            return $store_info;  //token验证成功，time_out刷新成功，可以获取接口信息

        }

        return json(['status'=>0,'msg'=>'账号不存在','data'=>[]]);

    }
public static function checkPhone($value) {
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