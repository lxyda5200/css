<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:21
 */

namespace app\mainstore\controller;


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
     * 提现详情（记录）
     */
    public function tixian_record(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id') ? intval(input('id')) : 0 ;
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $start_time = input('start_time');
            $end_time = input('end_time');
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if (!empty($keywords)) {
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where['store_tixian_record.id|store.store_name|store_tixian_record.alipay_account'] = ['like', "%{$keywords}%"];
            }
            if(!empty($id)){
                $where['store_tixian_record.store_id'] = ['eq', $id];
                $where['store.p_id'] = ['eq', $store_info['id']];
            }else{
                $where['store.p_id|store.id'] = ['eq', $store_info['id']];
            }
            //时间
            if (!empty($start_time) || !empty($end_time)) {
                if($start_time>$end_time){
                    return \json(self::callback(0,'开始时间不能大于结束时间'));
                }
                $start_time=date("Y-m-d H:i:s", $start_time);
                $end_time=date("Y-m-d H:i:s", $end_time);
                $where['store_tixian_record.create_at'] = ['between',[$start_time,$end_time]];
            }
            $total = Db::view('store_tixian_record','id')
                ->view('store','store_name','store_tixian_record.store_id = store.id','left')
                ->where($where)
                ->where('store_tixian_record.code',10000)
                ->count();
            $list =  Db::view('store_tixian_record','id,order_no,create_at,money,alipay_account,code')
                ->view('store','store_name','store_tixian_record.store_id = store.id','left')
                ->where($where)
                ->where('store_tixian_record.code',10000)
                ->page($page,$size)
                ->order('store_tixian_record.id','desc')
                ->select();
            foreach ($list as $k=>&$v){
                $v['create_at']=strtotime($v['create_at']);
                $v['detail']=Db::view('store_money_detail','id,money,create_time')
                    ->view('store','store_name','store_money_detail.store_id = store.id','left')
                    ->where('store_money_detail.tixian_record_id',$v['id'])
                    ->select();
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}