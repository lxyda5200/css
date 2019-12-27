<?php

namespace templateMsg;

use think\Db;

class CreateTemplate extends Config
{

    /**
     * 生成潮搭审核模板信息
     * @param $chaoda_id
     * @return array|bool
     */
    public static function createAuditMsgData($title, $status){
        $data = [
            'keyword1' => [  //类型
                'value' => '潮搭发布审核'
            ],
            'keyword2' => [  //状态
                'value' => $status
            ],
            'keyword3' => [  //申请项目
                'value' => $title
            ],
            'keyword4' => [  //处理时间
                'value' => date('Y-m-d H:i', time())
            ],
        ];
        return $data;
    }

    /**
     * 生成返利到账提示模板
     * @param $price_profit
     * @param $order_no
     * @param $product_name
     * @return array
     */
    public static function createProfitGetMsgData($price_profit, $order_no, $product_name){
        ##返利到账
        $data = [
            'keyword1' => [  //收益金额
                'value' => $price_profit
            ],
            'keyword2' => [  //到账时间
                'value' => date('Y-m-d H:i', time())
            ],
            'keyword3' => [  //商品名称
                'value' => $product_name
            ],
            'keyword4' => [  //订单号
                'value' => $order_no
            ],
        ];
        return $data;
    }

    /**
     * 生成退款提醒模板
     * @param $money_refund
     * @param $product_name
     * @param $store_name
     * @param $refund_type
     * @return array
     */
    public static function createRefundMsgData($money_refund, $product_name, $store_name, $refund_type){

        $data = [
            'keyword1' => [  //退款金额
                'value' => $money_refund
            ],
            'keyword2' => [  //商品名称
                'value' => $product_name
            ],
            'keyword3' => [  //退款商家
                'value' => $store_name
            ],
            'keyword4' => [  //退款类型
                'value' => $refund_type
            ],
            'keyword5' => [  //退款账户
                'value' => '原路退还'
            ],
            'keyword6' => [  //到账时间
                'value' => date("Y-m-d H:i", time())
            ],
        ];
        return $data;

    }

    /**
     * 生成发货消息模板
     * @param $logistics
     * @param $product_name
     * @param $order_no
     * @param $address
     * @return array
     */
    public static function createOrderSendMsgData($logistics, $product_name, $order_no, $address){

        $data = [
            'keyword1' => [  //快递公司
                'value' => $logistics
            ],
            'keyword2' => [  //发货时间
                'value' => date('Y-m-d H:i', time())
            ],
            'keyword3' => [  //物品名称
                'value' => $product_name
            ],
            'keyword4' => [  //订单号
                'value' => $order_no
            ],
            'keyword5' => [  //收货地址
                'value' => $address
            ]
        ];
        return $data;

    }

    /**
     *生成订单支付消息模板
     * @param $order_no
     * @param $price_order
     * @param $product_name
     * @return array
     */
    public static function createOrderPayMsgData($order_no, $price_order, $product_name){

        $data = [
            'keyword1' => [  //订单号
                'value' => $order_no
            ],
            'keyword2' => [  //支付时间
                'value' => date("Y-m-d H:i", time())
            ],
            'keyword3' => [  //订单金额
                'value' => $price_order
            ],
            'keyword4' => [  //商品名称
                'value' => $product_name
            ]
        ];
        return $data;

    }

    /**
     * 生成优惠券下发消息模板
     * @param $coupon_name
     * @param $store_name
     * @param $use_desc
     * @param $use_limit
     * @param $expiration_time
     * @param $number
     * @return array
     */
    public static function createCouponGetMsgData($coupon_name, $store_name, $use_desc, $use_limit, $expiration_time, $number){

        $data = [
            'keyword1' => [  //物品名称
                'value' => $coupon_name
            ],
            'keyword2' => [  //店铺名称
                'value' => $store_name
            ],
            'keyword3' => [  //使用说明
                'value' => $use_desc
            ],
            'keyword4' => [  //适用范围
                'value' => $use_limit
            ],
            'keyword5' => [  //时间
                'value' => "到期时间:" . date("Y-m-d H:i", $expiration_time)
            ],
            'keyword6' => [  //领取数量
                'value' => $number
            ]
        ];
        return $data;

    }

    /**
     * 发送模板消息
     * @param $open_id
     * @param $templateInfo
     * @param $form_id
     * @param $access_token
     * @return bool|string
     */
    public static function sendTemplateMsg($open_id, $templateInfo, $form_id, $access_token){
        ##组装data
        $data = [
            'touser' => $open_id,
            'template_id' => $templateInfo['template_id'],
            'page' => $templateInfo['page'],
            'form_id' => $form_id['form_id'],
            'data' => $templateInfo['data'],
//                'emphasis_keyword' => $templateInfo['emphasis_keyword']
        ];
        ##发送消息
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token={$access_token}";

        return self::http($url,$data);
    }

    /**
     * 获取模板ID
     * @param $field
     * @return mixed
     */
    public static function getModelId($field){
        $config = (new parent())->modelIds;
        return $config[$field];
    }

    /**
     * 获取小程序页面
     * @param $field
     * @return mixed
     */
    public static function getPage($field){
        $config = (new parent())->pages;
        return $config[$field];
    }

    //封装请求方法
    public static function http($url,$json){
//        header("Content-type: image/jpeg");
//        $header = "Accept-Charset: utf-8";
        $json = json_encode($json);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: '.strlen($json))
        );
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }else {
            $error = curl_errno($ch);
//            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

}