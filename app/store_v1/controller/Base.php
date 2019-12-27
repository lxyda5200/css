<?php


namespace app\store_v1\controller;


use app\common\controller\Email;
use think\Config;
use think\Db;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use my_redis\MRedis;
use think\response\Json;
use think\Session;
header("content-type:text/html;charset=utf-8");
class Base extends \app\common\controller\Base
{

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 当前URL
     * @var string
     */
    protected $requestUri = '';

    /**
     * 是否登录
     * @var bool
     */
    protected $_logined = false;

    /**
     * 账号信息
     * @var array
     */
    protected $store_info=[];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * 分页截至数量
     * @var int
     */
    protected $size = 10;

    protected $config = [];

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        header("Access-Control-Allow-Origin:*");
//        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Credentials: true');// 设置是否允许发送 cookies
//        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Expose-Headers: *');
//        header('Access-Control-Allow-Methods: *');
        header('X-Frame-Options: deny');
        header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin');
        $this->page = input('page') ? intval(input('page')) : 1 ;
        $this->size = input('size') ? intval(input('size')) : 10 ;
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');

        $this->config = Config::get('email_config');

        $modulename = $this->request->module();
        $controllername = strtolower($this->request->controller());
        $actionname = strtolower($this->request->action());

        // 获取token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));
        // 获取当前请求的URI
        $this->requestUri = str_replace('.', '/', $controllername) . '/' . $actionname;
        // 检测是否需要验证登录
        if (!$this->match($this->noNeedLogin)) {
            //初始化
            $this->checktoken($token);
            //检测是否登录
            if (!$this->_logined) {
                $this->error('需要用户登录');
            }
            // 判断是否需要验证权限
            if (!$this->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->check($this->requestUri)) {
                    $this->error('没有该权限', null, 403);
                }
            }
//        } else {
//            // 如果有传递token才验证是否登录状态
//            if ($token) {
//                $this->checktoken($token);
//            }
        }
    }

    protected function match($arr = []){
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 检测是否是否有对应权限
     * @param string $path   控制器/方法
     * @param string $module 模块 默认为当前模块
     * @return boolean
     */
    public function check($path = null, $module = null)
    {
        if (!$this->_logined) {
            return false;
        }
        //获取权限
//        $ruleList = $this->getRuleList();
        $rules = [];
//        foreach ($ruleList as $k => $v) {
//            $rules[] = $v['name'];
//        }
//        $url = ($module ? $module : request()->module()) . '/' . (is_null($path) ? $this->getRequestUri() : $path);
//        $url = strtolower(str_replace('.', '/', $url));
//        return in_array($url, $rules) ? true : false;
        return  true;
    }


    /**
     * 发送邮件
     * @param $email  //邮箱
     * @param $html   //内容
     * @param $title  //标题
     * @return bool
     */
    protected function setemail($email,$html,$title){
        $emails  = new Email();
        $result = $emails->to($email)
            ->subject($title)
            ->message($html)
            ->send();
        return $result;
    }


    /**
     * 发送验证邮件
     * @param $params
     * @return \think\response\Json
     */
    protected function codeemail($email){
        $email_code = random(6,1);
        $html ='<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="keywords" content="服饰,美妆,引流,电商,流量,购物,潮流,资讯" />
    <meta name="viewport" content="user-scalable=yes">
    <link rel="stylesheet" href="'.$this->config['mail_url'].'/static/email/emailModel.css">
    <meta name="description"
          content="四川神龟科技有限公司成立于2017年05月，旗下平台“超神宿APP”，着力于服饰与美妆领域，通过互联网连接实体门店与消费者，为品牌和用户提供优质服务。超神宿对用户进行精准分析，通过线上商品的展示和销售，有效地为实体商家实现线上至线下引流，以及品牌线上新品发布、活动促销和推广宣传等。旗下项目“流量小店”，有效整合各经销商、生产商的资源，以便利店的形式在全国范围内开设直营与加盟店。依托线上平台的庞大流量，将线上平台与线下门店相互结合，为用户提供更加便捷的购物体验。" />
    <title>四川神龟科技-商家平台-验证通知</title>
</head>
<body>
<div class="h-content">
    <p class="m1">尊敬的超神宿商家：</p>
    <div class="content-box">
        <div class="top">
            <p class="m2">您好！您正在注册的超神宿商家账号的邮箱验证码为：</p>
            <p class="m2 fontCss"><h3>'.$email_code.'</h3></p>
        </div>
        <div class="btm">
            <img src="'.$this->config['mail_url'].'/static/email/logo_two.png" class="logo-img">
            <p class="m3">官网：<a class="color-link" href="'.$this->config['mail_url'].'">'.$this->config['mail_url'].'</a></p>
            <p class="m3">地址：成都市高新区天府大道中段666号希顿国际广场B座702</p>
            <p class="m3">联系电话：028-85255310</p>
            <p class="sm">我们的使命是让实体品牌重塑品牌价值</p>
        </div>
    </div>
</div>
</body>
</html>';
        if(empty($this->config['mail_time'])){
            $this->config['mail_time'] =10;
        }
        $result =$this->setemail($email,$html,'您好！您正在注册的超神宿商家账号,请查收你的验证码,'.$this->config['mail_time'].'分钟内有效');
        if($result){
            Session::set('email',$email);
            Session::set('email_code',$email_code);
            Session::set('email_xpire_time',$_SERVER['REQUEST_TIME'] + 60*$this->config['mail_time']);
            return json(self::callback(0,'发送邮件成功'));
        }else{
            return json(self::callback(0,'发送邮件失败'));
        }
    }


    /**
     * 邮件验证码验证
     * @param $email
     * @param $code
     * @return bool
     */
    protected function chen_email($email,$code){
        if (!Session::has('email') || !Session::has('email_code')) {
            return false;
        }
        if (Session::get('email') != $email || Session::get('email_code') != $code) {
            return false;
        }

        if (Session::get('email_xpire_time') < time()) {
            return false;
        }
        return true;
    }


    /**
     * token验证
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function checktoken($token=''){
//        try{
            if(empty($token)){
                $token = request()->post('token');
            }
            if (!empty($token)) {
                //商户查询
                $store_info = Db::name('business')->where('token','eq',$token)->find();
                if($store_info){
                    if (time() - $store_info['token_expire_time'] > 0) {
                        //token长时间未使用而过期，需重新登陆
                        $this->error('登录已失效，请重新登录');
                    }
                    if ($store_info['business_status'] != 1) {
                        $this->error('账号已禁用');
                    }
                    if($store_info['main_id'] >1){
                        //查询店铺是否存在
                        $store = Db::name('store')->where('id',$store_info['store_id'])->find();
                        if (empty($store_info)) {
                            $this->error('店铺不存在');
                        }
                        //更新token过期时间
                        $new_expire_time = time() + 604800; //604800是七天 token七天保留时间
                        Db::name('business')->where('token','eq',$token)->setField('token_expire_time',$new_expire_time);
                        $data = $store;
                        unset($data['password']);
                        unset($data['pay_password']);
                        unset($data['token']);
                        unset($data['token_expire_time']);
                        unset($data['business_img']);
                        unset($data['brand_img']);
                        unset($data['brand_name']);
                        $data['user_id'] = $store_info['id'];
                        $data['business_name'] = $store_info['business_name'];
                        $data['mobile_user'] = $store_info['mobile'];
                        $data['email'] = $store_info['email'];
                        $data['group_id'] = $store_info['group_id'];
                        $data['user_user_name'] = $store_info['user_name'];
                        $data['is_type'] = $store_info['pid'];
                        $data['main_id'] = $store_info['main_id'];
                        $data['store_id'] = $store['id'];
//                $data['token'] = $store_info['token'];
                        $this->_logined = true;
                        $this->store_info = $data;
                    }else{
                        $data['store_id'] = 0;
                        $data['user_id'] = $store_info['id'];
                        $data['business_name'] = $store_info['business_name'];
                        $data['mobile_user'] = $store_info['mobile'];
                        $data['email'] = $store_info['email'];
                        $data['group_id'] = $store_info['group_id'];
                        $data['user_user_name'] = $store_info['user_name'];
                        $data['is_type'] = $store_info['pid'];
                        $data['main_id'] = $store_info['main_id'];
                        $this->_logined = true;
                        $this->store_info = $data;
//                    return json(self::callback(0,'账号未完成店铺注册'));
//                    $this->error('账号未完成店铺注册');
                    }
                }else{
                    $this->error('账号不存在');
                }
            }
//        }catch (\Exception $e){
//            $this->error($e->getMessage());
//        }
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
     * 操作成功返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为1
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 操作失败返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function error($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed  $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型，支持json/xml/jsonp
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'status' => $code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : 'json';

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }


    /**
     * ajax返回
     * @param int $status
     * @param string $msg
     * @param null $data
     */
    public function ajaxReturn($status = 1, $msg = 'success', $data = null) {
        exit(json_encode(['status' => $status, 'msg' => $msg, 'data' => $data]));
    }
}