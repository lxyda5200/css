<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 15:45
 */

namespace app\user_v2\controller;


use app\common\controller\IhuyiSMS;
use app\user_v2\common\UserLogic;
use think\Config;
use think\Loader;
use think\Db;
use think\Log;

class AliPay
{

    /**
     * 支付宝转账
     * @param $order_no
     * @param $alipay_account
     * @param $money
     * @return bool|mixed|\SimpleXMLElement
     */
    public function transfer($order_no,$alipay_account,$money){
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = Config::get('alipay_config.app_id');
        $aop->rsaPrivateKey = Config::get('alipay_config.rsa_private_key');
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayFundTransToaccountTransferRequest();
        $request->setBizContent("{" .
            "\"out_biz_no\":\"$order_no\"," .
            "\"payee_type\":\"ALIPAY_LOGONID\"," .
            "\"payee_account\":\"$alipay_account\"," .
            "\"amount\":\"$money\"," .
            "\"payer_show_name\":\"\"," .
            "\"payee_real_name\":\"\"," .
            "\"remark\":\"提现\"" .
            "  }");

        $result = $aop->execute ($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $object = $result->$responseNode;
        $result =  json_decode( json_encode($object),true);
        return  $result;
    }



    /**
     * 支付宝退款
     * @param $order_no
     * @param $money
     */
    public function alipay_refund($order_no,$order_id,$money){

        Loader::import('alipay.AopSdk');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = Config::get('alipay_config.app_id');
        $aop->rsaPrivateKey = Config::get('alipay_config.rsa_private_key');
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayTradeRefundRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"$order_no\"," .
            "\"out_request_no\":\"$order_id\"," .
            "\"refund_amount\":\"$money\"," .
            "\"refund_reason\":\"订单退款\"" .
            "  }");
        $result = $aop->execute ( $request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/goods_refund/']);
        Log::write($result->$responseNode);

        if(!empty($resultCode) && $resultCode == 10000){
            return true;
        } else {
            return false;
        }
    }

    /**
     * 支付宝app支付
     * @param $out_trade_no
     * @param $total_amount
     * @param $notify
     * @return string
     */
    public function appPay($out_trade_no,$total_amount,$notify){

        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = Config::get('alipay_config.app_id');
        $aop->rsaPrivateKey = Config::get('alipay_config.rsa_private_key');
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest();
//SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"超神宿APP支付\","
            . "\"subject\": \"超神宿APP支付\","
            . "\"out_trade_no\": \"$out_trade_no\","
            . "\"timeout_express\": \"30m\","
            . "\"total_amount\": \"$total_amount\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";

        $request->setNotifyUrl($notify);
        $request->setBizContent($bizcontent);
//这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
//htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        #dump($response);//就是orderString 可以直接给客户端请求，无需再做处理。
        #die;
        return $response;
    }


    /**
     * 支付宝回调-再次支付
     */
    public function repay_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/repay/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->value('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '支付宝' ){
                        Log::write("订单信息错误");
                        Log::write('---End支付宝回调---');
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
                                    $pt_count = Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->where('order_status',2)->count();
                                    $pt_count = $pt_count+1;
                                    if ($pt_count == $pt_info['pt_size']){
                                        Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>2]);
                                        // 更新订单状态
                                        Db::name('product_order')
                                            ->where('order_no',$out_trade_no)
                                            ->update(['order_status'=>2,'pay_time'=>time()]);
                                        //完成拼团修改为待发货
                                        Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->update(['order_status'=>3]);
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
                            // 更新订单状态
                            Db::name('product_order')
                                ->where('order_no',$out_trade_no)
                                ->update(['order_status'=>3,'pay_time'=>time(),'pay_scene'=>1]);

                            //发送短信提示商家发货
//                            IhuyiSMS::order_code1($store_mobile);
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
                    Log::write('---End支付宝回调---');
                    exit();
                }
                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
            Log::write('---End支付宝回调---');
        }

    }

    /**
     * 支付宝回调-购买商品
     */
    public function goods_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/goods/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('pay_order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->column('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '支付宝' ){
                        Log::write("订单信息错误");
                        Log::write('---End支付宝回调---');
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
                                    Db::name('chaoda_pt_info')->where('id',$orderInfo['pt_id'])->update(['pt_status'=>1]);
                                    Db::name('product_order')
                                        ->where('pay_order_no',$out_trade_no)
                                        ->update(['order_status'=>2,'pay_time'=>time()]);

                                }else{
                                    //是拼团参与
                                    //是否拼团完成
                                    $pt_count = Db::name('product_order')->where('pt_id',$orderInfo['pt_id'])->where('order_status',2)->count();
                                    $pt_count = $pt_count+1;
                                    if ($pt_count == $pt_info['pt_size']){
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
//                            foreach($store_mobile as $v){
//                                if($v)IhuyiSMS::order_code1($v);
//                            }

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
                    }

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
                    Log::write('---End支付宝回调---');
                    exit();
                }
                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
            Log::write('---End支付宝回调---');
        }

    }

    /**
     * 支付宝回调-购买商品
     */
    /*public function goods_alipay_notify2(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/goods/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('product_order')->where('pay_order_no',$out_trade_no)->find();

                    $store_mobile = Db::name('store')->where('id',$orderInfo['store_id'])->value('mobile');

                    if($orderInfo['order_status'] !=1 || $orderInfo['pay_type'] != '支付宝' ){
                        Log::write("订单信息错误");
                        Log::write('---End支付宝回调---');
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
                    Log::write('---End支付宝回调---');
                    exit();
                }
                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
            Log::write('---End支付宝回调---');
        }

    }*/

    /**
     * 支付宝回调-购买会员
     */
    public function member_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/member/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                // 启动事务
                $orderinfo = Db::name('member_order')->where('order_no',$out_trade_no)->find();

                if ($orderinfo['status'] != 1 ){
                    Log::write("订单信息错误");
                    Log::write('---End支付宝回调---');
                    exit();
                }
                // 验证通过后订单处理等逻辑
//                Db::name('member_order')->where('order_no',$out_trade_no)->update(['pay_type'=>'苹果内购','pay_time'=>time(),'status'=>2]);

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
                Log::write("OK");
                Log::write('---End支付宝回调---');
                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }
        } else {
            Log::write('验证失败');
            Log::write('---End支付宝回调---');
        }
    }
    /**
     * 支付宝回调-购买礼包
     */
    public function giftpack_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/giftpack/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                $orderInfo = Db::name('giftpack_order')->where('order_no',$out_trade_no)->find();

                if ($orderInfo['status'] != 1 ){
                    Log::write("订单信息错误");
                    Log::write('---End支付宝回调---');
                    exit();
                }

                $giftpack_info = Db::name('giftpack')->where('id',$orderInfo['giftpack_id'])->find();

                if (!$giftpack_info){
                    Log::write("礼包不存在");
                    Log::write('---End支付宝回调---');
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

                Db::name('giftpack_order')->where('order_no',$out_trade_no)->update(['pay_type'=>'支付宝','pay_time'=>time(),'status'=>2]);

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



                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
            Log::write('---End支付宝回调---');
        }

    }
    
    /**
     * 支付宝回调-长租房
     */
    public function long_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/long/']);
        Log::write('---Begin支付宝回调---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                // 逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('long_order')->where('order_no',$out_trade_no)->find();

                    if ($orderInfo['status'] != 1 || $orderInfo['pay_type'] != '支付宝' ){
                        Log::write("订单信息错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    if ($orderInfo['reserve_money'] != $total_fee){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
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
                    Log::write('---End支付宝回调---');
                    exit();
                }
                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
        }
        Log::write('---End支付宝回调---');
    }


    /**
     * 支付宝回调-短租房
     */
    public function short_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/short/']);
        Log::write('---Begin支付宝回调---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功

            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                //逻辑处理 修改订单状态
                // 启动事务
                Db::startTrans();
                try{
                    $orderInfo = Db::name('short_order')->where('order_no',$out_trade_no)->find();

                    if( $orderInfo['status']!=1 || $orderInfo['pay_type']!='支付宝' ){
                        Log::write("订单信息错误");
                        Log::write('---End支付宝回调---');
                        exit();
                    }

                    if( $orderInfo['pay_money'] != $total_fee ){
                        Log::write("订单金额错误");
                        Log::write('---End支付宝回调---');
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
                    Log::write('---End支付宝回调---');
                    exit();
                }
                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo "success";		//请不要修改或删除
            }else{
                echo "fail";
            }

        } else {
            Log::write('验证失败');
        }
        Log::write('---End支付宝回调---');
    }

    /**
     * 买单支付回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function maidan_alipay_notify(){
        Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/alipay_log/maidan/']);
        Log::write('---Begin支付宝回调日志---');
        Loader::import('alipay.AopSdk');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = Config::get('alipay_config.alipay_rsa_public_key');
        $verify_result = $aop->rsaCheckV1($_POST, NULL, "RSA2");

        if ($verify_result) {//验证成功
            $out_trade_no = $_POST['out_trade_no'];  //商户订单号
            $trade_no = $_POST['trade_no'];  //支付宝交易号
            $trade_status = $_POST['trade_status'];  //交易状态
            $total_fee = $_POST['total_amount'];    //支付宝交易金额

            Log::write('订单号：'.$out_trade_no.',支付宝交易号：'.$trade_no.'已支付金额：'.$total_fee);

            if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                #获取订单信息
                $order_info = Db::name('maidan_order')->where(['order_sn'=>$out_trade_no])->field('id,store_id,status,user_id,price_maidan,price_yj,member_order_id')->find();
                if(!$order_info){
                    Log::write("订单号{$out_trade_no}不存在");
                    Log::write('---End支付宝回调---');
                    exit();
                }
                if($order_info['status'] != 1){
                    Log::write("订单号{$out_trade_no} => 订单状态异常不存在,当前状态【{$order_info['status']}】");
                    Log::write('---End支付宝回调---');
                    exit();
                }
                #修改订单状态,添加订单支付单号
                $data = [
                    'status' => 2,
                    'pay_time' => time()
                ];
                $res = Db::name('maidan_order')->where(['id'=>$order_info['id']])->update($data);
                if($res === false){
                    Log::write("订单号{$out_trade_no} => 订单信息更新失败");
                    Log::write('---End支付宝回调---');
                    exit();
                }

                $userInfo = Db::name('user')->where(['user_id'=>$order_info['user_id']])->field('nickname,mobile,invitation_user_id')->find();

                ##修改购买会员订单的状态
                if($order_info['member_order_id']){
                    $member_order_id = $order_info['member_order_id'];
                    $res = Db::name('member_order')->where(['order_id'=>$member_order_id])->update(['status'=>2,'pay_time'=>time(),'pay_type'=>'支付宝支付']);
                    if($res === false){
                        Log::write("订单号{$out_trade_no} => 会员购买订单信息更新失败");
                        Log::write('---End支付宝回调---');
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

                ##发送短信
                $storeInfo = Db::name('store')->where(['id'=>$order_info['store_id']])->field('mobile')->find();
                //IhuyiSMS::maidan_code($storeInfo['mobile'],$out_trade_no,$userInfo['nickname'],hide_phone($userInfo['mobile']),$order_info['price_yj']);

                Log::write("OK");
                Log::write('---End支付宝回调---');

                echo 'success';
            }else{
                echo 'fail';
            }
        }
    }



}