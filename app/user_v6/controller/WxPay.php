<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/29
 * Time: 17:06
 */

namespace app\user_v6\controller;

use app\common\controller\IhuyiSMS;
use app\user_v6\common\Logic;
use app\user_v6\common\UserLogic;
use app\user_v6\model\DrawLotteryUserNum;
use think\Db;
use think\Log;
require_once(__DIR__."/Wxpay/lib/WxPay.Api.php");
require_once(__DIR__."/Wxpay/lib/WxPay.Notify.php");
require_once(__DIR__."/Wxpay/unit/log.php");
class WxPay
{

    /**
     * 获取订单签名信息
     * @param $order_id @订单号
     * @param $total_fee @交易金额
     * @param $notify @回调地址
     * @return array @订单签名
     * @throws \WxPayException
     */
    public function getOrderSign($order_id,$total_fee,$notify)
    {

        $order_id = str_pad($order_id, 8, "0", STR_PAD_LEFT);

        $input = new \WxPayUnifiedOrder();
        // 设置app_id
        $input->SetAppid(\WxPayConfig::APPID);
        // 设置match_id
        $input->SetMch_id(\WxPayConfig::MCHID);
        // 添加一个随机字符串
        $input->SetNonce_str(\WxPayConfig::MCHID.date("YmdHis"));
        // 添加支付现在名称
        $input->SetBody("超神宿");
        // 商家订单号
        $input->SetOut_trade_no($order_id);
        // 支付金额
        $input->SetTotal_fee($total_fee*100);
        $input->SetSpbill_create_ip("1.1.1.1");
        $input->SetNotify_url($notify);
        // 支付方式为app
        $input->SetTrade_type("APP");
        $order_data = \WxPayApi::unifiedOrder($input);
        //统一下单
        $order_data['timestamp'] = time();
        $str = 'appid='.$order_data['appid'].'&noncestr='.$order_data['nonce_str'].'&package=Sign=WXPay&partnerid='.\WxPayConfig::MCHID.'&prepayid='.$order_data['prepay_id'].'&timestamp='.$order_data['timestamp'];
        //③ 重新生成签名，并将结果返回给客户端
        $order_data['sign'] = strtoupper(md5($str.'&key='.\WxPayConfig::KEY));
        $parameter = array(
            'appid' => $order_data['appid'],
            'partnerid'=>$order_data['mch_id'],
            'prepayid'=>$order_data['prepay_id'],
            'package'=>'Sign=WXPay',
            'noncestr'=>$order_data['nonce_str'],
            'timestamp'=>$order_data['timestamp'],
            'sign' => $order_data['sign']
        );

        return $parameter;
    }

    /**
     * 微信退款
     * @param $out_trade_no
     * @param $total_fee
     * @param $refund_fee
     * @return bool
     * @throws \Exception
     */
    public function wxpay_refund($out_trade_no,$total_fee,$refund_fee){
        $input = new \WxPayRefund();
        $input->SetOut_trade_no($out_trade_no);
        $input->SetTotal_fee($total_fee*100);
        $input->SetRefund_fee($refund_fee*100);
        $input->SetOut_refund_no(\WxPayConfig::MCHID.date("YmdHis"));
        $input->SetOp_user_id(\WxPayConfig::MCHID);
        $param = \WxPayApi::refund($input);
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/goods_refund/']);
        Log::write($param);
        if($param['result_code'] == 'SUCCESS'){
            return true;
        }

        #return false;
        throw new \Exception($param['err_code_des']);
    }

    /**
     * 购买商品回调
     * @throws \WxPayException
     */
    public function goods_wxpay_notify2(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/goods/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('pay_order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->value('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    $pay_money = Db::name('product_order')->where('pay_order_no',$out_trade_no)->sum('pay_money');
                    if($pay_money != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    //是否实物类型订单
                    if ($orderInfo['order_type'] == 1){
                        if ($orderInfo['is_group_buy'] == 1) {
                            if ($orderInfo['pt_type'] == 0){
                                //如果是普通团购订单
                                $pt_info = Db::name('user_pt')->where('id',$orderInfo['pt_id'])->find();
                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('pay_order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    $pt_count = Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->where('order_status',2)->count();
                                    $pt_count = $pt_count+1;
                                    if ($pt_count == $pt_info['pt_size']){
                                        Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pt_id',$orderInfo['pt_id'])
                                            ->update(['order_status'=>3,'pay_time'=>time()]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pay_order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                    }
                                }
                            }else{
                                //潮搭拼团订单
                                //如果是普通团购订单
                                $pt_info = Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->find();

                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Log::write("拼团发起is_header=".$orderInfo['is_header']);
                                    Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('pay_order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    Log::write("拼团参与is_header=".$orderInfo['is_header']);
                                    $pt_count = Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->where('order_status',2)->count();
                                    $pt_count = $pt_count+1;
                                    Log::write("pt_count=".$pt_count);
                                    if ($pt_count == $pt_info['pt_size']){
                                        Log::write('拼团完成');
                                        Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pay_order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                        //完成拼团修改为待发货
                                        Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->update(['order_status'=>3]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pay_order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                    }
                                }
                                $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                                Db::name('chaoda_pt_product_info')->where('pt_id',$pt_info['id'])->where('product_id',$orderdetail['product_id'])->setField('status',1);
                            }
                        }else{
                            // 更新订单状态
                            Db::name('product_order')
                                ->where('pay_order_no',$out_trade_no)
                                ->update(['order_status'=>3,'pay_time'=>time()]);
                            //发送短信提示商家发货
                            IhuyiSMS::order_code1($store_mobile);
                        }
                    }else{
                        //虚拟卡券类商品订单状态直接完成
                        //1 更新订单状态
                        Db::name('product_order')
                            ->where('pay_order_no',$out_trade_no)
                            ->update(['order_status'=>6,'pay_time'=>time()]);

                        //2 派发卡券
                        $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                        $brand_name = Db::name('store')->where('id',$orderInfo['store_id'])->value('brand_name');
                        for ($i=0;$i<$orderdetail['number'];$i++){
                            $card_id = Db::name('user_card')->insertGetId([
                                'user_id' => $orderInfo['user_id'],
                                'cover' => $orderdetail['cover'],
                                'coupon_name' => $orderdetail['product_name'],
                                'brand_name' => $brand_name,
                                'card_code' => build_order_no(''),
                                'status' => 1,
                                'start_time' => time(),
                                'end_time' => time() + $orderdetail['days'] * 24 * 3600
                            ]);

                            Db::name('user_card_store')->insert(['card_id' => $card_id,'store_id' => $orderInfo['store_id']]);
                        }

                    }

                    //订单详情 增加销量
                    $order_detail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->select();
                    foreach ($order_detail as $k=>$v){
                        Db::name('product')->where('id',$v['product_id'])->setInc('sales',$v['number']);
                        //给商家发送推送以及系统消息   商品售完
                        Logic::sellout_system_msg($orderInfo['store_id'],$v['product_id']);
                    }
                    //给商家发送推送以及系统消息   待发货
                    Logic::fahou_system_msg($orderInfo['store_id']);

                    //修改累计金额
                    Db::name('user')->where('user_id',$orderInfo['user_id'])->setInc('leiji_money',$total_fee);
                    $userinfo = Db::name('user')->where('user_id',$orderInfo['user_id'])->find();

                    //累计消费金额超过3000成为会员
//                    if ($userinfo['type'] == 1){
//                        if (($userinfo['leiji_money']) >= 3000){
//                            Db::name('user')->where('user_id',$orderInfo['user_id'])->setField('type',2);
//                        }
//                    }

                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }


    /**
     * 购买商品回调
     * @throws \WxPayException
     */
    public function goods_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/goods/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('pay_order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->column('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    $pay_money = Db::name('product_order')->where('pay_order_no',$out_trade_no)->sum('pay_money');
                    if($pay_money != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    //是否实物类型订单
                    if ($orderInfo['order_type'] == 1){
                        //是否团购商品
                        if ($orderInfo['is_group_buy'] == 1) {
                            if ($orderInfo['pt_type'] == 0){
                                //如果是普通团购订单
                                $pt_info = Db::name('user_pt')->where('id',$orderInfo['pt_id'])->find();
                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('pay_order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    if ($pt_info['ypt_size'] == $pt_info['pt_size']){
                                        Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pt_id',$orderInfo['pt_id'])
                                            ->update(['order_status'=>3,'pay_time'=>time()]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pay_order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                    }
                                }
                            }else{
                                //潮搭拼团订单
                                //如果是普通团购订单
                                $pt_info = Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->find();
                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('pay_order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    if ($pt_info['ypt_size'] == $pt_info['pt_size']){
                                        Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pt_id',$orderInfo['pt_id'])
                                            ->update(['order_status'=>3,'pay_time'=>time()]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pay_order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);

                                    }
                                }

                                $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                                Db::name('chaoda_pt_product_info')->where('pt_id',$pt_info['id'])->where('product_id',$orderdetail['product_id'])->setField('status',1);
                            }

                        }else{

                            ##获取用户会员状态
                            $member_end_time = Db::name('user')->where(['user_id'=>$orderInfo['user_id']])->value('end_time');
                            $is_member = $member_end_time>=time() ? 1 : 0;

                            // 更新订单状态
                            Db::name('product_order')
                                ->where('pay_order_no',$out_trade_no)

                                ->update(['order_status'=>3,'pay_time'=>time(),'is_member'=>$is_member]);
                            //发送短信提示商家发货
                            foreach($store_mobile as $v){
                                if($v)IhuyiSMS::order_code1($v);
                            }
                        }
                    }else{
                        //虚拟卡券类商品订单状态直接完成
                        //1 更新订单状态
                        Db::name('product_order')
                            ->where('pay_order_no',$out_trade_no)
                            ->update(['order_status'=>6,'pay_time'=>time()]);

                        //2 派发卡券
                        $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                        $brand_name = Db::name('store')->where('id',$orderInfo['store_id'])->value('brand_name');
                        for ($i=0;$i<$orderdetail['number'];$i++){
                            $card_id = Db::name('user_card')->insertGetId([
                                'user_id' => $orderInfo['user_id'],
                                'cover' => $orderdetail['cover'],
                                'coupon_name' => $orderdetail['product_name'],
                                'brand_name' => $brand_name,
                                'card_code' => build_order_no(''),
                                'status' => 1,
                                'start_time' => time(),
                                'end_time' => time() + $orderdetail['days'] * 24 * 3600
                            ]);

                            Db::name('user_card_store')->insert(['card_id' => $card_id,'store_id' => $orderInfo['store_id']]);
                        }

                    }

                    //订单详情 增加销量
                    $order_detail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->select();
                    foreach ($order_detail as $k=>$v){
                        Db::name('product')->where('id',$v['product_id'])->setInc('sales',$v['number']);
                        //给商家发送推送以及系统消息   商品售完
                        Logic::sellout_system_msg($orderInfo['store_id'],$v['product_id']);
                    }
                    //给商家发送推送以及系统消息   待发货
                    Logic::fahou_system_msg($orderInfo['store_id']);
                    
                    //修改累计金额
                    Db::name('user')->where('user_id',$orderInfo['user_id'])->setInc('leiji_money',$total_fee);
                    $userinfo = Db::name('user')->where('user_id',$orderInfo['user_id'])->find();

                    //累计消费金额超过3000成为会员
//                    if ($userinfo['type'] == 1){
//                        if (($userinfo['leiji_money']) >= 3000){
//                            Db::name('user')->where('user_id',$orderInfo['user_id'])->setField('type',2);
//                        }
//                    }
                    // 提交事务
                    Db::commit();
                    ##增加用户抽奖次数
                    DrawLotteryUserNum::addUserDrawLotteryNum($userinfo['user_id'], $pay_money);
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }

    /**
     * 购买商品回调 --再次支付
     * @throws \WxPayException
     */
    public function repay_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/repay/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->value('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    $pay_money = Db::name('product_order')->where('order_no',$out_trade_no)->value('pay_money');
                    if($pay_money != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    //是否实物类型订单
                    if ($orderInfo['order_type'] == 1){
                        //是否团购商品
                        if ($orderInfo['is_group_buy'] == 1) {
                            if ($orderInfo['pt_type'] == 0){
                                //如果是普通团购订单
                                $pt_info = Db::name('user_pt')->where('id',$orderInfo['pt_id'])->find();
                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    if ($pt_info['ypt_size'] == $pt_info['pt_size']){
                                        Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pt_id',$orderInfo['pt_id'])
                                            ->update(['order_status'=>3,'pay_time'=>time()]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                    }
                                }
                            }else{
                                //潮搭拼团订单
                                //如果是普通团购订单
                                $pt_info = Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->find();
                                if ($orderInfo['is_header'] == 1) {
                                    //是拼团发起人
                                    //修改拼团记录为拼团中
                                    Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    if ($pt_info['ypt_size'] == $pt_info['pt_size']){
                                        Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('pt_id',$orderInfo['pt_id'])
                                            ->update(['order_status'=>3,'pay_time'=>time()]);
                                    }else{
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);

                                    }
                                }

                                $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                                Db::name('chaoda_pt_product_info')->where('pt_id',$pt_info['id'])->where('product_id',$orderdetail['product_id'])->setField('status',1);
                            }

                        }else{

                            ##获取用户会员状态
                            $member_end_time = Db::name('user')->where(['user_id'=>$orderInfo['user_id']])->value('end_time');
                            $is_member = $member_end_time>=time() ? 1 : 0;

                            // 更新订单状态
                            Db::name('product_order')
                                ->where('order_no',$out_trade_no)

                                ->update(['order_status'=>3,'pay_time'=>time(),'pay_scene'=>1,'is_member'=>$is_member]);
                            //发送短信提示商家发货
                            IhuyiSMS::order_code1($store_mobile);
                        }
                    }else{
                        //虚拟卡券类商品订单状态直接完成
                        //1 更新订单状态
                        Db::name('product_order')
                            ->where('order_no',$out_trade_no)
                            ->update(['order_status'=>6,'pay_time'=>time()]);

                        //2 派发卡券
                        $orderdetail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->find();
                        $brand_name = Db::name('store')->where('id',$orderInfo['store_id'])->value('brand_name');
                        for ($i=0;$i<$orderdetail['number'];$i++){
                            $card_id = Db::name('user_card')->insertGetId([
                                'user_id' => $orderInfo['user_id'],
                                'cover' => $orderdetail['cover'],
                                'coupon_name' => $orderdetail['product_name'],
                                'brand_name' => $brand_name,
                                'card_code' => build_order_no(''),
                                'status' => 1,
                                'start_time' => time(),
                                'end_time' => time() + $orderdetail['days'] * 24 * 3600
                            ]);

                            Db::name('user_card_store')->insert(['card_id' => $card_id,'store_id' => $orderInfo['store_id']]);
                        }

                    }

                    //订单详情 增加销量
                    $order_detail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->select();
                    foreach ($order_detail as $k=>$v){
                        Db::name('product')->where('id',$v['product_id'])->setInc('sales',$v['number']);
                    }

                    //修改累计金额
                    Db::name('user')->where('user_id',$orderInfo['user_id'])->setInc('leiji_money',$total_fee);
                    //$userinfo = Db::name('user')->where('user_id',$orderInfo['user_id'])->find();

                    //累计消费金额超过3000成为会员
//                    if ($userinfo['type'] == 1){
//                        if (($userinfo['leiji_money']) >= 3000){
//                            Db::name('user')->where('user_id',$orderInfo['user_id'])->setField('type',2);
//                        }
//                    }

                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }

    /**
     * 购买商品回调
     * @throws \WxPayException
     */
   /* public function goods_wxpay_notify2(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/goods/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('pay_order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->value('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    $pay_money = Db::name('product_order')->where('pay_order_no',$out_trade_no)->sum('pay_money');
                    if($pay_money != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    //是否团购商品
                    if ($orderInfo['is_group_buy'] == 1) {
                        //如果是团购订单
                        $pt_info = Db::name('user_pt')->where('id',$orderInfo['pt_id'])->find();
                        if ($orderInfo['is_header'] == 1) {
                            //是拼团发起人
                            //修改拼团记录为拼团中
                            Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                            Db::name('product_order')
                                ->where('pay_order_no',$out_trade_no)
                                ->update(['order_status'=>2,'pay_time'=>time()]);
                            // 提交事务
                            Db::commit();

                        }else{
                            //是拼团参与
                            //是否拼团完成
                            if ($pt_info['ypt_size'] == $pt_info['pt_size']){
                                Db::name('user_pt')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                // 更新订单状态
                                Db::name('product_order')
                                    ->where('pt_id',$orderInfo['pt_id'])
                                    ->update(['order_status'=>3,'pay_time'=>time()]);
                            }else{
                                // 更新订单状态
                                Db::name('product_order')
                                    ->where('pay_order_no',$out_trade_no)
                                    ->update(['order_status'=>2,'pay_time'=>time()]);
                            }
                        }

                    }else{
                        // 更新订单状态
                        Db::name('product_order')
                            ->where('pay_order_no',$out_trade_no)

                            ->update(['order_status'=>3,'pay_time'=>time()]);
                        //发送短信提示商家发货
                        IhuyiSMS::order_code1($store_mobile);
                    }

                    //订单详情 增加销量
                    $order_detail = Db::name('product_order_detail')->where('order_id',$orderInfo['id'])->select();
                    foreach ($order_detail as $k=>$v){
                        Db::name('product')->where('id',$v['product_id'])->setInc('sales',$v['number']);
                    }

                    //修改累计金额
                    Db::name('user')->where('user_id',$orderInfo['user_id'])->setInc('leiji_money',$total_fee);
                    $userinfo = Db::name('user')->where('user_id',$orderInfo['user_id'])->find();

                    //累计消费金额超过3000成为会员
                    if ($userinfo['type'] == 1){
                        if (($userinfo['leiji_money']) >= 3000){
                            Db::name('user')->where('user_id',$orderInfo['user_id'])->setField('type',2);
                        }
                    }

                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }*/

    /**
     * 购买会员回调
     * @throws \WxPayException
     */
    public function member_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/member/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                $orderinfo = Db::name('member_order')->where('order_no',$out_trade_no)->find();

                if ($orderinfo['status'] != 1 ){
                    Log::write("订单信息错误");
                    Log::write('---End微信回调---');
                    exit();
                }
                //更新
                Db::name('member_order')->where('order_no',$out_trade_no)->update(['pay_type'=>'支付宝','pay_time'=>time(),'status'=>2]);
                $userinfo = Db::name('user')->where('user_id',$orderinfo['user_id'])->find();
                //续费和新买并判断是否有优惠券并自动下发
                $member_card_id=$orderinfo['member_card_id'];
                $user_id=$orderinfo['user_id'];
                automember($user_id,$member_card_id);
                //是否有邀请人
                if ($userinfo['invitation_user_id']) {
                    //返利
                    $fanli_money = Db::name('member_price')->where('id',1)->value('member_fanli_money');
                    $userinfo2 = Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->find();
                    if ($userinfo2){
                        Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->setInc('money',$fanli_money);

                        Db::name('user_money_detail')->insert([
                            'user_id' => $userinfo['invitation_user_id'],
                            'order_id' => $orderinfo['id'],
                            'note' => '会员返利',
                            'money' => $fanli_money,
                            'balance' => $userinfo2['money'],
                            'create_time'=> time()
                        ]);
                    }
                }
                if ($userinfo['invitation_user_id'] != $orderinfo['share_user_id']){
                    //是否有分享人
                    if ($orderinfo['share_user_id']) {
                        //返利
                        $fanli_money = Db::name('member_price')->where('id',1)->value('member_fanli_money');
                        $userinfo2 = Db::name('user')->where('user_id',$orderinfo['share_user_id'])->find();
                        if ($userinfo2){
                            Db::name('user')->where('user_id',$orderinfo['share_user_id'])->setInc('money',$fanli_money);
                            Db::name('user_money_detail')->insert([
                                'user_id' => $orderinfo['share_user_id'],
                                'order_id' => $orderinfo['id'],
                                'note' => '会员返利',
                                'money' => $fanli_money,
                                'balance' => $userinfo2['money'],
                                'create_time'=> time()
                            ]);
                        }
                    }
                }
                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }

    /**
     * 购买礼包回调
     * @throws \WxPayException
     */
    public function giftpack_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/giftpack/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }

        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                $orderInfo = Db::name('giftpack_order')->where('order_no',$out_trade_no)->find();

                if ($orderInfo['status'] != 1 ){
                    Log::write("订单信息错误");
                    Log::write('---End微信回调---');
                    exit();
                }

                $giftpack_info = Db::name('giftpack')->where('id',$orderInfo['giftpack_id'])->find();

                if (!$giftpack_info){
                    Log::write("礼包不存在");
                    Log::write('---End微信回调---');
                    exit();
                }

                //门店卡券
                $card_info = Db::name('giftpack_card')->where('giftpack_id',$orderInfo['giftpack_id'])->where('type',1)->select();
                //优惠券卡券
                $coupon_info = Db::name('giftpack_card')->where('giftpack_id',$orderInfo['giftpack_id'])->where('type',2)->select();

                $time = time();
                if ($card_info){
                    foreach ($card_info as $k=>$v){

                        $card_store = Db::name('giftpack_card_store')->field('store_id')->where('card_id',$v['id'])->select();

                        $card_code = build_order_no('');
                        //增加用户卡券
                        $card_id = Db::name('user_card')->insertGetId([
                            'user_id' => $orderInfo['user_id'],
                            'cover' => $v['cover'],
                            'coupon_name' => $v['coupon_name'],
                            'satisfy_money' => $v['satisfy_money'],
                            'coupon_money' => $v['coupon_money'],
                            'brand_name' => $v['brand_name'],
                            'card_code' => $card_code,
                            'status' => 1,
                            'start_time' => $time,
                            'end_time' => $time + 3600 * 24 * $v['days']
                        ]);

                        foreach ($card_store as $k2=>$v2){
                            $card_store[$k2]['card_id'] = $card_id;
                        }

                        Db::name('user_card_store')->insertAll($card_store);


                    }
                }

                if ($coupon_info){
                    foreach ($coupon_info as $key=>$val){
                        //增加用户优惠券
                        Db::name('coupon')->insert([
                            'user_id' => $orderInfo['user_id'],
                            'coupon_name' => $val['coupon_name'],
                            'satisfy_money' => $val['satisfy_money'],
                            'coupon_money' => $val['coupon_money'],
                            'status' => 1,
                            'create_time' => $time,
                            'expiration_time' => $time + 3600 * 24 * $val['days']
                        ]);
                    }
                }

                Db::name('giftpack_order')->where('order_no',$out_trade_no)->update(['pay_type'=>'微信','pay_time'=>time(),'status'=>2]);

                Db::name('user')->where('user_id',$orderInfo['user_id'])->setField('type',2);

                $userinfo = Db::name('user')->where('user_id',$orderInfo['user_id'])->find();

                //是否有邀请人
                if ($userinfo['invitation_user_id']) {
                    //返利
                    #$fanli_money = Db::name('member_price')->where('id',1)->value('giftpack_fanli_money');
                    $fanli_money = Db::name('giftpack')->where('id',$orderInfo['giftpack_id'])->value('fanli_money');

                    $userinfo2 = Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->find();

                    if ($userinfo2){
                        Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->setInc('money',$fanli_money);

                        Db::name('user_money_detail')->insert([
                            'user_id' => $userinfo['invitation_user_id'],
                            'order_id' => $orderInfo['id'],
                            'note' => '礼包返利',
                            'money' => $fanli_money,
                            'balance' => $userinfo2['money'],
                            'create_time'=> time()
                        ]);
                    }
                }


                if ($userinfo['invitation_user_id'] != $orderInfo['share_user_id']){
                    //是否有分享人
                    if ($orderInfo['share_user_id']) {
                        //返利
                        #$fanli_money = Db::name('member_price')->where('id',1)->value('giftpack_fanli_money');
                        $fanli_money = Db::name('giftpack')->where('id',$orderInfo['giftpack_id'])->value('fanli_money');

                        $userinfo2 = Db::name('user')->where('user_id',$orderInfo['share_user_id'])->find();

                        if ($userinfo2){
                            Db::name('user')->where('user_id',$orderInfo['share_user_id'])->setInc('money',$fanli_money);

                            Db::name('user_money_detail')->insert([
                                'user_id' => $orderInfo['share_user_id'],
                                'order_id' => $orderInfo['id'],
                                'note' => '礼包返利',
                                'money' => $fanli_money,
                                'balance' => $userinfo2['money'],
                                'create_time'=> time()
                            ]);
                        }
                    }
                }



                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }

    //长租房回调
    public function long_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/long/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('long_order')->where('order_no',$out_trade_no)->find();

                    if ($orderInfo['status'] != 1 || $orderInfo['pay_type'] != '微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }

                    if ($orderInfo['reserve_money'] != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End微信回调---');
                        exit();
                    }

                    // 更新订单状态
                    Db::name('long_order')
                        ->where('order_no',$out_trade_no)
                        ->update(['status'=>2,'pay_time'=>time()]);

                    //房源改为已租状态
                    Db::name('house')->where('id',$orderInfo['house_id'])->setField('renting_status',2);


                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }


    //短租房
    public function short_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/short/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE)
        {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            elseif($notify->data["result_code"] == "FAIL"){
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            }
            else{
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no   = $data['out_trade_no'];   // 商户订单号
            $total_fee      = $data['total_fee'];      // 总金额
            $result_code    = $data['result_code'];    // 微信预支付返回值
            $out_trade_no   = ltrim($out_trade_no,"0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：'.$out_trade_no.',已支付金额：'.$total_fee);

            if($result_code == 'SUCCESS'){
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('short_order')->where('order_no',$out_trade_no)->find();

                    if($orderInfo['status']!=1 || $orderInfo['pay_type']!='微信' ){
                        Log::write("订单信息错误");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    if($orderInfo['pay_money'] != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End微信回调---');
                        exit();
                    }

                    // 更新订单状态
                    Db::name('short_order')
                        ->where('order_no',$out_trade_no)
                        ->update(['status'=>2,'pay_time'=>time()]);


                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    Log::write("订单操作失败:".$e->getMessage());
                    Log::write('---End微信回调---');
                    exit();
                }

                Log::write('OK');
                Log::write('---End微信回调---');
                echo $this->ResponseSuccessToWX(); //返回状态码

            }

        }
    }

    /**
     * 买单支付回调
     * @throws \WxPayException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function maidan_wxpay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/wxpay_log/maidan/']);
        Log::write('---Begin微信回调---');
        $notify = new \WxPayResults();

        //存储微信的回调
        #$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");
        $data = $notify->fromXml($xml);

        //验证签名，并回应微信。
        if($notify->checkSign() == FALSE){
            $this->ResponseFailToWX("FAIL","微信签名失败");
            //返回状态码 错误信息
            exit();
        }
        if($notify->checkSign() == TRUE) {
            if ($notify->data["return_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            } elseif ($notify->data["result_code"] == "FAIL") {
                Log::write("微信通信出错");
                Log::write('---End微信回调---');
                exit();
            } else {
                Log::write("微信支付状态成功");
            }
            // 获取订单支付信息
            $out_trade_no = $data['out_trade_no'];   // 商户订单号
            $total_fee = $data['total_fee'];      // 总金额
            $result_code = $data['result_code'];    // 微信预支付返回值
            $out_trade_no = ltrim($out_trade_no, "0");// 去掉左侧多余的0
            $total_fee = $total_fee / 100;
            Log::write('订单号：' . $out_trade_no . ',已支付金额：' . $total_fee);

            if ($result_code == 'SUCCESS') {
                #获取订单信息
                $order_info = Db::name('maidan_order')->where(['order_sn'=>$out_trade_no])->field('id,store_id,status,user_id,price_maidan,price_yj,member_order_id,coupon_id,coupon_money,price_store,staff_id,new_user_reward')->find();
                if(!$order_info){
                    addErrLog($data,"买单支付回调订单号{$out_trade_no}不存在",6);
                    Log::write("订单信息错误");
                    Log::write('---End微信回调---');
                    exit();
                }
                if($order_info['status'] != 1){
                    addErrLog($data,"买单支付回调=>订单号{$out_trade_no} => 订单状态异常不存在,当前状态【{$order_info['status']}】",6);
                    Log::write("订单号{$out_trade_no} => 订单状态异常不存在,当前状态【{$order_info['status']}】");
                    Log::write('---End微信回调---');
                    exit();
                }
                #修改订单状态,添加订单支付单号
                $data = [
                    'status' => 2,
                    'pay_time' => time(),
                    'is_finish' => 1,
                    'finish_time' => time()
                ];
                $res = Db::name('maidan_order')->where(['id'=>$order_info['id']])->update($data);
                if($res === false){
                    addErrLog($data,"买单支付回调=>订单号{$out_trade_no} => 订单信息更新失败",6);
                    Log::write("订单号{$out_trade_no} => 订单信息更新失败");
                    Log::write('---End微信回调---');
                    exit();
                }
                $userInfo = Db::name('user')->where(['user_id'=>$order_info['user_id']])->field('nickname,mobile,invitation_user_id')->find();
                //判断是否有优惠券
                if(isset($order_info['coupon_id']) && $order_info['coupon_id']>0){
                    $res_coupon = Db::name('coupon')->where('id',$order_info['coupon_id'])->update(['status'=>2,'use_time'=>time()]);
                    if($res_coupon === false) Log::write('修改优惠券状态为使用失败');
                }

                ##修改购买会员订单的状态
                if($order_info['member_order_id']){
                    $member_order_id = $order_info['member_order_id'];
                    $res = Db::name('member_order')->where(['order_id'=>$member_order_id])->update(['status'=>2,'pay_time'=>time(),'pay_type'=>'微信支付']);
                    if($res === false){
                        Log::write("订单号{$out_trade_no} => 会员购买订单信息更新失败");
                        Log::write('---End微信回调---');
                        exit();
                    }
                    ##续费或开通会员||下发优惠券
                    automember($order_info['user_id'],1);

                    ##增加用户会员开通成功通知
                    UserLogic::addUserMsg($order_info['user_id'],3,'您的会员已购买成功');

                    //是否有邀请人
                    if ($userInfo['invitation_user_id']) {
                        $invitation_user_id = $userInfo['invitation_user_id'];
                        //返利
                        $fanli_money = Db::name('member_price')->where('id',1)->value('member_fanli_money');
                        $userinfo2 = Db::name('user')->where('user_id',$invitation_user_id)->find();
                        if ($userinfo2){
                            Db::name('user')->where('user_id',$invitation_user_id)->setInc('money',$fanli_money);

                            Db::name('user_money_detail')->insert([
                                'user_id' => $invitation_user_id,
                                'order_id' => $member_order_id['id'],
                                'note' => '会员返利',
                                'money' => $fanli_money,
                                'balance' => $userinfo2['money'],
                                'create_time'=> time()
                            ]);
                        }
                    }
                }

                if(isset($order_info['staff_id']) && $order_info['staff_id']){
                    ##修改员工收益记录表状态
                    $res = Db::name('bussiness_profit')->where(['maidan_order_id'=>$order_info['id']])->setField('status',2);

                    ##更新员工累计经手买单金额
                    Db::name('business')->where(['id'=>$order_info['staff_id']])->setInc('maidan_total_money',$order_info['price_yj']);

                    ##更新员工邀请新用户数
                    if($order_info['new_user_reward'] > 0)
                        Db::name('business')->where(['id'=>$order_info['staff_id']])->setInc('invite_user_num',1);
                }

                ##结转商家流水
                ###增加商户余额(收入)
                $store_money = Logic::storeMoney($order_info['store_id']);
                $res = Logic::IncStoreMoney($order_info['store_id'], $order_info['price_store']);
                if($res === false)addErrLog($_POST,'买单回调,商家流水结转失败',6);

                ###增加店铺收入记录
                $data_store_money_detail = [
                    'store_id' => $order_info['store_id'],
                    'order_id' => $order_info['id'],
                    'note' => "商品收入,买单结转[订单号{$out_trade_no}]",
                    'money' => $order_info['price_store'],
                    'balance' => $store_money + $order_info['price_store'],
                    'create_time' => time()
                ];

                $res = Logic::addStoreIncomeRecord($data_store_money_detail);
                if($res === false)addErrLog($_POST,'买单回调,添加店铺收入失败',6);

                ##发送短信

                $storeInfo = Db::name('store')->where(['id'=>$order_info['store_id']])->field('mobile')->find();
                IhuyiSMS::maidan_code($storeInfo['mobile'],$out_trade_no,$userInfo['nickname'],hide_phone($userInfo['mobile']),$order_info['price_yj']);

                Log::write('OK');
                Log::write('---End微信回调---');

                ##增加用户抽奖次数
                DrawLotteryUserNum::addUserDrawLotteryNum($order_info['user_id'], $order_info['price_yj']);

                echo $this->ResponseSuccessToWX(); //返回状态码
            }
        }
    }


    private function ResponseSuccessToWX(){
        //返回给微信确认
        $array = array('return_code'=>'SUCCESS', 'return_msg' => 'OK');
        $result = $this->ToXml($array);
        return $result;
    }
    private function ResponseFailToWX($return_code='FAIL',$return_msg=''){
        //返回给微信确认
        $array = array('return_code'=>$return_code, 'return_msg' => $return_msg);
        $result = $this->ToXml($array);
        return $result;
    }


    /**
     * 生成xml
     * @param $values
     * @return string
     */
    private static function ToXml($values)
    {
        $xml = "<xml>";
        foreach ($values as $key=>$val)
        {
            if (is_numeric($val) && $key == 'total_fee'){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }


}