<?php


namespace jiguang;


use think\Config;
use think\Db;
use think\Exception;

class JiG
{

    protected static $url = [

        'register_user' => "https://api.im.jpush.cn/v1/users/",

        'check_status'  => "https://api.im.jpush.cn/v1/users/%s/userstat",

        'get_info' => "https://api.im.jpush.cn/v1/users/%s",

        'edit_info' => "https://api.im.jpush.cn/v1/users/%s"

    ];

    protected static $conf = [

        'appkey' => 'a6067b45e0fa458f5d88e43a',  //AppKey  商城

        'secret'=> '67ae723172fdb437e1fc6294', //Secret  商城

        'store_appkey' => 'db86117852ebb61eae1908b4',   //AppKey  商家

        'store_secret' => '52fe624876c195095778cb65',   //Secret  商家

    ];

    /**
     * 注册普通用户
     * @param $user_id
     * @return array
     */
    public static function registerUser($user_id){

        try{
            $web_path = Config::get('web_path');
            $server = Config::get('web_server');

            $user_info = Db::name('user')->where(['user_id'=>$user_id])->field('user_id,nickname,avatar')->find();
            $im_user_name = "user_" . (string)$user_info['user_id'] . "$server";
            $password = self::createPwd();
            if($user_info['avatar']!=''){
                if(substr($user_info['avatar'],0,4) == 'http'){
                    $avatar = $user_info['avatar'];
                }else{
                    $avatar = $web_path . $user_info['avatar'];
                }
            }else{
                $avatar = 'http://appwx.supersg.cn/default/user_logo.png';
            }
            $param[] = [
                'username' => $im_user_name,
                'password' => $password,
                'nickname' => $user_info['nickname'],
                'avatar' => $avatar
            ];
            $param = json_encode($param);

            $res = self::post('register_user', $param);

            $res = json_decode($res,true);
            if(isset($res[0]['error']))throw new Exception($res['error']['message']);

            ##写入用户表 JiG_id
            $JiG_id = $res[0]['username'];
            Db::name('user')->where(['user_id'=>$user_id])->update(['jig_id'=>$JiG_id,'jig_pwd'=>$password]);

            return ['status'=>1,'jig_id'=>$JiG_id,'jig_pwd'=>$password];
        }catch(Exception $e){
            return ['status'=>0,'err'=>$e->getMessage()];
        }

    }

    /**
     * 注册店铺客服
     * @param $business_id
     * @return array
     */
    public static function registerService($business_id){
        try{
            $web_path = Config::get('web_path');
            $server = Config::get('web_server');

            $user_info = Db::name('business')->alias('b')
                ->join('store s','s.id = b.store_id','LEFT')
                ->where(['b.id'=>$business_id])
                ->field('s.cover,b.business_name')->find();
            $im_user_name = "store_" . (string)$business_id . $server;
            $password = self::createPwd();
            $param[] = [
                'username' => $im_user_name,
                'password' => $password,
                'nickname' => $user_info['business_name']?:"店小二",
//                'avatar' => $_SERVER['HTTP_HOST'] . $user_info['cover']
                'avatar' => $user_info['cover']!=''? ($web_path . $user_info['cover']):'http://appwx.supersg.cn/default/user_logo.png'
            ];
            $param = json_encode($param);

            $res = self::post('register_user', $param, 2);

            $res = json_decode($res,true);

            if(isset($res[0]['error']))throw new Exception($res['error']['message']);

            ##写入用户表 JiG_id
            $JiG_id = $res[0]['username'];
            Db::name('business')->where(['id'=>$business_id])->update(['jig_id'=>$JiG_id,'jig_pwd'=>$password]);

            return ['status'=>1,'jig_id'=>$JiG_id,'jig_pwd'=>$password];
        }catch(Exception $e){
            return ['status'=>0,'err'=>$e->getMessage()];
        }
    }

    public static function editUserInfo($user_id, $type, $value){
        switch ($type){
            case 1:
                self::editUserAvatar($user_id, $value);
                break;
            default:
                break;
        }
    }

    public static function editUserAvatar($user_id, $avatar){
        $username = Db::name('user')->where(['user_id'=>$user_id])->value('jig_id');
        $param = [
            'avatar' => $avatar
        ];
        $param = json_encode($param);
        self::doEditInfo($param, $username);
    }

    protected static function doEditInfo($param, $username){
        self::editPost('edit_info', $param,$username);
    }

    public static function editServiceInfo($user_id){
        $username = Db::name('business')->where(['id'=>$user_id])->value('jig_id');
        $param = [
            'avatar' => "http://wx.supersg.cn/uploads/store/cover/20191029/564ad55d002cacf4ad5325ed6e342fd3.png"
        ];
        $param = json_encode($param);

        $res = self::editPost('edit_info', $param,$username,2);

        print_r($res);
    }

    public static function editServiceData($username, $data){
        $data = json_encode($data);
        $res = self::editPost('edit_info', $data, $username,2);

        print_r($res);
    }
    public static function editUserData($username, $data){
        $data = json_encode($data);
        $res = self::editPost('edit_info', $data, $username,1);

        print_r($res);
    }

    public static function curl_put($url,$data,$type=1){

        $appkey = $type==1?self::$conf['appkey']:self::$conf['store_appkey'];
        $secret = $type==1?self::$conf['secret']:self::$conf['store_secret'];
        $base64 = base64_encode("$appkey:$secret");
        $header = array("Authorization:Basic $base64", "Content-Type:application/json");

        $ch = curl_init(); //初始化CURL句柄
        curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
//        curl_setopt ($ch, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PUT"); //设置请求方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//设置提交的字符串
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);           // 增加 HTTP Header（头）里的字段
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output,true);
    }

    /**
     * 获取客服
     * @param $user_id
     * @param $store_id
     * @return array
     */
    public static function getCustomerServiceInfo($user_id, $store_id){
        ##获取互动消息权限id
        $power_id = Db::name('business_power')->where(['mark'=>'jig'])->value('id');

        ##获取上一次聊天的客服(一天内)
        $last_service = Db::name('business_user')->alias('bu')
            ->join('business b','b.id = bu.business_id','LEFT')
            ->where(['bu.user_id'=>$user_id,'bu.store_id'=>$store_id,'bu.update_time'=>['GT',time()-24 * 60 * 60]])
            ->order('bu.update_time','desc')
            ->field('b.id,b.jig_id,b.jig_pwd')
            ->find();

        ##有历史聊天客服并且有客服权限
        if($last_service && self::checkPower($last_service['id'], $power_id)){
            ##检查是否在线
            if(self::checkCustomerServiceStatus($last_service['jig_id'])){  //在线
                $customer_info = ['business_id'=>$last_service['id'],'jig_id'=>$last_service['jig_id']];
                self::updateService($user_id, $customer_info['business_id'],$store_id);
                return ['status'=>1,'data'=>$customer_info];
            }
        }

        $business = Db::name('business')->alias('b')
            ->join('business_power_details bpd','bpd.business_id = b.id','LEFT')
            ->where(function($query) use ($store_id, $power_id){
                $query->where(['b.store_id'=>$store_id,'b.pid'=>['GT',0],'bpd.power_id'=>$power_id]);
            })
            ->whereOr(function($query) use ($store_id){
                $query->where(['b.store_id'=>$store_id,'b.pid'=>0]);
            })
            ->group('b.id')
            ->field('
                b.id,b.jig_id,b.jig_pwd,b.last_service_time,b.store_id,b.pid,
                bpd.id as power
            ')
            ->order('last_service_time','asc')
            ->select();

        $offline_list = [];
        foreach($business as $v){
            if((isset($v['power']) || $v['pid'] == 0) && isset($v['jig_id']) && $v['jig_id']){  //有权限且注册
                if(self::checkCustomerServiceStatus($v['jig_id'])){  //在线
                    $customer_info = ['business_id'=>$v['id'],'jig_id'=>$v['jig_id']];
                    self::updateService($user_id, $customer_info['business_id'],$store_id);
                    return ['status'=>1,'data'=>$customer_info];
                }else{  //未在线
                    $offline_list[] = $v;
                }
            }
        }
        if(empty($offline_list)){
            $customer_info = [
                'business_id' => '62',
                'jig_id' => 'store_62_formal'
            ];
            return ['status'=>1,'data'=>$customer_info];
        }

        ##都不在线[发送推送消息,并返回客服信息]
        $customer_info = ['business_id'=>$offline_list[0]['id'],'jig_id'=>$offline_list[0]['jig_id']];
//        self::sendMsgToCustomerService("s" . (string)$customer_info['business_id']);
        self::updateService($user_id, $customer_info['business_id'],$store_id);
        return ['status'=>1,'data'=>$customer_info];
    }

    /**
     * 更新客服最近服务时间
     * @param $business_id
     */
    public static function updateBusinessServiceTime($business_id){
        Db::name('business')->where(['id'=>$business_id])->setField('last_service_time',time());
    }

    /**
     * 建立用户客服连接记录
     * @param $user_id
     * @param $business_id
     * @param $store_id
     */
    public static function linkBusinessAndUser($user_id, $business_id, $store_id){
        $model = Db::name('business_user');
        $id = $model->where(['user_id'=>$user_id,'business_id'=>$business_id])->value('id');
        if(!$id){
            $model->insert([
                'user_id' => $user_id,
                'store_id' => $store_id,
                'business_id' => $business_id,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }else{
            $model->where(['id'=>$id])->update([
                'update_time' => time()
            ]);
        }
    }

    /**
     * 更新服务信息
     * @param $user_id
     * @param $business_id
     * @param $store_id
     */
    public static function updateService($user_id, $business_id, $store_id){
        self::updateBusinessServiceTime($business_id);
        self::linkBusinessAndUser($user_id, $business_id, $store_id);
    }

    /**
     * 检查用户是否在线
     * @return bool
     */
    public static function checkCustomerServiceStatus($jig_id){
        $data = self::get('check_status',$jig_id);
        $data = json_decode($data,true);
        return (isset($data['online']) && $data['online'])?true:false;
    }

    /**
     * 获取客服信息
     * @param $user_id
     */
    public static function getServiceInfo($user_id){
        $username = Db::name('business')->where(['id'=>$user_id])->value('jig_id');
        $data = self::get('get_info',$username);
        print_r($data);
    }

    /**
     * 获取用户信息
     * @param $user_id
     */
    public static function getUserInfo($user_id){
        $username = Db::name('user')->where(['user_id'=>$user_id])->value('jig_id');
        $data = self::storeGet('get_info',$username);
        print_r($data);
    }

    /**
     * 发送一条推送消息
     * @param $alias
     * @return bool|string
     */
    public static function sendMsgToCustomerService($alias){
        $appkey = self::$conf['store_appkey'];
        $secret = self::$conf['store_secret'];
        $base64=base64_encode("$appkey:$secret");
        $header=array("Authorization:Basic $base64","Content-Type:application/json");
        $data = array();
        $data['platform'] = 'all';          //目标用户终端手机的平台类型android,ios,winphone
        $receiver = [
            'alias' => [$alias]
        ];
        $data['audience'] = $receiver;      //目标用户
        $content = "收到一条客户消息";
        $data['notification'] = array(
            //统一的模式--标准模式
            "alert"=>$content,
            //安卓自定义
//            "android"=>array(
//                "alert"=>$content,
//                "title"=>"超神宿客户消息",
//                "builder_id"=>1,
////                "extras"=>array("type"=>$m_type, "txt"=>$m_txt)
//            ),
//            //ios的自定义
//            "ios"=>array(
//                "alert"=>$content,
//                "badge"=>"1",
//                "sound"=>"default",
////                "extras"=>array("type"=>$m_type, "txt"=>$m_txt)
//            )
        );

        //苹果自定义---为了弹出值方便调测
        $data['message'] = array(
            "msg_content"=>$content,
//            "extras"=>array("type"=>$m_type, "txt"=>$m_txt)
        );

        //附加选项
        $data['options'] = array(
            "sendno"=>time(),
            "time_to_live"=>86400, //保存离线时间的秒数默认为一天
            "apns_production"=>false, //布尔类型   指定 APNS 通知发送环境：0开发环境，1生产环境。或者传递false和true
        );
        $param = json_encode($data);
        $res = self::push_curl($param,$header);

        if($res){       //得到返回值--成功已否后面判断
            return $res;
        }else{          //未得到返回值--返回失败
            return false;
        }
    }

    /**
     * 给用户推送消息
     * @param $alias
     * @param $type
     * @param $par
     * @return bool|string
     */
    public static function sendMsgToStaff($alias, $type, $par=[]){
        $info = self::createMsgExtrasAndContents($type, $par);
        if(!$info)return false;
        $appkey = self::$conf['store_appkey'];
        $secret = self::$conf['store_secret'];
        $base64=base64_encode("$appkey:$secret");
        $header=array("Authorization:Basic $base64","Content-Type:application/json");
        $data = array();
        $data['platform'] = 'all';          //目标用户终端手机的平台类型android,ios,winphone
        if(!is_array($alias))$alias = [$alias];
        $receiver = [
            'alias' => $alias
        ];
        $data['audience'] = $receiver;      //目标用户

        $data['notification'] = array(
            //统一的模式--标准模式
//            "alert"=>$content,
            //安卓自定义
            "android"=>array(
                "alert"=>$info['content'],
                "title"=>$info['title'],
//                "builder_id"=>1,
                "extras"=>$info['extras']
            ),
            //ios的自定义
            "ios"=>array(
                "alert"=>[
                    'title' => $info['title'],
                    'subtitle' => $info['content']
                ],
//                "title"=>$title,
//                "badge"=>"1",
//                "sound"=>"default",
                "extras"=>$info['extras']
            ),
            "winphone" => array(
                "alert" => $info['content'],
                "title" => $info['title'],
                "extras" => $info['extras']
            )
        );

        //苹果自定义---为了弹出值方便调测
        $data['message'] = array(
            "msg_content"=>$info['content'],
//            "extras"=>array("type"=>$m_type, "txt"=>$m_txt)
        );

        //附加选项
        $data['options'] = array(
            "sendno"=>time(),
            "time_to_live"=>30 * 60, //保存离线时间的秒数默认为一天
            "apns_production"=>false, //布尔类型   指定 APNS 通知发送环境：0开发环境，1生产环境。或者传递false和true
        );
        $param = json_encode($data);
        $res = self::push_curl($param,$header);

        if($res){       //得到返回值--成功已否后面判断
            return $res;
        }else{          //未得到返回值--返回失败
            return false;
        }
    }

    public static function sendMsgToUser($alias, $type, $par=[]){
        $info = self::createMsgExtrasAndContents($type, $par);
        if(!$info)return false;
        $appkey = self::$conf['appkey'];
        $secret = self::$conf['secret'];
        $base64=base64_encode("$appkey:$secret");
        $header=array("Authorization:Basic $base64","Content-Type:application/json");
        $data = array();
        $data['platform'] = 'all';          //目标用户终端手机的平台类型android,ios,winphone
        if(!is_array($alias))$alias = [$alias];
        $receiver = [
            'alias' => $alias
        ];
        $data['audience'] = $receiver;      //目标用户

        $data['notification'] = array(
            //统一的模式--标准模式
//            "alert"=>$content,
            //安卓自定义
            "android"=>array(
                "alert"=>$info['content'],
                "title"=>$info['title'],
//                "builder_id"=>1,
                "extras"=>$info['extras']
            ),
            //ios的自定义
            "ios"=>array(
                "alert"=>[
                    'title' => $info['title'],
                    'subtitle' => $info['content']
                ],
//                "title"=>$title,
//                "badge"=>"1",
//                "sound"=>"default",
                "extras"=>$info['extras']
            ),
            "winphone" => array(
                "alert" => $info['content'],
                "title" => $info['title'],
                "extras" => $info['extras']
            )
        );

        //苹果自定义---为了弹出值方便调测
        $data['message'] = array(
            "msg_content"=>$info['content'],
//            "extras"=>array("type"=>$m_type, "txt"=>$m_txt)
        );

        //附加选项
        $data['options'] = array(
            "sendno"=>time(),
            "time_to_live"=>30 * 60, //保存离线时间的秒数默认为一天
            "apns_production"=>false, //布尔类型   指定 APNS 通知发送环境：0开发环境，1生产环境。或者传递false和true
        );
        $param = json_encode($data);
        $res = self::push_curl($param,$header);

        if($res){       //得到返回值--成功已否后面判断
            return $res;
        }else{          //未得到返回值--返回失败
            return false;
        }
    }

    public static function push_curl($param="",$header="") {
        if (empty($param)) { return false; }
        $postUrl = "https://api.jpush.cn/v3/push";
        $curlPost = $param;
        $ch = curl_init();                                      //初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);                      //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);           // 增加 HTTP Header（头）里的字段
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch);                                 //运行curl
        curl_close($ch);
        return $data;
    }

    /**
     * 验证客服权限
     * @param $business_id
     * @param $power_id
     * @return int|string
     */
    protected static function checkPower($business_id, $power_id){
        ##检查是否店长
        $pid = Db::name('business')->where(['id'=>$business_id])->value('pid');
        if(!$pid)return true;
        return Db::name('business_power_details')->where(['business_id'=>$business_id,'power_id'=>$power_id])->count('id');
    }

    /**
     * 生成密码
     * @return string
     */
    protected static function createPwd(){
        return "css_" . (string)rand(1000,9999);
    }

    /**
     * post会话
     * @param $url
     * @param $param
     * @return bool|string
     */
    protected static function post($url, $param, $type=1){

        $return_data = self::postCurl(self::$url[$url], $param, $type);

        return $return_data;

    }

    /**
     * 修改信息
     * @param $url
     * @param $param
     * @param $nickname
     * @param int $type
     * @return bool|string
     */
    protected static function editPost($url, $param, $nickname, $type=1){

        $url = sprintf(self::$url[$url],$nickname);

        $return_data = self::curl_put($url, $param, $type);

        return $return_data;
    }

    /**
     * get会话
     * @param $url
     * @param $string
     * @param $param
     * @return bool|string
     */
    protected static function get($url, $string){

        $url = sprintf(self::$url[$url],$string);

        $return_data = self::getCurl($url);

        return $return_data;

    }

    /**
     * user get 会话
     * @param $url
     * @param $string
     * @return bool|string
     */
    protected static function storeGet($url, $string){

        $url = sprintf(self::$url[$url],$string);

        $return_data = self::storeGetCurl($url);

        return $return_data;

    }

    /**
     * 操作curl
     * @param $url
     * @param $param
     * @return bool|string
     */
    protected static function postCurl($url, $param, $type=1){

        $appkey = $type==1?self::$conf['appkey']:self::$conf['store_appkey'];
        $secret = $type==1?self::$conf['secret']:self::$conf['store_secret'];
        $base64 = base64_encode("$appkey:$secret");
        $header = array("Authorization:Basic $base64", "Content-Type:application/json");

        $ch = curl_init();                                      //初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);                      //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);           // 增加 HTTP Header（头）里的字段
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $return_data = curl_exec($ch);                                 //运行curl
        curl_close($ch);

        return $return_data;
    }

    //HTTP请求（支持HTTP/HTTPS，支持GET/POST）
    protected static function getCurl($url){

        $appkey = self::$conf['store_appkey'];
        $secret = self::$conf['store_secret'];
        $base64 = base64_encode("$appkey:$secret");
        $header = array("Authorization:Basic $base64", "Content-Type:application/json");

        $ch = curl_init();                                      //初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 0);                      //post提交方式
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);           // 增加 HTTP Header（头）里的字段
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $return_data = curl_exec($ch);                                 //运行curl
        curl_close($ch);

        return $return_data;

    }

    //HTTP请求（支持HTTP/HTTPS，支持GET/POST）
    protected static function storeGetCurl($url){

        $appkey = self::$conf['appkey'];
        $secret = self::$conf['secret'];
        $base64 = base64_encode("$appkey:$secret");
        $header = array("Authorization:Basic $base64", "Content-Type:application/json");

        $ch = curl_init();                                      //初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 0);                      //post提交方式
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);           // 增加 HTTP Header（头）里的字段
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $return_data = curl_exec($ch);                                 //运行curl
        curl_close($ch);

        return $return_data;

    }

    /**
     * 构建参数
     * @param $type
     * @param $par
     * @return array
     */
    protected static function createMsgExtrasAndContents($type, $par){
        $conf = getMessageConf($type);
        $params = "";
        switch($type){
            case 'order_wait_pay_60':
                $params = sprintf($conf['param'], $par['order_id']);
                break;
            case 'order_wait_pay_30':
                $params = sprintf($conf['param'], $par['order_id']);
                break;
            case 'shouhou_pass':
                $params = $conf['param'];
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'shouhou_refuse':
                $params = $conf['param'];
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'order_send_pro':  //发货
                $params = sprintf($conf['param'], $par['order_id']);
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'order_sys_cancel':
                $params = sprintf($conf['param'], $par['order_id']);
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'order_store_cancel':
                $params = sprintf($conf['param'], $par['order_id']);
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'dynamic_add':
                $title = $par['title'];
                $params = sprintf($conf['param'], $par['dynamic_id']);
                $conf['content'] = $par['content'];
                break;
            case 'shouhou_refund':
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'order_refund':
                $params = sprintf($conf['param'], $par['order_id']);
                $conf['content'] = sprintf($conf['content'], $par['order_no']);
                break;
            case 'order_wait_handle_store':
                break;
            case 'fahou_system_msg':
                break;
            case 'shouhou_system_msg':
                break;
            case 'sellout_system_msg':
                break;
            default:
                return [];
                break;
        }
        $params = explode('&',$params);
        if($params[0]){
            $param = [];
            foreach($params as $v){
                $temp = explode('=',$v);
                $param[$temp[0]] = $temp[1];
            }
        }
        $extras = [
            "type"=>$conf['type'],
            "link"=>$conf['link'],
            'param'=>$param
        ];
        $content = $conf['content'];

        $res = compact('content','extras');
        $res['title'] = isset($title)?$title: "超神宿";
        return $res;
    }

}