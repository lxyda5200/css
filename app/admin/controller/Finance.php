<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/21
 * Time: 20:48
 */

namespace app\admin\controller;

use think\Db;
class Finance extends Admin
{

    //长租
    public function long_list(){

        $start_time=input('start_time');
        $end_time=input('end_time');
        if(!empty($start_time)){
            $where['create_time'] = array('egt',strtotime($start_time));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_time'] = array(array('egt',strtotime($start_time)),array('elt',strtotime($end_time))) ;

            }else{
                $where['create_time'] = array('elt',strtotime($end_time));
            }
        }


        //总收房款
        $data['zsfk'] = Db::name('long_rent_record')->where($where)->sum('money');
        //总收押金
        $data['zsyj'] = Db::name('long_order')->where($where)->where('status',2)->sum('deposit_money');
        //退回租金
        $data['thzj'] = Db::name('long_order')->where($where)->sum('refund_deposit');
        //退回押金
        $data['thyj'] = Db::name('long_order')->where($where)->sum('refund_rent');

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }

    //短租
    public function short_list(){

        $start_time=input('start_time');
        $end_time=input('end_time');
        if(!empty($start_time)){
            $where['create_time'] = array('egt',strtotime($start_time));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_time'] = array(array('egt',strtotime($start_time)),array('elt',strtotime($end_time))) ;

            }else{
                $where['create_time'] = array('elt',strtotime($end_time));
            }
        }
        //总收房款
        $data['zsfk'] = Db::name('short_order')->where($where)->where('status','in','2,3,4,5')->sum('pay_money');
        //总收押金
        $data['zsyj'] = Db::name('short_order')->where($where)->where('status','in','2,3,4,5')->sum('deposit_money');
        //退回租金
        $data['thzj'] = Db::name('short_order')->where($where)->where('status',-2)->sum('pay_money');
        //退回押金
        $data['thyj'] = Db::name('short_order')->where($where)->where('status',-2)->sum('pay_money');

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }

    //商城
    public function goods_list(){

        $start_time = input('start_time');
        $end_time = input('end_time');

        /*********************************订单总数**************************************/
        if(!empty($start_time)){
            $where['pay_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['pay_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['pay_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }

        //订单总数
        $data['total_order_number'] = Db::name('product_order')->where('order_status','neq',1)->where($where)->count();

        /*********************************应付总额**************************************/



        $id_arr = Db::name('product_order')->where($where)->where('order_status','neq',1)->column('id');

        $product_order = Db::name('product_order_detail')->where('order_id','in',$id_arr)->select();
        $total_freight = Db::name('product_order')->where('id','in',$id_arr)->sum('total_freight');
        $total_order_money = 0 ;
        foreach ($product_order as $k=>$v){
            $total_order_money += $v['price'] * $v['number'];
        }

        //订单总额
        $data['total_money'] = $total_order_money + $total_freight;

        /*********************************实际交易金额**************************************/

        $data['sj_money'] = Db::name('product_order')->where($where)->where('order_status','neq',1)->sum('pay_money');

        /*********************************支付宝交易金额**************************************/

        $data['zfb_sj_money'] = Db::name('product_order')->where($where)->where('order_status','neq',1)->where('pay_type','支付宝')->sum('pay_money');


        /*********************************微信交易金额**************************************/

        $data['wx_sj_money'] = Db::name('product_order')->where($where)->where('order_status','neq',1)->where('pay_type','微信')->sum('pay_money');

        /*********************************退款总额**************************************/

        unset($where);

        if(!empty($start_time)){
            $where['cancel_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['cancel_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['cancel_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }

        //退款金额
        $data['refund_money'] = Db::name('product_order')->where($where)->where('order_status',-1)->sum('pay_money');



        /*********************************商户提现金额**************************************/

        unset($where);

        if(!empty($start_time)){
            $where['create_at'] = array('egt',$start_time.' 00:00:00');
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_at'] = array(array('egt',$start_time.' 00:00:00'),array('elt',$end_time.' 23:59:59')) ;

            }else{
                $where['create_at'] = array('elt',$end_time.' 23:59:59');
            }
        }

        //退款金额
        $data['tixian_money'] = Db::name('store_tixian_record')->where($where)->where('code',10000)->sum('money');

        /*********************************用户提现金额**************************************/

        unset($where);

        if(!empty($start_time)){
            $where['create_at'] = array('egt',$start_time.' 00:00:00');
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_at'] = array(array('egt',$start_time.' 00:00:00'),array('elt',$end_time.' 23:59:59')) ;

            }else{
                $where['create_at'] = array('elt',$end_time.' 23:59:59');
            }
        }

        //退款金额
        $data['user_tixian_money'] = Db::name('user_tixian_record')->where($where)->where('code',10000)->sum('money');

        /*********************************平台提成**************************************/

        unset($where);

        if(!empty($start_time)){
            $where['confirm_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['confirm_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['confirm_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }

        //平台提成
        $data['platform_profit'] = Db::name('product_order')->where($where)->where('order_status','>=',5)->sum('platform_profit');

        /*********************************代理获利金额**************************************/

        unset($where);

        if(!empty($start_time)){
            $where['pay_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }

        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['pay_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['pay_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }

        $order_id_arr = Db::name('product_order')->where($where)->where('order_status','>=',3)->column('id');

        //代理金额
        $data['huoli_money'] = Db::name('product_order_detail')->where('order_id','in',$order_id_arr)->sum('huoli_money');

        /*********************************优惠券使用金额**************************************/
        //优惠券金额
        $data['coupon_money'] = Db::name('product_order')->where($where)->where('order_status','neq',1)->sum('coupon_money');

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    //物业
    public function property_list(){
        $start_time=input('start_time');
        $end_time=input('end_time');
        if(!empty($start_time)){
            $where['create_time'] = array('egt',strtotime($start_time));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_time'] = array(array('egt',strtotime($start_time)),array('elt',strtotime($end_time))) ;

            }else{
                $where['create_time'] = array('elt',strtotime($end_time));
            }
        }

        $data = Db::name('property')->select();

        $total_tj_money = 0;
        $total_dk_money = 0;
        foreach ($data as $k=>$v){
            $data[$k]['tj_money'] = Db::name('property_money_record')->where($where)->where('property_id',$v['property_id'])->where('type',1)->sum('money');
            $data[$k]['dk_money'] = Db::name('property_money_record')->where($where)->where('property_id',$v['property_id'])->where('type',2)->sum('money');

            $total_tj_money += $data[$k]['tj_money'];
            $total_dk_money += $data[$k]['dk_money'];
        }

        $this->assign('total_tj_money',$total_tj_money);
        $this->assign('total_dk_money',$total_dk_money);
        $this->assign('data',$data);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    //销售
    public function sale_list(){
        $start_time=input('start_time');
        $end_time=input('end_time');
        if(!empty($start_time)){
            $where['create_time'] = array('egt',strtotime($start_time));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['create_time'] = array(array('egt',strtotime($start_time)),array('elt',strtotime($end_time))) ;

            }else{
                $where['create_time'] = array('elt',strtotime($end_time));
            }
        }

        $data = Db::name('sale')->select();

        $total_long = 0;
        $total_short = 0;
        $total_goods = 0;
        foreach ($data as $k=>$v){
            $data[$k]['long'] = Db::name('long_order')->where($where)->where('status',2)->where('renting_status','in','1,2')->where('sale_id',$v['sale_id'])->count();
            $data[$k]['short'] = Db::name('short_order')->where($where)->where('status','in','4,5')->where('sale_id',$v['sale_id'])->count();
            $data[$k]['goods'] = Db::name('goods_order')->where($where)->where('order_status','in','4,5')->where('sale_id',$v['sale_id'])->count();

            $total_long += $data[$k]['long'];
            $total_short += $data[$k]['short'];
            $total_goods += $data[$k]['goods'];
        }

        $this->assign('total_long',$total_long);
        $this->assign('total_short',$total_short);
        $this->assign('total_goods',$total_goods);
        $this->assign('data',$data);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }

    public function house_list(){

        return $this->fetch();
    }
}