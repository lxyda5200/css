<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 17:11
 */

namespace app\user_v2\controller;

use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user_v2\common\Logic;
use app\user_v2\common\UserLogic;
use think\Db;
use think\Exception;
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
//-----------------------------------------以下为2019.7.30写超神宿app迭代版定时任务
    /**
     * 会员 购买的会员过期自动更改会员状态为普通用户
     */
    public function autoCancelMember(){
        $time = time();  //当前时间
        //查询所有会员用户且会员时间过期的
        $member_order = Db::name('user')
            ->field('user_id,type,user_status,start_time,end_time')
            ->where('end_time','<',$time)
            ->where('start_time','>',0)
            ->where('type',2)
            ->select();
        foreach ($member_order as $k=>$v){
            Db::name('user')->where('user_id',$v['user_id'])->update(['type'=>1,'start_time'=>0,'end_time'=>0]);
        }
    }

    /**
     * 30分钟未支付订单自动取消
     */
    public function autoCancelNotPayOrder(){
        $limit = config('config_order.hour_cancel_not_pay');
        $time_limit = $limit * 60 * 60;

        #查询所有的未支付的订单
        $list = UserLogic::orderListNotPay($time_limit);
        if(!$list)return;

        $order_ids = array_column($list,'id');
        $coupon_ids = array_filter(array_column($list,'coupon_id'));  //平台券
        $store_coupon_ids = array_filter(array_column($list,'store_coupon_id'));  //店铺券

        #查询所有未支付订单的订单号
        $order_nos = UserLogic::orderNosNotPay($time_limit);

        #查询所有的规格号
        $specs_ids = UserLogic::proListNotPay($order_ids);

        Db::startTrans();
        try{

            ##取消主订单
            $res = UserLogic::autoCancelOrder($order_ids);
            if($res === false)throw new Exception("\n\r" . "未支付自动取消订单失败=>订单ID【". json_encode($order_ids) ."}】");

            ##取消订单详情订单
            $res = UserLogic::autoCancelOrderDetail($order_ids);
            if($res === false)throw new Exception("\n\r" . "未支付自动取消订单详情失败=>订单ID【". json_encode($order_ids) ."}】");

            ##返还优惠券(平台)
            $res = UserLogic::returnCoupon($coupon_ids);
            if($res === false)throw new Exception("\n\r" . "未支付自动取消订单返还优惠券[平台]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");

            ##返还优惠券(店铺)
            $res = UserLogic::returnCoupon($store_coupon_ids);
            if($res === false)throw new Exception("\n\r" . "未支付自动取消订单返还优惠券[店铺]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($store_coupon_ids) ."】");

            ##返还库存
            foreach($specs_ids as $v){
                $res = UserLogic::returnStock($v['specs_id'],$v['number']);
                if($res === false)throw new Exception("\n\r" . "未支付自动取消订单返还库存失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");
            }

            ##生成消息通知
            foreach($order_nos as $v){
                $res = UserLogic::createReturnMsg($v['user_id'],$v['order_no']);
                if($res === false)throw new Exception("\n\r" . "未支付自动取消订单返还库存失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");
            }

            Db::commit();
            Log::info("\n\r" . "自动取消订单=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【".json_encode($coupon_ids)."】");

        }catch(Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
        }

    }

    /**
     *待发货7天未发货自动取消订单
     * @throws \Exception
     */
    public function autoCancelWaitSendOrder(){

        $limit = config('config_order.hour_cancel_not_send');
        $limit_time = $limit * 60 * 60;

        #查询所有的待发货未发货订单
        $list = UserLogic::orderListWaitSend($limit_time);
        if(!$list)return;

        $order_ids = array_column($list,'id');

        #查询所有产品规格id
        $list_pro = UserLogic::proListWaitSend($order_ids);

        #查询所有待发货未发货订单的订单号
        $order_nos = UserLogic::orderNosWaitSend($limit_time);

        Db::startTrans();
        try{

            Log::info("\r\n ================待发货7天未发货自动取消订单开始==================");

            $coupon_ids = array_filter(array_column($list,'coupon_id'));  //平台
            $store_coupon_ids = array_filter(array_column($list,'store_coupon_id'));  //店铺

            ##取消主订单
            $res = UserLogic::autoCancelOrder($order_ids);
            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单失败=>订单ID【". json_encode($order_ids) ."}】");

            ##取消订单详情订单
            $res = UserLogic::autoCancelOrderDetail($order_ids);
            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单详情失败=>订单ID【". json_encode($order_ids) ."}】");

            ##返还优惠券(平台)
            $res = UserLogic::returnCoupon($coupon_ids);
            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还优惠券[平台]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");

            ##返还优惠券(店铺)
            $res = UserLogic::returnCoupon($store_coupon_ids);
            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还优惠券[店铺]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($store_coupon_ids) ."】");

            ##返回库存
            foreach($list_pro as $v){
                $res = UserLogic::returnStock($v['specs_id'],$v['number']);
                if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还库存失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");
            }

            ##生成消息通知
            foreach($order_nos as $v){
                $res = UserLogic::createReturnMsg($v['user_id'],$v['order_no']);
                if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还库存失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");
            }

            ##退款
            foreach($list as $v){
                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($v['pay_order_no'],$v['id'],$v['pay_money']);
                }else if($v['pay_time'] == '微信'){
                    $total_pay_money = UserLogic::orderTotalPayMoney($v['pay_order_no']);
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($v['pay_order_no'],$total_pay_money,$v['pay_money']);
                }
                if ($res !== true)Log::error("\n\r" . "待发货自动取消订单退款失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【{$coupon_ids}】,退款类型=>【". $v['pay_type'] ."】");
            }

            Db::commit();
            Log::info("\r\n 待发货7天未发货自动取消订单===>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");
            Log::info("\r\n >>>>>>>>>>>>>>>>>>待发货7天未发货自动取消订单结束<<<<<<<<<<<<<<<<<<");
        }catch(Exception $e){

            Db::rollback();
            Log::error($e->getMessage());

        }

    }

    /**
     * 待收货15天自动收货
     */
    public function autoConfirmOrder(){

        $limit = config('config_order.hour_confirm_not_confirm');
        $limit_time = $limit * 60 * 60;

        #获取订单列表
        $list = UserLogic::orderListWaitFetch($limit_time);
        if(!$list)return;

        $order_ids = array_column($list,'id');

        Db::startTrans();

        try{

            #判断代购商品
            $orders_detail = UserLogic::proListMemberBuy($order_ids);
            $total_product_money = $dg_money = $user_money = [];
            if($orders_detail){  //有代购商品
                ##增加用户余额 增加代购收支记录
                $data_record = [];
                foreach($orders_detail as $v){
                    $product_money = $v['number'] * $v['price'];
                    $total_product_money[$v['store_id']] += $product_money;
                    $dg_money[$v['store_id']] += $v['huoli_money'];
                    ##增加代购记录
                    ###获取用户余额
                    $money = UserLogic::userMoney($v['user_id']);
                    ###增加用户余额
                    $res = UserLogic::addUserMoney($v['user_id'],$v['huoli_money']);
                    if($res === false)throw new Exception("\n\r" . "未收货自动确认收货返利失败=>订单IDS【". json_encode($order_ids) ."}】");
                    ###添加代购收入记录
                    $data_record[] = [
                        'user_id' => $v['user_id'],
                        'order_id' => $v['order_id'],
                        'order_detail_id' => $v['id'],
                        'note' => '代购收入',
                        'money' => $v['huoli_money'],
                        'balance' => $money + $v['huoli_money'],
                        'create_time' => time()
                    ];

                }
                ##添加获利记录
                if($data_record){
                    $res = UserLogic::addUserHuoliRecord($data_record);
                    if($res === false)throw new Exception("\n\r" . "未收货自动确认收货增加用户获利记录失败=>订单IDS【". json_encode($order_ids) ."}】");
                }
            }

            #普通商品
            $orders_detail = UserLogic::proListNormalBuy($order_ids);
            foreach($orders_detail as $v){
                $product_money = $v['number'] * $v['price'];
                $total_product_money[$v['store_id']] += $product_money;
            }

            ##计算运费|优惠券金额|平台提成
            $freight_price = $coupon_price = $platform_profit_price = $order_ids_arr = [];
            foreach($list as $v){
                $freight_price[$v['store_id']] += $v['total_freight'];
                $coupon_price[$v['store_id']] += $v['store_coupon_money'];
                $platform_profit_price[$v['store_id']] += $v['platform_profit'];
                $order_ids_arr[$v['store_id']] = $v['id'] . ",";
                $user_money[$v['user_id']] += ($v['pay_money'] - $v['total_freight']);
            }

            ##商家增加余额
            foreach($total_product_money as $k => $v){
                ##店铺收入 = 这一单的产品收入总金额 + 运费 - 平台提成 - 店铺优惠券价格 - 代购价
                $store_shouru = $v + $freight_price[$k] - $platform_profit_price[$k] - $coupon_price[$k] - $dg_money[$k];

                ###增加商户余额(收入)
                $store_money = Logic::storeMoney($k);
                $res = Logic::IncStoreMoney($k,$store_shouru);
                if($res === false)throw new Exception("\n\r" . "未收货自动确认收货增加店铺收入失败=>订单IDS【". json_encode($order_ids) ."}】");

                ###增加店铺收入记录
                $data_store_money_detail = [
                    'store_id' => $k,
                    'order_id' => 0,
                    'note' => "商品收入[自动确认收货][{$order_ids_arr[$k]}]",
                    'money' => $store_shouru,
                    'balance' => $store_money + $store_shouru,
                    'create_time' => time()
                ];

                $res = Logic::addStoreIncomeRecord($data_store_money_detail);
                if($res === false)throw new Exception("\n\r" . "未收货自动确认收货增加店铺收入记录失败=>订单IDS【". json_encode($order_ids) ."}】");

                //日志记录
                Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/log/debug/']);
                Log::write("========================自动确认收货,店铺ID【{$k}】=========================");
                Log::write('自动确认收货商品总金额:' . $v);
                Log::write('自动确认收货总运费:' . $freight_price[$k]);
                Log::write('自动确认收货平台提成:' . $platform_profit_price[$k]);
                Log::write('自动确认收货使用优惠券价格:' . $coupon_price[$k]);
                Log::write('自动确认收货返利价格:' . $dg_money[$k]);
                Log::write('自动确认收货商家营收:' . $store_shouru);
                Log::write(">>>>>>>>>>>>>>>>>>>>>>>>自动确认收货,店铺ID【{$k}】<<<<<<<<<<<<<<<<<<<<<<<<<");
            }

            ##更新用户累计消费金额
            foreach($user_money as $k => $v){
                ##更新累计消费金额
                $res = UserLogic::userIncLeijiMoney($k,$v);
                if($res === false)throw new Exception("\n\r" . "未收货自动确认收货增加用户累积记录失败=>订单IDS【". json_encode($order_ids) ."}】");
            }

            ##更新订单状态
            $res = Logic::confirmLotsOrder($order_ids);
            if($res === false)throw new Exception("\n\r" . "未收货自动确认收货更新订单状态失败=>订单IDS【". json_encode($order_ids) ."}】");
            Db::commit();

        }catch(Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
        }

    }

    /**
     * 申请售后商家7天未处理自动同意
     * @throws \Exception
     */
    public function autoAgreeAfterSale(){
        $limit = config('config_order.hour_agree_after_sale');
        $limit_time = $limit * 60 * 60;

        #获取售后列表
        $list_no_pro = UserLogic::proListAfterSaleWaitAgree($limit_time,1);
        $list_with_pro = UserLogic::proListAfterSaleWaitAgree($limit_time,2);
        Db::startTrans();
        try{
            ##已收货
            $shouhou_ids = array_column($list_with_pro,'shouhou_id');
            ###修改已收货的售后为同意
            $res = UserLogic::agreeShouhou($shouhou_ids);
            if($res === false)throw new Exception("\n\r" . "售后自动同意更新售后状态失败=>售后IDS【". json_encode($shouhou_ids) ."}】");

            ##未收货
            $shouhou_ids = array_column($list_no_pro,'shouhou_id');

            ###修改售后状态为已退款
            $res = UserLogic::agreeShouhouRefund($shouhou_ids);
            if($res === false)throw new Exception("\n\r" . "售后自动同意更新售后状态失败=>售后IDS【". json_encode($shouhou_ids) ."}】");

            $refund_lists = [];
            foreach($list_no_pro as $v){
                ###修改订单详情的退款金额与时间
                $refund_money = $v['coupon_id'] ? $v['realpay_money'] : ($v['number'] * $v['price']);
                $res = UserLogic::editOrderDetailRefund($v['id'],$refund_money);
                if($res === false)throw new Exception("\n\r" . "售后自动同意更新订单详情退款信息失败=>售后IDS【". json_encode($shouhou_ids) ."}】,当前订单详情ID【{$v['id']}】");

                $refund_lists[$v['order_no']]['refund_money'] += $refund_money;
                $refund_lists[$v['order_no']]['store_id'] = $v['store_id'];
                $refund_lists[$v['order_no']]['pay_type'] = $v['pay_type'];
            }

            ###退款
            foreach($refund_lists as $k => $v){
                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($k,$v['store_id'],$v['refund_money']);
                }else if($v['pay_time'] == '微信'){
                    $total_pay_money = UserLogic::orderTotalPayMoney($k);
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($k,$total_pay_money,$v['refund_money']);
                }
                if ($res !== true)Log::error("\n\r" . "售后自动同意退款失败=>订单号【". $k ."}】,退款类型=>【". $v['pay_type'] ."】");
            }

            Db::commit();

        }catch(Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
        }
    }

    /**
     * 售后=》用户退货15天后商户未确认收货退款【自动确认收货并退款】
     * @throws \Exception
     */
    public function autoShouhouArrivePro(){

        $limit = config('config_order.hour_arrive_pro');
        $limit_time = $limit * 60 * 60;

        #售后已发货列表
        $list = UserLogic::proListAfterSaleWaitArrive($limit_time);

        Db::startTrans();
        try{
            $shouhou_ids = array_column($list,'shouhou_id');
            ##修改售后状态为已收货退款
            $res = UserLogic::arriveShouhouPro($shouhou_ids);
            if($res === false)throw new Exception("\n\r" . "售后自动确认商家收货失败=>售后IDS【". json_encode($shouhou_ids) ."】");

            $refund_lists = [];
            ###修改订单详情的退款金额与时间
            foreach($list as $v){
                $refund_money = $v['coupon_id'] ? $v['realpay_money'] : ($v['number'] * $v['price']);
                $res = UserLogic::editOrderDetailRefund($v['id'],$refund_money);
                if($res === false)throw new Exception("\n\r" . "售后自动确认商家收货更新【订单详情退款信息失败】=>售后IDS【". json_encode($shouhou_ids) ."}】,当前订单详情ID【{$v['id']}】");

                $refund_lists[$v['order_no']]['refund_money'] += $refund_money;
                $refund_lists[$v['order_no']]['store_id'] = $v['store_id'];
                $refund_lists[$v['order_no']]['pay_type'] = $v['pay_type'];
            }

            ###退款
            foreach($refund_lists as $k => $v){
                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($k,$v['store_id'],$v['refund_money']);
                }else if($v['pay_time'] == '微信'){
                    $total_pay_money = UserLogic::orderTotalPayMoney($k);
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($k,$total_pay_money,$v['refund_money']);
                }
                if ($res !== true)Log::error("\n\r" . "售后自动确认商家收货【退款失败】=>订单号【". $k ."}】,退款类型=>【". $v['pay_type'] ."】");
            }

            Db::commit();
        }catch(Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
        }
    }


}