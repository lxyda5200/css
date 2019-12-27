<?php


namespace app\user_v2\common;

use app\common\controller\Base;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user_v2\controller\Task;
use think\Exception;
use think\Db;

class Orders
{

    /**
     * 获取订单详情id
     * @param $user_id
     * @return $order_id
     */
    public static function getorders($user_id,$order_id){
        return Db::name('product_order_detail')
            ->join('product_order','product_order.id = product_order_detail.order_id','left')
            ->where('product_order.user_id',$user_id)
            ->where('product_order_detail.order_id',$order_id)
            ->where('product_order_detail.is_shouhou','neq',1) //过滤掉售后订单
            ->group('product_order_detail.order_id')
            ->column('product_order_detail.order_id');
    }

    /**
     * 获取订单详情信息
     * @param $user_id
     * @return $order_id
     */
    public static function get_order_info($user_id,$order_id){
        return Db::view('product_order','id,order_no,pay_order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,distribution_mode,fahuo_time,address_status,pay_type,pay_time,store_id,pay_money,total_freight,order_status,create_time,is_group_buy,logistics_info,order_type,total_freight,pt_type,pt_id,finish_time,logistics_company,logistics_number')
            ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
            ->where('product_order.id',$order_id)
            ->where('product_order.user_id',$user_id)
            ->find();
    }
    /**
     * 30分钟未支付自动取消
     * @param $user_id
     * @return $order_id
     */
    public static function autoCancelNotPayOrder($user_id,$order_id,$coupon_id,$store_coupon_id,$order_no){

        //取消订单
        Db::name('product_order')->where('id',$order_id)->where('user_id',$user_id)->setField('order_status', -1);

        Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
        //返回库存
        $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
        foreach ($product as $k=>$v){
            Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
        }
        //判断是否有平台优惠券
        if($coupon_id>0 ){
            Db::name('coupon')->where('id',$coupon_id)->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }
        //判断是否有店铺优惠券
        if($store_coupon_id>0){
            Db::name('coupon')->where('id',$store_coupon_id)->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }
        //取消通知
        $msg_id = Db::name('user_msg')->insertGetId([
            'title' => '取消通知',
            'content' => '您的订单 '.$order_no.' 超时未支付，已自动取消',
            'type' => 2,
            'create_time' => time()
        ]);
        Db::name('user_msg_link')->insert([
            'user_id' => $user_id,
            'msg_id' => $msg_id
        ]);
    }
    /**
     * 7天未发货自动取消
     * @param $user_id
     * @return $order_id
     */
    public static function autoCancelWaitSendOrder($user_id,$order_id,$pay_type,$pay_order_no,$pay_money,$coupon_id,$store_coupon_id,$order_no){
        try{
        //todo 此处原路退款
        if ($pay_type == '支付宝') {
            $alipay = new AliPay();
            $res = $alipay->alipay_refund($pay_order_no,$order_id,$pay_money);
        }elseif ($pay_type == '微信'){
            $total_pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');
            $wxpay = new WxPay();
            $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$pay_money);
        }
        if ($res !== true){
            throw new Exception('取消订单退款失败');
        }
        //修改订单状态
        Db::name('product_order')->where('id',$order_id)->where('user_id',$user_id)->update(['order_status'=>1,'cancel_time'=> time()]);
        //返回库存
        $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
        foreach ($product as $k=>$v){
            Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
        }
        Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
        //3退款通知
        $msg_id = Db::name('user_msg')->insertGetId([
            'title' => '退款通知',
            'content' => '您的订单'.$order_no.'商家超时未发货,已自动取消,订单金额已原路返回',
            'type' => 2,
            'create_time' => time()
        ]);
        Db::name('user_msg_link')->insert([
            'user_id' => $user_id,
            'msg_id' => $msg_id
        ]);
        //判断是否有平台优惠券
        if($coupon_id>0 ){
            Db::name('coupon')->where('id',$coupon_id)->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }
        //判断是否有店铺优惠券
        if($store_coupon_id>0){
            Db::name('coupon')->where('id',$store_coupon_id)->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return true;
    }
    /**
     * 15天未确认收货自动确认收货
     * @param $user_id
     * @return $order_id
     */
    public static function autoConfirmOrder($user_id,$order_id,$coupon_id,$store_coupon_id,$order_no){
$task=new Task();
$task->autoConfirmOrder();
     return true;
    }
}