<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 17:11
 */

namespace app\user_v6\controller;

use app\common\controller\AliSMS;
use app\common\controller\IhuyiSMS;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user_v6\common\Logic;
use app\user_v6\common\UserLogic;
use app\user_v6\model\DrawLotteryRecord;
use jiguang\JiG;
use my_redis\MRedis;
use system_message\Msg;
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
     * 1小时未支付订单自动短信提醒
     */
    public function onehourSendMessageNotPayOrder(){
        try{
        $limit = config('config_order.one_hour_not_pay');
        $time_limit = $limit * 60 * 60;
        #查询所有的未支付的订单
        $list = UserLogic::NotPaylist($time_limit);
        if($list){
            foreach ( $list as $k=>$v){
                $num=Db::name('send_message_record')->where('pay_order_no',$v['pay_order_no'])->count();
                if(empty($num)){
                    $mobile=$v['mobile'];
                    $pay_order_no=$v['pay_order_no'];
                    $user_id=$v['user_id'];
//                    $res =  IhuyiSMS::onehourautosendMessageNotPayOrder($mobile,$pay_order_no,$user_id,$v['id']);
                    $res = AliSMS::sendOrderRepayMsg($mobile, $v['id'],$pay_order_no,$v['user_id'],60);
                    if($res['Code'] != 'OK')throw new Exception(json_encode(['content'=>['支付订单号'=>$pay_order_no,'用户user_id'=>$user_id],'title'=>"1小时未支付订单自动短信提醒失败"]));
                        JiG::sendMsgToUser("u{$v['user_id']}",'order_wait_pay_60', ['order_id'=>$v['id']]);

                        ##生成系统消息
                        Msg::addOrderCancelWarningSysMsg60($v['id']);
                }
            }
        }
        }catch(Exception $e){
            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],8);
            }else{
                addErrLog($e->getMessage(),'1小时未支付订单自动短信提醒',8);
            }

            return "FALSE";
        }
    }
    /**
     * 1.5小时未支付订单自动短信提醒
     */
    public function oneandahalfhourssendMessageNotPayOrder(){
        try{
        $limit = config('config_order.one_and_a_half_hours_not_pay');
        $time_limit = $limit * 60 * 60;
        #查询所有的未支付的订单
        $list = UserLogic::NotPaylist($time_limit);
        if($list){
            foreach ( $list as $k=>$v){
                $num=Db::name('send_message_record')->where('pay_order_no',$v['pay_order_no'])->count();
                if($num==1){
                    $mobile=$v['mobile'];
                    $pay_order_no=$v['pay_order_no'];
                    $user_id=$v['user_id'];
//                    $res = IhuyiSMS::oneandahalfhoursautosendMessageNotPayOrder($mobile,$pay_order_no,$user_id,$v['id']);
                    $res = AliSMS::sendOrderRepayMsg($mobile, $v['id'],$pay_order_no,$v['user_id'],90);
                    if($res['Code'] != 'OK')throw new Exception(json_encode(['content'=>['支付订单号'=>$pay_order_no,'用户user_id'=>$user_id],'title'=>"1.5小时未支付订单自动短信提醒失败"]));
                        JiG::sendMsgToUser("u{$v['user_id']}",'order_wait_pay_30', ['order_id'=>$v['id']]);
                        ##生成系统消息
                        Msg::addOrderCancelWarningSysMsg30($v['id']);
                }
            }
        }
        }catch(Exception $e){
            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],3);
            }else{
                addErrLog($e->getMessage(),'1.5小时未支付订单自动短信提醒',8);
            }

            return "FALSE";
        }
    }

    /**
     * 2小时未支付订单自动取消
     */
    public function autoCancelNotPayOrder(){

        $limit = config('config_order.hour_cancel_not_pay');
        $time_limit = $limit * 60 * 60;

        #查询所有的未支付的订单
        $list = UserLogic::orderListNotPay($time_limit);
        if(!$list)return "SUCCESS";

        $order_ids = array_column($list,'id');
        $coupon_ids = array_filter(array_column($list,'coupon_id'));  //平台券
        $store_coupon_ids = array_filter(array_column($list,'store_coupon_id'));  //店铺券
        $product_coupon_ids = array_filter(array_column($list,'product_coupon_id'));  //商品券

        #查询所有未支付订单的订单号
        $order_nos = UserLogic::orderNosNotPay($time_limit);

        #查询所有的规格号
        $specs_ids = UserLogic::proListNotPay($order_ids);

        Db::startTrans();
        try{

            ##取消主订单
            $res = UserLogic::autoCancelOrder($order_ids);
            if($res === false)throw new Exception(json_encode(['content'=>$order_ids,'title'=>"未支付自动取消订单失败"]));

            ##取消订单详情订单
            $res = UserLogic::autoCancelOrderDetail($order_ids);
            if($res === false)throw new Exception(json_encode(['content'=>$order_ids,'title'=>"未支付自动取消订单详情失败"]));

            ##返还优惠券(平台)
            $res = UserLogic::returnCoupon($coupon_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids,'优惠券IDS'=>$coupon_ids],'title'=>"未支付自动取消订单返还优惠券[平台]失败"]));

            ##返还优惠券(店铺)
            $res = UserLogic::returnCoupon($store_coupon_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids,'优惠券IDS'=>$store_coupon_ids],'title'=>"未支付自动取消订单返还优惠券[店铺]失败"]));

            ##返还优惠券(商品)
            $res = UserLogic::returnCoupon($product_coupon_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids,'优惠券IDS'=>$product_coupon_ids],'title'=>"未支付自动取消订单返还优惠券[商品]失败"]));

            ##返还库存
            foreach($specs_ids as $v){
                $res = UserLogic::returnStock($v['specs_id'],$v['number']);
                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未支付自动取消订单返还库存失败"]));
            }

            ##生成消息通知
            foreach($order_nos as $v){
//                $res = UserLogic::createReturnMsg($v['user_id'],$v['order_no'],1);
//                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未支付自动取消订单生成消息通知失败"]));
                Msg::addSysCancelNotPayOrderSysMsg($v['id']);
                JiG::sendMsgToUser("u{$v['user_id']}",'order_sys_cancel',['order_id'=>$v['id'],'order_no'=>$v['order_no']]);
            }

            Db::commit();

            return "SUCCESS";

        }catch(Exception $e){
            Db::rollback();
            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],3);
            }else{
                addErrLog($e->getMessage(),'30分钟未支付订单自动取消',8);
            }

            return "FALSE";
        }

    }

    /**
     *待发货12小时未发货自动取消订单
     * @throws \Exception
     */
    public function autoCancelWaitSendOrder(){

        $limit = config('config_order.hour_cancel_not_send');
        $limit_time = $limit * 60 * 60;

        #查询所有的待发货未发货订单
        $list = UserLogic::orderListWaitSend($limit_time);
        if(!$list)return "SUCCESS";

        $order_ids = array_column($list,'id');

        #查询所有产品规格id
        $list_pro = UserLogic::proListWaitSend($order_ids);

        #查询所有待发货未发货订单的订单号
        $order_nos = UserLogic::orderNosWaitSend($limit_time);

        Db::startTrans();
        try{

            $coupon_ids = array_filter(array_column($list,'coupon_id'));  //平台
            $store_coupon_ids = array_filter(array_column($list,'store_coupon_id'));  //店铺

            ##取消主订单
            $res = UserLogic::autoCancelOrder($order_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"待发货自动取消订单失败"]));

            ##取消订单详情订单
            $res = UserLogic::autoCancelOrderDetail($order_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"待发货自动取消订单详情失败"]));

            ##返还优惠券(平台)
//            $res = UserLogic::returnCoupon($coupon_ids);
//            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还优惠券[平台]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($coupon_ids) ."】");

            ##返还优惠券(店铺)
//            $res = UserLogic::returnCoupon($store_coupon_ids);
//            if($res === false)throw new Exception("\n\r" . "待发货自动取消订单返还优惠券[店铺]失败=>订单IDS【". json_encode($order_ids) ."}】,优惠券IDS=>【"  . json_encode($store_coupon_ids) ."】");

            ##返回库存
            foreach($list_pro as $v){
                $res = UserLogic::returnStock($v['specs_id'],$v['number']);
                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"待发货自动取消订单返还库存失败"]));
            }

            ##生成消息通知
            foreach($order_nos as $v){
//                $res = UserLogic::createReturnMsg($v['user_id'],$v['order_no']);
//                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"待发货自动取消订单生成消息通知失败"]));
                Msg::addSysCancelOrderSysMsg($v['id']);
                JiG::sendMsgToUser("u{$v['user_id']}",'order_sys_cancel',['order_id'=>$v['id'],'order_no'=>$v['order_no']]);
            }

            ##退款
            foreach($list as $v){

                $order_no = $v['pay_scene'] ? $v['order_no'] : $v['pay_order_no'];

                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order_no,$v['id'],$v['pay_money']);
                }else if($v['pay_type'] == '微信'){
                    $total_pay_money = $v['pay_scene'] ? $v['pay_money'] :UserLogic::orderTotalPayMoney($v['pay_order_no']);
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($order_no,$total_pay_money,$v['pay_money']);
                }
                if ($res !== true)addErrLog(['订单IDS'=>$order_ids,'退款类型'=>$v['pay_type'],'当前单号'=>$order_no],'待发货自动取消订单退款失败');

            }

            Db::commit();

            return "SUCCESS";

        }catch(Exception $e){
            Db::rollback();
            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],3);
            }else{
                addErrLog($e->getMessage(),'待发货7天未发货自动取消订单',8);
            }

            return "FALSE";
        }

    }

    /**
     * 待收货=》7天自动确认收货
     * @return string
     */
    public function autoConfirmOrder(){

        $limit = config('config_order.hour_confirm_not_confirm');
        $limit_time = $limit * 60 * 60;

        #获取订单列表
        $list = UserLogic::orderListWaitFetch($limit_time);
        if(!$list)return "SUCCESS";

        $order_ids = array_column($list,'id');

        Db::startTrans();

        try{

            ##更新订单状态为待评价
            $res = UserLogic::confirmOrder($order_ids);
            if($res === false)throw new Exception('更新订单状态失败');

            ##添加用户通知消息
            foreach($list as $v){
                $res = UserLogic::createConfirmMsg($v['user_id'], $v['order_no']);
                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"待收货自动确认收货生成消息通知失败"]));
            }

            Db::commit();
            return "SUCCESS";

        }catch(Exception  $e){
            Db::rollback();

            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],8);
            }else{
                addErrLog($e->getMessage(),'待收货7天自动确认收货',8);
            }

            return "FALSE";
        }

    }

    /**
     * 待评价 => 超过3天未评价自动评价(正常的订单商品)
     * @return string
     */
    public function autoComment(){

        $limit = config('config_order.hour_comment_not_comment');
        $limit_time = time() - $limit * 60 * 60;

        ##获取可自动评价product_order_detail id 列表
        $list = UserLogic::orderDetailAutoComment($limit_time);
        if(empty($list))return 'EMPTY';

        try{
            ##自动评价
            $order_detail_ids = array_column($list,'id');
            $res = UserLogic::autoCommentOrderDetail($order_detail_ids);
            if($res === false){
                throw new Exception('自动完成评价失败');
            }

            ##判断订单中的商品是否该评论的订单是否已全部评论,如果是则修改订单状态为已完成
            $order_ids = [];
            foreach($list as $v){
                if(!in_array($v['order_id'], $order_ids))$order_ids[] = $v['order_id'];
            }
            foreach($order_ids as $v){
                $check = UserLogic::checkOrderComment($v);
                if($check){ ##订单状态需要修改为已完成
                    $res = UserLogic::updateOrderStatusToFinish($v);
                    if($res === false)throw new Exception('自动评价=>订单状态修改至已完成失败');
                }
            }

            Db::commit();

            return 'SUCCESS';

        }catch(Exception $e){

            Db::rollback();
            addErrLog($list,$e->getMessage(),8);
            return 'FALSE';

        }

    }

    /**
     * 从待评价状态提交售后被打回的订单超过3天自动评价和修改订单当时状态
     * @return string
     */
    public function autoShouhouComment(){
        $limit = config('config_order.hour_comment_not_comment');
        $limit_time = time() - $limit * 60 * 60;

        ##获取从待评价状态申请售后的 product_order_detail id 列表
        $list = UserLogic::orderDetailAutoShouhouComment($limit_time);
        if(empty($list))return 'EMPTY';

        try{
            ##需要修改评论状态为已评论、修改订单商品详情当时的订单状态为已完成
            $order_detail_ids = array_column($list,'id');
            $res = UserLogic::autoEditCommentAndShouhouOrderStatus($order_detail_ids);
            if($res === false){
                throw new Exception('售后订单自动完成评价失败');
            }

        }catch(Exception $e){

            addErrLog($list,$e->getMessage(),8);
            return 'FALSE';

        }
    }

    /**
     * 已评价 => 4天自动完成订单(结转相关)
     * @return string
     */
    public function autoFinishOrder(){
        ##文件锁
        $fp = fopen(__DIR__."/task_lock.txt", "w+");
        if(!flock($fp,LOCK_EX | LOCK_NB)){
            return \json(self::callback(0,'系统繁忙，请稍后再试'));
        }

        $limit = config('config_order.hour_finish');
        $limit_time = time() - $limit * 60 * 60;

        ##获取可以结转的订单详情id
        $list = UserLogic::orderDetailAutoFinish($limit_time);
        $list2 = UserLogic::orderDetailShouhouAutoFinish($limit_time);
        $list = array_merge($list, $list2);
        if(empty($list))return 'SUCCESS';

        Db::startTrans();

        try{
            $store_shouru = $dg_money = $user_money = $rtn_coupon = $rtn_money = $order_ids = $detail_ids = $huoli_detail_ids = $product_sale = $specs_sale = [];
            foreach($list as $v){
                ##获利金额
                if($v['huoli_money'] > 0)$dg_money[$v['user_id']] += $v['huoli_money'];

                ##商家结转金额(relpay_money是已经扣除满减和优惠券金额的真实支付金额)
                if($v['discount_money'] > 0){
                    $percent = Logic::getAcPercent($v['activity_id']);
                    if($percent == 1){
                        $store_shouru[$v['store_id']] += $v['realpay_money'] + $v['discount_money'];
                    }else{
                        $store_shouru[$v['store_id']] += $v['realpay_money'];
                    }
                }elseif($v['return_money'] > 0){
                    $percent = Logic::getAcPercent($v['activity_id']);
                    if($percent == 1){
                        $store_shouru[$v['store_id']] += $v['realpay_money'];
                    }else{
                        $store_shouru[$v['store_id']] += $v['realpay_money'] - $v['return_money'];
                    }
                }else{
                    $store_shouru[$v['store_id']] += $v['realpay_money'];
                }

                ##判断优惠券金额
                if($v['product_coupon_money'] > 0){  ##使用了商品券
                    ##获取优惠券的承担比
                    $couponPlatformBear = (float)Logic::getCouponPlatformBear($v['product_coupon_id']);
                    $store_shouru[$v['store_id']] += $couponPlatformBear * $v['product_coupon_money'];
                }

                if($v['store_coupon_money'] > 0){  ##使用了店铺优惠券
                    ##获取优惠券的承担比
                    $couponPlatformBear = (float)Logic::getCouponPlatformBear($v['store_coupon_id']);
                    $store_shouru[$v['store_id']] += $couponPlatformBear * $v['store_coupon_money'];
                }

                if($v['coupon_money'] > 0){  ##使用了平台优惠券
                    ##获取优惠券的承担比
                    $couponPlatformBear = (float)Logic::getCouponPlatformBear($v['coupon_id']);
                    $store_shouru[$v['store_id']] += $couponPlatformBear * $v['coupon_money'];
                }

                ##用户实际消费金额
                $user_money[$v['user_id']] += $v['realpay_money'];

                ##返优惠券
                if($v['return_coupon_id']){
                    $rtn_coupon[$v['user_id']][] = $v['return_coupon_id'];
                }

                ##返现
                if($v['return_money']){
                    $rtn_money[$v['user_id']] += $v['return_money'];
                }

                ##订单ids
                if(!in_array($v['order_id'], $order_ids))$order_ids[] = $v['order_id'];

                ##订单商品详情ids
                $detail_ids[$v['store_id']][] = $v['id'];

                ##获利订单商品详情ids
                $huoli_detail_ids[$v['user_id']][] = $v['id'];

                ##商品销量
                $product_sale['product_id'] += $v['number'];

                ##规格销量
                $specs_sale['specs_id'] += $v['number'];
            }

            ##更新订单详情状态为已结转
            $order_detail_ids = array_column($list,'id');
            $res = UserLogic::finishOrderDetail($order_detail_ids);
            if($res === false)throw new Exception('自动结转=>订单商品详情更新状态失败');

            ##判断订单是否完成
            foreach($order_ids as $v){
                ##检查是否可修改状态
                if(UserLogic::checkOrderFinish($v)){
                    ##修改订单状态
                    $res = UserLogic::finishOrder($v);
                    if($res === false)throw new Exception('自动结转=>订单状态更新失败');
                }
            }

            ##店铺流水结转
            foreach($store_shouru as $k => $v){
                ###增加商户余额(收入)
                $store_money = Logic::storeMoney($k);
                $res = Logic::IncStoreMoney($k,$v);
                if($res === false)throw new Exception('自动结转=>增加店铺收入失败');

                ###增加店铺收入记录
                $detail_id = implode(',', $detail_ids[$k]);
                $data_store_money_detail = [
                    'store_id' => $k,
                    'order_id' => 0,
                    'note' => "商品收入[自动结转][订单商品详情IDS【{$detail_id}】]",
                    'money' => $v,
                    'balance' => $store_money + $v,
                    'create_time' => time()
                ];

                $res = Logic::addStoreIncomeRecord($data_store_money_detail);
                if($res === false)throw new Exception('自动结转=>增加店铺收入记录失败');
            }

            ##用户获利返现
            foreach($dg_money as $k => $v){
                ###获取用户余额
                $money = UserLogic::userMoney($k);
                ###增加用户余额
                $res = UserLogic::addUserMoney($k,$v);
                if($res === false)throw new Exception('自动结转=>返利失败');
                ###添加代购收入记录
                $data_record[] = [
                    'user_id' => $k,
                    'order_id' => 0,
                    'order_detail_id' => 0,
                    'note' => "代购收入[订单商品详情IDS【{$huoli_detail_ids[$k]}】]",
                    'money' => $v,
                    'balance' => $money + $v,
                    'create_time' => time()
                ];
            }

            ##添加获利记录
            if(isset($data_record) && $data_record){
                $res = UserLogic::addUserHuoliRecord($data_record);
                if($res === false)throw new Exception('自动结转=>增加用户获利记录失败');
            }

            ##更新用户累积消费金额
            foreach($user_money as $k => $v){
                $res = UserLogic::userIncLeijiMoney($k,$v);
                if($res === false)throw new Exception('自动结转=>增加用户累积记录失败');
            }

            ##活动返回优惠券
            foreach($rtn_coupon as $k =>$v){
                foreach($v as $vv){
                    $coupon_info = Logic::getCouponInfo($vv);
                    if(!$coupon_info || !$coupon_info['is_open'] || $coupon_info['end_time'] <= time())continue;
                    $data = [
                        'user_id' => $k,
                        'coupon_id' => $coupon_info['id'],
                        'coupon_name' => $coupon_info['coupon_name'],
                        'store_id' => $coupon_info['store_id'],
                        'satisfy_money' => $coupon_info['satisfy_money'],
                        'coupon_money' => $coupon_info['coupon_money'],
                        'expiration_time' => $coupon_info['end_time'],
                        'create_time' => time(),
                        'coupon_type' => $coupon_info['coupon_type']
                    ];
                    $res = UserLogic::userGetCoupon($data);
                    if(!$res)throw new Exception('自动结转=>返还优惠券失败');
                }
            }

            ##用户活动返现
            foreach($rtn_money as $k => $v){
                ###获取用户余额
                $money = UserLogic::userMoney($k);
                ###增加用户余额
                $res = UserLogic::addUserMoney($k,$v);
                if($res === false)throw new Exception('自动结转=>返现失败');
                ###添加代购收入记录
                $data_record2[] = [
                    'user_id' => $k,
                    'order_id' => 0,
                    'order_detail_id' => 0,
                    'note' => "活动返现",
                    'money' => $v,
                    'balance' => $money + $v,
                    'create_time' => time()
                ];
            }

            ##添加返现记录
            if(isset($data_record2) && $data_record2){
                $res = UserLogic::addUserHuoliRecord($data_record2);
                if($res === false)throw new Exception('自动结转=>增加用户返现记录失败');
            }

            ##增加销量
            foreach($product_sale as $k => $v){
                $res = Logic::updateProSales($k, $v);
                if($res === false)throw new Exception('自动结转=>增加商品销量失败');
            }

            ##增加规格销量
            foreach($specs_sale as $k => $v){
                $res = Logic::updateSpecsSales($k, $v);
                if($res === false)throw new Exception('自动结转=>增加商品规格销量失败');
            }

            ##更新店铺已完成订单数 && 更新店铺最近一单成交时间
            foreach($store_shouru as $k => $v){
                Logic::updateStoreDealNum($k);
                Logic::updateStoreLatelyDealTime($k);
            }

            Db::commit();

            flock($fp,LOCK_UN);//释放锁
            fclose($fp);

            return 'SUCCESS2';

        }catch(Exception $e){
            Db::rollback();
            echo $e->getTraceAsString();
            addErrLog($list,$e->getMessage(),8);
            return 'FALSE';
        }

    }

    /**
     * 待收货7天自动收货(改变至待评价)
     */
//    public function autoConfirmOrder1(){
//
//        $limit = config('config_order.hour_confirm_not_confirm');
//        $limit_time = $limit * 60 * 60;
//
//        #获取订单列表
//        $list = UserLogic::orderListWaitFetch($limit_time);
//        if(!$list)return "SUCCESS";
//
//        $order_ids = array_column($list,'id');
//
//        Db::startTrans();
//
//        try{
//
//            #判断代购商品
//            $orders_detail = UserLogic::proListMemberBuy($order_ids);
//            $total_product_money = $dg_money = $user_money = [];
//            if($orders_detail){  //有代购商品
//                ##增加用户余额 增加代购收支记录
//                $data_record = [];
//                foreach($orders_detail as $v){
//                    $product_money = $v['number'] * $v['price'];
//                    $total_product_money[$v['store_id']] += $product_money;
//                    $dg_money[$v['store_id']] += $v['huoli_money'];
//                    ##增加代购记录
//                    ###获取用户余额
//                    $money = UserLogic::userMoney($v['user_id']);
//                    ###增加用户余额
//                    $res = UserLogic::addUserMoney($v['user_id'],$v['huoli_money']);
//                    if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货返利失败"]));
//                    ###添加代购收入记录
//                    $data_record[] = [
//                        'user_id' => $v['user_id'],
//                        'order_id' => $v['order_id'],
//                        'order_detail_id' => $v['id'],
//                        'note' => '代购收入',
//                        'money' => $v['huoli_money'],
//                        'balance' => $money + $v['huoli_money'],
//                        'create_time' => time()
//                    ];
//
//                }
//                ##添加获利记录
//                if($data_record){
//                    $res = UserLogic::addUserHuoliRecord($data_record);
//                    if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货增加用户获利记录失败"]));
//                }
//            }
//
//            #普通商品
//            $orders_detail = UserLogic::proListNormalBuy($order_ids);
//            foreach($orders_detail as $v){
//                $product_money = $v['number'] * $v['price'];
//                $total_product_money[$v['store_id']] += $product_money;
//            }
//
//            ##计算运费|优惠券金额|平台提成
//            $freight_price = $coupon_price = $platform_profit_price = $order_ids_arr = [];
//            foreach($list as $v){
//                $freight_price[$v['store_id']] += $v['total_freight'];
//                $platform_profit_price[$v['store_id']] += $v['platform_profit'];
//                $order_ids_arr[$v['store_id']] = $v['id'] . ",";
//                $user_money[$v['user_id']] += ($v['pay_money'] - $v['total_freight']);
//
//                ##店铺承担优惠券金额
//                ###平台券
//                $coupon_info = Logic::getCouponRuleInfoByCouponId($v['coupon_id']);
//                $coupon_price[$v['store_id']] += (1 - $coupon_info['platform_bear']) * $v['coupon_money'];
//                ###店铺券
//                $store_coupon_info = Logic::getCouponRuleInfoByCouponId($v['store_coupon_id']);
//                $coupon_price[$v['store_id']] += (1 - $store_coupon_info['platform_bear']) * $v['store_coupon_money'];
//                ###商品券
//                $pro_coupon_info = Logic::getCouponRuleInfoByCouponId($v['product_coupon_id']);
//                $coupon_price[$v['store_id']] += (1 - $pro_coupon_info['platform_bear']) * $v['product_coupon_money'];
//            }
//
//            ##商家增加余额
//            foreach($total_product_money as $k => $v){
//
//                ##店铺收入 = 这一单的产品收入总金额 + 运费 - 平台提成 - 店铺优惠券价格 - 代购价
//                $store_shouru = $v + $freight_price[$k] - $platform_profit_price[$k] - $coupon_price[$k] - $dg_money[$k];
//
//                ###增加商户余额(收入)
//                $store_money = Logic::storeMoney($k);
//                $res = Logic::IncStoreMoney($k,$store_shouru);
//                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货增加店铺收入失败"]));
//
//                ###增加店铺收入记录
//                $data_store_money_detail = [
//                    'store_id' => $k,
//                    'order_id' => 0,
//                    'note' => "商品收入[自动确认收货][{$order_ids_arr[$k]}]",
//                    'money' => $store_shouru,
//                    'balance' => $store_money + $store_shouru,
//                    'create_time' => time()
//                ];
//
//                $res = Logic::addStoreIncomeRecord($data_store_money_detail);
//                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货增加店铺收入记录失败"]));
//
//            }
//
//            ##更新用户累计消费金额
//            foreach($user_money as $k => $v){
//                ##更新累计消费金额
//                $res = UserLogic::userIncLeijiMoney($k,$v);
//                if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货增加用户累积记录失败"]));
//            }
//
//            ##更新订单状态
//            $res = Logic::confirmLotsOrder($order_ids);
//            if($res === false)throw new Exception(json_encode(['content'=>['订单IDS'=>$order_ids],'title'=>"未收货自动确认收货更新订单状态失败"]));
//
//            ##更新店铺已完成订单数 && 更新店铺最近一单成交时间
//            foreach($total_product_money as $k => $v){
//                Logic::updateStoreDealNum($k);
//                Logic::updateStoreLatelyDealTime($k);
//            }
//
//            Db::commit();
//
//            return "SUCCESS";
//
//        }catch(Exception $e){
//            Db::rollback();
//
//            $data = json_decode($e->getMessage(),true);
//            if(is_array($data)){
//                addErrLog($data['content'],$data['title'],3);
//            }else{
//                addErrLog($e->getMessage(),'待收货15天自动收货',4);
//            }
//
//            return "FALSE";
//        }
//
//    }

    /**
     * 申请售后商家7天未处理自动同意(停用)
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
            if($res === false)throw new Exception(json_encode(['content'=>['售后IDS'=>$shouhou_ids],'title'=>"售后自动同意更新售后状态失败"]));

            ##未收货
            $shouhou_ids = array_column($list_no_pro,'shouhou_id');

            ###修改售后状态为已退款
            $res = UserLogic::agreeShouhouRefund($shouhou_ids);
            if($res === false)throw new Exception(json_encode(['content'=>['售后IDS'=>$shouhou_ids],'title'=>"售后自动同意更新售后状态失败"]));

            $refund_lists = [];
            foreach($list_no_pro as $v){
                ###修改订单详情的退款金额与时间
                $refund_money = $v['coupon_id'] ? $v['realpay_money'] : ($v['number'] * $v['price']);
                $res = UserLogic::editOrderDetailRefund($v['id'],$refund_money);
                if($res === false)throw new Exception(json_encode(['content'=>['售后IDS'=>$shouhou_ids,'当前订单详情ID'=>$v['id']],'title'=>"售后自动同意更新订单详情退款信息失败"]));

                $refund_lists[$v['order_no']]['refund_money'] += $refund_money;
                $refund_lists[$v['order_no']]['store_id'] = $v['store_id'];
                $refund_lists[$v['order_no']]['pay_type'] = $v['pay_type'];
                $refund_lists[$v['order_no']]['pay_scene'] = $v['pay_scene'];
                $refund_lists[$v['order_no']]['pay_order_no'] = $v['pay_order_no'];
                $refund_lists[$v['order_no']]['pay_money'] = $v['pay_money'];
            }

            ###退款
            foreach($refund_lists as $k => $v){
                $order_no = $v['pay_scene'] ? $v['order_no'] : $v['pay_order_no'];
                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order_no,$v['store_id'],$v['refund_money']);
                }else if($v['pay_type'] == '微信'){
                    $total_pay_money = $v['pay_scene'] ? $v['pay_money'] : (UserLogic::orderTotalPayMoney($v['pay_order_no']));
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($order_no,$total_pay_money,$v['refund_money']);
                }
                if ($res !== true)addErrLog(['售后IDS'=>$shouhou_ids,'订单号'=>$k,"退款类型"=>$v['pay_type'],'错误信息'=>$res],'售后自动同意退款失败',8);
            }

            Db::commit();

            return "SUCCESS";

        }catch(Exception $e){
            Db::rollback();

            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],3);
            }else{
                addErrLog($e->getMessage(),'申请售后商家7天未处理自动同意',8);
            }

            return "FALSE";
        }
    }

    /**
     * 售后=》用户退货7天后商户未确认收货退款【自动确认收货并退款】
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
            if($res === false)throw new Exception(json_encode(['content'=>['售后IDS'=>$shouhou_ids],'title'=>"售后自动确认商家收货失败"]));

            $refund_lists = [];
            ###修改订单详情的退款金额与时间
            foreach($list as $v){
//                $refund_money = $v['coupon_id'] ? $v['realpay_money'] : ($v['number'] * $v['price']);
                $refund_money = $v['realpay_money'];
                $res = UserLogic::editOrderDetailRefund($v['id'],$refund_money);
                if($res === false)throw new Exception(json_encode(['content'=>['售后IDS'=>$shouhou_ids,'当前订单详情ID'=>$v['id']],'title'=>"售后自动确认商家收货更新【订单详情退款信息失败】"]));

                $refund_lists[$v['order_no']]['refund_money'] += $refund_money;
                $refund_lists[$v['order_no']]['store_id'] = $v['store_id'];
                $refund_lists[$v['order_no']]['pay_type'] = $v['pay_type'];
            }

            ###退款
            foreach($refund_lists as $k => $v){
                if($v['pay_type'] == '支付宝'){
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($k,$v['store_id'],$v['refund_money']);
                }else if($v['pay_type'] == '微信'){
                    $total_pay_money = UserLogic::orderTotalPayMoney($k);
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($k,$total_pay_money,$v['refund_money']);
                }
                if ($res !== true)addErrLog(['售后IDS'=>$shouhou_ids,'订单号'=>$k,"退款类型"=>$v['pay_type'],'错误信息'=>$res],'售后自动确认商家收货【退款失败】',8);
            }

            Db::commit();

            return "SUCCESS";
        }catch(Exception $e){
            Db::rollback();

            $data = json_decode($e->getMessage(),true);
            if(is_array($data)){
                addErrLog($data['content'],$data['title'],3);
            }else{
                addErrLog($e->getMessage(),'售后=》用户退货15天后商户未确认收货退款【自动确认收货并退款】',8);
            }

            return "FALSE";
        }
    }

    /**
     * 定时任务发送活动提醒
     * @return string
     * @throws \Exception
     */
    public function autoActivityClock(){

        ##获取待发消息的列表
        $list = UserLogic::getAcNeedClockList();

        try{
            ##循环发信息
            foreach($list as $v){
                if(isset($v['mobile'])){

                    ##改状态
                    $res = UserLogic::updateAcClockStatus($v['id']);
                    if($res === false)throw new Exception('活动提醒状态更新失败');

                    ##发信息
//                    $res = IhuyiSMS::ac_clock($v['mobile'], $v['message']);
                    $res = AliSMS::sendAcClockMsg($v['mobile'],$v['product_name'],$v['product_id']);
                    if($res['Code'] != 'OK')throw new Exception("活动开始提醒短息发送失败,失败信息【{$res}】");

                    ##添加系统消息
                    Msg::addActivityWillStartSysMsg($v['id']);
                }
            }

            return 'SUCCESS';
        }catch(Exception $e){

            addErrLog($e->getMessage(),'活动提醒短信发送',8);
            return 'FALSE';

        }

    }

    /**
     * 售后用户发货 7 天后店铺未作处理，自动确认收货
     * @return string
     * @throws \Exception
     */
    public function autoReceiveShouHouPro(){

        $limit = config('config_order.hour_receive_shouhou_pro');

        $limit_time = time() - $limit * 60 * 60;

        ##获取待商家确认收货列表
        $list = UserLogic::getShouhouAutoReceiveList($limit_time);
        if(empty($list))return 'SUCCESS';

        Db::startTrans();
        try{
            foreach($list as $v){
                ##修改售后状态
                $res = UserLogic::editProShouhouReceive($v['id']);
                if($res === false)throw new Exception('自动确认商家收货失败');

                ##修改订单商品详情的状态
                $res = UserLogic::editProOrderDetailShouhou($v['detail_id'], $v['relpay_money']);
                if($res === false)throw new Exception('自动确认商家收货=>修改订单详情状态失败');

                ##退款
                $pay_order_no = $v['pay_scene']?$v['order_no']:$v['pay_order_no'];
                ###退款通知
                $res = UserLogic::addUserMsg($v['user_id'],0,'您的订单'.$v['order_no'].'中的商品售后成功,订单金额已原路返回');
                if($res === false)throw new Exception('自动确认商家收货=>生成退款通知消息失败');

                if($v['relpay_money'] >= 0.01){
                    //todo 此处原路退款
                    if ($v['pay_type'] == '支付宝') {
                        $alipay = new AliPay();
                        $res = $alipay->alipay_refund($pay_order_no,$v['order_id'],$v['relpay_money']);
                    }elseif ($v['pay_type'] == '微信'){
                        $total_pay_money = $v['pay_scene']?$v['pay_money']: UserLogic::sumOrderPayMoney($pay_order_no);
                        $wxpay = new WxPay();
                        $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$v['relpay_money']);
                    }
                    if ($res !== true){
                        throw new Exception('自动确认商家收货=>取消订单退款失败');
                    }
                }
            }

            Db::commit();

            return 'SUCCESS';
        }catch(Exception $e){
            addErrLog($e->getMessage(),'售后-自动确认商家收货',5);
            return 'FALSE';
        }
    }

    /**
     * 售后3天商家未处理自动转为平台介入
     * @return string
     */
    public function autoShouhouPlatformService(){

        $limit = config('config_order.hour_shouhou_platform_service');

        $limit_time = time() - $limit * 60 * 60;

        $list = UserLogic::getShouhouAutoPlatformServiceList($limit_time);

        if(empty($list))return 'EMPTY';

        ##处理商品订单详情修改为已客服介入状态
        $order_detail_ids = array_column($list,'order_detail_id');

        $res = UserLogic::updateOrderDetailPlatformService($order_detail_ids);

        if($res === false)return 'FALSE';

        return 'SUCCESS';

    }

    /**
     * 员工收益 每月15号或者月末结算
     */
    public function businessProfit(){
        //是否需要判断 结算时间是否是15号或者月末
        $nowDay=date('d');
        $moDay = date('t');
        $profitDate = ['15'];
        array_push($profitDate,$moDay);
        //是否需要限制当前时间是否可以调用该接口
        //if(in_array($nowDay,$profitDate)){
        //当前时间 应该结算的时间
            if($nowDay >= 15 && $nowDay < $moDay){  //大于本月15号同时小于本月最后一天  应该结算 1-15号的收益
                $profit_time = strtotime(date("Y-m-15 23:59:59"));
            }elseif($nowDay == $moDay){ //等于本月最后一天    应该结算本月的收益
                $profit_time = time();
            }else{ //结算上月的收益
                $shangyue = date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day'));
                $profit_time =  strtotime($shangyue) + 86399;
            }
            $where['create_time'] = ['elt',$profit_time];
            $where['status'] = ['eq',2];
            Db::startTrans();
            try {
                $res = Db::name('bussiness_profit')
                    ->where($where)
                    ->select();
                if ($res === false) throw new Exception('结算员工收益失败');
                $res2 = Db::name('bussiness_profit')->where($where)->update(['status' => 3]);
                if ($res2 === false) throw new Exception('结算员工收益失败');
                //1.买单提成；2.销售总额阶梯奖励；3.单新用户推广奖励；4.首个用户额外奖励；5.平台阶梯奖励;
                foreach ($res as &$v) {
                    switch ($v['type']) {
                        case 1:
                            $note = '买单提成';
                            break;
                        case 2:
                            $note = '销售总额阶梯奖励';
                            break;
                        case 3:
                            $note = '单新用户推广奖励';
                            break;
                        case 4:
                            $note = '首个用户额外奖励';
                            break;
                        case 5:
                            $note = '平台阶梯奖励';
                            break;
                        default:
                            $note = '买单提成';
                    }
                    //增加员工金额详情表记录
                    $businessMoney = Db::name('business_money_detail')->insert([
                        'order_id' => '0',
                        'user_id' => $v['staff_id'],
                        'note' => $note,
                        'money' => $v['price_profit'],
                        'balance' => '0',
                        'create_time' => time(),
                        'type' => $v['type'],
                        'profit_id' => $v['id']
                    ]);
                    //增加员工表中的金额
                    $business = Db::name('business')->where(['id' => $v['staff_id']])->setInc('money', $v['price_profit']);
                    if ($businessMoney === false || $business === false) {
                        throw new Exception('结算员工收益失败');
                    }
                }
                Db::commit();
            }catch(Exception $e){
                Db::rollback();
                return "FALSE";
            }
        //}
        return 'SUCCESS';
    }

    /**
     * 删除7天前的员工操作日志
     */
    public function deteleBusinessLog(){
        $delete_time = strtotime("-7 day");
        Db::name('business_log')->where(['create_time'=> ['lt',$delete_time]])->delete();
        return 'SUCCESS';
    }

    /**
     * 插入抽奖记录
     */
    public function importDrawRecord(){
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();
        $key = getDrawImportRecordKey();
        $num = 10;
        do{
            $data = $redis->lPop($key);
            $data = json_decode($data,true);
            $res = true;
            if($data && is_array($data)){
                $res = DrawLotteryRecord::addRecord($data);
            }
            $num --;
        }while(is_array($data) && $data && $num > 0 && $res !== false);
    }


}