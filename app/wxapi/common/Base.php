<?php
namespace app\wxapi\common;

use think\Db;
class Base
{

    /**
     * 接口回调
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public static function callback($status = 1,$msg = '',$data = []){
        return ['status'=>$status,'msg'=>$msg,'data'=>$data];
    }

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
     * @param $uid
     * @param $token
     * @return array|false|\PDOStatement|string|\think\Model|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkToken($uid,$token){

        if (empty($uid) || empty($token)) {
            return json(self::callback(0,'需要用户验证'),400);
        }

        $userInfo = Db::name('user')->where('uid',$uid)->where('token','eq',$token)->find();

        if (!empty($userInfo)) {

            if (time() - $userInfo['token_expire_time'] > 0) {

                return json(self::callback(-1,'登录已失效，请重新登录'),400); //token长时间未使用而过期，需重新登陆

            }

            $new_expire_time = time() + 604800; //604800是七天 token七天保留时间

            if ( Db::name('user')->where('token','eq',$token)->setField('token_expire_time',$new_expire_time) ) {


                return $userInfo;  //token验证成功，time_out刷新成功，可以获取接口信息

            }

        }

        return json(self::callback(0,'账号异常登录'),400);  //token错误验证失败

    }
}