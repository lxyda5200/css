<?php


namespace app\business\controller;


use app\business\model\BusinessModel;
use app\business\model\BusinessPowerDetailsModel;
use app\business\model\UserModel;
use think\Controller;
use think\Request;
use think\Db;

class Base extends Controller
{
    // 定义无需认证接口
    protected static $noLogin = [
        'User' => ['login','test'],
    ];

    public static $user_id = 0;           // 用户ID

    private static $token = 0;            // 用户token

    public static $user_info = [];        // 用户信息

    public static $requestInstance = [];  // 请求信息

    public static $params = [];           // 请求参数

    public function __construct(Request $request = null)
    {
        self::$requestInstance = Request::instance();
        self::$params = \request()->post();
        // 调用接口认证
        self::checkToken($request);
        self::log($request);
    }

    public static function log($request){
        $module = $request -> module();
        $controller = $request -> controller();
        $action = $request -> action();
        $business_id = $request->header('userId');
        $data['request'] = json_encode(request()->post());
        $data['ip'] = $request->ip();
        $data['business_id'] = empty($business_id) ? '0':$business_id;
        $data['operation'] = $module.'/'.$controller.'/'.$action;
        $data['create_time'] = time();

        Db('business_log')->insert($data);
    }

    /**
     * 用户token检测(主要针对需验证的接口)
     * @param $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function checkToken($request){
        $controller = $request -> controller();
        $action = $request -> action();
        self::$user_id = $request->header('userId');

        // 检测是否需要登录
        if (!isset(self::$noLogin[$controller]) || !in_array($action,self::$noLogin[$controller])){
            self::$token = $request->header('token');

            if (!self::$user_id || !self::$token){
                exit(self::returnResponse(1001,'身份验证失败',null));
            }

            // TODO 查找用户数据并比对是否一致
            $staff_user_info = self::getUserInfo(['b.token' => self::$token, 'b.id' => self::$user_id]);

            if ($staff_user_info){
                // 判断身份是否过期  1分钟差值【合理设置】
                if ($staff_user_info['token_expire_time']-time() < 60) exit(self::returnResponse(1001,'身份过期请重新登录',null));
            }else{
                exit(self::returnResponse(1001,'身份验证失败',null));
            }
        }else{
            $staff_user_info = self::getUserInfo(['b.id' => self::$user_id]);
        }

        self::$user_info = $staff_user_info;
    }

    /**
     *  获取用户信息
     * @param $where
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserInfo($where){
        $info = BusinessModel::where($where)
            ->alias('b')
            ->join('store s', 'b.store_id = s.id')
            -> field(['b.id,b.token_expire_time,b.mobile,s.store_status,b.pid,IF(b.pid>0,0,1) as is_main_user,b.business_status,b.store_id']) -> find();

        return $info;
    }

    /**
     *  密码加密
     * @param $password
     * @return false|string
     */
    public static function passEncryption($password){
        $return = password_hash($password, PASSWORD_DEFAULT);
        return $return;
    }

    /**
     *  生成用户验证TOKEN
     * @param null $prefix
     * @return string
     */
    public static function makeUserToken($prefix = null){
        mt_srand((double)microtime() * 10000);
        $charId = strtoupper(md5(uniqid(rand() , true)));
        $hyphen = chr(45);//"-"
        $uuid = chr(123)//"{"
            .substr($charId,0,8).$hyphen
            .substr($charId,8,4).$hyphen
            .substr($charId,12,4).$hyphen
            .substr($charId,16,4).$hyphen
            .substr($charId,20,12)
            .chr(125);//"}"

        $getUUID = strtoupper(str_replace("-","",$uuid));
        $generateReadableUUID = $prefix . date("ymdHis") . sprintf('%03d' , rand(0 , 999)) . substr($getUUID , 4 , 4);
        return md5($generateReadableUUID);
    }

    /**
     *  接口返回json格式数据 公共方法
     * @param int $code    状态码
     * @param string $msg  状态猫叔
     * @param array $data  返回数据
     * @return false|string  组装json格式数据
     */
    public static function returnResponse($code = 0, $msg = '', $data = []){
        $data = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'request' => request()->post()
        ];

        return json_encode($data);
    }

    /**
     * 判断权限
     * @param $is_main_user
     * @param $user_id
     * @param $power_id
     */
    public static function power($is_main_user,$user_id,$power_id){
        if($is_main_user == 1) return true;
        $info = BusinessPowerDetailsModel::where(['business_id'=>$user_id,'power_id'=>$power_id])->count();
        return $info;
    }
}