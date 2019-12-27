<?php

$web_path = \think\Config::get('web_path');

return [

    'message_push' => [  // 小程序消息通知模板ID

        'audit_notice' => 'zRskihA26tEV3pg1JMxw_bpZOod5oy925q36cvRxcPs',  //审核通知

        'profit_get_notice' => 'SFbqB0NSqmSe0Zkn3ch33eP17xQY2r5VZiYRMRx-I0Y',  //收益到账通知

        'refund_notice' => '6QPioVNjh1WptgJrIYp_JyAUAQR6LH-q7bap_azhrgg',  //退款通知

        'order_send_notice' => 'G3kYUt3_BC9G2wKVDU7JgbynMljqzzgtgZX9_xpranQ',  //发货通知

        'order_pay_notice' => 'EOyI-397ysXPTJBk1xy4kfTHoEOu2kaSLH1fSgM0pss',  //订单支付成功通知

    ],

    'activity' => [  //活动

        'sheng_xin_gou' => [

            'store_ids' => [],

//            'product_ids' => [8190,8195,8192,9042,8311,8312,8193,9043,9044,9602],
            'product_ids' => [3,4,5,2,1,9043,9044,9602],

            'title' => "省心购",

            'desc' => '新人美妆限时狂欢'

        ],

        'xin_xian_xian_chao' => [

            'store_ids' => [509],

            'product_ids' => [4227,4228,4247,4230,4232,4233,4236,4246,4237,4238,4240,4242,4229],

            'title' => "新鲜现炒",

            'desc' => '工厂特惠福利'

        ]

    ],

    'is_catch_err' => false,   //是否监控异常

    'xcx_white_func' => ['saveformid'],  //小程序退出登录白名单方法

    'message_model' => [  //短信模板
        [
            'title' => '短信模板1',
            'content' => '这里是短信模板1'
        ],
        [
            'title' => '短信模板2',
            'content' => '这里是短信模板2'
        ],
        [
            'title' => '短信模板3',
            'content' => '这里是短信模板3'
        ],
    ],

    'week_day' => [

        '0' => '周天',

        '1' => '周一',

        '2' => '周二 ',

        '3' => '周三',

        '4' => '周四',

        '5' => '周五',

        '6' => '周六'
    ],

    ##排序权重常量
    'order_constant' => [

        'order_deal_weight' => 2,

        'store_follow_weight' => 2,

        'distance_weight' => 2,

        'product_sale_weight' => 1,

        'product_collect_weight' => 0.5,

        'product_read_weight' => 0.1,

    ],

    ##小程序缩略图压缩比例
    'compress_config' => [

        'check' => [300, 300],

        'chaoda' => [600, 600]

    ],

    ##活动上线提醒短信内容
    'activity_clock_message' => "您预约的（%s）还有10分钟就要开售了！历史最低价，快上APP准备开抢吧！{$web_path}/app/joinactivity.html?product_id=%d。",

    ##活动倒计时显示时长
    'activity_count_down' => 6,  //单位h

    ##订单未支付提醒短信内容(订单生成1小时),不影响发送的短信内容
    'order_repay_message_60' => "还有1小时，您的订单就自动取消啦！快来看看吧，进入APP/小程序，{$web_path}/app/repay.html?order_id=%d",

    ##订单未支付提醒短信内容(订单生成1.5小时),不影响发送的短信内容
    'order_repay_message_90' => "还有半个小时，您的订单就自动取消啦！快来看看吧，进入APP/小程序，{$web_path}/app/repay.html?order_id=%d",

    ##H5页面链接
    'h5_url' => [

        'brand_story' => $web_path . '/app/cssapp/brand_story/index.html?id=%d',

        'brand_dynamic_news' => $web_path . '/app/cssapp/brand_dynamic_news/index.html?id=%d',

        'new_trend' => $web_path . '/app/cssapp/new_trend_detail/index.html?id=%d',

        'store_banner' => $web_path . '/app/common_h5.html?a=user_v6&b=product&c=storeBannerDetail&id=%d'

    ],

    ##新用户定义时间,单位H
    'new_user_limit_hour' => 12,

    ##极光消息推送内容
    'jig_content' => [
        'order_wait_pay_60' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '还有1小时您的订单就自动取消啦！快来看看吧，点击查看详情>'
        ],
        'order_wait_pay_30' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '还有半小时您的订单就自动取消啦！快来看看吧，点击查看详情>'
        ],
        'shouhou_pass' => [
            'type' => 1,
            'link' => 2,
            'param' => '',
            'content' => '您的%s订单售后申请处理成功，点击查看详情>'
        ],
        'shouhou_refuse' => [
            'type' => 1,
            'link' => 2,
            'param' => '',
            'content' => '您的%s订单售后申请失败，点击查看详情>'
        ],
        'order_send_pro' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '您的%s订单已发货，点击查看详情'
        ],
        'order_sys_cancel' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '您的%s订单已被系统自动取消'
        ],
        'order_store_cancel' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '您的%s订单已被商家取消，金额已经退回账户，点击查看详情>'
        ],
        'dynamic_add' => [
            'type' => 1,
            'link' => 9,
            'param' => 'id=%d',
            'content' => ''  //标题+内容前20个字符+封面
        ],
        'shouhou_refund' => [
            'type' => 1,
            'link' => 2,
            'param' => '',
            'content' => '您的订单%s退款成功，点击查看详情>'
        ],
        'order_refund' => [
            'type' => 1,
            'link' => 1,
            'param' => 'order_id=%d',
            'content' => '您的订单%s退款成功，点击查看详情>'
        ],
        'order_wait_handle_store' => [
            'type' => 1,
            'link' => 1,    //跳转待发货列表
            'param' => '',
            'content' => '有新的订单需要发货，点击查看详情>',
        ],
        'fahou_system_msg' => [
            'type' => 1,
            'link' => 1,    //跳系统消息列表
            'param' => '',
            'content' => '您有新的待发货订单,请进入发货>',
        ],
        'shouhou_system_msg' => [
            'type' => 1,
            'link' => 1,    //跳系统消息列表
            'param' => '',
            'content' => '您有新的售后订单，请进入处理>',
        ],
        'sellout_system_msg' => [
            'type' => 1,
            'link' => 1,    //跳系统消息列表
            'param' => '',
            'content' => '您的宝贝已售完，请前往下架>',
        ]

    ],

    ##系统消息推送内容
    'system_content' => [

        'order_wait_pay_60' => '还有1小时您的订单就自动取消啦！',
        'order_wait_pay_30' => '还有半小时您的订单就自动取消啦！',
        'order_sys_cancel' => '您的%s订单已被系统自动取消',

    ],

    ##默认头像
    'head_pic' => [
        '00a6f5fb4ea906c9611070afe32251d5.jpg',
        '088dca8c86931abb14282ed575c25467.jpg',
        '0d609a1084633b2bbdfe987b44301112.jpg',
        '1216bb77f719733411d35cb5e92beae6.jpg',
        '189c5bfe09446e9c75a63fc0134cef04.jpg',
        '25808081fb2c9d244228cc1fbaba5b7e.jpg',
        '260347a168aa395e75490cd963598239.jpeg',
        '269dc9112b2c743e51b3fb7ecc3ea85a.jpeg',
        '28ae7256bcafdc6a75b89e4bb03ce456.jpg',
        '2e93390f1da9466b8142df5611d129ca.jpg',
        '2ea6ccae54fd983d9cb349b6171326a0.jpg',
        '362963a7c2e75b39848ad814b432b378.jpeg',
        '3b76718e1d0ff393b26e1ab9fe7f9712.jpeg',
        '3bd7e7a819c7811b2e4f3106f0c0f2e3.jpg',
        '3ccba31a1a85ccccc63dae835d2c6f9c.jpeg',
        '3dba8dcefd66383ac2a33b760051b252.jpg',
        '3fbdd3d588f2d6ce78171e7cb392b4a6.jpg',
        '4464bc3e6e2d3c54806f61e688bc91f2.jpg',
        '45abc6949bbbbecb66f5a45ec8b87f81.jpg',
        '4f6cb6a6b5b566a0ae869aa63776f1b4.jpg',
        '52c462b3df921963b8464530f6db3eee.jpeg',
        '5bb679b19df97bc1e0dad5d524bce4db.jpg',
        '5bfce2498c2bc49cd3d8d855c9bd9141.jpeg',
        '5e8699ff253584eba843a9a010c9f369.jpg',
        '65b84a7be6bb707f337f7e0b3146b98e.jpeg',
        '66b1cdcd706c97ea118af09f3abf5c76.jpg',
        '66b6b8c696d3bbc23f3c36b47c98c5a8.jpeg',
        '67e0e06699172fd6c9841849bff2fa76.jpg',
        '68600c2e44bd6cbe7da01dfab50b260a.jpeg',
        '6aa8ba29078aea952e9f7195c5119bfb.jpg',
        '6b5b6759b30155ae426fa41399f4c8ae.jpeg',
        '6e597b40e477cc7864ce5d97e52c5198.jpg',
        '6f06424ce5e0a032617bbb28444781f1.jpeg',
        '74043af3ca9992ae4731434c91a0a019.jpg',
        '7417c15ebba9eeafd5fc35911a826f25.jpeg',
        '7511df32f0e1eb555f072059a7fa74b7.jpeg',
        '78ef651de45ad415760288673912b6b4.jpeg',
        '7cd23dbedb7cb5e9a16c2e401e2d69ce.jpg',
        '81d6e2755a568f47a89f9ac2e122c1a1.jpg',
        '83424aa154e270c72bba8543bac7823c.jpeg',
        '84501236b4cf55abee6cd88f965c811e.jpg',
        '869dadd3decabfcc98e052b0e5434f48.jpeg',
        '86a12629274c1b52e3bbe457b04be2a4.jpeg',
        '87e826c3a6f3d72ca0a1da9db70f0614.jpeg',
        '8a47510db69f9605c28cfecc2cba1e39.png',
        '8a7510f115d9a3046da4edc2cbf8d580.jpg',
        '901b3c86228ac15f404c06766b510699.jpeg',
        '91101d2e500bce4465bdae3be7d81e60.jpeg',
        '957d7db46f98e9f3c84f101b5fc460b8.png',
        '9963c5483b28c40d44f10b5d5fbd732b.jpeg',
        '9afae5a5afc13ad25e9b592c9df8e7d8.jpg',
        '9d5a8b045249c610691c7ac21f5eba62.jpeg',
        '9fb7d51e3c37722e30710f69d83d495b.jpeg',
        'a14c7aeaf74740ad565d6489c53f0abe.jpg',
        'a5ae5ffd397821ead1171cc5f8d71188.jpeg',
        'a75c47c97a39bea4a6cb1ee8f7616e41.jpeg',
        'aa5bf0d109a9d37d5b75b056d9ab32b7.jpeg',
        'aec54649aed1b4bebc1e653b9429815a.jpg',
        'b1d543cd19c14ea81564700275e797f3.jpeg',
        'b9210e135e71600a2dfebe30f692cb14.jpg',
        'ba223e462778f5f55195ee08e3cadc7e.png',
        'bff5f2bfa54b6f74efdc765188b7c5c8.jpeg',
        'c063887727cf351a1e2482531ddcb353.jpeg',
        'c5dd1a96ecdfcb07a1a27a73197c4d41.jpg',
        'c833e617218501d1c0657bee302a8672.jpg',
        'c9ab0dc750d8e475dd8663cf4e8d9201.jpeg',
        'c9bf02ba66ef20191fad806a7be8da63.jpg',
        'c9de5dcb6d5372455c52fdbecc78e4e5.jpg',
        'cb42c8cf71cd726ca0c5d04c00b45fd4.png',
        'cec135039767c001be67aa4c20ad2080.jpeg',
        'cf89dd0b89d5cf981b3c05cf2e2b7086.jpeg',
        'd12016d5b741b8ba432f22e60cd0bcba.jpg',
        'd1d41ff7313f6a30779497c60ace778f.jpeg',
        'd35032e40b2b56c29e679ee977eed39a.jpeg',
        'd49f6e1b895ae44292f78ca634bab889.jpg',
        'dad3b04f9e3fcb415eb6ac91e48f58b7.jpeg',
        'dbd3a6e025d45fcf18c19cec12fd9ff0.jpg',
        'dd17a772d664deb1801f5d2c4a42b737.jpg',
        'e31a46fe1b00533061d10f27049dbe9e.jpg',
        'e4e7f8afa9aab0d53f0b29020fd4f811.jpg',
        'e717dcdf1dfd515fc655d6a993535095.jpg',
        'e9ef01469b18dbbc9f566c6e370fe31c.jpeg',
        'ea0fe79ef4b90b5a2757bb28134b60de.jpeg',
        'eb94c8bec977f6961b0d031a284ca30d.jpeg',
        'ef2510e7bd11e02ad533f4110b55a02f.jpg',
        'ewqewqewqewqewq1.jpeg',
        'fbd580ebf9c3a81c3c3001a212b5eecf.jpg',
        'fd8c548f5cc7062df8b87d6d8746aacf.jpeg',
    ]


];