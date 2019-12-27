<?php
use think\Route;

Route::post('business/login'                ,'business/user/login');                    // 用户登录
Route::post('business/logout'                ,'business/user/logout');                    // 用户登录
Route::post('business/home'                 ,'business/user/home');                     // 首页总览
Route::post('business/homeTask'             ,'business/user/myHomeTask');               // 首页我的任务
Route::post('business/taskData'             ,'business/user/myTaskOrderData');          // 我的任务订单数据

Route::post('business/productList'          ,'business/user/getStoreProductList');      // 获取店铺商品列表
Route::post('business/productDetails'       ,'business/user/getProductDetails');        // 商品详情
Route::post('business/productUpper'         ,'business/user/productUpper');             // 商品上架
Route::post('business/productLower'         ,'business/user/productLower');             // 商品下架

Route::post('business/shopOrder'            ,'business/user/getShopOrderByType');       // 商城订单数据
Route::post('business/orderDetails'         ,'business/user/getOrderDetails');          // 订单详情接口
Route::post('business/orderGet'             ,'business/user/businessGetOrder');         // 员工领取订单(稍后发货)
Route::post('business/orderSend'            ,'business/user/businessSendOrder');        // 员工发货订单(订单发货)
Route::post('business/cancelOrder'          ,'business/user/businessCancelOrder');      // 员工取消订单
Route::post('business/businessOrder'        ,'business/user/getBusinessOrder');         // 获取员工订单
Route::post('business/refuseRefund'         ,'business/user/businessRefuseRefund');     // 售后订单拒绝退款
Route::post('business/getShou'              ,'business/user/businessGetShou');          // 售后 稍后发货
Route::post('business/salesReturn'          ,'business/user/salesReturn');              // 售后 同意退货
Route::post('business/moneyBack'            ,'business/user/moneyBack');                // 售后 同意退款
Route::post('business/refuseApplication'    ,'business/user/refuseApplication');        // 售后 拒绝申请

Route::post('business/orderStatistics'      ,'business/user/getOrderStatistics');       // 订单统计
Route::post('business/shopMaiStatistics'    ,'business/user/getShopMaiStatistics');     // 收益统计
Route::post('business/statisticsDetail'     ,'business/user/getStatisticsDetail');      // 统计订单详情

Route::post('business/businessInfo'         ,'business/user/getBusinessInfo');          // 获取员工详情
Route::post('business/exitAvatar'           ,'business/user/businessEditAvatar');       // 员工修改头像
Route::post('business/staffAccount'         ,'business/user/getStaffAccount');          // 获取员工账号
Route::post('business/addBusinessAccount'   ,'business/user/addBusinessAccount');       // 新建员工账号【暂时无权限】
Route::post('business/myWallet'             ,'business/user/getMyWallet');              // 我的钱包
Route::post('business/addWithdraw'          ,'business/user/businessAddWithdraw');      // 员工提现【未调用支付宝第三方接口】
Route::post('business/getPowerList'         ,'business/user/getPowerList');             // 获取权限列表
Route::post('business/getBusinessPower'     ,'business/user/getBusinessPower');         // 获取员工权限列表
Route::post('business/editBusinessPower'    ,'business/user/editBusinessPower');        // 修改员工权限
Route::post('business/getRole'              ,'business/user/getRoleData');              // 获取员工角色【设计图中的岗位】数据列表

Route::post('business/msgData'              ,'business/user/getBusinessMsgData');       // 获取员工消息数据[未读]
Route::post('business/lookSysMsg'           ,'business/user/lookSysMsgList');            // 获取系统消息数据列表
Route::post('business/delSysMsg'            ,'business/user/businessDelMsgData');       // 员工删除系统消息【无此功能】

Route::post('business/businessSpread'       ,'business/user/getRecommendList');         // 新员工推广列表
Route::post('business/spreadDetails'        ,'business/user/getRecommendDetail');       // 新员工推广详情
Route::post('business/getSalesDetail'       ,'business/user/getSalesDetail');           // 销售提成详情
Route::post('business/getIncomesDetail'      ,'business/user/getIncomesDetail');        // 员工其他收入详情

Route::post('business/couponList'           ,'business/user/couponList');               // 优惠券
Route::post('business/getCoupon'            ,'business/user/getCoupon');                // 领取优惠券列表
Route::post('business/validateDetail'       ,'business/user/validateDetail');           // 优惠券核销列表详情
Route::post('business/validateCoupon'       ,'business/user/validateCoupon');           // 优惠券核销券码

Route::post('business/businessQrcode'        ,'business/user/businessQrcode'); // 生成员工二维码
Route::post('business/businessProfit'        ,'business/user/businessProfit'); // 员工收益结算
Route::post('business/fahou_system_msg'        ,'business/user/fahou_system_msg'); //
Route::post('business/shouhou_system_msg'        ,'business/user/shouhou_system_msg'); //
Route::post('business/sellout_system_msg'        ,'business/user/sellout_system_msg'); //
Route::post('business/deteleBusinessLog'        ,'business/user/deteleBusinessLog'); //
Route::post('business/test'        ,'business/user/test'); //

