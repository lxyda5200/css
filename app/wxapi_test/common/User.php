<?php
namespace app\wxapi_test\common;

use think\Db;
class User
{

    /**
     * token令牌生成
     * @return array
     */
    public static function setToken(){

        $str = md5(uniqid(md5(microtime(true)),true));  //生成一个不会重复的字符串
        $token = sha1($str);  //加密  token字符串
        $token_expire_time = strtotime("+7 days");  //token过期时间
        return ['wx_token'=>$token,'wx_token_expire_time'=>$token_expire_time];

    }

    /**
     * token验证
     * @return array|false|\PDOStatement|string|\think\Model|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkToken($user_id = '',$token = ''){

        ##登录白名单
        $whtie_func_config = config('config_common.xcx_white_func');

        if (empty($user_id)) {
            $user_id = request()->post('user_id');
        }

        if (empty($token)) {
            $token = request()->post('token');
        }

        if (empty($user_id) || !intval($user_id) || empty($token) || strlen($token) != 40) {
            return json(['status'=>0,'msg'=>'需要用户验证','data'=>[]],400);
        }

        $userInfo = Db::name('user')->where('user_id',$user_id)->find();
        //验证用户是否注册
        //开始
        if (!$userInfo['mobile'] && !in_array(strtolower(request()->action()), $whtie_func_config)) {
            return json(['status'=>404,'msg'=>'用户未注册！','data'=>[]],400);
        }
        //结束
        if (!empty($userInfo)) {

            if ($userInfo['wx_token'] != $token) {
             //token错误验证失败
                return json(['status'=>1000,'msg'=>'账号在其他地方登陆','data'=>[]],400);
            }
            if (time() - $userInfo['wx_token_expire_time'] > 0) {
                 //token长时间未使用而过期，需重新登陆
                return json(['status'=>1000,'msg'=>'登录已失效，请重新登录','data'=>[]],400);
            }
            $new_expire_time = time() + 604800; //604800是七天 token七天保留时间
            if ($userInfo['user_status'] != 1 && $userInfo['user_status'] != 3) {
                return json(['status'=>-1,'msg'=>'账号已被禁用，请联系管理员','data'=>[]],400);
            }

            if ($userInfo['login_out'] == -1 && !in_array(strtolower(request()->action()), $whtie_func_config)) {
                return json(['status'=>1,'msg'=>'','data'=>[]],200);
            }
            Db::name('user')->where('wx_token','eq',$token)->setField('wx_token_expire_time',$new_expire_time);

            return $userInfo;  //token验证成功，time_out刷新成功，可以获取接口信息

        }

        return json(['status'=>0,'msg'=>'账号不存在','data'=>[]],400);

    }





}