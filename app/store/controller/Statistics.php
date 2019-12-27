<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:21
 */

namespace app\store\controller;


use app\common\controller\Base;
use think\Db;
use think\response\Json;
use think\Session;
class Statistics extends Base
{

    /**
     * 七天收益
     */
    public function profit(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $a=0;
            for ($i=6;$i>=0;$i--) {

                $date = date('Y-m-d',strtotime("-$i day"));

                $data[$a]['date'] = $date;

                $money = Db::name('store_money_detail')->where("FROM_UNIXTIME(create_time,'%Y-%m-%d') = '$date'")->where('store_id',$store_info['id'])->where('note','eq','商品收入')->sum('money');

                /*$order = Db::name('product_order')
                    ->where("FROM_UNIXTIME(confirm_time,'%Y-%m-%d') = '$date'")
                    ->where('store_id',$store_info['id'])
                    ->where('order_status',5)
                    ->select();

                $total_huoli_money = 0;
                foreach ($order as $k=>$v){
                    $huoli_money = Db::name('product_order_detail')->where('order_id',$v['id'])->sum('huoli_money');
                    $total_huoli_money += $huoli_money;
                }

                $total_freight = Db::name('product_order')
                    ->where("FROM_UNIXTIME(confirm_time,'%Y-%m-%d') = '$date'")
                    ->where('store_id',$store_info['id'])
                    ->where('order_status',5)
                    ->sum('total_freight');

                $order_id_arr = Db::name('product_order')
                    ->where("FROM_UNIXTIME(confirm_time,'%Y-%m-%d') = '$date'")
                    ->where('store_id',$store_info['id'])
                    ->where('order_status',5)
                    ->column('id');

                $order_detail = Db::name('product_order_detail')->where('order_id','in',$order_id_arr)->select();

                $total_product_price = 0 ;
                foreach ($order_detail as $k2=>$v2){
                    $total_product_price += $v2['number'] * $v2['price'];
                }

                $platform_profit = Db::name('product_order')
                    ->where("FROM_UNIXTIME(confirm_time,'%Y-%m-%d') = '$date'")
                    ->where('store_id',$store_info['id'])
                    ->where('order_status',5)
                    ->sum('platform_profit');

                $data[$a]['profit_money'] = $total_freight + $total_product_price - $platform_profit - $total_huoli_money;*/
                $data[$a]['profit_money'] = $money;

                $a = $a + 1;

            }

            foreach ($data as $k=>$v){
                $data[$k]['date'] = mb_substr($v['date'],5,5);
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 七天退款
     */
    public function refund(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $a=0;
            for ($i=6;$i>=0;$i--) {
                $date = date('Y-m-d',strtotime("-$i day"));
                $data[$a]['date'] = $date;
                /*$data[$i]['refund_money'] = Db::name('product_order_detail')
                    ->join('product_order','product_order.id = product_order_detail.order_id','left')
                    ->where("date_format(product_order_detail.refund_time,'%Y-%m-%d') = '$date'")
                    ->where('product_order.store_id',$store_info['id'])
                    ->where('product_order_detail.is_refund',1)
                    ->sum('refund_money');*/

                $data[$a]['refund_money'] = Db::name('product_order')
                    ->where("FROM_UNIXTIME(cancel_time,'%Y-%m-%d') = '$date'")
                    ->where('store_id',$store_info['id'])
                    ->where('order_status',-1)
                    ->sum('pay_money');
                $a = $a + 1;
            }

            foreach ($data as $k=>$v){
                $data[$k]['date'] = mb_substr($v['date'],5,5);
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 提现记录
     */
    public function tixian_record(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 0 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::name('store_tixian_record')->where('code',10000)->where('store_id',$store_info['id'])->count();

            $list = Db::name('store_tixian_record')
                ->field('create_at,money')
                ->where('code',10000)
                ->where('store_id',$store_info['id'])
                ->order('create_at','desc')
                ->page($page,$size)
                ->select();

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}