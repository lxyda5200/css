<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/19
 * Time: 17:25
 */

namespace app\common\controller;


use think\Controller;
use think\Request;

class Base extends Controller
{

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        ##异常处理
        if(config('config_common.is_catch_err')){
            set_error_handler(['app\common\controller\Base', 'errHandle']);
        }
    }


    /**
     * 接口回调
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public static function callback($status = 1,$msg = '',$data = 0,$flag=false){
        if ($data==0 && !$flag){
            $data = new \stdClass();
        }
        //正式阶段
        #return ['status'=>$status,'msg'=>$msg,'data'=>$data];
        //测试阶段
        return ['status'=>$status,'msg'=>$msg,'data'=>$data,'request'=>request()->post()];
    }

    /**
     * 支付宝账号验证
     * @param $alipay_account
     * @return bool
     */
    public function ALIAccountVerify($alipay_account){
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if(preg_match("/^1[34578]\d{9}$/", $alipay_account) || preg_match($pattern,$alipay_account)){
            return true;
        }
        return false;
    }

    /**
     * 监控异常
     * @param $errorNum
     * @param $errorMs
     * @param $errorFile
     * @param $errorLine
     */
    public static function errHandle($errorNum, $errorMs, $errorFile, $errorLine){
        $content = compact('errorMs','errorFile','errorNum','errorLine');
        addErrLog($content, "异常抛出监控");
    }

}