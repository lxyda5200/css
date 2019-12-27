<?php


namespace app\admin\controller;


use think\Controller;
use app\admin\model\MaidanOrder;

class MaiDan extends Controller
{

    public function lists(MaidanOrder $maidanOrder){

        $store_name = input('post.store_name','','addslashes,strip_tags,trim');
        $user_mobile = input('post.user_mobile','','addslashes,strip_tags,trim');
        $order_sn = input('post.order_sn','','addslashes,strip_tags,trim');
        $start_time = input('post.start_time','','addslashes,strip_tags,trim');
        $end_time = input('post.end_time','','addslashes,strip_tags,trim');
        $status = 2;

        $where['mo.status'] = $status;
        if($store_name)$where['s.store_name'] = ['LIKE',"%{$store_name}%"];
        if($user_mobile)$where['mo.user_mobile'] = ['LIKE',"%{$user_mobile}%"];
        if($order_sn)$where['mo.order_sn'] = ['LIKE',"%{$order_sn}%"];
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if($start_time && $end_time){
            $where['mo.create_time'] = ['BETWEEN', [$start_time, $end_time]];
        }elseif($start_time && !$end_time){
            $where['mo.create_time'] = ['EGT', $start_time];
        }elseif(!$start_time && $end_time){
            $where['mo.create_time'] = ['ELT', $end_time];
        }

        $lists = $maidanOrder->alias('mo')
            ->join('store s','s.id = mo.store_id','LEFT')
            ->join('user u','u.user_id = mo.user_id','LEFT')
            ->where($where)
            ->where('(mo.store_id IN (217,98,76,580) AND mo.price_maidan >1) OR (mo.store_id NOT IN (217,98,76,580))') //屏蔽订单1元以下测试数据
            ->where('mo.user_id NOT IN (15953,13955,16904,16871,12929,17054,17027,16166,11510,16059,16908,13042,16197,16862,15361,10655,15169,13145,13828,16883)')//屏蔽测试
            ->field(['mo.id','mo.price_maidan','mo.price_yj','0+CONVERT(mo.discount,CHAR) as discount',' 0+CONVERT(mo.discount_platform,CHAR) as discount_platform','mo.is_finish',
            'mo.pay_time','mo.order_sn','mo.member_order_id','mo.coupon_money','mo.platform_profit','mo.price_store',
            'mo.platform_policy','mo.store_policy','mo.user_mobile','s.store_name','u.nickname','0+CONVERT(s.maidan_deduct,CHAR) as maidan_deduct'])
            ->order('mo.id DESC')
            ->paginate(15,false,['query'=>$this->request->param()]);

        ##获取总买单金额
        $total_maidan_yj = $maidanOrder->getTotalMaiDanYJ();

        ##获取商家总实收
        $total_store_price = $maidanOrder->getTotalStorePrice();

        ##获取平台总提成
        $total_platform_profit = $maidanOrder->getTotalPlatformProfit();

        ##获取买单总收入
        $total_maidan_pay = $maidanOrder->getTotalMaiDanPay();

        ##当前条件下的买单金额
        $maidan_yj = $maidanOrder->getCurMaiDanYJ($where);

        ##当前条件下的商家实收
        $store_price = $maidanOrder->getCurStorePrice($where);

        ##当前条件下的平台提成
        $platform_profit = $maidanOrder->getCurPlatformProfit($where);

        ##当前条件下的买单收入
        $maidan_pay = $maidanOrder->getCurMaiDanPay($where);

        ##传递参数
        $param = $this->request->param();

        $this->assign(compact('lists','total_maidan_yj','total_store_price','total_platform_profit','total_maidan_pay','maidan_yj','store_price','platform_profit','maidan_pay','param'));

        return $this->fetch();
    }

    /**
     * 员工奖励机制
     * @return mixed
     */
    public function maidanConfig(){
        return $this->fetch();
    }

}