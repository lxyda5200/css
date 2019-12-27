<?php
/**
 * Created by PhpStorm.
 * sale: 贝拉
 * Date: 2018/7/26
 * Time: 15:31
 */

namespace app\sale\common;

use think\Db;
class Sale
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
    public static function checkToken(){

        $sale_id = request()->post('sale_id');

        $token = request()->post('token');

        if (empty($sale_id) || !intval($sale_id) || empty($token) || strlen($token) != 40) {
            return json(['status'=>0,'msg'=>'需要用户验证','data'=>[]],400);
        }

        $saleInfo = Db::name('sale')->where('sale_id',$sale_id)->find();

        if (!empty($saleInfo)) {

            if ($saleInfo['token'] != $token) {
                //token错误验证失败
                return json(['status'=>1000,'msg'=>'账号在其他地方登陆','data'=>[]],400);
            }

            if (time() - $saleInfo['token_expire_time'] > 0) {

                //token长时间未使用而过期，需重新登陆
                return json(['status'=>1000,'msg'=>'登录已失效，请重新登录','data'=>[]],400);

            }

            $new_expire_time = time() + 604800; //604800是七天 token七天保留时间

            if ($saleInfo['sale_status'] != 1) {
                return json(['status'=>-1,'msg'=>'账号已被禁用，请联系管理员','data'=>[]],400);
            }

            Db::name('sale')->where('token','eq',$token)->setField('token_expire_time',$new_expire_time);

            return $saleInfo;  //token验证成功，time_out刷新成功，可以获取接口信息

        }

        return json(['status'=>0,'msg'=>'账号不存在','data'=>[]],400);

    }
}