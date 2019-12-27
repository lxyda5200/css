<?php


namespace app\user_v4\common;

use app\user_v4\model\User as UserModel;


class Login
{

    protected $wx_config = [
        'app_id' => 'wx96ba29dd6fd2f004',
	    'app_secret' => '1116b2ce4e1ae84d455547606112bd66'
    ];

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

    /**
     * 微信授权登录获取access_token
     * @param $code
     */
    public static function getWxAccessToken($code){
        $config = (new self())->wx_config;
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$config['app_id']}&secret={$config['app_secret']}&code={$code}&grant_type=authorization_code";
        $result = self::request($url);
        return json_decode($result,true);
    }

    /**
     * 获取第三方登录微信的用户信息
     * @param $access_token
     * @return mixed
     */
    public static function thirdGetWxUserInfo($access_token,$openid){
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}";
        $result = self::request($url);
        return json_decode($result,true);
    }

    /**
     * 更新app_token
     * @param $user_id
     * @return UserModel
     */
    public static function updateToken($user_id){

        $token = User::setToken();
        $data = [
            'token' => $token['token'],
            'token_expire_time' => $token['token_expire_time'],
            'login_time' => time()
        ];
        return UserModel::updateToken($user_id,$data);

    }

    /**
     * 获取第三方登录新浪用户信息
     * @param $access_token
     * @param $user_id
     * @return mixed
     */
    public static function thirdGetSinaUserInfo($access_token, $user_id){
        $url = "https://api.weibo.com/2/users/show.json";
        $data = [
            'access_token' => $access_token,
            'uid' => $user_id
        ];
        $result = self::request($url,true,'get',json_encode($data));
        return json_decode($result,true);
    }

}