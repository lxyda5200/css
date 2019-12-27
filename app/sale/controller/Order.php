<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/8
 * Time: 10:27
 */

namespace app\sale\controller;


use app\common\controller\Base;
use app\sale\model\GoodsOrder;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use think\Db;
use think\response\Json;

class Order extends Base
{

    //1待支付 2待发货 3待收货 4待评价 5已完成 -2取消订单(已付款时取消) -1取消订单(未支付时取消) -3拒收订单
    /**
     * 送货服务
     */
    public function distributionServiceList(){
        $status = input('status');  //1待配送  2配送中  3已签收  4拒收

        switch ($status){
            case 1:
                $where['order_status'] = ['eq',3];
                $where['distribution_status'] = ['eq',1];
                break;
            case 2:
                $where['order_status'] = ['eq',3];
                $where['distribution_status'] = ['eq',2];
                break;
            case 3:
                $where['order_status'] = ['in','4,5'];
                break;
            case 4:
                $where['order_status'] = ['eq',-3];
                break;
        }
        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $where['sale_id'] = ['eq',$userInfo['sale_id']];

        $orderlist = GoodsOrder::where('sale_id',$userInfo['sale_id'])
            ->where($where)
            ->field('id,order_no,pay_money,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,create_time')
            ->order('create_time desc')
            ->select();

        $orderlist = $orderlist->toArray();
        foreach ($orderlist as $k=>$v){
            $goods_info = Db::view('goods_order_detail','goods_id,number,price')
                ->view('goods','goods_name,spec,unit,description','goods.id = goods_order_detail.goods_id','left')
                ->where('order_id','eq',$v['id'])
                ->select();
            foreach ($goods_info as $k2=>$v2){
                $goods_info[$k2]['goods_img'] = Db::name('goods_img')->field('img_url')->where('goods_id',$v2['goods_id'])->select();
            }
            $orderlist[$k]['goods_info'] = $goods_info;
        }

        return \json(self::callback(1,'',$orderlist));
    }


    /**
     * 订单配送
     */
    public function distribution(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = GoodsOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->order_status != 3 || $order->distribution_status != 1){
            return \json(self::callback(0,'该订单不支持此操作'));
        }

        $order->distribution_status = 2;

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));

    }

    /**
     * 订单拒收
     */
    public function rejection(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = GoodsOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->order_status != 3 || $order->distribution_status != 2){
            return \json(self::callback(0,'该订单不支持此操作'));
        }

        $order->order_status = -3;

        //todo 此处原路退款
        if ($order->pay_type == '支付宝') {
            $alipay = new AliPay();
            $res = $alipay->alipay_refund($order->order_no,$order->pay_money);
        }elseif ($order->pay_type == '微信'){
            $wxpay = new WxPay();
            $res = $wxpay->wxpay_refund($order->order_no,$order->pay_money,$order->pay_money);
        }

        if ($res !== true){
            return \json(self::callback(0,'拒收订单退款失败'));
        }

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }
}