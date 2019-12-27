<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 17:11
 */

namespace app\wxapi_test\controller;

use think\Db;
use think\Log;
class Task
{

    /**
     * 七天自动确认收货
     */
    public function confirmOrder(){
        $time = time()-60*60*24*7;  //定义7天
        $row = Db::name('product_order')
            ->where('fahuo_time','<=',$time)
            ->where('order_status',4)
            ->select();

        # Db::name()->getLastSql();
        #dump($row);die;

        foreach ($row as $k=>$v){

            $order_id = $v['id'];

            $userInfo = Db::name('user')->where('user_id',$v['user_id'])->find();

            //判断是否有代购商品
            $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',2)->select();
            $dg_money = 0;
            $total_product_money = 0;
            if ($order_detail) {
                //增加用户余额 增加代购收支记录
                foreach ($order_detail as $k2=>$v2){
                    $product_money = $v2['number'] * $v2['price'];
                    $total_product_money += $product_money;

                    //增加代购记录
                    $money = Db::name('user')->where('user_id',$userInfo['user_id'])->value('money');
                    Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('money',$v2['huoli_money']);
                    Db::name('user_money_detail')->insert([
                        'user_id' => $userInfo['user_id'],
                        'order_id' => $order_id,
                        'order_detail_id' => $v2['id'],
                        'note' => '代购收入',
                        'money' => $v2['huoli_money'],
                        'balance' => $money + $v2['huoli_money'],
                        'create_time' => time()
                    ]);
                    $dg_money += $v2['huoli_money'];
                }
            }else{
                $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',1)->select();
                foreach ($order_detail as $k2=>$v2){
                    $product_money = $v2['number'] * $v2['price'];
                    $total_product_money += $product_money;
                }
            }

            //增加商家余额 增加商家收益记录

            $store_shouru = $total_product_money + $v['total_freight'] - $v['platform_profit'] - $dg_money;  //商家实际收入 减去平台手续费和代购奖励金额

            //日志记录

            /*Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/log/debug/']);
            Log::write('支付金额1:'.$total_product_money);
            Log::write('支付金额2:'.$v['total_freight']);
            Log::write('支付金额3:'.$v['platform_profit']);
            Log::write('支付金额4:'.$dg_money);
            Log::write($store_shouru);*/

            $store_money = Db::name('store')->where('id',$v['store_id'])->value('money');
            Db::name('store')->where('id',$v['store_id'])->setInc('money',$store_shouru);
            Db::name('store_money_detail')->insert([
                'store_id' => $v['store_id'],
                'order_id' => $order_id,
                'note' => '商品收入',
                'money' => $store_shouru,
                'balance' => $store_money + $store_shouru,
                'create_time' => time()
            ]);

            $data['order_status'] = 5;
            $data['confirm_time'] = time();

            //修改累计金额
            Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('leiji_money',$v['pay_money']);
            $userinfo = Db::name('user')->where('user_id',$userInfo['user_id'])->find();

            //累计消费金额超过3000成为会员
            if ($userinfo['type'] == 1){
                if (($userinfo['leiji_money']) >= 3000){
                    Db::name('user')->where('user_id',$userInfo['user_id'])->setField('type',2);
                }
            }

            Db::name('product_order')->where('id',$v['id'])->strict(false)->update($data);

        }

    }

    /**
     * 半小时不支付自动取消长租订单
     */
    public function cancelLongOrder(){

        $time = time()-30*60;  //定义多少时间之后
        #$date = date('Y-m-d H:i:s',$time);
        $long_order = Db::name('long_order')
            ->where('create_time','<=',$time)
            ->where('status',1)
            ->select();

        foreach ($long_order as $k=>$v){
            Db::name('house')->where('id',$v['house_id'])->setField('renting_status',1);
        }

        $row = Db::name('long_order')
            ->where('create_time','<=',$time)
            ->where('status',1)
            ->delete();


        return $row;
    }

    /**
     * 半小时不支付自动取消短租订单
     */
    public function cancelShortOrder(){
        $time = time()-30*60;  //定义多少时间之后
        #$date = date('Y-m-d H:i:s',$time);
        $row = Db::name('short_order')
            ->where('create_time','<=',$time)
            ->where('status',1)
            ->delete();
        return $row;
    }

    /**
     * 半小时不支付自动取消商品订单
     */
    public function cancelProductOrder(){

        $time = time()-30*60;  //定义多少时间之后

        $product_order = Db::name('product_order')
            ->where('create_time','<',$time)
            ->where('order_status',1)
            ->select();

        $rows = 0;

        foreach ($product_order as $k=>$v){
            //返回库存
            $product = Db::name('product_order_detail')->where('order_id',$v['id'])->select();

            foreach ($product as $k2=>$v2){
                Db::name('product_specs')->where('id',$v2['specs_id'])->setInc('stock',$v2['number']);
            }

            //是否拼团订单

            if ($v['is_group_buy'] == 1){
                if ($v['pt_type'] == 1){
                    //潮搭拼团
                    //是否拼团发起人 如果是删除拼团
                    if ($v['is_header'] == 1){
                        Db::name('chaoda_pt_info')->where('user_id',$v['user_id'])->where('id',$v['pt_id'])->delete();
                        Db::name('chaoda_pt_product_info')->where('pt_id',$v['pt_id'])->delete();
                    }else{
                        //不是则减少拼团人数
                        Db::name('chaoda_pt_info')->where('id',$v['pt_id'])->setDec('ypt_size',1);
                    }
                }else{
                    if ($v['pt_id']) {

                        //是否拼团发起人 如果是删除拼团
                        if ($v['is_header'] == 1){
                            Db::name('user_pt')->where('user_id',$v['user_id'])->delete();
                        }else{
                            //不是则减少拼团人数
                            Db::name('user_pt')->where('id',$v['pt_id'])->setDec('ypt_size',1);
                        }
                    }
                }

            }


            //删除已失效订单

            $res = Db::name('product_order_detail')->where('order_id',$v['id'])->delete();
            $row = Db::name('product_order')->where('id',$v['id'])->delete();

            $rows += $row;
        }


        return $rows;
    }


    /*
     * 拼团失败自动取消拼团订单
     * */
    public function cancelPtOrder(){

        //拼团失败 1取消订单 2退款 3退款通知
        $pt_id_arr = Db::name('user_pt')->where('pt_status',1)->where('end_time','<',time())->column('id');  //查询时间到期的拼团

        $product_order = Db::view('product_order')
            ->view('user_pt','pt_status','user_pt.id = product_order.pt_id','left')
            ->where('user_pt.pt_status',1)
            ->where('user_pt.end_time','<',time())
            ->where('product_order.pt_id','neq',0)
            ->where('product_order.order_status',2)
            ->select();

        Db::startTrans();

        foreach ($product_order as $k=>$v) {
            //1取消订单
            Db::name('product_order')->where('id',$v['id'])->update(['order_status'=>-1,'cancel_time'=>time()]);
            //2退款
            if ($v['pay_type'] == '支付宝') {
                $alipay = new AliPay();
                $res = $alipay->alipay_refund($v['pay_order_no'],$v['id'],$v['pay_money']);
            }elseif ($v['pay_type'] == '微信'){
                $wxpay = new WxPay();
                $total_pay_money = Db::name('product_order')->where('pay_order_no',$v['pay_order_no'])->sum('pay_money');
                $res = $wxpay->wxpay_refund($v['pay_order_no'],$total_pay_money,$v['pay_money']);
            }
            if ($res){
                //3退款通知
                $msg_id = Db::name('user_msg')->insertGetId([
                    'title' => '退款通知',
                    'content' => '您的订单'.$v['order_no'].'拼团失败,订单金额已原路返回',
                    'type' => 2,
                    'create_time' => time()
                ]);

                Db::name('user_msg_link')->insert([
                    'user_id' => $v['user_id'],
                    'msg_id' => $msg_id
                ]);
            }
        }

        $row = Db::name('user_pt')->where('id','in',$pt_id_arr)->delete();

        Db::commit();
        return $row;
    }

    /*
     * 拼团失败自动取消拼团订单
     * */
    public function cancelChaodaPtOrder(){

        //拼团失败 1取消订单 2退款 3退款通知
        $pt_id_arr = Db::name('chaoda_pt_info')->where('pt_status',1)->where('end_time','<',time())->column('id');  //查询时间到期的拼团

        $product_order = Db::view('product_order')
            ->view('chaoda_pt_info','pt_status','chaoda_pt_info.id = product_order.pt_id','left')
            ->where('chaoda_pt_info.pt_status',1)
            ->where('chaoda_pt_info.end_time','<',time())
            ->where('product_order.pt_id','neq',0)
            ->where('product_order.order_status',2)
            ->select();

        dump($product_order);

        Db::startTrans();

        foreach ($product_order as $k=>$v) {
            //1取消订单
            Db::name('product_order')->where('id',$v['id'])->update(['order_status'=>-1,'cancel_time'=>time()]);
            //2退款
            if ($v['pay_type'] == '支付宝') {
                $alipay = new AliPay();
                $res = $alipay->alipay_refund($v['pay_order_no'],$v['id'],$v['pay_money']);
            }elseif ($v['pay_type'] == '微信'){
                $wxpay = new WxPay();
                $total_pay_money = Db::name('product_order')->where('pay_order_no',$v['pay_order_no'])->sum('pay_money');
                $res = $wxpay->wxpay_refund($v['pay_order_no'],$total_pay_money,$v['pay_money']);
            }

            dump($res);

            if ($res){
                //3退款通知
                $msg_id = Db::name('user_msg')->insertGetId([
                    'title' => '退款通知',
                    'content' => '您的订单'.$v['order_no'].'拼团失败,订单金额已原路返回',
                    'type' => 2,
                    'create_time' => time()
                ]);

                Db::name('user_msg_link')->insert([
                    'user_id' => $v['user_id'],
                    'msg_id' => $msg_id
                ]);
            }
        }


        $row = Db::name('chaoda_pt_info')->where('id','in',$pt_id_arr)->delete();

        Db::commit();
        return $row;
    }


    /*
     * 自动下架预购商品
     * */
    public function xiajiaPorudct(){
        $row = Db::name('product')->where('status',1)->where('category_id',2)->where('end_time','<',time())->setField('status',0);

        return $row;
    }

    /*
     * 购物车数据清理
     * */
    public function shopping_cart(){

        $product_info = Db::view('shopping_cart','product_id,specs_id,number')
            ->view('store','is_ziqu','store.id = shopping_cart.store_id','left')
            ->view('product_specs','product_name,price,product_specs,cover','product_specs.id = shopping_cart.specs_id','left')
            ->view('product','freight','product.id = product_specs.product_id','left')
            ->select();

        foreach ($product_info as $k=>$v){
            if ($v['product_name'] == NULL){
                Db::name('shopping_cart')->where('product_id',$v['product_id'])->where('specs_id',$v['specs_id'])->delete();
            }
        }

    }

}