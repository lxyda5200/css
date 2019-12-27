<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/12/14
 * Time: 11:18
 */

namespace app\wxapi\controller;

use app\common\controller\Base;
use app\wxapi\model\ProductOrder;
use app\wxapi\model\ProductOrderDetail;
use templateMsg\CreateTemplate;
use think\Db;
use app\wxapi\common\User;
use think\Exception;
use think\Log;
use think\response\Json;
use app\wxapi\common\Logic;
use app\wxapi\common\UserLogic;

class Order extends Base
{

    /*
     * {
  "uid": 10006,    //登录uid
  "token": "16ffc2ce49e54bd527dea4b7312e4ba1",
  "is_shopping_cart": 0,   //是否从购物车加入 1是 0否
  "pay_money": "260",      //支付金额
  "shouhuo_username": "1",   //收货人姓名
  "shouhuo_mobile": "1",   //收货人电话
  "shouhuo_address": "1",   //收货人地址
  "coupon_id": "1",    //优惠券id
  "store_info": [             //商户信息
    {
      "store_uid": 10003,    //商户uid
      "product_info": [          //产品信息
        {
          "product_id": 5,        //产品id
          "number": 2,            //数量
          "price": 40,           //成交价格
          "distribution_mode",   //配送方式 1到店自取 2快递
          "freight":0    //运费
        },
        {
          "product_id": 7,
          "number": 1,
          "price": 180,
          "distribution_mode"   //配送方式 1到店自取 2快递
          "freight":0    //运费"
        }
      ]
    }
  ]
}
     * */

    /**
     * 提交订单2
     */
    public function old_submitOrder(){
        try {

            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);

            //token 验证
            $userInfo = User::checkToken($post['user_id'],$post['token']);
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            /*$userId = $userInfo['user_id'];
            if ($userId != 10011) {
                return \json(self::callback(0,'更新中，暂停止下单'),400);
            }*/
            $pay_money = $post['pay_money'];   //支付总金额
            $shouhuo_username = $post['shouhuo_username'] ? trim($post['shouhuo_username']) : '';   //收货人姓名
            $shouhuo_mobile = $post['shouhuo_mobile'] ? trim($post['shouhuo_mobile']) : '';       //收货人电话
            $shouhuo_address = $post['shouhuo_address'] ? trim($post['shouhuo_address']) : '';      //收货地址
            $address_status = isset($post['address_status']) ? intval(input('address_status')) : 1;     //地址状态 1已填写地址 0未填写地址
            $coupon_id = isset($post['coupon_id']) ? intval($post['coupon_id']) : 0 ;     //优惠券id
            $is_shopping_cart = isset($post['is_shopping_cart']) ? intval($post['is_shopping_cart']) : 0 ;   //是否从购物车加入 1是 0否
            $is_group_buy = isset($post['is_group_buy']) ? intval($post['is_group_buy']) : 0 ;    //是否团购商品  1是 0否
            $pt_id = input('pt_id') ? intval(input('pt_id')) : 0 ;   //拼团id

            $store_info = $post['store_info'];   //商品信息

            if ($address_status == 1){
                if (!$shouhuo_address){
                    return \json(self::callback(0,'参数错误'),400);
                }
            }else{
                $shouhuo_address = '';
            }

            if ($pay_money == 0 || !$pay_money){
                return \json(self::callback(0,'下单失败,金额不能为0'));
            }

            $fp = fopen(__DIR__."/lock.txt", "w+");

            if(!flock($fp,LOCK_EX | LOCK_NB)){
                throw new \Exception('系统繁忙，请稍后再试');
            }
            $pay_order_no = build_order_no('C');
            $coupon_money = 0 ; //优惠券金额

            //是否有优惠券
            if ($coupon_id) {
                $coupon_info = Db::name('coupon')->where('id',$coupon_id)->find();
                if (!$coupon_info) {
                    throw new \Exception('优惠券不存在');
                }

                if ($coupon_info['status'] !=1 ){
                    throw new \Exception('优惠已使用');
                }

                if ($coupon_info['expiration_time'] < time()) {
                    throw new \Exception('优惠券已过期');
                }

                /*if ($pay_money < $coupon_info['satisfy_money']) {
                   throw new \Exception('优惠券不符合使用条件');
               }*/

                $coupon_money = $coupon_info['coupon_money'];
            }

            Db::startTrans();

            if ($coupon_id){
                Db::name('coupon')->where('id',$coupon_id)->update(['use_time'=>time(),'status'=>2]);
            }

            $store_number = count($store_info);

            foreach ($store_info as $k=>$v){

                $store = Db::name('store')->where('id',$v['store_id'])->find();
                if (!$store){
                    throw new \Exception('店铺不存在');
                }

                $product_info = $v['product_info'];

                $total_huoli_money = 0; //总获利金额
                $product_total_price = 0;  //商品总价格

                $max_freight = getArrayMax($product_info,'freight');  //获取订单最大值运费

                $total_platform_price = 0 ;
                $total_price = 0 ;

                foreach ($product_info as $k2=>$v2) {
                    $product_specs = Db::name('product_specs')
                        ->join('product','product.id = product_specs.product_id','left')
                        ->join('store','store.id = product.store_id','left')
                        ->field('product_specs.*,product.is_group_buy,product.pt_size,pt_validhours,product.type,product.huoli_money,product.product_type,product.days')
                        ->where('product_specs.id',$v2['specs_id'])
                        ->find();
                    if (!$product_specs) {
                        throw new \Exception('商品不存在');
                    }

                    if ($product_specs['stock'] < $v2['number']) {
                        throw new \Exception('库存不足');
                    }

                    $product_type = $product_specs['product_type'];  //商品类型 1实物类 2虚拟类

                    //减少库存
                    $res = Db::name('product_specs')->where('id',$v2['specs_id'])->setDec('stock',$v2['number']);

                    if (!$res) {
                        throw new \Exception('库存减少失败');
                    }


                    $price = $product_specs['price'] - $product_specs['platform_price'];
                    $is_header = 0;

                    //是否团购商品
                    if ($is_group_buy == 1) {

                        if ($product_specs['is_group_buy'] != 1) {
                            throw new \Exception('该商品不支持团购');
                        }
                        $price = $product_specs['group_buy_price'];
                        $pt_size = $product_specs['pt_size'];


                        //如果是发起拼团
                        if (!$pt_id) {
                            $is_header = 1;
                            $end_time = time() + 60*60*$product_specs['pt_validhours'];
                            //生成用户拼团记录
                            $pt_id = Db::name('user_pt')->insertGetId([
                                'user_id' => $userInfo['user_id'],
                                'store_id' => $v['store_id'],
                                'product_id' => $product_specs['product_id'],
                                'specs_id' => $v2['specs_id'],
                                'end_time' => $end_time,
                                'ypt_size' => 1,
                                'pt_size' => $pt_size,
                                'pt_status' => 0,
                                'create_time' => time()
                            ]);
                        }else {
                            //参与拼团

                            $pt_info = Db::name('user_pt')->where('id',$pt_id)->where('pt_status',1)->find();
                            if (!$pt_info) {
                                throw new \Exception('参与的拼团不存在');
                            }

                            if ($pt_info['end_time'] <= time()) {
                                throw new \Exception('该拼团已结束');
                            }

                            //判断拼团人数是否已满
                            $ypt_num = Db::name('product_order')->where('pt_id',$pt_id)->where('order_status','>=','1')->count();
                            if ($ypt_num >= $product_specs['pt_size']) {
                                throw new \Exception('拼团人数已满');
                            }

                            if(Db::name('product_order')->where('user_id',$userInfo['user_id'])->where('pt_id',$pt_id)->count()) {
                                throw new \Exception('已参与当前拼团');
                            }

                            //增加已参与拼团人数  提交订单后增加
                            Db::name('user_pt')->where('id',$pt_id)->setInc('ypt_size',1);
                        }
                    }

                    //清空购物车
                    if ($is_shopping_cart == 1){
                        Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$v2['specs_id'])->delete();
                    }

                    $product_total_price += $price * $v2['number'];

                    $total_price += ($price+$product_specs['platform_price']) * $v2['number'];  //商品总价 = 单价 * 数量



                    $product_info[$k2]['order_id'] = &$order_id;
                    $product_info[$k2]['product_id'] = $product_specs['product_id'];
                    $product_info[$k2]['specs_id'] = $v2['specs_id'];
                    $product_info[$k2]['cover'] = $product_specs['cover'];
                    $product_info[$k2]['product_name'] = $product_specs['product_name'];
                    $product_info[$k2]['product_specs'] = $product_specs['product_specs'];
                    $product_info[$k2]['number'] = $v2['number'];
                    $product_info[$k2]['price'] = $price;
                    $product_info[$k2]['platform_price'] = $product_specs['platform_price'];
                    $product_info[$k2]['freight'] = $v2['freight'];
                    $product_info[$k2]['type'] = $product_specs['type'];
                    $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];
                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                }
                $order_no = build_order_no('W');
                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);   //拆分订单支付金额 = 商家商品总价格 + 运费 - (优惠券/商家数量)
                $total_price = $total_price < 0 ? 0 : $total_price;
                if ($pay_money == 0.01){
                    $total_price = 0.01;
                }
                $platform_profit_bili = $store['platform_ticheng'];
                $platform_profit = ($product_total_price-$total_huoli_money) * ($platform_profit_bili/100); //(商品总价格-总代购金额) * 平台收益比例

                $order_id = Db::name('product_order')->insertGetId([
                    'user_id' => $post['user_id'],
                    'order_no' => $order_no,
                    'pay_order_no' => $pay_order_no,
                    'store_id' => $v['store_id'],
                    'pay_money' => $total_price,
                    'shouhuo_username' => $shouhuo_username,
                    'shouhuo_mobile' => $shouhuo_mobile,
                    'shouhuo_address' => $shouhuo_address,
                    'address_status' => $address_status,
                    'is_group_buy' => $is_group_buy ,
                    'pt_id' => $pt_id,
                    'is_header' => $is_header,
                    'coupon_id' => $coupon_id,
                    'coupon_money' => round(($coupon_money/$store_number)),
                    'total_freight' => $max_freight,
                    'platform_profit' => $platform_profit,
                    'distribution_mode' => $v['distribution_mode'],
                    'order_status' => 1,
                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
                    'total_platform_price' => $total_platform_price,
                    'create_time' => time()
                ]);
                $order_id = intval($order_id);
                if (!$order_id){
                    Db::rollback();
                    return \json(self::callback(0,'操作失败1'));
                }
                $result = Db::name('product_order_detail')->strict(false)->insertAll($product_info);
                if (!$result){
                    Db::rollback();
                    return \json(self::callback(0,'操作失败2'));
                }
            }

            /*if (bccomp($pay_money,$total_price,2) != 0 ) {
                //日志记录

                Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/log2/debug/']);
                Log::write('支付金额1:'.$pay_money);
                Log::write('支付金额2:'.$total_price);
                Db::rollback();
                throw new \Exception('订单金额错误');
            }*/

            flock($fp,LOCK_UN);//释放锁
            Db::commit();

            fclose($fp);




            return \json(self::callback(1,'',['order_id'=>$order_id,'pay_order_no'=>$pay_order_no]));

        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 提交订单
     */
    public function submitOrder3(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //获取并查询用户信息
            $user_id=$post['user_id'];
            if(!$user_id){
                return \json(self::callback(0,'参数错误'));
            }
            //token 验证
            $userInfo = User::checkToken($post['user_id'],$post['token']);
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $pay_money = $post['pay_money'];   //支付总金额
            $shouhuo_username = $post['shouhuo_username'] ? trim($post['shouhuo_username']) : '';   //收货人姓名
            $shouhuo_mobile = $post['shouhuo_mobile'] ? trim($post['shouhuo_mobile']) : '';       //收货人电话
            $address_status = isset($post['address_status']) ? intval(input('address_status')) : 1;     //地址状态 1已填写地址 0未填写地址
            $shouhuo_address = $post['shouhuo_address'] ? trim($post['shouhuo_address']) : '';      //收货地址
            $coupon_id = isset($post['coupon_id']) ? intval($post['coupon_id']) : 0 ;     //优惠券id
            $is_shopping_cart = isset($post['is_shopping_cart']) ? intval($post['is_shopping_cart']) : 0 ;   //是否从购物车加入 1是 0否
            $is_group_buy = isset($post['is_group_buy']) ? intval($post['is_group_buy']) : 0 ;    //是否团购商品  1是 0否
            $pt_type = isset($post['pt_type']) ? intval($post['pt_type']) : 0 ;   //拼团类型 0普通拼团 1潮搭拼团
            $chaoda_id = isset($post['chaoda_id']) ? intval($post['chaoda_id']) : 0 ;   //潮搭id  非潮搭拼团则传0
            $pt_id = $post['pt_id'] ? intval($post['pt_id']) : 0 ;  //拼团id
            $store_info = $post['store_info'];   //商品信息

            if ($address_status == 1 && !$shouhuo_address) {
                return \json(self::callback(0,'收货地址不能为空'));
            }

            if ($pay_money <0 || !$pay_money) {
                return \json(self::callback(0,'下单失败,支付金额错误'));
            }

            $fp = fopen(__DIR__."/lock.txt", "w+");
            if(!flock($fp,LOCK_EX | LOCK_NB)){
                return \json(self::callback(0,'系统繁忙，请稍后再试'));
            }

            $pay_order_no = build_order_no('C');   //生成支付订单号

//            $coupon_money = 0 ; //优惠券金额
//            //是否有优惠券
//            if ($coupon_id) {
//                $coupon_info = Db::name('coupon')->where('id',$coupon_id)->find();
//                if (!$coupon_info) {
//                    return \json(self::callback(0,'优惠券不存在'));
//                }
//
//                if ($coupon_info['status'] !=1 ){
//                    return \json(self::callback(0,'优惠已使用'));
//                }
//
//                if ($coupon_info['expiration_time'] < time()) {
//                    return \json(self::callback(0,'优惠券已过期'));
//                }
//
//                $coupon_money = $coupon_info['coupon_money'];
//            }

            Db::startTrans();

            //修改优惠券使用状态
//            if ($coupon_id){
//                Db::name('coupon')->where('id',$coupon_id)->update(['use_time'=>time(),'status'=>2]);
//            }

            $store_number = count($store_info);   //店铺数量
$summoney=0;//定义总金额
            foreach ($store_info as $k=>$v){

                $store = Db::name('store')->where('id',$v['store_id'])->find();
                if (!$store){
                    Db::rollback();
                    return \json(self::callback(0,'店铺不存在'));
                }

                $product_info = $v['product_info'];

                $total_huoli_money = 0;   //总获利金额
                $product_total_price = 0;   //商品总价格

                $max_freight = getArrayMax($product_info,'freight');  //获取订单最大值运费

                $total_platform_price = 0 ;   //平台总提成
                $total_price = 0 ;  //支付金额

                foreach ($product_info as $k2=>$v2) {
                    $product_specs = Db::name('product_specs')
                        ->join('product','product.id = product_specs.product_id','left')
                        ->join('store','store.id = product.store_id','left')
                        ->field('product_specs.*,product.is_group_buy,product.pt_size,pt_validhours,product.type,product.huoli_money,product.product_type,product.days')
                        ->where('product_specs.id',$v2['specs_id'])
                        ->find();

                    if (!$product_specs) {
                        Db::rollback();
                        return \json(self::callback(0,'商品不存在'));
                    }

                    if ($product_specs['stock'] < $v2['number']) {
                        Db::rollback();
                        return \json(self::callback(0,$product_specs['product_name'].'库存不足'));
                    }

                    $product_type = $product_specs['product_type'];  //商品类型 1实物类 2虚拟类

                    $price = $product_specs['price'] - $product_specs['platform_price'];  //单价减去平台加价

                    //非拼团潮搭商品
                    if ($is_group_buy == 0 && $chaoda_id != 0){
                        $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                    }

                    //拼团商品处理
                    if ($is_group_buy == 1) {
                        switch ($pt_type){
                            case 0:
                                /**************************************普通拼团**************************************/
                                if ($product_specs['is_group_buy'] != 1) {
                                    Db::rollback();
                                    return \json(self::callback(0,'该商品不支持团购'));
                                }
                                $price = $product_specs['group_buy_price'];
                                $pt_size = $product_specs['pt_size'];

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $product_specs['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id = Db::name('user_pt')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'end_time' => $end_time,
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);
                                }else {
                                    //参与拼团
                                    $pt_info = Db::name('user_pt')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        Db::rollback();
                                        return \json(self::callback(0,'参与的拼团不存在'));
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        Db::rollback();
                                        return \json(self::callback(0,'该拼团已结束'));

                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();
                                    if ($ypt_num >= $product_specs['pt_size']) {
                                        Db::rollback();
                                        return \json(self::callback(0,'拼团人数已满'));
                                    }

                                    if(Db::name('product_order')
                                        ->where('user_id',$userInfo['user_id'])
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->count()) {
                                        Db::rollback();
                                        return \json(self::callback(0,'已参与当前拼团'));
                                    }

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('user_pt')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            case 1:
                                /**************************************潮搭拼团***************************************/

                                if (!$chaoda_id) {
                                    return \json(self::callback(0,'潮搭拼团参数错误'));
                                }

                                $chaoda_info = Db::name('chaoda')->where('id',$chaoda_id)->where('is_delete',0)->find();
                                $chaoda_tag_info = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->column('product_id');
                                if (!$chaoda_info) {
                                    return \json(self::callback(0,'潮搭不存在'));
                                }
                                $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                                $pt_size = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->count();

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $chaoda_info['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id = Db::name('chaoda_pt_info')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'chaoda_id' => $chaoda_id,
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'end_time' => $end_time,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);

                                    foreach ($chaoda_tag_info as $k3=>$v3){
                                        $pt_product_info[$k3]['product_id'] = $v3;
                                        $pt_product_info[$k3]['pt_id'] = $pt_id;
                                        $chaoda_price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$v3)->value('price');
                                        $pt_product_info[$k3]['price'] = $chaoda_price;
                                        $pt_product_info[$k3]['status'] = 0;
                                    }

                                    Db::name('chaoda_pt_product_info')->insertAll($pt_product_info);
                                }else{
                                    //参与拼团
                                    $pt_info = Db::name('chaoda_pt_info')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        Db::rollback();
                                        return \json(self::callback(0,'参与的拼团不存在'));
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        Db::rollback();
                                        return \json(self::callback(0,'该拼团已结束'));
                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();

                                    if ($ypt_num >= $pt_size) {
                                        Db::rollback();
                                        return \json(self::callback(0,'拼团人数已满'));
                                    }

                                    /*if(Db::name('product_order')
                                        ->where('user_id',$userInfo['user_id'])
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',1)
                                        ->where('pt_id',$pt_id)
                                        ->count()) {
                                        Db::rollback();
                                        return \json(self::callback(0,'已参与当前拼团，请到订单列表查看'));
                                    }*/

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('chaoda_pt_info')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            default:
                                Db::rollback();
                                return \json(self::callback('pt_type参数值错误'));
                                break;
                        }
                    }

                    //减少库存
                    if (!Db::name('product_specs')->where('id',$v2['specs_id'])->setDec('stock',$v2['number'])){
                        Db::rollback();
                        return \json(self::callback(0,'库存减少失败'));
                    }

                    //清空购物车
                    if ($is_shopping_cart == 1){
                        Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$v2['specs_id'])->delete();
                    }

                    $product_total_price += $price * $v2['number'];   //商品的总价格
                    $total_price += ($price + $product_specs['platform_price']) * $v2['number'];  //商品总价 = 单价 * 数量

                    $product_info[$k2]['order_id'] = &$order_id;
                    $product_info[$k2]['product_id'] = $product_specs['product_id'];
                    $product_info[$k2]['specs_id'] = $v2['specs_id'];
                    $product_info[$k2]['cover'] = $product_specs['cover'];
                    $product_info[$k2]['product_name'] = $product_specs['product_name'];
                    $product_info[$k2]['product_specs'] = $product_specs['product_specs'];
                    $product_info[$k2]['number'] = $v2['number'];
                    $product_info[$k2]['price'] = $price;
                    $product_info[$k2]['platform_price'] = $product_specs['platform_price'];
                    $product_info[$k2]['freight'] = $v2['freight'];
                    $product_info[$k2]['type'] = $product_specs['type'];

                    //判断是不是会员用户
                    if($userInfo['type']==2){
                        $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    }else{
                        $product_info[$k2]['huoli_money'] =0;
                    }
                    //----结束

//                    $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];

                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                }
                $order_no = build_order_no('W');
                //拆分订单 单笔订单支付金额计算  商家商品总价格 + 运费 - (优惠券/商家数量)
//                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);
                //支付金额最低一分钱
//                if ($pay_money <= 0.01){
//                    $total_price = 0.01;
//                }
                if ($total_price < 0.01){
                    $total_price = 0.01;
               }
                $platform_profit_bili = $store['platform_ticheng'];  //店铺提成比例
                $platform_profit = ($product_total_price-$total_huoli_money) * ($platform_profit_bili/100); //(商品总价格-总代购金额) * 平台收益比例

                if(!empty($chaoda_id)){
                    $total_price = $pay_money;
                }
                $total_price2 = $total_price;//未享受优惠的时候的总价
//--------------------------
                //每个店铺选择一张优惠券
                //是否有优惠券
                if (isset($v['coupon_id']) && $v['coupon_id']>0) {

//                    $coupon_info = Db::name('css_coupon')->where('id',$coupon_id)->find();
                    $coupon_info = Db::view('coupon','id,user_id,coupon_id,status,create_time,expiration_time')
                        ->view('coupon_rule','store_id,start_time,end_time,type,satisfy_money,coupon_money,coupon_type','coupon.coupon_id = coupon_rule.id','left')
                        ->where('coupon.id',$v['coupon_id'])
                        ->where('coupon.user_id',$userInfo['user_id'])
                        ->find();
                    if (!$coupon_info) {
                        return \json(self::callback(0,'优惠券不存在'));
                    }
                    if ($coupon_info['status'] !=1 ){
                        return \json(self::callback(0,'优惠已使用'));
                    }
                    if ($coupon_info['expiration_time'] < time() || $coupon_info['expiration_time'] < time()) {
                        return \json(self::callback(0,'优惠券已过期'));
                    }

                    if ($coupon_info['satisfy_money'] > $total_price ) {
                        return \json(self::callback(0,'订单金额不能小于满减优惠券满减金额'));
                    }

                    $total_price= $total_price-$coupon_info['coupon_money'];
                    if($total_price<=0){
                        $total_price3 = 0;
                    }else{
                        $total_price3=$total_price;
                    }
                    $coupon_money= $coupon_info['coupon_money'];
                    $coupon_id=$coupon_info['coupon_id'];
                    $user_css_coupon_id=$coupon_info['id'];
                    //修改优惠券使用状态
                    Db::name('coupon')->where('id',$coupon_info['id'])->update(['use_time'=>time(),'status'=>2]);

                }else{
                    //如果没有优惠券
                    $coupon_money= 0;
                    $coupon_id=0;
                    $user_css_coupon_id=0;
                    $total_price3=$total_price;
                }
                //--------------------------------------
                $total_price = $total_price + $max_freight;//加运费
                if($total_price3==0){
                    $total_price = $total_price3 + $max_freight;//只有运费
                }

                //如果订单总价等于0
                if($total_price==0 ){
                    $total_price = 0.01;
                }

                //生成订单数据
                $order_id = Db::name('product_order')->insertGetId([
                    'user_id' => $post['user_id'],
                    'order_no' => $order_no,
                    'pay_order_no' => $pay_order_no,
                    'store_id' => $v['store_id'],
                    'pay_money' => $total_price,
                    'shouhuo_username' => $shouhuo_username,
                    'shouhuo_mobile' => $shouhuo_mobile,
                    'shouhuo_address' => $shouhuo_address,
                    'address_status' => $address_status,
                    'is_group_buy' => $is_group_buy ,
                    'pt_type' => $pt_type,  //拼团类型 0 普通拼团 1 潮搭拼团
                    'pt_id' => $pt_id,
                    'is_header' => isset($is_header) ? $is_header : 0 ,
                    'coupon_id' => $coupon_id,
//                    'coupon_money' => round(($coupon_money/$store_number)),
                    'coupon_money' => $coupon_money,
                    'total_freight' => $max_freight,
                    'platform_profit' => $platform_profit,
                    'distribution_mode' => $v['distribution_mode'],
                    'order_status' => 1,
                    'user_css_coupon_id' => $user_css_coupon_id ,
                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
                    'total_platform_price' => $total_platform_price,
                    'chaoda_id' => $chaoda_id,
                    'create_time' => time(),
                    'operate_time' => time()
                ]);

                //-------------判断是否有优惠券 如果有按照等比例计算实际付款价格
                if($coupon_id>0 && $coupon_money>0 && $user_css_coupon_id>0){
                    if($total_price3==0){
                    }else{
                        $paysmoney=$total_price-$max_freight;
                        $product_legth=count($product_info);
                        if($product_legth>1){
                            //多个商品
                            if(is_array($product_info)){
                                $moneys=0;
                                for ($x=0; $x<$product_legth-1; $x++) {
                                    $product_info[$x]['realpay_money']=($product_info[$x]['price']*$product_info[$x]['number'])/$total_price2*$paysmoney;
                                    $moneys+=$product_info[$x]['realpay_money'];
                                }
                                //计算最后一个商品总价-前面综合=剩余价格
                                $product_info[$product_legth-1]['realpay_money']=$paysmoney-$moneys;
                            }
                        }else{
                            //单商品
                            $product_info[0]['realpay_money']=$paysmoney;
                        }
                    }
                }
                //--------------优惠券计算结束

                $order_id = intval($order_id);

                $result = Db::name('product_order_detail')->strict(false)->insertAll($product_info);

                if (!$order_id || !$result){
                    Db::rollback();
                    return \json(self::callback(0,'下单操作失败'));
                }

            }

            flock($fp,LOCK_UN);//释放锁
            Db::commit();

            fclose($fp);

            //-----------------------调起支付开始
            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->find();
//            $order = $orderModel->where('pay_order_no',$pay_order_no)->find();
            $data['order_id'] = $order->id;
            $data['pay_order_no'] = $order->pay_order_no;
            $pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');

            if (!$order){
                throw new \Exception('订单不存在');
            }
            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }

            //************************************************
            //定义回调地址
            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
            $openid=$userInfo['wx_openid'];
            $wxPay = new WxPay();
            $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money,$notify_url,$openid);
            $pay_type = "微信小程序";
            $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            return \json(self::callback(1,'签名成功',$data));

//-----------------------------------结束

//这一行为之前的            return \json(self::callback(1,'',['order_id'=>$order_id,'pay_order_no'=>$pay_order_no]));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 提交订单
     */
    public function submitOrder_old201999(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
//            Log::info(print_r($post,true));
            $pay_money = $post['pay_money'];   //支付总金额
            $shouhuo_username = $post['shouhuo_username'] ? trim($post['shouhuo_username']) : '';   //收货人姓名
            $shouhuo_mobile = $post['shouhuo_mobile'] ? trim($post['shouhuo_mobile']) : '';       //收货人电话
            $address_status = isset($post['address_status']) ? intval(input('address_status')) : 1;     //地址状态 1已填写地址 0未填写地址
            $shouhuo_address = $post['shouhuo_address'] ? trim($post['shouhuo_address']) : '';      //收货地址
            $coupon_id = isset($post['coupon_id']) ? intval($post['coupon_id']) : 0 ;     //平台优惠券id
            $is_shopping_cart = isset($post['is_shopping_cart']) ? intval($post['is_shopping_cart']) : 0 ;   //是否从购物车加入 1是 0否
            $is_group_buy = isset($post['is_group_buy']) ? intval($post['is_group_buy']) : 0 ;    //是否团购商品  1是 0否
            $pt_type = isset($post['pt_type']) ? intval($post['pt_type']) : 0 ;   //拼团类型 0普通拼团 1潮搭拼团
            $chaoda_id = isset($post['chaoda_id']) ? intval($post['chaoda_id']) : 0 ;   //潮搭id  非潮搭拼团则传0
            $pt_id = $post['pt_id'] ? intval($post['pt_id']) : 0 ;  //拼团id
            $store_info = $post['store_info'];   //商品信息

            if ($address_status == 1 && !$shouhuo_address) {
                return \json(self::callback(0,'收货地址不能为空'));
            }
            if ($pay_money <= 0 || !$pay_money) {
                return \json(self::callback(0,'下单失败,支付金额错误'));
            }

            $fp = fopen(__DIR__."/lock.txt", "w+");
            if(!flock($fp,LOCK_EX | LOCK_NB)){
                return \json(self::callback(0,'系统繁忙，请稍后再试'));
            }
            $pay_order_no = build_order_no('C');   //生成支付订单号
            $data['pay_order_no'] = $pay_order_no;

            Db::startTrans();
            $coupon_data = [];
            $store_coupon_ids = [];

            foreach ($store_info as $k=>$v){
                //查询是否有店铺优惠券
                if(isset($v['store_coupon_id']) && $v['store_coupon_id'])$store_coupon_ids[] = $v['store_coupon_id'];

                $store = Db::name('store')->where('id',$v['store_id'])->find();
                if (!$store){
                    throw new Exception('店铺不存在');
                }
                //查询是否有商品id有则是商品优惠券
                if(isset($v['product_id']) && $v['product_id']){
                    $product = Db::name('product')->where('id',$v['product_id'])->where('status',1)->find();
                    if (!$product){
                        throw new Exception('商品不存在或已下架');
                    }

                }


                $product_info = $v['product_info'];
                $total_huoli_money = 0;   //总获利金额
                $product_total_price = 0;   //商品总价格
                $max_freight = getArrayMax2($product_info,'freight',$v['distribution_mode']);  //获取订单最大值运费

                //Log::info($v['distribution_mode'].'==========='.$max_freight);

                $total_platform_price = 0 ;   //平台总提成
                $total_price = 0 ;  //支付金额

                foreach ($product_info as $k2=>$v2) {
                    $product_specs = Db::name('product_specs')
                        ->join('product','product.id = product_specs.product_id','left')
                        ->join('store','store.id = product.store_id','left')
                        ->field('product_specs.*,product.is_group_buy,product.pt_size,pt_validhours,product.type,product.huoli_money,product.product_type,product.days')
                        ->where('product_specs.id',$v2['specs_id'])
                        ->find();

                    if (!$product_specs) {
                        throw new Exception('商品不存在');
                    }

                    if ($product_specs['stock'] < $v2['number']) {
                        throw new Exception('库存不足');
                    }

                    $product_type = $product_specs['product_type'];  //商品类型 1实物类 2虚拟类

//                    $price = $product_specs['price'] - $product_specs['platform_price'];  //单价减去平台加价
                    $price = $product_specs['price'];  //单价减去平台加价

                    //非拼团潮搭商品
                    if ($is_group_buy == 0 && $chaoda_id != 0){
                        $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                    }

                    //拼团商品处理
                    if ($is_group_buy == 1) {
                        switch ($pt_type){
                            case 0:
                                /**************************************普通拼团**************************************/
                                if ($product_specs['is_group_buy'] != 1) {
                                    throw new Exception('该商品不支持团购');
                                }
                                $price = $product_specs['group_buy_price'];
                                $pt_size = $product_specs['pt_size'];

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $product_specs['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id = Db::name('user_pt')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'end_time' => $end_time,
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);
                                }else {
                                    //参与拼团
                                    $pt_info = Db::name('user_pt')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        throw new Exception('参与的拼团不存在');
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        throw new Exception('该拼团已结束');
                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();
                                    if ($ypt_num >= $product_specs['pt_size']) {
                                        throw new Exception('拼团人数已满');
                                    }

                                    if(Db::name('product_order')
                                        ->where('user_id',$userInfo['user_id'])
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->count()) {
                                        throw new Exception('已参与当前拼团');
                                    }

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('user_pt')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            case 1:
                                /**************************************潮搭拼团***************************************/

                                if (!$chaoda_id) {
                                    return \json(self::callback(0,'潮搭拼团参数错误'));
                                }

                                $chaoda_info = Db::name('chaoda')->where('id',$chaoda_id)->where('is_delete',0)->find();
                                $chaoda_tag_info = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->column('product_id');
                                if (!$chaoda_info) {
                                    return \json(self::callback(0,'潮搭不存在'));
                                }
                                $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                                $pt_size = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->count();

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $chaoda_info['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id2 = Db::name('chaoda_pt_info')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'chaoda_id' => $chaoda_id,
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'end_time' => $end_time,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);
                                    foreach ($chaoda_tag_info as $k3=>$v3){
                                        $pt_product_info[$k3]['product_id'] = $v3;
                                        $pt_product_info[$k3]['pt_id'] = $pt_id2;
                                        $chaoda_price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$v3)->value('price');
                                        $price = $pt_product_info[$k3]['price'] = $chaoda_price;
                                        $pt_product_info[$k3]['status'] = 0;
                                    }

                                    Db::name('chaoda_pt_product_info')->insertAll($pt_product_info);
                                }else{
                                    //参与拼团
                                    $pt_info = Db::name('chaoda_pt_info')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        throw new Exception('参与的拼团不存在');
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        throw new Exception('该拼团已结束');
                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();

                                    if ($ypt_num >= $pt_size) {
                                        throw new Exception('拼团人数已满');
                                    }

                                    /*if(Db::name('product_order')
                                        ->where('user_id',$userInfo['user_id'])
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',1)
                                        ->where('pt_id',$pt_id)
                                        ->count()) {
                                        Db::rollback();
                                        return \json(self::callback(0,'已参与当前拼团，请到订单列表查看'));
                                    }*/

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('chaoda_pt_info')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            default:
                                throw new Exception('pt_type参数值错误');
                                break;
                        }
                    }

                    //清空购物车
                    if ($is_shopping_cart == 1){
                        Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$v2['specs_id'])->delete();
                    }

                    $product_total_price += $price * $v2['number'];   //商品的总价格
//                    $total_price += ($price + $product_specs['platform_price']) * $v2['number'];  //商品总价 = 单价 * 数量
                    $total_price += $price * $v2['number'];  //商品总价 = 单价 * 数量

                    $product_info[$k2]['order_id'] = &$order_id;
                    $product_info[$k2]['product_id'] = $product_specs['product_id'];
                    $product_info[$k2]['specs_id'] = $v2['specs_id'];
                    $product_info[$k2]['cover'] = $product_specs['cover'];
                    $product_info[$k2]['product_name'] = $product_specs['product_name'];
                    $product_info[$k2]['product_specs'] = $product_specs['product_specs'];
                    $product_info[$k2]['number'] = $v2['number'];
                    $product_info[$k2]['price'] = $price;
                    $product_info[$k2]['platform_price'] = $product_specs['platform_price'];
                    $product_info[$k2]['freight'] = $product_specs['freight'];
                    $product_info[$k2]['type'] = $product_specs['type'];
                    $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];
                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                    $coupon_data[$v['store_id']]['product'][] = $product_info[$k2];
                } //商品循环结束
                $order_no = build_order_no('W');
                //拆分订单 单笔订单支付金额计算  商家商品总价格 + 运费 - (优惠券/商家数量)
//                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);
                //支付金额最低一分钱
//                if ($pay_money <= 0.01){
//                    $total_price = 0.01;
//                }
                $platform_profit_bili = $store['platform_ticheng'];  //店铺提成比例
                $platform_profit = ($product_total_price-$total_huoli_money) * ($platform_profit_bili/100); //(商品总价格-总代购金额) * 平台收益比例

//                if(!empty($chaoda_id)){
//                    $total_price = $pay_money;
//                }

                //生成订单数据
//                $order_id = Db::name('product_order')->insertGetId([
//                    'user_id' => $post['user_id'],
//                    'order_no' => $order_no,
//                    'pay_order_no' => $pay_order_no,
//                    'store_id' => $v['store_id'],
//                    'pay_money' => $total_price,
//                    'shouhuo_username' => $shouhuo_username,
//                    'shouhuo_mobile' => $shouhuo_mobile,
//                    'shouhuo_address' => $shouhuo_address,
//                    'address_status' => $address_status,
//                    'is_group_buy' => $is_group_buy ,
//                    'pt_type' => $pt_type,  //拼团类型 0 普通拼团 1 潮搭拼团
//                    'pt_id' => $pt_id,
//                    'is_header' => isset($is_header) ? $is_header : 0 ,
//                    'coupon_id' => $coupon_id,
//                    'coupon_money' => round(($coupon_money/$store_number)),
//                    'total_freight' => $max_freight,
//                    'platform_profit' => $platform_profit,
//                    'distribution_mode' => $v['distribution_mode'],
//                    'order_status' => 1,
//                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
//                    'total_platform_price' => $total_platform_price,
//                    'chaoda_id' => $chaoda_id,
//                    'create_time' => time()
//                ]);
                $coupon_data[$v['store_id']]['store_coupon_id'] = $v['store_coupon_id']?:0;
                $coupon_data[$v['store_id']]['product_id'] = $v['product_id']?:0;
                $coupon_data[$v['store_id']]['data'] = [
                    'user_id' => $post['user_id'],
                    'store_coupon_id' => $v['store_coupon_id']?:0,
                    'product_id' => $v['product_id']?:0,
                    'order_no' => $order_no,
                    'pay_order_no' => $pay_order_no,
                    'store_id' => $v['store_id'],
                    'pay_money' => $total_price,
                    'shouhuo_username' => $shouhuo_username,
                    'shouhuo_mobile' => $shouhuo_mobile,
                    'shouhuo_address' => $shouhuo_address,
                    'address_status' => $address_status,
                    'is_group_buy' => $is_group_buy ,
                    'pt_type' => $pt_type,  //拼团类型 0 普通拼团 1 潮搭拼团
                    'pt_id' => $pt_id,
                    'is_header' => isset($is_header) ? $is_header : 0 ,
                    'coupon_id' => $coupon_id,
//                    'coupon_money' => round(($coupon_money/$store_number)),
                    'total_freight' => $max_freight,
                    'platform_profit' => $platform_profit,
                    'distribution_mode' => $v['distribution_mode'],
                    'order_status' => 1,
                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
                    'total_platform_price' => $total_platform_price,
                    'chaoda_id' => $chaoda_id,
                    'create_time' => time()
                ];

//                $order_id = intval($order_id);
//                $result = Db::name('product_order_detail')->strict(false)->insertAll($product_info);
//                if (!$order_id || !$result){
//                    Db::rollback();
//                    return \json(self::callback(0,'下单操作失败'));
//                }
            }//店铺循环结束

            ##判断优惠券的叠加
            if($coupon_id && (!empty($store_coupon_ids) || !empty($product_coupon_ids))){
                $is_superposition_pt = Logic::getCouponSuperpositionAndExpireTime($coupon_id);
                if(!$is_superposition_pt || $is_superposition_pt == 1)throw new Exception('该平台优惠券已不能使用');
                $store_coupon_count = Logic::getNotSuperpositionCoupon($store_coupon_ids);
                if($store_coupon_count < count($store_coupon_ids))throw new Exception('当前店铺优惠券已不可使用');
            }

            $last_money_tt = 0;  //剩余价格总价

            $pay_money_tt = 0 ; //支付总价格(产品总价 + 运费 - 优惠券)

            foreach($coupon_data as $k => &$v){
                ##第一次均摊(店铺券均摊)
                $store = $v['data'];
//                print_r($store);die;
                $price_tt = $store['pay_money'];
                $product_id = $store['product_id'];
                $store_id = $store['store_id'];

                $coupon_info = Logic::getCouponPrice2($store['store_coupon_id'],$product_id,$store_id);

                if(!$coupon_info['type'] && !$coupon_info['coupon_money'] && $store['store_coupon_id'])throw new Exception('当前店铺或商品优惠券已不可使用');
                if($coupon_info['type']==3 ){

                    //商品优惠券
                    $rest_coupon_money = $coupon_info['coupon_money'];   //剩余优惠券金额
                    foreach($v['product'] as $kk => &$vv){
                        if($vv['product_id']==$coupon_info['product_id']){
                            //判断商品金额与优惠券金额
                            $price_t = $vv['number'] * $vv['price'];  //该产品总价格
                            if($price_t < $coupon_info['coupon_money']){  //优惠券金额大于订单总价
                                $vv['realpay_money'] = 0;
                                $vv['store_coupon_money'] = $price_t;
                            }else{
                                $vv['store_coupon_money'] = $rest_coupon_money;
                                $vv['realpay_money'] = round($price_t - $rest_coupon_money,2);
                                }
                            $v['data']['pay_money'] = round($price_tt - $vv['store_coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                            $v['data']['store_coupon_money'] = $vv['store_coupon_money'];
                            $v['last_money'] = $price_tt - $vv['store_coupon_money'];   //除去运费的实际支付金额

                        }else{
                            $vv['realpay_money'] = $vv['number'] * $vv['price'];
                            $vv['store_coupon_money'] = 0;
                        }
                     }

                    $last_money_tt += $v['last_money'];
                    $pay_money_tt += $v['data']['pay_money'];

                } elseif ($coupon_info['type']==2){

                    if(!$coupon_info['coupon_money'] && $store['store_coupon_id'])throw new Exception('当前店铺优惠券已不可使用');

                    if($price_tt < $coupon_info['satisfy_money'] || $price_tt <=$coupon_info['coupon_money'])throw new Exception('店铺优惠券不满足使用金额');
                    ##如果优惠券金额大于本单支付总金额
                    if($price_tt < $coupon_info['coupon_money']){
                        $v['data']['store_coupon_money'] = $price_tt;
                        $v['data']['pay_money'] = $v['data']['total_freight'];  //加上运费的金额
                        $v['last_money'] = 0;   //除去运费的实际支付金额
                    }else{
                        ##店铺第一次均摊实付金额
                        $v['data']['pay_money'] = round($price_tt - $coupon_info['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                        $v['data']['store_coupon_money'] = $coupon_info['coupon_money'];
                        $v['last_money'] = $price_tt - $coupon_info['coupon_money'];   //除去运费的实际支付金额
                    }
                    $last_money_tt += $v['last_money'];  //剩余的所有店铺总价
                    $pay_money_tt += $v['data']['pay_money'];  //包含运费的实付总价
                    //Log::info($pay_money_tt.'=>2691');

                    $num = 1;
                    $rest_coupon_money = $coupon_info['coupon_money'];   //剩余优惠券金额
                    foreach($v['product'] as $kk => &$vv){
                        $price_t = $vv['number'] * $vv['price'];  //该产品总价格
                        if($price_tt < $coupon_info['coupon_money']){  //优惠券金额大于订单总价
                            $vv['realpay_money'] = 0;
                            $vv['store_coupon_money'] = $price_t;
                        }else{
                            if(count($v['product']) <= $num){  ##最后一个产品
                                $vv['store_coupon_money'] = $rest_coupon_money;
                                $vv['realpay_money'] = round($price_t - $rest_coupon_money,2);
                            }else{  ##前几个产品
                                $coupon_pay = round(($price_t/$price_tt) * $coupon_info['coupon_money'],2);
//                            Log::info("price_t=>" . $price_t);
//                            Log::info("price_tt=>" . $price_tt);
//                            Log::info("coupon_money=>" . $coupon_info['coupon_money']);
                                $vv['realpay_money'] = floor(($price_t - $coupon_pay)*100)/100;
                                $vv['store_coupon_money'] = $coupon_pay;
                                $rest_coupon_money -= $coupon_pay;

                            }
                        }
                        $num ++;
                    }

                }else{

                    foreach($v['product'] as $kk => &$vv){
                        $vv['realpay_money'] = $vv['number'] * $vv['price'];
                        $vv['store_coupon_money'] = 0;
                    }
                    $v['last_money'] = round($price_tt ,2);  //加上运费的金额

                    $v['data']['pay_money'] = round($price_tt + $v['data']['total_freight'],2);  //加上运费的金额
                    $last_money_tt += $price_tt;
                    $pay_money_tt += round($price_tt  + $v['data']['total_freight'],2);

                }

            }

            ##第二次均摊(平台券)
            if($coupon_id){

                $coupon_info = Logic::getCouponPrice($coupon_id);
                if(!$coupon_info['satisfy_money'] && !$coupon_info['coupon_money'])throw new Exception('当前平台优惠券已不可使用');
                //Log::info(print_r($coupon_info,true));
                //Log::info($last_money_tt);
                if($coupon_info['satisfy_money'] > $last_money_tt || $coupon_info['coupon_money'] >= $last_money_tt)throw new Exception('当前平台优惠券不满足使用条件');
                $pay_money_tt = 0;  //支付总价格(产品总价 + 运费 - 优惠券)
                //Log::info($pay_money_tt.'=>1723');
                $num2 = 1;
                $rest_pt_coupon_money = $coupon_info['coupon_money'];
                foreach($coupon_data as $k => &$v){  //判断每个店铺的均摊
                    $price_tt = round($v['last_money'],2);

                    ##优惠券金额大于剩余支付金额
                    if($coupon_info['coupon_money'] >= $last_money_tt){
                        $v['data']['coupon_money'] = $price_tt;
                        $v['data']['pay_money'] = $v['data']['total_freight'];  //加上运费的金额
                    }else{
                        ##店铺第二次均摊实付金额
                        if($num2 >= count($coupon_data)){
                            $v['data']['coupon_money'] = $rest_pt_coupon_money;
                            $v['data']['pay_money'] = round($price_tt - $v['data']['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                        }else{
                            $v['data']['coupon_money'] = round(($price_tt / $last_money_tt) * $coupon_info['coupon_money'],2);
                            $v['data']['pay_money'] = round($price_tt - $v['data']['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                            $rest_pt_coupon_money -= $v['data']['coupon_money'];
                        }

                    }
                    $num2 ++;
                    $pay_money_tt += $v['data']['pay_money'];
                    //Log::info($pay_money_tt.'====>1748');
                    $coupon_money_pt_per = $v['data']['coupon_money'];
//                    $price_tt = $price_tt - $v['data']['coupon_money'];
                    ##均摊到具体产品
                    $num = 1;
                    $rest_coupon_money = $coupon_money_pt_per;   //剩余优惠券金额
                    foreach($v['product'] as $kk => &$vv){
                        $price_rest = $vv['realpay_money'];
                        if($coupon_info['coupon_money'] >= $last_money_tt){
                            $vv['realpay_money'] = 0;
                            $vv['coupon_money'] = $price_rest;
                        }else{
                            if($num >= count($v['product'])){  ##最后一个商品
                                $vv['coupon_money'] = $rest_coupon_money;
                                $vv['realpay_money'] = $price_rest - $rest_coupon_money;

                            }else{
                                $coupon_pay = round(($price_rest/$price_tt) * $coupon_money_pt_per ,2);
                                $vv['realpay_money'] = floor(($price_rest - $coupon_pay)*100)/100;
                                $vv['coupon_money'] = $coupon_pay;
                                $rest_coupon_money -= $coupon_pay;
                                //Log::info($rest_coupon_money);
                            }
                        }
                        $num ++;
                    }
                }
            }

            if($pay_money_tt <= 0.01)$pay_money_tt=0.01;

            //Log::info(print_r($coupon_data,true));

            ###round原因=》计算的值没有问题但是会在后面增加00000000001,导致和传入的值不一样

            $pay_money_tt = round($pay_money_tt,2);

            if($pay_money_tt != $pay_money){
                throw new Exception('支付金额错误2' . "提交金额=>{$pay_money},计算金额=>{$pay_money_tt}");
            }

            //Log::info(print_r($coupon_data,true));

            foreach($coupon_data as $k=>&$v){
                $order_data = $v['data'];
                unset($v['distribution_mode']);
                $order_id = Db::name('product_order')->insertGetId($order_data);
                if($order_id === false)throw new Exception('订单创建失败');
                $data['order_id'] = $order_id;
                foreach($v['product'] as $kk => &$vv){
                    $vv['store_id'] = $order_id;
                    unset($vv['distribution_mode']);
                    unset($vv['goods_img']);
                    unset($vv['is_ziqu']);
                    unset($vv['ischecked']);
                    unset($vv['show_product_specs']);
                    unset($vv['standardOne']);
                    unset($vv['standardOneVanule']);
                    unset($vv['standardTwo']);
                    unset($vv['standardTwoVanule']);
                    unset($vv['stock']);
                    unset($vv['store_id']);
                    unset($vv['freight']);
                    $res_detail = Db::name('product_order_detail')->insert($vv);
                    if($res_detail === false)throw new Exception('订单创建失败2');
                    ##减少库存
                    $res_stock = Db::name('product_specs')->where(['id'=>$vv['specs_id']])->setDec('stock',$vv['number']);
                    if($res_stock === false)throw new Exception('库存减少失败');
                }
                ##优惠券使用确认
                if(isset($v['store_coupon_id']) && $v['store_coupon_id']){
                    $res_coupon = Db::name('coupon')->where(['id'=>$v['store_coupon_id']])->update(['status'=>2,'use_time'=>time()]);
                    if($res_coupon === false)throw new Exception('商家券使用失败');
                }
            }
            ##平台券使用确认
            if($coupon_id){
                $res_coupon_pt = Db::name('coupon')->where(['id'=>$coupon_id])->update(['status'=>2,'use_time'=>time()]);
                if($res_coupon_pt === false)throw new Exception('平台券使用失败');
            }
            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->find();
            $pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');
            $data['order_id'] = $order->id;
            $data['pay_order_no'] = $order->pay_order_no;
            ## 预支付
            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
            $openid=$userInfo['wx_openid'];
            $wxPay = new WxPay();
            $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money,$notify_url,$openid);
            $pay_type = "微信小程序";
            Db::name('product_order')->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            flock($fp,LOCK_UN);//释放锁
            Db::commit();
            fclose($fp);
            return \json(self::callback(1,'调起支付成功',$data));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 提交订单
     */
    public function submitOrder(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
//            Log::info(print_r($post,true));
            $pay_money = $post['pay_money'];   //支付总金额
            $shouhuo_username = $post['shouhuo_username'] ? trim($post['shouhuo_username']) : '';   //收货人姓名
            $shouhuo_mobile = $post['shouhuo_mobile'] ? trim($post['shouhuo_mobile']) : '';       //收货人电话
            $address_status = isset($post['address_status']) ? intval(input('address_status')) : 1;     //地址状态 1已填写地址 0未填写地址
            $shouhuo_address = $post['shouhuo_address'] ? trim($post['shouhuo_address']) : '';      //收货地址
            $coupon_id = isset($post['coupon_id']) ? intval($post['coupon_id']) : 0 ;     //平台优惠券id
            $is_shopping_cart = isset($post['is_shopping_cart']) ? intval($post['is_shopping_cart']) : 0 ;   //是否从购物车加入 1是 0否
            $is_group_buy = isset($post['is_group_buy']) ? intval($post['is_group_buy']) : 0 ;    //是否团购商品  1是 0否
            $pt_type = isset($post['pt_type']) ? intval($post['pt_type']) : 0 ;   //拼团类型 0普通拼团 1潮搭拼团
            $chaoda_id = isset($post['chaoda_id']) ? intval($post['chaoda_id']) : 0 ;   //潮搭id  非潮搭拼团则传0
            $pt_id = $post['pt_id'] ? intval($post['pt_id']) : 0 ;  //拼团id
            $store_info = $post['store_info'];   //商品信息
            if ($address_status == 1 && !$shouhuo_address) {
                return \json(self::callback(0,'收货地址不能为空'));
            }
            if ($pay_money <= 0 || !$pay_money) {
                return \json(self::callback(0,'下单失败,支付金额错误'));
            }

            $fp = fopen(__DIR__."/lock.txt", "w+");
            if(!flock($fp,LOCK_EX | LOCK_NB)){
                return \json(self::callback(0,'系统繁忙，请稍后再试'));
            }

            $pay_order_no = build_order_no('C');   //生成支付订单号
            $data['pay_order_no'] = $pay_order_no;

            Db::startTrans();
            $coupon_data = [];
            $store_coupon_ids = [];
            $product_coupon_ids = [];
            foreach ($store_info as $k=>$v){
                //店铺优惠券
                if(isset($v['store_coupon_id']) && $v['store_coupon_id']>0 && empty($v['product_coupon_id']))$store_coupon_ids[] = $v['store_coupon_id'];
                //商品优惠券
                if(isset($v['product_coupon_id']) && $v['product_coupon_id']>0 && empty($v['store_coupon_id']))$product_coupon_ids[] = $v['product_coupon_id'];
                $store = Db::name('store')->where('id',$v['store_id'])->find();
                if (!$store){
                    throw new Exception('店铺不存在');
                }

                $product_info = $v['product_info'];
                $total_huoli_money = 0;   //总获利金额
                $product_total_price = 0;   //商品总价格
                $max_freight = getArrayMax2($product_info,'freight',$v['distribution_mode']);  //获取订单最大值运费

                //Log::info($v['distribution_mode'].'==========='.$max_freight);

                $total_platform_price = 0 ;   //平台总提成
                $total_price = 0 ;  //支付金额

                foreach ($product_info as $k2=>$v2) {
                    $product_specs = Db::name('product_specs')
                        ->join('product','product.id = product_specs.product_id','left')
                        ->join('store','store.id = product.store_id','left')
                        ->field('product_specs.*,product.is_group_buy,product.pt_size,pt_validhours,product.type,product.huoli_money,product.product_type,product.days')
                        ->where('product_specs.id',$v2['specs_id'])
                        ->find();

                    if (!$product_specs) {
                        throw new Exception('商品不存在');
                    }

                    if ($product_specs['stock'] < $v2['number']) {
                        throw new Exception('库存不足');
                    }

                    $product_type = $product_specs['product_type'];  //商品类型 1实物类 2虚拟类

                    //$price = $product_specs['price'] - $product_specs['platform_price'];  //单价减去平台加价
                    $price = $product_specs['price'];
                    //非拼团潮搭商品
                    if ($is_group_buy == 0 && $chaoda_id != 0){
                        $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                    }

                    //拼团商品处理
                    if ($is_group_buy == 1) {
                        switch ($pt_type){
                            case 0:
                                /**************************************普通拼团**************************************/
                                if ($product_specs['is_group_buy'] != 1) {
                                    throw new Exception('该商品不支持团购');
                                }
                                $price = $product_specs['group_buy_price'];
                                $pt_size = $product_specs['pt_size'];

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $product_specs['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id = Db::name('user_pt')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'end_time' => $end_time,
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);
                                }else {
                                    //参与拼团
                                    $pt_info = Db::name('user_pt')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        throw new Exception('参与的拼团不存在');
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        throw new Exception('该拼团已结束');
                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();
                                    if ($ypt_num >= $product_specs['pt_size']) {
                                        throw new Exception('拼团人数已满');
                                    }

                                    if(Db::name('product_order')
                                        ->where('user_id',$userInfo['user_id'])
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->count()) {
                                        throw new Exception('已参与当前拼团');
                                    }

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('user_pt')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            case 1:
                                /**************************************潮搭拼团***************************************/

                                if (!$chaoda_id) {
                                    return \json(self::callback(0,'潮搭拼团参数错误'));
                                }

                                $chaoda_info = Db::name('chaoda')->where('id',$chaoda_id)->where('is_delete',0)->find();
                                $chaoda_tag_info = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->column('product_id');
                                if (!$chaoda_info) {
                                    return \json(self::callback(0,'潮搭不存在'));
                                }
                                $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                                $pt_size = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->count();

                                //发起拼团和参与拼团
                                if ($pt_id == 0) {
                                    //发起拼团
                                    $is_header = 1;
                                    $end_time = time() + 60 * 60 * $chaoda_info['pt_validhours'];
                                    //生成用户拼团记录
                                    $pt_id2 = Db::name('chaoda_pt_info')->insertGetId([
                                        'user_id' => $userInfo['user_id'],
                                        'store_id' => $v['store_id'],
                                        'chaoda_id' => $chaoda_id,
                                        'product_id' => $product_specs['product_id'],
                                        'specs_id' => $product_specs['id'],
                                        'ypt_size' => 1,
                                        'pt_size' => $pt_size,
                                        'end_time' => $end_time,
                                        'pt_status' => 0,
                                        'create_time' => time()
                                    ]);
                                    foreach ($chaoda_tag_info as $k3=>$v3){
                                        $pt_product_info[$k3]['product_id'] = $v3;
                                        $pt_product_info[$k3]['pt_id'] = $pt_id2;
                                        $chaoda_price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$v3)->value('price');
                                        $price = $pt_product_info[$k3]['price'] = $chaoda_price;
                                        $pt_product_info[$k3]['status'] = 0;
                                    }

                                    Db::name('chaoda_pt_product_info')->insertAll($pt_product_info);
                                }else{
                                    //参与拼团
                                    $pt_info = Db::name('chaoda_pt_info')->where('id',$pt_id)->where('pt_status',1)->find();
                                    if (!$pt_info) {
                                        throw new Exception('参与的拼团不存在');
                                    }

                                    if ($pt_info['end_time'] <= time()) {
                                        throw new Exception('该拼团已结束');
                                    }

                                    //判断拼团人数是否已满
                                    $ypt_num = Db::name('product_order')
                                        ->where('is_group_buy',1)
                                        ->where('pt_type',0)
                                        ->where('pt_id',$pt_id)
                                        ->where('order_status','>=','1')
                                        ->count();

                                    if ($ypt_num >= $pt_size) {
                                        throw new Exception('拼团人数已满');
                                    }

                                    //增加已参与拼团人数  提交订单后增加
                                    Db::name('chaoda_pt_info')->where('id',$pt_id)->setInc('ypt_size',1);
                                }

                                break;
                            default:
                                throw new Exception('pt_type参数值错误');
                                break;
                        }
                    }

                    //清空购物车
                    if ($is_shopping_cart == 1){
                        Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$v2['specs_id'])->delete();
                    }

                    $product_total_price += $price * $v2['number'];   //商品的总价格
                    // $total_price += ($price + $product_specs['platform_price']) * $v2['number'];  //商品总价 = 单价 * 数量
                    $total_price += $price * $v2['number'];
                    $product_info[$k2]['order_id'] = &$order_id;
                    $product_info[$k2]['product_id'] = $product_specs['product_id'];
                    $product_info[$k2]['specs_id'] = $v2['specs_id'];
                    $product_info[$k2]['cover'] = $product_specs['cover'];
                    $product_info[$k2]['product_name'] = $product_specs['product_name'];
                    $product_info[$k2]['product_specs'] = $product_specs['product_specs'];
                    $product_info[$k2]['number'] = $v2['number'];
                    $product_info[$k2]['price'] = $price;
                    $product_info[$k2]['platform_price'] = $product_specs['platform_price'];
                    $product_info[$k2]['freight'] = $product_specs['freight'];
                    $product_info[$k2]['type'] = $product_specs['type'];
                    //判断是不是会员用户
                    if($userInfo['type']==2){
                        $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    }else{$product_info[$k2]['huoli_money'] =0;}
                    //----结束
                    // $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];
                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                    $coupon_data[$v['store_id']]['product'][] = $product_info[$k2];
                } //商品循环结束
                $order_no = build_order_no('W');
                //拆分订单 单笔订单支付金额计算  商家商品总价格 + 运费 - (优惠券/商家数量)
//                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);
                //支付金额最低一分钱
//                if ($pay_money <= 0.01){
//                    $total_price = 0.01;
//                }
                $platform_profit_bili = $store['platform_ticheng'];  //店铺提成比例
                $platform_profit = ($product_total_price-$total_huoli_money) * ($platform_profit_bili/100); //(商品总价格-总代购金额) * 平台收益比例

                $coupon_data[$v['store_id']]['store_coupon_id'] = $v['store_coupon_id']?:0;//店铺优惠券
                $coupon_data[$v['store_id']]['product_coupon_id'] = $v['product_coupon_id']?:0;//商品优惠券
                $coupon_data[$v['store_id']]['data'] = [
                    'user_id' => $post['user_id'],
                    'store_coupon_id' => $v['store_coupon_id']?:0,
                    'product_coupon_id' => $v['product_coupon_id']?:0,
                    'order_no' => $order_no,
                    'pay_order_no' => $pay_order_no,
                    'store_id' => $v['store_id'],
                    'pay_money' => $total_price,
                    'shouhuo_username' => $shouhuo_username,
                    'shouhuo_mobile' => $shouhuo_mobile,
                    'shouhuo_address' => $shouhuo_address,
                    'address_status' => $address_status,
                    'is_group_buy' => $is_group_buy ,
                    'pt_type' => $pt_type,  //拼团类型 0 普通拼团 1 潮搭拼团
                    'pt_id' => $pt_id,
                    'is_header' => isset($is_header) ? $is_header : 0 ,
                    'coupon_id' => $coupon_id,
//                    'coupon_money' => round(($coupon_money/$store_number)),
                    'total_freight' => $max_freight,
                    'platform_profit' => $platform_profit,
                    'distribution_mode' => $v['distribution_mode'],
                    'order_status' => 1,
                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
                    'total_platform_price' => $total_platform_price,
                    'chaoda_id' => $chaoda_id,
                    'create_time' => time()
                ];

            }  //店铺循环结束

            ##判断优惠券的叠加
//            if($coupon_id && (!empty($store_coupon_ids) || !empty($product_coupon_ids))){
//                $is_superposition_pt = Logic::getCouponSuperpositionAndExpireTime($coupon_id);
//                if(!$is_superposition_pt || $is_superposition_pt == 1)throw new Exception('该平台优惠券已不能使用');
//                if($store_coupon_ids){
//                    $store_coupon_count = Logic::getNotSuperpositionCoupon($store_coupon_ids);
//                    if($store_coupon_count < count($store_coupon_ids))throw new Exception('当前店铺优惠券已不可使用');
//                }elseif($product_coupon_ids){
//                    $product_coupon_count = Logic::getNotSuperpositionproductCoupon($product_coupon_ids);
//                    if($product_coupon_count < count($product_coupon_ids))throw new Exception('当前商品优惠券已不可使用');
//                }
//            }
            ##判断优惠券的叠加
            if($coupon_id && (!empty($store_coupon_ids) || !empty($product_coupon_ids))){
                $is_superposition_pt = Logic::getCouponSuperpositionAndExpireTime($coupon_id);
                if(!$is_superposition_pt)throw new Exception('该平台优惠券已不能使用');
                if($is_superposition_pt == 1)throw new Exception('该平台优惠券不能叠加使用');
                if($store_coupon_ids){ //判断商家券是否叠加
                    $store_coupon_info = Logic::getNotSuperpositionCoupon2($store_coupon_ids);
                    if(count($store_coupon_info) < count($store_coupon_ids))throw new Exception('店铺优惠券参数错误');
                    $time = time();
                    foreach($store_coupon_info as $v){
                        if($v['expiration_time'] < $time)throw new Exception("商家券[{$v['coupon_name']}]已过期");
                        if($v['status'] != 1)throw new Exception("商家券[{$v['coupon_name']}]已不可使用");
                        if($v['is_superposition'] != 2)throw new Exception("商家券[{$v['coupon_name']}]不可叠加使用");
                    }
                }elseif($product_coupon_ids){ //判断商品券是否叠加
                    $product_coupon_info = Logic::getNotSuperpositionproductCoupon2($product_coupon_ids);
                    if(count($product_coupon_info) < count($product_coupon_ids))throw new Exception('商品优惠券参数错误');
                    $time = time();
                    foreach($product_coupon_info as $v){
                        if($v['expiration_time'] < $time)throw new Exception("商品券[{$v['coupon_name']}]已过期");
                        if($v['status'] != 1)throw new Exception("商品券[{$v['coupon_name']}]已不可使用");
                        if($v['is_superposition'] != 2)throw new Exception("商品券[{$v['coupon_name']}]不可叠加使用");
                    }
                }
            }
            //Log::info(print_r($coupon_data,true));
            $last_money_tt = 0;  //剩余价格总价

            $pay_money_tt = 0 ; //支付总价格(产品总价 + 运费 - 优惠券)

            foreach($coupon_data as $k => &$v){
                ##第一次均摊(店铺券均摊)
                $store = $v['data'];
//                print_r($store);die;
                $price_tt = $store['pay_money'];
                if($store['store_coupon_id']>0 && empty($store['product_coupon_id'])){
                    //店铺券
                    $coupon_info = Logic::getCouponPrice2($store['store_coupon_id']);
                    if(!$coupon_info['coupon_money'] && $store['store_coupon_id'])throw new Exception('当前店铺优惠券已不可使用');
                    if($price_tt < $coupon_info['satisfy_money'] || $price_tt <=$coupon_info['coupon_money'])throw new Exception('店铺优惠券不满足使用金额');
                    ##如果优惠券金额大于本单支付总金额
                    if($price_tt < $coupon_info['coupon_money']){
                        $v['data']['store_coupon_money'] = $price_tt;
                        $v['data']['pay_money'] = $v['data']['total_freight'];  //加上运费的金额
                        $v['last_money'] = 0;   //除去运费的实际支付金额
                    }else{
                        ##店铺第一次均摊实付金额
                        $v['data']['pay_money'] = round($price_tt - $coupon_info['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                        $v['data']['store_coupon_money'] = $coupon_info['coupon_money'];
                        $v['last_money'] = $price_tt - $coupon_info['coupon_money'];   //除去运费的实际支付金额
                    }
                    $last_money_tt += $v['last_money'];  //剩余的所有店铺总价
                    $pay_money_tt += $v['data']['pay_money'];  //包含运费的实付总价
                    //Log::info($pay_money_tt.'=>2691');

                    $num = 1;
                    $rest_coupon_money = $coupon_info['coupon_money'];   //剩余优惠券金额
                    foreach($v['product'] as $kk => &$vv){
                        $price_t = $vv['number'] * $vv['price'];  //该产品总价格
                        if($price_tt < $coupon_info['coupon_money']){  //优惠券金额大于订单总价
                            $vv['realpay_money'] = 0;
                            $vv['store_coupon_money'] = $price_t;
                        }else{
                            if(count($v['product']) <= $num){  ##最后一个产品
                                $vv['store_coupon_money'] = $rest_coupon_money;
                                $vv['realpay_money'] = round($price_t - $rest_coupon_money,2);
                            }else{  ##前几个产品
                                $coupon_pay = round(($price_t/$price_tt) * $coupon_info['coupon_money'],2);
//                            Log::info("price_t=>" . $price_t);
//                            Log::info("price_tt=>" . $price_tt);
//                            Log::info("coupon_money=>" . $coupon_info['coupon_money']);
                                $vv['realpay_money'] = floor(($price_t - $coupon_pay)*100)/100;
                                $vv['store_coupon_money'] = $coupon_pay;
                                $rest_coupon_money -= $coupon_pay;
                                //Log::info($rest_coupon_money);
                            }
                        }
                        $num ++;
                    }
                }elseif($store['product_coupon_id']>0 && empty($store['store_coupon_id'])){
                    //商品券
                    $coupon_info = Logic::getCouponPrice4($store['product_coupon_id']);
                    if(!$coupon_info['coupon_money'] && $store['product_coupon_id'])throw new Exception('当前商品优惠券已不可使用');
                    $rest_coupon_money = $coupon_info['coupon_money'];   //优惠券金额
                        //多商品
                        $price_ts =0;
                        $product_ids=explode(',',trimFunc($coupon_info['product_ids']));
                        $num = 0;
                        foreach($v['product'] as $kk => &$vv){
                            if (in_array($vv['product_id'], $product_ids))
                            {
                               //有这个商品
                                $price_ts += $vv['number'] * $vv['price'];  //该产品总价格
                                $num++;
                            }
                        }
                        //无门槛
                        if($coupon_info['satisfy_money']==0 && $coupon_info['coupon_money']>0){
                            if($price_ts<$coupon_info['coupon_money']){
                                throw new Exception('商品金额不能小于减的金额');
                            }
                        }elseif($coupon_info['satisfy_money']>0 && $coupon_info['coupon_money']>0){
                            if($price_ts<$coupon_info['satisfy_money']){
                                throw new Exception('商品金额不能小于满的金额');
                            }
                        }

                        $num2=1;

                        foreach($v['product'] as $kk => &$vv){
                            $price_t = $vv['number'] * $vv['price'];  //该产品总价格

                            if (in_array($vv['product_id'], $product_ids))
                            {

                                //有这个商品
                                if($num <= $num2){  ##最后一个产品
                                    $vv['product_coupon_money'] = $rest_coupon_money;
                                    $vv['realpay_money'] = round($price_t - $rest_coupon_money,2);

                                }else{  ##前几个产品

                                    $coupon_pay = round(($price_t/$price_ts) * $coupon_info['coupon_money'],2);
//                            Log::info("price_t=>" . $price_t);
//                            Log::info("price_tt=>" . $price_tt);
//                            Log::info("coupon_money=>" . $coupon_info['coupon_money']);
                                    $vv['realpay_money'] = floor(($price_t - $coupon_pay)*100)/100;
                                    $vv['product_coupon_money'] = $coupon_pay;
                                    $rest_coupon_money -= $coupon_pay;

                                    //Log::info($rest_coupon_money);
                                }
                                $num2++;
                            }else{
                                $vv['realpay_money'] = $price_t;
                                $vv['product_coupon_money'] = 0;
                            }
                        }
                        $v['data']['product_coupon_money'] = $coupon_info['coupon_money'];
                        $v['last_money'] = $price_tt - $coupon_info['coupon_money'];   //除去运费的实际支付金额
                        $v['data']['pay_money'] = round($v['last_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                        $last_money_tt += $v['last_money'];
                        $pay_money_tt += $v['data']['pay_money'];

                }else{
                    //没有券
                    foreach($v['product'] as $kk => &$vv){
                        $vv['realpay_money'] = $vv['number'] * $vv['price'];
                        $vv['store_coupon_money'] = 0;
                    }
                    $v['last_money'] = round($price_tt ,2);  //加上运费的金额

                    $v['data']['pay_money'] = round($price_tt + $v['data']['total_freight'],2);  //加上运费的金额
                    $last_money_tt += $price_tt;
                    $pay_money_tt += round($price_tt  + $v['data']['total_freight'],2);
                }

            }

            //Log::info(print_r($coupon_data,true));
            //Log::info(print_r($last_money_tt,true));

            ##第二次均摊(平台券)
            if($coupon_id){
                $coupon_info = Logic::getCouponPrice($coupon_id);
                if(!$coupon_info['satisfy_money'] && !$coupon_info['coupon_money'])throw new Exception('当前平台优惠券已不可使用');
                //Log::info(print_r($coupon_info,true));
                //Log::info($last_money_tt);
                if($coupon_info['satisfy_money'] > $last_money_tt || $coupon_info['coupon_money'] >= $last_money_tt)throw new Exception('当前平台优惠券不满足使用条件');

                $pay_money_tt = 0;  //支付总价格(产品总价 + 运费 - 优惠券)
                //Log::info($pay_money_tt.'=>1723');

                $num2 = 1;
                $rest_pt_coupon_money = $coupon_info['coupon_money'];
                foreach($coupon_data as $k => &$v){  //判断每个店铺的均摊
                    $price_tt = round($v['last_money'],2);
                    ##优惠券金额大于剩余支付金额
                    if($coupon_info['coupon_money'] >= $last_money_tt){
                        $v['data']['coupon_money'] = $price_tt;
                        $v['data']['pay_money'] = $v['data']['total_freight'];  //加上运费的金额
                    }else{
                        ##店铺第二次均摊实付金额
                        if($num2 >= count($coupon_data)){
                            $v['data']['coupon_money'] = $rest_pt_coupon_money;
                            $v['data']['pay_money'] = round($price_tt - $v['data']['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                        }else{
                            $v['data']['coupon_money'] = round(($price_tt / $last_money_tt) * $coupon_info['coupon_money'],2);
                            $v['data']['pay_money'] = round($price_tt - $v['data']['coupon_money'] + $v['data']['total_freight'],2);  //加上运费的金额
                            $rest_pt_coupon_money -= $v['data']['coupon_money'];
                            //Log::info($rest_pt_coupon_money);
                            //Log::info($v['data']['coupon_money']);
                            //Log::info($v['data']['pay_money']);
                            //Log::info($price_tt);
                        }
                    }
                    $num2 ++;

                    $pay_money_tt += $v['data']['pay_money'];
                    //Log::info($pay_money_tt.'====>1748');
                    $coupon_money_pt_per = $v['data']['coupon_money'];

//                    $price_tt = $price_tt - $v['data']['coupon_money'];

                    ##均摊到具体产品
                    $num = 1;
                    $rest_coupon_money = $coupon_money_pt_per;   //剩余优惠券金额
                    foreach($v['product'] as $kk => &$vv){
                        $price_rest = $vv['realpay_money'];
                        if($coupon_info['coupon_money'] >= $last_money_tt){
                            $vv['realpay_money'] = 0;
                            $vv['coupon_money'] = $price_rest;
                        }else{
                            if($num >= count($v['product'])){  ##最后一个商品
                                $vv['coupon_money'] = $rest_coupon_money;
                                $vv['realpay_money'] = $price_rest - $rest_coupon_money;
                            }else{
                                $coupon_pay = round(($price_rest/$price_tt) * $coupon_money_pt_per ,2);
                                //   Log::info("price_rest=>" . $price_rest);
                                // Log::info("price_tt=>" . $price_tt);
                                // Log::info("coupon_money_pt_per=>" . $coupon_money_pt_per);
                                $vv['realpay_money'] = floor(($price_rest - $coupon_pay)*100)/100;
                                $vv['coupon_money'] = $coupon_pay;
                                $rest_coupon_money -= $coupon_pay;
                                //Log::info($rest_coupon_money);
                            }
                        }
                        $num ++;
                    }
                }
            }
            if($pay_money_tt <= 0.01)$pay_money_tt=0.01;

            //Log::info(print_r($coupon_data,true));

            ###round原因=》计算的值没有问题但是会在后面增加00000000001,导致和传入的值不一样

            $pay_money_tt = round($pay_money_tt,2);

            if($pay_money_tt != $pay_money){
                throw new Exception('支付金额错误2' . "提交金额=>{$pay_money},计算金额=>{$pay_money_tt}");
            }

//            throw new Exception('恭喜');

            //Log::info(print_r($coupon_data,true));

            foreach($coupon_data as $k=>&$v){
                $order_data = $v['data'];
                unset($v['distribution_mode']);
                $order_id = Db::name('product_order')->insertGetId($order_data);
                if($order_id === false)throw new Exception('订单创建失败');
                $data['order_id'] = $order_id;
                foreach($v['product'] as $kk => &$vv){
                    $vv['store_id'] = $order_id;
                    unset($vv['distribution_mode']);
                    unset($vv['goods_img']);
                    unset($vv['is_ziqu']);
                    unset($vv['ischecked']);
                    unset($vv['show_product_specs']);
                    unset($vv['standardOne']);
                    unset($vv['standardOneVanule']);
                    unset($vv['standardTwo']);
                    unset($vv['standardTwoVanule']);
                    unset($vv['stock']);
                    unset($vv['store_id']);
                    unset($vv['freight']);
                    $res_detail = Db::name('product_order_detail')->insert($vv);
                    if($res_detail === false)throw new Exception('订单创建失败2');
                    ##减少库存
                    $res_stock = Db::name('product_specs')->where(['id'=>$vv['specs_id']])->setDec('stock',$vv['number']);
                    if($res_stock === false)throw new Exception('库存减少失败');
                }
                ##店铺优惠券使用确认
                if(isset($v['store_coupon_id']) && $v['store_coupon_id']){
                    $res_coupon = Db::name('coupon')->where(['id'=>$v['store_coupon_id']])->update(['status'=>2,'use_time'=>time()]);
                    if($res_coupon === false)throw new Exception('店铺券使用失败');
                }
                ##商品优惠券使用确认
                if(isset($v['product_coupon_id']) && $v['product_coupon_id']){
                    $res_coupon = Db::name('coupon')->where(['id'=>$v['product_coupon_id']])->update(['status'=>2,'use_time'=>time()]);
                    if($res_coupon === false)throw new Exception('商品券使用失败');
                }
            }
            ##平台券使用确认
            if($coupon_id){
                $res_coupon_pt = Db::name('coupon')->where(['id'=>$coupon_id])->update(['status'=>2,'use_time'=>time()]);
                if($res_coupon_pt === false)throw new Exception('平台券使用失败');
            }
            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->find();
            $pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');
            $data['order_id'] = $order->id;
            $data['pay_order_no'] = $order->pay_order_no;
            ## 预支付
            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
            $openid=$userInfo['wx_openid'];
            $wxPay = new WxPay();
            $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money,$notify_url,$openid);
            $pay_type = "微信小程序";
            Db::name('product_order')->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            flock($fp,LOCK_UN);//释放锁
            Db::commit();
            fclose($fp);
            return \json(self::callback(1,'调起支付成功',$data));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 确认重新支付
     */
    public function Pay(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $data['pay_order_no'] = $order_no = input('pay_order_no');
            $data['order_id'] = $order_id =input('order_id') ? intval(input('order_id')) : 0 ;
            if(!$order_no && !$order_id){
                return json(self::callback(0, "参数错误"), 400);
            }

//开始调起支付
            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->where('user_id',$userInfo['user_id'])->find();

            if (!$order){
                throw new \Exception('订单不存在');
            }
            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }
            $data['pay_order_no'] = $order->order_no;
            $order_no=$order->order_no;
            $pay_money = Db::name('product_order')->where('id',$order_id)->sum('pay_money');
            if($pay_money<=0){
                $pay_money=0.01;
            }
            //************************************************
            //定义回调地址
            $notify_url = SERVICE_FX."/wxapi/wx_pay/continue_goods_wxpay_notify";
            $openid=$userInfo['wx_openid'];
            $wxPay = new WxPay();
            $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url,$openid);
            $pay_type = "微信小程序";
            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            return \json(self::callback(1,'签名成功',$data));
//结束返回
//            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
//            $wxPay = new XcxPay();
//            $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
//            $pay_type = "微信小程序";
//            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
//            return \json(self::callback(1,'签名成功',$data));

            //************************************************
            //定义回调地址
//            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
//            //调用同一支付
//            require_once "./Xcxpay/lib/WxPay.Api.php";
//            require_once "./Xcxpay/example/WxPay.JsApiPay.php";
//            require_once "./Xcxpay/example/WxPay.Config.php";
//            require_once "./Xcxpay/example/log.php";
//            //①、获取用户openid
//            $tools = new \JsApiPay();
//            $openId = $tools->GetOpenid();
//            //②、统一下单
//            $input = new \WxPayUnifiedOrder();
//            $input->SetBody("超神宿测试");
//            $input->SetAttach("超神宿");
//            $input->SetOut_trade_no($order_no);
//            $input->SetTotal_fee($pay_money*100); //单位是分转换为元需要乘以100
//            $input->SetTime_start(date("YmdHis"));
//            $input->SetTime_expire(date("YmdHis", time() + 600));
//            $input->SetGoods_tag("test");
//            $input->SetNotify_url("$notify_url");
//            $input->SetTrade_type("JSAPI");
//            $input->SetOpenid($openId);
//            $config = new \WxPayConfig();
//            $order = \WxPayApi::unifiedOrder($config, $input);
//            $jsApiParameters = $tools->GetJsApiParameters($order);
//            if($jsApiParameters){
//            $data['pay_info']=$jsApiParameters;
//            $pay_type = "微信小程序";
//            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
//            return \json(self::callback(1,'签名成功',$data));
//            }else{
//                return \json(self::callback(0,'签名错误'));
//            }

// ters($order);
            //--------------------
//            $openId=$userInfo['wx_openid'];
//            //②、统一下单
//            $tools = new \JsApiPay();
//            $input = new \WxPayUnifiedOrder();
//            $input->SetBody("test1");
//            $input->SetAttach("test2");
//            $input->SetOut_trade_no($order_no);
//            $input->SetTotal_fee($pay_money*100);
//            $input->SetTime_start(date("YmdHis"));
//            $input->SetTime_expire(date("YmdHis", time() + 600));
//            $input->SetGoods_tag("test3");
//            $input->SetNotify_url($notify_url);
//            $input->SetTrade_type("JSAPI");
//            $input->SetOpenid($openId);
//            $config = new \WxPayConfig();
//            $order = \WxPayApi::unifiedOrder($config, $input);
//            $jsApiParameters = $tools->GetJsApiParameters($order);
//            $data['pay_info']=$jsApiParameters;
//            $pay_type = "微信小程序";
//            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
//            return \json(self::callback(1,'签名成功',$data));
            //--------------------
            //*******************************************88
            // switch($pay_type){
            //     case 1:
            //         $pay_type = "支付宝";
            //         $notify_url = SERVICE_FX."/user/ali_pay/goods_alipay_notify";
            //         $aliPay = new AliPay();
            //         $data['alipay_order_info'] = $aliPay->appPay($order_no,$pay_money,$notify_url);

            //         break;
            //     case 2:

            //         $pay_type = "微信";
            //         $notify_url = SERVICE_FX."/user/wx_pay/goods_wxpay_notify";
            //         $wxPay = new WxPay();
            //         $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
            //         break;
            //     default:
            //         throw new \Exception('支付方式错误');
            //         break;
            // }


//            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 整单继续支付
     */
    public function continuePay(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $data['pay_order_no'] = $order_no = input('pay_order_no');
            if(!$order_no ){
                return json(self::callback(0, "参数错误"), 400);
            }
            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->find();
            $data['order_id'] = $order->id;
            if (!$order){
                throw new \Exception('订单不存在');
            }
            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }
            $data['pay_order_no'] = $order->pay_order_no;
            $order_no=$order->pay_order_no;
            $pay_money = Db::name('product_order')->where('pay_order_no',$order_no)->sum('pay_money');
            if($pay_money<=0){
                $pay_money=0.01;
            }
            //************************************************
            //定义回调地址
            $notify_url = SERVICE_FX."/wxapi/wx_pay/goods_wxpay_notify";
            $openid=$userInfo['wx_openid'];
            $wxPay = new WxPay();
            $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url,$openid);
            $pay_type = "微信小程序";
            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            return \json(self::callback(1,'签名成功',$data));
        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 批量提交订单
     */
    public function submitinfo()
    {
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json) {
                return $userInfo;
            }
            $param = $this->request->post();
            $order_info = $param['order_info'];
            //循环取出对应商品信息
            $store_info = [];
            $user_id=$userInfo['user_id'];
            $ptmoney=0;
            foreach ($order_info as $k => $v) {
                $moneys=0;
                $store_coupons=0;
                if (empty($v)) {

                    //等于空不作处理
                } else {
                    //循环取出每家店的商品信息

                    $store_id=$k;

                    //查询店铺信息
                    $store_info[$k] = Db::name('store')->field('id,store_name,type,cover as store_logo')->where('id', $k)->find();
                    $order_info = [];

                    if (isset($v['0']['type']) && isset($v['0']['chaoda_id'])) {
                        $store_info[$k]['chaoda_id'] = $v['0']['chaoda_id'];
                        if ($v['0']['type'] == 1) {
                            //查询潮搭
                            //获取邮费
                            $freight = Db::name('chaoda')->where('id', $v['0']['chaoda_id'])->value('freight');
                            if($freight==''||$freight==null){
                                $freight='0.00';
                            }
                            //循环所有chaoda
                            $moneys=0;
                            foreach ($v as $k1 => $v1) {
                                //查询单个商品
                                $order_info[$k1] = Db::name('product_specs')->field('id as specs_id,cover,product_id,product_name,stock,product_specs')->where('id', $v1['id'])->find();
                                $order_info[$k1]['number'] = $v1['num'];
                                $order_info[$k1]['show_product_specs'] = str_replace("{", '', $order_info[$k1]['product_specs']);
                                $order_info[$k1]['show_product_specs'] = str_replace("}", '', $order_info[$k1]['show_product_specs']);
                                $order_info[$k1]['show_product_specs'] = str_replace("\"", '', $order_info[$k1]['show_product_specs']);
                                //查询潮搭单价
                                $price = Db::name('chaoda_tag')->where('product_id', $order_info[$k1]['product_id'])->where('chaoda_id', $v1['chaoda_id'])->value('price');
                                $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id', $order_info[$k1]['product_id'])->order('id asc')->select();
                                //查询默认id值
                                $order_info[$k1]['product_specs'] = json_decode($order_info[$k1]['product_specs']);
                                foreach ($key as $k2 => $v2) {
                                    $key[$k2]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id', $v2['id'])->select();
                                    $vv = $key[$k2]['value'];
                                    foreach ($vv as $k4 => $v4) {
                                        foreach ($order_info[$k1]['product_specs'] as $k5 => $v5) {
                                            if ($v5 == $v4['attribute_value']) {
                                                $value_id[$k2]['id'] = $v2['id'];
                                                $value_id[$k2]['value'] = $v4['id'];
                                            }
                                        }
                                    }
                                }
                                $order_info[$k1]['price'] = $price;
                                $order_info[$k1]['key_value'] = $value_id;
                                $order_info[$k1]['specs'] = $key;
                                //定义变量
                                $coupon_type=3;//商品优惠券
                                $product_id=$order_info[$k1]['product_id'];
                                $money=$price*$v1['num'];//金额
                                $moneys+=$money;
                                //查询商品券数量
                                $product_coupons = UserLogic::productCoupos($money,$coupon_type,$user_id, $store_id, $product_id);
                                if(isset($product_coupons)){
                                    foreach ($product_coupons as $k7=>$v7){
                                        $product_coupons_s[]=$v7;
                                    }
                                }
                            }
                            //查询店铺优惠券数量
                            $store_coupon_type=2;
                            $product_id=0;
                            $store_coupons = UserLogic::productCoupos($moneys,$store_coupon_type,$user_id, $store_id, $product_id);

                        } else {
                            //报错
                            return \json(self::callback(0, '未知错误'));
                        }

                        //查询平台优惠券数量
                        $pt_coupon_type=1;
                        $store_id=0;
                        $product_id=0;
                        $pt_coupons = UserLogic::productCoupos($moneys,$pt_coupon_type,$user_id, $store_id, $product_id);
                        $product_coupons=count(array_unique($product_coupons_s));
                        $store_info[$k]['freight'] = $freight;
                        $store_info[$k]['store_coupons'] = $product_coupons+$store_coupons;
                        $store_info[$k]['order_info'] = $order_info;
                        $list['$store_info'] = $store_info;
                        //返回数据
                        $data['data'] = $list['$store_info'];
                        $data['pt_coupons']=$pt_coupons;
                        return \json(self::callback(1, '', $data));

                    } else {

                        //购物车
                        //批量购物车处理
                        $yunfei[$k]='';
                        foreach ($v as $k1 => $v1) {
                            //查询单个商品
                            $order_info[$k1] = Db::name('product_specs')->field('id as specs_id,cover,product_id,product_name,stock,product_specs,price')->where('id', $v1['id'])->find();
                            $order_info[$k1]['number'] = $v1['num'];
                            $order_info[$k1]['show_product_specs'] = str_replace("{", '', $order_info[$k1]['product_specs']);
                            $order_info[$k1]['show_product_specs'] = str_replace("}", '', $order_info[$k1]['show_product_specs']);
                            $order_info[$k1]['show_product_specs'] = str_replace("\"", '', $order_info[$k1]['show_product_specs']);
                            //运费
                            $order_info[$k1]['freight'] = Db::name('product')->where('id', $order_info[$k1]['product_id'])->value('freight');
                            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id', $order_info[$k1]['product_id'])->order('id asc')->select();
                            //查询默认id值
                            $order_info[$k1]['product_specs'] = json_decode($order_info[$k1]['product_specs']);
                            foreach ($key as $k2 => $v2) {
                                $key[$k2]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id', $v2['id'])->select();
                                $vv = $key[$k2]['value'];
                                foreach ($vv as $k4 => $v4) {
                                    foreach ($order_info[$k1]['product_specs'] as $k5 => $v5) {
                                        if ($v5 == $v4['attribute_value']) {
                                            $value_id[$k2]['id'] = $v2['id'];
                                            $value_id[$k2]['value'] = $v4['id'];
                                        }
                                    }
                                }
                            }
                            $order_info[$k1]['key_value'] = $value_id;
                            $order_info[$k1]['specs'] = $key;
                            $yunfei[$k][]= $order_info[$k1]['freight'];
                            //定义变量
                            $coupon_type=3;//商品优惠券
                            $product_id=$order_info[$k1]['product_id'];
                            $money=$order_info[$k1]['price']*$v1['num'];//金额
                            $moneys+=$money;

                            //查询商品券数量
                            $product_coupons = UserLogic::productCoupos($money,$coupon_type,$user_id, $store_id, $product_id);
                            if(isset($product_coupons)){
                                foreach ($product_coupons as $k7=>$v7){
                                    $product_coupons_s[]=$v7;
                                }
                            }
                        }
                    }
                    //----店铺
                    //查询店铺优惠券数量
                    $store_coupon_type=2;
                    $product_id=0;
                    $store_coupons = UserLogic::productCoupos($moneys,$store_coupon_type,$user_id, $store_id, $product_id);

                    $store_info[$k]['freight']=max($yunfei[$k]);
                    $store_info[$k]['order_info'] = $order_info;
                    $product_coupon=count(array_unique($product_coupons_s));
                    $product_coupons_s=[];
                    $store_info[$k]['store_coupons'] = $product_coupon+$store_coupons;
                }
                $ptmoney+=$moneys;
            }
            //查询平台优惠券数量
            $pt_coupon_type=1;
            $store_id=0;
            $product_id=0;
            $pt_coupons = UserLogic::productCoupos($ptmoney,$pt_coupon_type,$user_id, $store_id, $product_id);
            $list['store_info'] = $store_info;
            //返回数据
            $data['data'] = $list['store_info'];
            $data['pt_coupons']=$pt_coupons;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
        }

    /**
     * 订单评论
     */
    public function comment(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $order_id=$param['order_id'];
            $data=$param['data'];
            $specs_id=$data['0']['specs_id'];
            $content=$data['0']['content'];

            if (!$order_id || !$specs_id || !$content) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->find();

            if (!$order){
                throw new \Exception('该订单不存在');
            }

            if ($order->order_status != 5) {
                throw new \Exception('该订单不支持此操作');
            }

            foreach ($data as $k=>$v){

              $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$v['specs_id'])->where('is_comment',0)->find();

                if($product==0){
                    //如果没有则不处理
                }else{
                    //查询到有 则评论
                    $comment_id = Db::name('product_comment')->insertGetId([
                        'order_id' => $order_id,
                        'product_id' => $product['product_id'],
                        'specs_id' => $v['specs_id'],
                        'user_id' =>$userInfo['user_id'],
                        'content' => $v['content'],
                        'create_time' => time()
                    ]);
                    $comment_id = intval($comment_id);
                    //判断是否有图片

                    $images=$v['images'];

                    if(!$images){

                    }else{
                        //处理图片评论
                        foreach ($images as $k1=>$v1){

                            $comment_img = [
                                'comment_id' => $comment_id,
                                'img_url'=>$v1
                            ];
                            $insert_comment_img =Db::table('product_comment_img')->insert($comment_img);

                            if($insert_comment_img===false){

                            }else{

                            }
                        }
                    }
                }
                Db::name('product_order_detail')->where('id',$product['id'])->setField('is_comment',1);
            }
//查询评论数量
$num=Db::name('product_order_detail')->where('order_id',$order_id)->count();
            //查询评论数量
 $num2=Db::name('product_order_detail')->where('order_id',$order_id)->where('is_comment',1)->count();

if($num==$num2){
    Db::name('product_order')->where('id',$order_id)->update(['finish_time'=>time(),'order_status'=>6,'operate_time'=>time()]);
}else{

}

            //订单全部评论完
//            if (Db::name('product_order_detail')->where('id',$product['id'])->count()) {
//                $order->order_status = 6;
//                $order->allowField(true)->save();
//            }
            return \json(self::callback(1,'评论成功',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 取消订单
     */
    public function cancel(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }
            $order =Db::name('product_order')->where('id',$order_id)->find();
//            $order = ProductOrder::get($order_id);
            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }
            $order_status = $order['order_status'];
            $coupon_id = $order['coupon_id'];
            $store_coupon_id = $order['store_coupon_id'];
            $product_coupon_id = $order['product_coupon_id'];
            $pay_scene = $order['pay_scene'];
            $pay_order_no = $order['pay_order_no'];
            Db::startTrans();
            if ($order_status == 1){
                $result =  Db::name('product_order')->where('id',$order_id)->update(['order_status'=>-1,'cancel_time'=>time(),'operate_time'=>time()]);
                if($result === false)throw new Exception('取消订单修改订单状态失败');
               $res2= Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
                if($res2 === false)throw new Exception('取消订单修改订单详情状态失败');
                //返回库存
                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
                foreach ($product as $k=>$v){
                   $stock= Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
                    if($stock === false)throw new Exception('取消订单恢复库存失败');
                }
                //判断是否有店铺优惠券
                if($store_coupon_id>0){
                   $coupons= Db::name('coupon')->where('id',$store_coupon_id)->update(['status'=>1,'use_time'=>0]);
                    if($coupons === false)throw new Exception('取消订单恢复优惠券失败');
                }
                //判断是否有平台优惠券 有则返回
                if($coupon_id>0 ){
                   $ptcoupon= Db::name('coupon')->where('id',$coupon_id)->update(['status'=>1,'use_time'=>0]);
                    if($ptcoupon === false)throw new Exception('取消订单恢复优惠券失败');
                }
                //判断是否有商品优惠券 有则返回
                if($product_coupon_id>0 ){
                    $ptcoupon= Db::name('coupon')->where('id',$product_coupon_id)->update(['status'=>1,'use_time'=>0]);
                    if($ptcoupon === false)throw new Exception('取消订单恢复优惠券失败');
                }
            }elseif($order_status == 3){
                //todo 此处原路退款
                $total_pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');
                if($order['pay_money']>0){
                    $wxpay = new WxPay();
                    if($pay_scene==1){
                        //继续支付的
                        $respay = $wxpay->wxpay_refund($order['order_no'],$order['pay_money'],$order['pay_money']);
                    }else{
                        //一次支付
                        $respay = $wxpay->wxpay_refund($order['pay_order_no'],$total_pay_money,$order['pay_money']);

                    }
                    if($respay === false)throw new Exception('取消订单退款失败');
                }
                //3退款通知
                $msg_id = Db::name('user_msg')->insertGetId([
                    'title' => '退款通知',
                    'content' => '您的订单 '.$order['order_no'].' 已取消，订单金额已原路返回',
                    'type' => 2,
                    'create_time' => time()
                ]);
                if($msg_id === false)throw new Exception('取消订单生成退款通知失败');
               $msg_res= Db::name('user_msg_link')->insert([
                    'user_id' => $order['user_id'],
                    'msg_id' => $msg_id
                ]);
                if($msg_res === false)throw new Exception('取消订单生成退款通知失败');
                //更新状态
                $result =  Db::name('product_order')->where('id',$order_id)->update(['order_status'=>-1,'cancel_time'=>time(),'operate_time'=>time()]);
                if($result === false)throw new Exception('取消订单修改订单失败');
                $res=Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
                if($res === false)throw new Exception('取消订单修改订单详情失败');
                //返回库存
                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
                $product_name = "";
                foreach ($product as $k=>$v){
                   $stock= Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
                    if($stock === false)throw new Exception('取消订单修改恢复库存失败');
                    $product_name .= "," . $v['product_name'];
                }
                $product_name = trim($product_name,',');
                addErrLog($order['pay_money']);
                if($order['pay_money'] > 0){
                    ##退款到账模板消息通知
                    ###店铺名
                    $store_info = Db::name('store')->where(['id'=>$order['store_id']])->field('store_name')->find();
                    $templateData = createRefundTemplateData($userInfo['user_id'], $order_id, $order['pay_money'], $product_name, $store_info['store_name'], "订单取消");

                    if($templateData['status']>0){
                        $res = UserLogic::useFormId($templateData['data']['form_id']['id']);
                        if($res === false){
                            $templateData['user_id'] = $order['user_id'];
                            addErrLog($templateData,'退款成功模板消息发送失败',2);
                        }

                        ##发送消息
                        $dataTemplate = $templateData['data'];
                        $res = CreateTemplate::sendTemplateMsg($dataTemplate['open_id'], $dataTemplate['templateInfo'], $dataTemplate['form_id'], $dataTemplate['access_token']);
                        $result = json_decode($res, true);
                        if($result && isset($result['errcode'])){
                            $errCode = $result['errcode'];
                            if($errCode > 0){
                                $templateData['user_id'] = $order['user_id'];
                                $templateData['errcode'] = $errCode;
                                $templateData['errmsg'] = $result['errmsg'];
                                addErrLog($templateData,'退款成功模板消息发送失败',2);
                            }
                        }else{
                            $templateData['user_id'] = $order['user_id'];
                            $templateData['errmsg'] = "curl请求失败";
                            addErrLog($templateData,'退款成功模板消息发送失败',3);
                        }
                    }else{
                        $templateData['user_id'] = $order['user_id'];
                        addErrLog($templateData,'退款成功模板发送失败',1);
                    }
                }
            }elseif($order_status == 2){
                return \json(self::callback(0,'正在拼单中不能取消'));
            }else{
                return \json(self::callback(0,'该订单不支持此操作'));
            }
            Db::commit();
                return \json(self::callback(1,'取消成功',true));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 确认收货
     */
    public function confirm(){
        try {
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            Db::startTrans();
            $order = ProductOrder::get($order_id);

            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }

            if ($order->order_status == 4){

                //判断是否有代购商品
                $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',2)->select();
                $dg_money = 0;
                $total_product_money = 0;
                $product_name = "";
                if ($order_detail) {
                    //增加用户余额 增加代购收支记录
                    foreach ($order_detail as $k=>$v){
                        $product_money = $v['number'] * $v['price'];
                        $total_product_money += $product_money;
                        $product_name .= "," . $v['product_name'];

                        //增加代购记录
                        $money = Db::name('user')->where('user_id',$userInfo['user_id'])->value('money');
                        Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('money',$v['huoli_money']);
                        Db::name('user_money_detail')->insert([
                            'user_id' => $userInfo['user_id'],
                            'order_id' => $order_id,
                            'order_detail_id' => $v['id'],
                            'note' => '代购收入',
                            'money' => $v['huoli_money'],
                            'balance' => $money + $v['huoli_money'],
                            'create_time' => time()
                        ]);
                        $dg_money += $v['huoli_money'];
                    }
                }else{
                    $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',1)->select();
                    foreach ($order_detail as $k=>$v){
                        $product_money = $v['number'] * $v['price'];
                        $total_product_money += $product_money;
                    }
                }

                //增加商家余额 增加商家收益记录
                ##商家承担的优惠券金额
                $coupon_money = 0;
                ###平台券
                $coupon_info = Logic::getCouponRuleInfoByCouponId($order->coupon_id);
                $coupon_money += (1 - $coupon_info['platform_bear']) * $order->coupon_money;
                ###商品券
                $pro_coupon_info = Logic::getCouponRuleInfoByCouponId($order->product_coupon_id);
                $coupon_money += (1 - $pro_coupon_info['platform_bear']) * $order->product_coupon_money;
                ###店铺券
                $store_coupon_info = Logic::getCouponRuleInfoByCouponId($order->store_coupon_id);
                $coupon_money += (1 - $store_coupon_info['platform_bear']) * $order->store_coupon_money;

                //增加商家余额 增加商家收益记录

                ##商家实际收入  (订单商品总价 + 订单运费 - 平台抽成 - 返利金额 - 商家承担的优惠券金额)
                $store_shouru = $total_product_money + $order->total_freight - $order->platform_profit - $dg_money - $coupon_money;
               // $store_shouru = $total_product_money + $order->total_freight - $order->platform_profit - $dg_money;  //商家实际收入 减去平台手续费和代购奖励金额

                //日志记录

                Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/log/debug/']);
                Log::write('支付金额1:'.$total_product_money);
                Log::write('支付金额2:'.$order->total_freight);
                Log::write('支付金额3:'.$order->platform_profit);
                Log::write('支付金额4:'.$dg_money);
                Log::write($store_shouru);

                $store_money = Db::name('store')->where('id',$order->store_id)->value('money');
                Db::name('store')->where('id',$order->store_id)->setInc('money',$store_shouru);
                Db::name('store_money_detail')->insert([
                    'store_id' => $order->store_id,
                    'order_id' => $order_id,
                    'note' => '商品收入',
                    'money' => $store_shouru,
                    'balance' => $store_money + $store_shouru,
                    'create_time' => time()
                ]);
                $order->order_status = 5;
                $order->confirm_time = time();
                $order->operate_time = time();
            }else{
                Db::rollback();
                return \json(self::callback(0,'该订单不支持此操作'));
            }

            //修改累计金额
            Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('leiji_money',$order->pay_money);
            $userinfo = Db::name('user')->where('user_id',$userInfo['user_id'])->find();

            //累计消费金额超过3000成为会员
//            if ($userinfo['type'] == 1){
//                if (($userinfo['leiji_money']) >= 3000){
//                    Db::name('user')->where('user_id',$userInfo['user_id'])->setField('type',2);
//                }
//            }

            $result = $order->allowField(true)->save();

            if (!$result){
                Db::rollback();
                return \json(self::callback(0,'操作失败'));
            }

            ##发送用户收益到账模板消息通知
            if($dg_money > 0){
                $templateData = createProfitGetTemplateData($userInfo['user_id'], $dg_money, $order->order_no, $product_name);
                if($templateData['status']>0){
                    $res = UserLogic::useFormId($templateData['data']['form_id']['id']);
                    if($res === false){
                        $templateData['user_id'] = $userInfo['user_id'];
                        addErrLog($templateData,'获利到账模板消息发送失败',2);
                    }

                    ##发送消息
                    $dataTemplate = $templateData['data'];
                    $res = CreateTemplate::sendTemplateMsg($dataTemplate['open_id'], $dataTemplate['templateInfo'], $dataTemplate['form_id'], $dataTemplate['access_token']);
                    $result = json_decode($res, true);
                    if($result && isset($result['errcode'])){
                        $errCode = $result['errcode'];
                        if($errCode > 0){
                            $templateData['user_id'] = $userInfo['user_id'];
                            $templateData['errcode'] = $errCode;
                            $templateData['errmsg'] = $result['errmsg'];
                            addErrLog($templateData,'获利到账模板消息发送失败',2);
                        }
                    }else{
                        $templateData['user_id'] = $userInfo['user_id'];
                        $templateData['errmsg'] = "curl请求失败";
                        addErrLog($templateData,'获利到账模板消息发送失败',3);
                    }
                }else{
                    $templateData['user_id'] = $userInfo['user_id'];
                    addErrLog($templateData,'获利到账模板发送失败',1);
                }
            }

            Db::commit();
            return \json(self::callback(1,'',true));

        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 订单列表
     */
    public function orderList(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_status = input('order_status') ? intval(input('order_status')) : 0 ;
            $address_status = input('address_status');
            $address_status = isset($address_status) ? intval(input('address_status')) : 1 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            if (empty($order_status)) { //从!改为empty()
                return \json(self::callback(0,'参数错误'),400);
            }
         //   if ($order_status == 3){
//                $where1['address_status'] = ['eq',$address_status];
                //$where2['product_order.address_status'] = ['eq',$address_status];
//                $where5['product_order.distribution_mode'] = ['eq',2];//快递的
//                $where6['pay_type'] = ['eq','微信小程序'];
//                $where4['product_order.pay_type'] = ['eq','微信小程序'];
       //     }
            if($order_status==1 || $order_status == 2||$order_status == 3){
                //只查询小程序的待支付订单
                $where3['pay_type'] = ['eq','微信小程序'];
                $where4['product_order.pay_type'] = ['eq','微信小程序'];
            }
            //特殊处理到店自取显示在状态为4的待收货状态
//            if($order_status==4){
//                $total = Db::name('product_order')
//                    ->where('user_id',$userInfo['user_id'])
//                    ->where(("user_is_delete = 0 AND (order_status = $order_status OR (order_status= 3 AND distribution_mode=1)) "))
//                    ->count();
//                $list = Db::view('product_order','id,order_no,pay_order_no,store_id,pay_money,total_freight,pay_type,order_status,address_status,pt_id,order_type,pt_type,distribution_mode')
//                    ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
//                    ->where('product_order.user_id',$userInfo['user_id'])
//                    ->where(("product_order.user_is_delete = 0 AND (product_order.order_status = $order_status OR (product_order.order_status= 3 AND product_order.distribution_mode=1)) "))
//                    ->page($page,$size)
//                    ->order('product_order.operate_time','desc')
//                    ->order('product_order.create_time','desc')
//                    ->select();
//                foreach ($list as $k=>$v) {
//                    $list[$k]['pt_type'] = intval($v['pt_type']);
//                    $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
//                    if ($order_status == 2){
//                        if ($v['pt_type'] == 0){
//                            $pt_info = Db::name('user_pt')->where('id',$v['pt_id'])->find();
//                            $list[$k]['cha_num'] = $pt_info['pt_size'] - $pt_info['ypt_size'];
//                        }else{
//                            $dpt_product = Db::view('chaoda_pt_product_info','product_id')
//                                ->where('pt_id',$v['pt_id'])
//                                ->where('status',0)
//                                ->select();
//
//                            foreach ($dpt_product as $key=>$value){
//                                $dpt_product[$key]['cover'] = Db::name('product_specs')->where('product_id',$value['product_id'])->value('cover');
//                            }
//
//                            $list[$k]['dpt_product'] = $dpt_product;
//                        }
//
//                    }
//                    $product_info = Db::view('product_order_detail','product_id,specs_id,product_specs,product_name,number,price,platform_price,is_comment,is_shouhou,is_refund')
//                        ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id','left')
//                        ->where('product_order_detail.order_id',$v['id'])
//                        ->select();
//                    foreach ($product_info as $k2=>$v2){
//                        $product_info[$k2]['price'] = $v2['price'] + $v2['platform_price'];
//                        $product_info[$k2]['show_product_specs']=$v2['product_specs'];
//                        $product_info[$k2]['show_product_specs']=str_replace("{",'', $product_info[$k2]['show_product_specs']);
//                        $product_info[$k2]['show_product_specs']=str_replace("}",'', $product_info[$k2]['show_product_specs']);
//                        $product_info[$k2]['show_product_specs']=str_replace("\"",'',$product_info[$k2]['show_product_specs']);
//                    }
//                    $list[$k]['product_info'] = $product_info;
//                }
////查询已完成把已取消订单一起返回
//            }else
 if($order_status==6){
                    $total = Db::name('product_order')
                        ->where('user_id',$userInfo['user_id'])
                        ->where(("user_is_delete = 0 AND (order_status = $order_status OR order_status= -1) "))
                        ->count();
                    $list = Db::view('product_order','id,order_no,pay_order_no,store_id,pay_money,total_freight,pay_type,order_status,address_status,pt_id,order_type,pt_type,distribution_mode')
                        ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
                        ->where('product_order.user_id',$userInfo['user_id'])
                        ->where(("product_order.user_is_delete = 0 AND (product_order.order_status = $order_status OR product_order.order_status= -1 ) "))
                        ->page($page,$size)
                        ->order('product_order.operate_time','desc')
                        ->order('product_order.create_time','desc')
                        ->select();
                    foreach ($list as $k=>$v) {
                        $list[$k]['pt_type'] = intval($v['pt_type']);
                        $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                        if ($order_status == 2){
                            if ($v['pt_type'] == 0){
                                $pt_info = Db::name('user_pt')->where('id',$v['pt_id'])->find();
                                $list[$k]['cha_num'] = $pt_info['pt_size'] - $pt_info['ypt_size'];
                            }else{
                                $dpt_product = Db::view('chaoda_pt_product_info','product_id')
                                    ->where('pt_id',$v['pt_id'])
                                    ->where('status',0)
                                    ->select();
                                foreach ($dpt_product as $key=>$value){
                                    $dpt_product[$key]['cover'] = Db::name('product_specs')->where('product_id',$value['product_id'])->value('cover');
                                }
                                $list[$k]['dpt_product'] = $dpt_product;
                            }
                        }
                        $product_info = Db::view('product_order_detail','product_id,specs_id,product_specs,product_name,number,price,platform_price,is_comment,is_shouhou,is_refund')
                            ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id','left')
                            ->where('product_order_detail.order_id',$v['id'])
                            ->select();
                        foreach ($product_info as $k2=>$v2){
                            $product_info[$k2]['price'] = $v2['price'] + $v2['platform_price'];
                            $product_info[$k2]['show_product_specs']=$v2['product_specs'];
                            $product_info[$k2]['show_product_specs']=str_replace("{",'', $product_info[$k2]['show_product_specs']);
                            $product_info[$k2]['show_product_specs']=str_replace("}",'', $product_info[$k2]['show_product_specs']);
                            $product_info[$k2]['show_product_specs']=str_replace("\"",'',$product_info[$k2]['show_product_specs']);
                        }
                        $list[$k]['product_info'] = $product_info;
                    }
            }else{

                $total = Db::name('product_order')
                    ->where('user_id',$userInfo['user_id'])
                    ->where('order_status',$order_status)
                    ->where($where3)
                    ->where('user_is_delete',0)
                    ->count();
                $list = Db::view('product_order','id,order_no,pay_order_no,store_id,coupon_id,coupon_money,pay_money,total_freight,pay_type,order_status,address_status,pt_id,order_type,pt_type')
                    ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
                    ->where('product_order.user_id',$userInfo['user_id'])
                    ->where('product_order.order_status',$order_status)
                    ->where($where4)
                    ->where('product_order.user_is_delete',0)
                    ->page($page,$size)
                    ->order('product_order.operate_time','desc')
                    ->order('product_order.create_time','desc')
                    ->select();
                foreach ($list as $k=>$v) {
                    $list[$k]['pt_type'] = intval($v['pt_type']);
                    $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                //查询代付款状态有优惠券的返还优惠券
                    if ($order_status == 1){
                        if ($v['coupon_id'] > 0){
                            $numbers = Db::name('product_order')->where('pay_order_no',$v['pay_order_no'])->count();
                            //如果只有一家店铺就让用户使用 否则返还优惠券更改为商品实际价格
                if($numbers>1){
                    //返还平台优惠券
                    Db::startTrans();
                   $ptcoupon= Db::name('coupon')->where('id',$v['coupon_id'])->update(['status'=>1,'use_time'=>0]);
                    if($ptcoupon === false)throw new Exception('更新优惠券失败]');
                    //更改返回数据
                    $list[$k]['pay_money']=$v['pay_money']+$v['coupon_money'];
                    $list[$k]['coupon_money']=0;
                    $list[$k]['coupon_id']=0;
                    $genxin=[
                        'pay_money' => $list[$k]['pay_money'],
                        'coupon_money' => 0,
                        'coupon_id' =>0
                    ];
                   $uporder= Db::name('product_order')->where('id',$v['id'])->update($genxin);
                    if($uporder === false)throw new Exception('更新订单失败]');
                    //更新订单详情
                     $details=Db::name('product_order_detail')->where('order_id',$v['id'])->select();
                     foreach ($details as $k1=>$v1){
                         $realmoney=$v1['realpay_money']+$v1['coupon_money'];
                         $genxin2=[
                             'realpay_money' => $realmoney,
                             'coupon_money' => 0
                         ];
                        $updetail= Db::name('product_order_detail')->where('id',$v1['id'])->update($genxin2);
                         if($updetail === false)throw new Exception('更新订单详情失败]');
                     }
                    Db::commit();
                }
                        }
                    }
                    if ($order_status == 2){
                        if ($v['pt_type'] == 0){
                            $pt_info = Db::name('user_pt')->where('id',$v['pt_id'])->find();
                            $list[$k]['cha_num'] = $pt_info['pt_size'] - $pt_info['ypt_size'];
                        }else{
                            $dpt_product = Db::view('chaoda_pt_product_info','product_id')
                                ->where('pt_id',$v['pt_id'])
                                ->where('status',0)
                                ->select();

                            foreach ($dpt_product as $key=>$value){
                                $dpt_product[$key]['cover'] = Db::name('product_specs')->where('product_id',$value['product_id'])->value('cover');
                            }
                            $list[$k]['dpt_product'] = $dpt_product;
                        }
                    }
                    $product_info = Db::view('product_order_detail','product_id,specs_id,product_specs,product_name,number,price,platform_price,is_comment,is_shouhou,is_refund')
                        ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id','left')
                        ->where('product_order_detail.order_id',$v['id'])
                        ->select();
                    foreach ($product_info as $k2=>$v2){
                        $product_info[$k2]['price'] = $v2['price'] + $v2['platform_price'];
                        $product_info[$k2]['show_product_specs']=$v2['product_specs'];
                        $product_info[$k2]['show_product_specs']=str_replace("{",'', $product_info[$k2]['show_product_specs']);
                        $product_info[$k2]['show_product_specs']=str_replace("}",'', $product_info[$k2]['show_product_specs']);
                        $product_info[$k2]['show_product_specs']=str_replace("\"",'',$product_info[$k2]['show_product_specs']);
                    }
                    $list[$k]['product_info'] = $product_info;
                }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除订单
     */
    public function delete(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }
            $model = new ProductOrder();
            $order = $model->where('id',$order_id)->where('user_id',$userInfo['user_id'])->where('user_is_delete',0)->find();
            if (!$order){
                throw new \Exception('订单不存在');
            }
            $order->user_is_delete = 1;
            $order->operate_time = time();
            $res = $order->save();
            if (!$res){
                throw new \Exception('操作失败');
            }
            return \json(self::callback(1,'',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 填写收货地址
     */
    public function addShouhuoAddress(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            $shouhuo_address = input('shouhuo_address');
            $shouhuo_mobile = input('shouhuo_mobile');
            $shouhuo_username = input('shouhuo_username');

            if (!$order_id || !$shouhuo_address || !$shouhuo_mobile || !$shouhuo_username){
                return \json(self::callback(0,'参数错误'),400);
            }

            $model = new ProductOrder();
            $order = $model->where('id',$order_id)->where('user_id',$userInfo['user_id'])->where('user_is_delete',0)->find();

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->address_status == 1){
                throw new \Exception('已添加收货地址');
            }

            $order->shouhuo_address = $shouhuo_address;
            $order->shouhuo_mobile = $shouhuo_mobile;
            $order->shouhuo_username = $shouhuo_username;
            $order->address_status = 1;

            $res = $order->save();

            if (!$res){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,'',true));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 订单详情
     */
    public function orderDetail(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $order_info = Db::view('product_order','id,order_no,pay_order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,finish_time as end_time,coupon_id,coupon_money,store_coupon_id,store_coupon_money,product_coupon_id,product_coupon_money,logistics_company as express_company,logistics_number as track_number,address_status,pay_type,pay_time,store_id,pay_money,total_freight,order_status,create_time,is_group_buy,logistics_info,order_type,total_freight,pt_type,pt_id,cancel_time,distribution_mode,chaoda_id')
                ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
                ->where('product_order.id',$order_id)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->find();
            if (!$order_info) {
                throw new \Exception('订单不存在');
            }
            //判断是否有商品优惠券
            if($order_info['product_coupon_id'] && $order_info['product_coupon_id'] && $order_info['store_coupon_id']==0 ){
                $order_info['store_coupon_money']=$order_info['product_coupon_money'];
            }
            $order_info['pt_type'] = intval($order_info['pt_type']);

            if ($order_info['pt_type'] == 1 && $order_info['order_status'] != 1){
                $dpt_product = Db::view('chaoda_pt_product_info','product_id')
                    ->where('pt_id',$order_info['pt_id'])
                    ->where('status',0)
                    ->select();

                foreach ($dpt_product as $key=>$value){
                    $dpt_product[$key]['cover'] = Db::name('product_specs')->where('product_id',$value['product_id'])->value('cover');
                }

                $order_info['dpt_product'] = $dpt_product;
            }
            $order_info['product_number'] = Db::name('product_order_detail')->where('order_id',$order_info['id'])->count();
            $product_info = Db::view('product_order_detail','order_id,product_id,specs_id,product_specs,product_name,number,price,is_comment,is_shouhou,is_refund')
                ->view('product_specs','cover,platform_price','product_specs.id = product_order_detail.specs_id','left')
                ->where('product_order_detail.order_id',$order_info['id'])
                ->select();
            foreach ($product_info as $k=>$v){
                $product_info[$k]['price'] = $v['price'] + $v['platform_price'];
                $product_info[$k]['show_product_specs'] = str_replace("{", '', $v['product_specs']);
                $product_info[$k]['show_product_specs'] = str_replace("}", '', $product_info[$k]['show_product_specs']);
                $product_info[$k]['show_product_specs'] = str_replace("\"", '', $product_info[$k]['show_product_specs']);

                if ($v['is_shouhou'] == 1){
                    $shouhou = Db::name('product_shouhou')
                        ->field('id,description,refuse_description')
                        ->where('order_id',$v['order_id'])
                        ->where('specs_id',$v['specs_id'])
                        ->find();
                    $product_info[$k]['description'] = $shouhou['description'];
                    $product_info[$k]['refuse_description'] = $shouhou['refuse_description'];
                    $product_info[$k]['shouhou_img'] = Db::name('product_shouhou_img')->where('shouhou_id',$shouhou['id'])->column('img_url');
                }
            }

            $order_info['product_info'] = $product_info;

            return \json(self::callback(1,'',$order_info));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 生成订单号
     */
    public function editOrderNo(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $pay_order_no = build_order_no('C');

            Db::name('product_order')->where('id',$order_id)->setField('pay_order_no',$pay_order_no);

            return \json(self::callback(1,'',['pay_order_no' => $pay_order_no]));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 生成订单号
     */
    public function editOrderNo1(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            #$pay_order_no = build_order_no('C');

            $pay_order_no = Db::name('product_order')->where('id',$order_id)->value('pay_order_no');

            # Db::name('product_order')->where('id',$order_id)->setField('pay_order_no',$pay_order_no);

            return \json(self::callback(1,'',['pay_order_no' => $pay_order_no]));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 订单售后
     */
    public function shouhou(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            $description = input('description');
            $link_mobile = input('link_mobile');
            $link_name = input('link_name');
            $return_mode = input('return_mode') ? intval(input('return_mode')) : 1 ;  //退货方式 1送至自提点 2快递
            $files = $this->request->file('img');

            if (!$order_id || !$specs_id || !$description || !$files || !$link_mobile) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->find();

            if (!$order){
                throw new \Exception('该订单不存在');
            }

            if ($order->order_status != 5) {
                throw new \Exception('该订单不支持此操作');
            }

            $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();

            if (!$product) {
                throw new \Exception('该商品不存在');
            }

            if ($product['is_shouhou'] == 1){
                throw new \Exception('该商品已售后');
            }

            foreach ($files as $key=>$file){

                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'shouhou');
                if($info){

                    $img[$key]['shouhou_id'] = &$shouhou_id;
                    $img_url = DS.'uploads'.DS.$this->request->module().DS.'shouhou'.DS.$info->getSaveName();
                    $img[$key]['img_url'] = str_replace(DS,"/",$img_url);

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }

            $shouhou_id = Db::name('product_shouhou')->insertGetId([
                'order_id' => $order_id,
                'product_id' => $product['id'],
                'specs_id' => $specs_id,
                'user_id' =>$userInfo['user_id'],
                'link_mobile' => $link_mobile,
                'link_name' => $link_name,
                'description' => $description,
                'return_mode' => $return_mode,
                'create_time' => time()
            ]);

            $shouhou_id = intval($shouhou_id);

            Db::name('product_shouhou_img')->insertAll($img);

            Db::name('product_order_detail')->where('id',$product['id'])->setField('is_shouhou',1);

            /*//订单全部评论完
            if (Db::name('product_order_detail')->where('id',$product['id'])->count()) {
                $order->order_status = 6;
                $order->allowField(true)->save();
            }*/

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));

        }
    }


}