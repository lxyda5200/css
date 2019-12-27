<?php
namespace app\wxapi\common;

use think\Db;
use think\response\Json;
use app\common\controller\Base;
use WXBizDataCrypt;
use think\Log;

include_once 'User.php';
include_once "wxBizDataCrypt.php";
class Weixin
{

    //定义小程序常量
    const APPID = 'wx1cd5c0af60f9b194';
    const APPSECRET = '5accf455040e29a8f6a62ae586c8dda6';

    /**
     * 定义分享页面
     * @var array
     */
    protected $page_type = [
        'tabIndex' => 'pages/tab-index/index',
        'freeStyleDetail' => 'pages/freeStyle-detail/index',
        'productDetail' => 'pages/product-detail/index',
        'index' => 'pages/index/index'
    ];

    //定义回调
    /**
     * 接口回调
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public static function callback($status = 1,$msg = '',$data = 0){
        if ($data==0){
            $data = new \stdClass();
        }
        //正式阶段
        #return ['status'=>$status,'msg'=>$msg,'data'=>$data];

        //测试阶段
        return ['status'=>$status,'msg'=>$msg,'data'=>$data,'request'=>request()->post()];
    }
    //封装请求方法
    public static function request($url, $https = true, $method = 'get', $data = null)
    {

        //1.初始化url
        $ch = curl_init($url);
        //2.设置相关的参数
        //字符串不直接输出,进行一个变量的存储
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //判断是否为https请求
        if ($https === true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        //判断是否为post请求
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        //3.发送请求
        $str = curl_exec($ch);
        //4.关闭连接,避免无效消耗资源
        curl_close($ch);
        //返回请求到的结果
        return $str;
    }

    //获取用户openid
    public static function getOpenid($code)
    {

        //传递参数
        $js_code = $code;
        $appid = self::APPID;
        $secret = self::APPSECRET;
        $url = 'https://api.weixin.qq.com/sns/jscode2session?grant_type=authorization_code&appid='.$appid.'&secret='.$secret.'&js_code='.$js_code;
        $result = self::request($url);
        $result = json_decode($result, true);
        if ($result && isset($result['session_key']) && isset($result['openid'])) {
            $wx_openid = $result['openid'];
            $session_key = $result['session_key'];
            if(isset($result['unionid'])){
                $unionid = $result['unionid'];
            }else{
                $unionid = '';
            }
            //查询用户是否已经有记录
            $content = Db::table('user')->where('wx_openid',$wx_openid)->find();
            $tokenInfo = \app\wxapi\common\User::setToken();
            if ($content){
                $genxin = [
                    'wx_token' => $tokenInfo['wx_token'],
                    'login_time' => time(),
                    'wx_token_expire_time' => $tokenInfo['wx_token_expire_time'],
                    'wx_session_key' => $session_key
                ];
                $rst = Db::name('user')->where('wx_openid',$content['wx_openid'])->update($genxin);
                if ($rst === false){
                    //报错
                    return json(['status' => 0, 'msg' => '更新用户数据失败', 'data' => []], 400);
                }
                //查询是否有优惠券
                $coupon=getcoupon($content['user_id'],$content['login_time']);
                if(empty($coupon)){$coupon=[];}
                $user=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,description')->where('wx_openid',$content['wx_openid'])->find();
                //判断用户是否有下单
                $order=Db::table('product_order')->field('user_id,id')->where('user_id',$user['user_id'])->count();
                $order > 0 ? $user['is_new_user']=0 : $user['is_new_user']=1;
            } else {
                //查询是否有优惠券

                //新增一个用户
                $newuser = [
                    'wx_openid' => $wx_openid,
                    'wx_unionid' => $unionid,
                    'wx_session_key' => $session_key,
                    'nickname' => '新用户',
                    'avatar' => '/default/user_logo.png',
                    'create_time' => time(),
                    'login_time' => time(),
                    'wx_token' => $tokenInfo['wx_token'],
                    'source' => 1,
                    'wx_token_expire_time' => $tokenInfo['wx_token_expire_time']
                ];
                $user_id = Db::name('user')->insertGetId($newuser);
                //添加中间表
                $user_and_store = ['user_id' => $user_id, 'create_time' => time()];
                Db::table('user_and_store')->insert($user_and_store);
                $invitation_code = createCode($user_id);
                Db::name('user')->where('user_id',$user_id)->setField('invitation_code',$invitation_code);//生成邀请码
                if (!$user_id) {
                    //报错
                    return json(['status' => 0,'msg' => '添加用户失败','data' => []], 400);
                }
                $user=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,description')->where('wx_openid',$wx_openid)->find();
                //判断用户是否有下单
                $order=Db::table('product_order')->field('user_id,id')->where('user_id',$user['user_id'])->count();
                $order > 0 ? $user['is_new_user']=0 : $user['is_new_user']=1;
                $coupon=[];
            }
//            return json(['status' => 1,'msg' => '返回成功',$data]);
            //修改为已登录
            Db::name('user')->where('wx_openid',$wx_openid)->setField('login_out',1);
            if($user['avatar']==''){
                $user['avatar']='/default/user_logo.png';
            }
            $share=share_set();
            $user_info= array_merge($user,$share);
           $coupon_number=count($coupon);
            if($coupon_number>1){
                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/2x.png';
                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                $data['coupon_info']['list']=$coupon;
            }elseif($coupon_number==1){
                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/1x.png';
                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                $data['coupon_info']['list']=$coupon;
            }
            $data['user_info']=$user_info;
            return \json(self::callback( 1,'消息返回成功!', $data));
        } else {
            //报错
            return json(['status' => 0,'msg' => '获取openid失败','data' => []], 400);
        }
    }

    //解密用户信息
    public static function getUserPhone($sessionKey, $iv, $encryptedData)
    {
        $appid = self::APPID;
        $pc = new WXBizDataCrypt($appid, $sessionKey);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );
        return $errCode == 0 ? $data : false;
    }
//生成二维码
//    public function getQRcode($data='')
//    {
//        //传递参数
//        $appid = self::APPID;
//        $secret = self::APPSECRET;
//        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
//        $result = self::request($url);
//        $result = json_decode($result, true);
//        if ($result && isset($result['access_token']) && isset($result['expires_in'])) {
//            //获取到token
//            $access_token = $result['access_token'];
//            $result['timestamp'] = time();
//            //写入文件
//             file_put_contents("access_token.txt",json_encode($result));
//
//            return \json(self::callback( 1,'获取access_token成功!', $data));
//        } else {
//            //报错
//            return json(['status' => 0,'msg' => '获取access_token失败','data' => []], 400);
//        }
//
//    }

    /**
     * 微信接口获取access_token
     * @return bool
     */
    static public function getApiAccessToken(){

        $appid = self::APPID;
        $secret = self::APPSECRET;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
        $result = self::request($url);
        $result = json_decode($result, true);
        if ($result && isset($result['access_token']) && isset($result['expires_in'])) {
            $result['timestamp'] = time();
            //写入文件
            $path = '/usr/share/nginx/html/access_token.txt';
            file_put_contents($path,json_encode($result));
            return $result['access_token'];
        } else {
            return false;
        }

    }

    /**
     * 获取本地access_token
     * @return bool
     */
    static public function getLocalAccessToken(){

        #获取文件中的access_token
        $path = '/usr/share/nginx/html/access_token.txt';
        if(!file_exists($path))return false;
        $data = file_get_contents($path);
        $data = json_decode($data,true);
//print_r($data);
        #判断内容
        if(!$data || !isset($data['access_token']) || !isset($data['timestamp']))return false;

        #判断有效性
        if(time() - $data['timestamp'] >= 6000)return false;

        #返回
        return $data['access_token'];

    }

    /**
     * 获取access_token
     * @return bool
     */
    static public function getAccessToken(){
        $access_token = self::getLocalAccessToken();
        if(!$access_token)$access_token = self::getApiAccessToken();
        return $access_token;
    }

    /**
     * 生成小程序二维码
     * @param $scene
     * @param $page
     * @return array
     */
    static public function getQrcode($scene,$page){

        $access_token = self::getAccessToken();
        if(!$access_token)return returnArr(1,'access_token获取失败');

        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$access_token}";

        #获取页面路劲
        $page = (new self())->page_type[$page];
        if(!$page)return returnArr(1,'分享页面未定义');

        $data = [
            'scene' => $scene,
            'page' => $page,
            'is_hyaline' => true
        ];

        #生成图片
        $res = http($url,json_encode($data));
//        file_put_contents('test.png',$res);
//        header("Content-type: image/png");
        if(!not_json($res))return returnArr(1,'二维码生成失败');

        return returnArr(0,'',$res);

    }


    public static function imgSecCheck($file){
        $access_token = self::getAccessToken();
        if(!$access_token)return returnArr(1,'access_token获取失败');

        $url = "https://api.weixin.qq.com/wxa/img_sec_check?access_token={$access_token}";

        $data['media'] = $file;

        $res = http_request($url,$data);
        return $res;
    }

    public static function msgSecCheck($content){
        $access_token = self::getAccessToken();
        if(!$access_token)return returnArr(1,'access_token获取失败');

        $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token={$access_token}";
        $data['content'] = $content;
        $data = json_encode($data);

        $res = http($url, $data);
        return $res;
    }


}