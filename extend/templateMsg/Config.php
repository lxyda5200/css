<?php


namespace templateMsg;


class Config
{

    protected $modelIds = [

        'audit_notice' => 'zRskihA26tEV3pg1JMxw_bpZOod5oy925q36cvRxcPs',  //审核通知

        'profit_get_notice' => 'SFbqB0NSqmSe0Zkn3ch33eP17xQY2r5VZiYRMRx-I0Y',  //收益到账通知

        'refund_notice' => '6QPioVNjh1WptgJrIYp_JyAUAQR6LH-q7bap_azhrgg',  //退款通知

        'order_send_notice' => 'G3kYUt3_BC9G2wKVDU7JgbynMljqzzgtgZX9_xpranQ',  //发货通知

        'order_pay_notice' => 'EOyI-397ysXPTJBk1xy4kfTHoEOu2kaSLH1fSgM0pss',  //订单支付成功通知

        'coupon_get_notice' => 'VqliQxj2iVMCaGRYp4kasg5vy37s-RIJmytgARBDfDQ',  //卡券下发通知

    ];

    protected $pages = [

        'audit_notice' => 'pkg-myself/pages/my-publish-detail/index',  //审核通知

        'profit_get_notice' => 'pages/tab-myself/index',  //收益到账通知

        'refund_notice' => 'pages/order-detail/index',  //退款通知

        'order_send_notice' => 'pages/order-detail/index',  //发货通知

        'order_pay_notice' => 'pages/order-detail/index',  //订单支付成功通知

        'coupon_get_notice' => 'pkg-myself/pages/my-coupon/index?couType=normal',  //优惠券下发成功通知

    ];

}