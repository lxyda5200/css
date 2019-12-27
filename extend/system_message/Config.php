<?php


namespace system_message;


class Config
{

    public static function getConfig($option)
    {
        if(!$option)return [];

        self::$option = $option;

        return [
            'content' => self::getAttr('content'),
            'link_type' => self::getAttr('link_type'),
            'title' => self::getAttr('title'),
//            'param' => self::getAttr('param'),
        ];
    }

    public static function getParam($option)
    {
        if(!$option)return "";
        self::$option = $option;
        return self::getAttr('param');
    }

    protected static $option = "";

    ##系统消息内容
    protected static $content = [

        'order_deliver_goods' => '您的订单%s已发货',  //店铺发货

        'order_will_cancel_60' => '还有1小时您的订单就自动取消啦！',  //订单还有一小取消

        'order_will_cancel_30' => '还有1小时您的订单就自动取消啦！',  //订单还有半小取消

        'shouhou_refuse' => '您的订单%s售后申请失败',  //售后申请失败

        'shouhou_pass' => '您的订单%s售后申请处理成功',  //售后申请成功

        'shouhou_refund' => '您的订单%s退款成功',  //售后退款成功

        'order_cancel_by_system' => '您的订单%s已被系统自动取消，金额已经退回账户',   //系统取消订单

        'order_cancel_by_system_not_pay' => '您的订单%s已被系统自动取消',  //系统自动取消超时未支付订单

        'warning_order_will_cancel_60' => '还有1小时您的订单就自动取消啦！',  //订单还有一小时取消

        'warning_order_will_cancel_30' => '还有半小时您的订单就自动取消啦！',  //订单还有半小时取消

        'activity_will_start_clock' => '您预约的商品%s还有10分钟就要开售了！历史最低价，快去抢购吧！',  //活动即将开始提示

    ];

    ##系统消息跳转类型
    ## 1.订单详情 2.售后列表 3.商品详情
    protected static $link_type = [

        'order_deliver_goods' => 1,   //店铺发货[订单详情]

        'order_will_cancel_60' => 1,  //订单取消

        'order_will_cancel_30' => 1,  //订单取消

        'shouhou_refuse' => 2,  //售后申请失败

        'shouhou_pass' => 2,  //售后申请成功

        'shouhou_refund' => 2,  //售后退款成功

        'order_cancel_by_system' => 1,  //系统取消订单

        'order_cancel_by_system_not_pay' => 1,  //系统自动取消超时未支付订单

        'warning_order_will_cancel_60' => 1,  //订单还有一小时取消

        'warning_order_will_cancel_30' => 1,  //订单还有半小时取消

        'activity_will_start_clock' => 3,  //活动即将开始提示

    ];

    ##系统消息标题
    protected static $title = [

        'order_deliver_goods' => '订单提醒',   //店铺发货

        'order_will_cancel_60' => '订单提醒',  //订单取消

        'order_will_cancel_30' => '订单提醒',  //订单取消

        'shouhou_refuse' => '售后提醒',  //售后申请失败

        'shouhou_pass' => '售后提醒',  //售后申请成功

        'shouhou_refund' => '退款提醒',  //售后退款成功

        'order_cancel_by_system' => '订单提醒',  //系统取消订单

        'order_cancel_by_system_not_pay' => '订单提醒',  //系统自动取消超时未支付订单

        'warning_order_will_cancel_60' => '订单提醒！',  //订单还有一小时取消

        'warning_order_will_cancel_30' => '订单提醒！',  //订单还有半小时取消

        'activity_will_start_clock' => '活动提醒',  //活动即将开始提示

    ];

    ##系统消息参数
    protected static $param = [

        'order_deliver_goods' => 'order_id=%d',   //店铺发货

        'order_will_cancel_60' => 'order_id=%d',  //订单取消

        'order_will_cancel_30' => 'order_id=%d',  //订单取消

        'shouhou_refuse' => '',  //售后申请失败

        'shouhou_pass' => '',  //售后申请成功

        'shouhou_refund' => '',  //售后退款成功

        'order_cancel_by_system' => 'order_id=%d',  //系统取消订单

        'order_cancel_by_system_not_pay' => 'order_id=%d',  //系统自动取消超时未支付订单

        'warning_order_will_cancel_60' => 'order_id=%d',  //订单还有一小时取消

        'warning_order_will_cancel_30' => 'order_id=%d',  //订单还有半小时取消

        'activity_will_start_clock' => 'product_id=%d',  //活动即将开始提示

    ];

    ##获取属性
    protected static function getAttr($attr){
        $item = self::$$attr;
        return isset($item[self::$option])?$item[self::$option]:"";
    }

}