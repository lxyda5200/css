<?php


namespace app\common\controller;

use Aliyun\DySDKLite\SignatureHelper;
use think\Db;
use think\Exception;
use think\Config;
class AliSMS
{

    public static function sendAcClockMsg($mobile, $product_name, $product_id){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){
                $res=['Code'=>'OK'];
                return $res;
            }//已关闭直接返回
            ##验证手机
            if(!(new self())->checkMobile($mobile))throw new Exception('手机格式错误');

                // fixme 必填：是否启用https
            $security = false;

            $data = (new self())->createParams('ac_clock', $mobile);

            $params = $data['params'];

            // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
            $params['TemplateParam'] = Array (
                "product_name" => $product_name,
                "product_id" => $product_id
            );

            // fixme 可选: 设置发送短信流水号
            //$params['OutId'] = "12345";

            // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
            //$params['SmsUpExtendCode'] = "1234567";

            return (new self())->commonSend($data, $params, $security);

        }catch(Exception $e){

            return ['Code'=>'error', 'Message'=>$e->getMessage()];

        }

    }

    public static function sendOrderRepayMsg($mobile, $order_id, $pay_order_no, $user_id, $type){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){
                $res=['Code'=>'OK'];
                return $res;
            }//已关闭直接返回
            ##验证手机
            if(!(new self())->checkMobile($mobile))throw new Exception('手机格式错误');

            // fixme 必填：是否启用https
            $security = false;

            $data = (new self())->createParams("order_repay_{$type}", $mobile);

            $params = $data['params'];

            // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
            $params['TemplateParam'] = Array (
                "order_id" => $order_id
            );

            // fixme 可选: 设置发送短信流水号
            //$params['OutId'] = "12345";

            // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
            //$params['SmsUpExtendCode'] = "1234567";

            $data2 = [
                'pay_order_no' => $pay_order_no,
                'mobile' => $mobile,
                'user_id' => $user_id,
                'content' => sprintf(config("config_common.order_repay_message_{$type}"), $order_id),
                'create_time' => time()
            ];
            Db::table('send_message_record')->insert($data2);

            $res = (new self())->commonSend($data, $params, $security);

            return ($res);

        }catch(Exception $e){

            return ['Code'=>'error', 'Message'=>$e->getMessage()];

        }

    }


    public static function sendEntryStatus($mobile, $type){

        try{
            //短信开关
            $message_tap = Config::get('message_tap');
            if(!$message_tap){
                $res=['Code'=>'OK'];
                return $res;
            }//已关闭直接返回
            ##验证手机
            if(!(new self())->checkMobile($mobile))throw new Exception('手机格式错误');

            // fixme 必填：是否启用https
            $security = false;

            $data = (new self())->createParams($type, $mobile);

            $params = $data['params'];

            // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
            $params['TemplateParam'] = Array (

            );

            // fixme 可选: 设置发送短信流水号
            //$params['OutId'] = "12345";

            // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
            //$params['SmsUpExtendCode'] = "1234567";

            $res = (new self())->commonSend($data, $params, $security);

            return ($res);

        }catch(Exception $e){

            return ['Code'=>'error', 'Message'=>$e->getMessage()];

        }

    }






    /**
     * 生成参数
     * @param $type
     * @return array
     */
    private function createConfig($type){

        $conf = config('config_uploads.ali_oss');

        $accessKeyId = $conf['AccessKeyID'];

        $accessKeySecret = $conf['AccessKeySecret'];

        $signName = $conf['msg_sign_name'];

        $templateCode = $conf['msg_template_code'][$type];

        return compact('accessKeyId','accessKeySecret','signName','templateCode');

    }

    private function createParams($type, $mobile){

        $params = array ();

        // *** 需用户填写部分 ***

        $config = $this->createConfig($type);

        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = $config['accessKeyId'];
        $accessKeySecret = $config['accessKeySecret'];

        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $mobile;

        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignName"] = $config['signName'];

        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = $config['templateCode'];

        return compact('accessKeyId','accessKeySecret','params');

    }

    private function checkMobile($mobile){

        $pattern = "/^1(3[0-9]|4[5-9]|5[0-35-9]|7[0-9]|8[0-9]|9[89])\\d{8}$/";

        return preg_match($pattern, $mobile)?true:false;

    }

    /**
     * 发送信息
     * @param $data
     * @param $params
     * @param $security
     * @return mixed
     */
    private function commonSend($data, $params, $security){

        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if(!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
        $helper = new SignatureHelper();

        // 此处可能会抛出异常，注意catch
        $content = $helper->request(
            $data['accessKeyId'],
            $data['accessKeySecret'],
            "dysmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            )),
            $security
        );

        return json_decode(json_encode($content),true);

    }

}