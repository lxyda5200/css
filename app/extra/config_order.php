<?php


return [
    'one_hour_not_pay' => 1,   //1小时未支付提醒支付

    'one_and_a_half_hours_not_pay' => 1.5,  //1.5小时未支付提醒支付

    'hour_cancel_not_pay' => 2,   //订单未支付自动取消订单时间

//    'hour_cancel_not_send' => 7 * 24,  //订单已支付未发货自动取消订单时间
   // 'hour_cancel_not_send' => 12,  //订单已支付未发货自动取消订单时间
    'hour_cancel_not_send' => 24,  //订单已支付未发货自动取消订单时间
//    'hour_confirm_not_confirm' => 15 * 24,  //订单已发货用户未收货自动确认收货时间
   // 'hour_confirm_not_confirm' => 7 * 24,  //订单已发货用户未收货自动确认收货时间
    'hour_confirm_not_confirm' => 6 * 24,  //订单已发货用户未收货自动确认收货时间
    'hour_agree_after_sale' => 7 * 24,  //订单已提交售后商家未确认售后自动确认时间
    'hour_arrive_pro' => 15 * 24,  //用户退货后商户未确认收货自动退款时间

    //'hour_comment_not_comment' => 3 * 24,  //用户待评价未评价自动评价时间
    'hour_comment_not_comment' => 1 * 24,  //用户待评价未评价自动评价时间
    //'hour_finish' => 4 * 24,  //订单自动完成(结转等)时间
    'hour_finish' => 2 * 24,  //订单自动完成(结转等)时间
    'hour_receive_shouhou_pro' => 7 * 24,  //售后自动确认收货时间
    'hour_shouhou_platform_service' => 3 * 24,  //售后订单未处理自动转为平台介入时间

    'time_safe' => 1572927689,  //安全时间(订单查询安全时间)

];