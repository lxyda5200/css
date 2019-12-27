<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/14
 * Time: 11:18
 */

namespace app\user\controller;

use app\common\controller\Base;
use app\user\model\ProductOrder;
use think\Db;
use app\user\common\User;
use think\Log;
use think\response\Json;
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
    public function submitOrder(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
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
            if ($pay_money <= 0 || !$pay_money) {
                return \json(self::callback(0,'下单失败,支付金额错误'));
            }

            $fp = fopen(__DIR__."/lock.txt", "w+");
            if(!flock($fp,LOCK_EX | LOCK_NB)){
                return \json(self::callback(0,'系统繁忙，请稍后再试'));
            }

            $pay_order_no = build_order_no('C');   //生成支付订单号

            $coupon_money = 0 ; //优惠券金额
            //是否有优惠券
            if ($coupon_id) {
                $coupon_info = Db::name('coupon')->where('id',$coupon_id)->find();
                if (!$coupon_info) {
                    return \json(self::callback(0,'优惠券不存在'));
                }

                if ($coupon_info['status'] !=1 ){
                    return \json(self::callback(0,'优惠已使用'));
                }

                if ($coupon_info['expiration_time'] < time()) {
                    return \json(self::callback(0,'优惠券已过期'));
                }

                $coupon_money = $coupon_info['coupon_money'];
            }

            Db::startTrans();

            //修改优惠券使用状态
            if ($coupon_id){
                Db::name('coupon')->where('id',$coupon_id)->update(['use_time'=>time(),'status'=>2]);
            }

            $store_number = count($store_info);   //店铺数量

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
                        return \json(self::callback(0,'库存不足'));
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
                    $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];

                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                }

                $order_no = build_order_no('W');

                //拆分订单 单笔订单支付金额计算  商家商品总价格 + 运费 - (优惠券/商家数量)
                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);

                //支付金额最低一分钱
                if ($pay_money <= 0.01){
                    $total_price = 0.01;
                }

                $platform_profit_bili = $store['platform_ticheng'];  //店铺提成比例
                $platform_profit = ($product_total_price-$total_huoli_money) * ($platform_profit_bili/100); //(商品总价格-总代购金额) * 平台收益比例

                if(!empty($chaoda_id)){
                    $total_price = $pay_money;
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
                    'coupon_money' => round(($coupon_money/$store_number)),
                    'total_freight' => $max_freight,
                    'platform_profit' => $platform_profit,
                    'distribution_mode' => $v['distribution_mode'],
                    'order_status' => 1,
                    'order_type' => $product_type,  //商品类型 1实物类 2虚拟类
                    'total_platform_price' => $total_platform_price,
                    'chaoda_id' => $chaoda_id,
                    'create_time' => time()
                ]);
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

            return \json(self::callback(1,'',['order_id'=>$order_id,'pay_order_no'=>$pay_order_no]));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取支付信息
     */
    public function getPayInfo(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $data['pay_order_no'] = $order_no = input('pay_order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->find();
            $data['order_id'] = $order->id;

            $pay_money = Db::name('product_order')->where('pay_order_no',$order_no)->sum('pay_money');

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }

            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$pay_money,$notify_url);

                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            $orderModel->where('pay_order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
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

            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            $content = input('content');
            $files = $this->request->file('img');

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

            $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();

            if (!$product) {
                throw new \Exception('该商品不存在');
            }

            if ($product['is_comment'] == 1){
                throw new \Exception('该商品已评论');
            }

            foreach ($files as $key=>$file){

                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'comment');
                if($info){

                    $img[$key]['comment_id'] = &$comment_id;
                    $img_url = DS.'uploads'.DS.$this->request->module().DS.'comment'.DS.$info->getSaveName();
                    $img[$key]['img_url'] = str_replace(DS,"/",$img_url);

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }

            $comment_id = Db::name('product_comment')->insertGetId([
                'order_id' => $order_id,
                'product_id' => $product['product_id'],
                'specs_id' => $specs_id,
                'user_id' =>$userInfo['user_id'],
                'content' => $content,
                'create_time' => time()
            ]);

            $comment_id = intval($comment_id);

            Db::name('product_comment_img')->insertAll($img);

            Db::name('product_order_detail')->where('id',$product['id'])->setField('is_comment',1);

            //订单全部评论完
            if (Db::name('product_order_detail')->where('id',$product['id'])->count()) {
                $order->order_status = 6;
                $order->allowField(true)->save();
            }

            return \json(self::callback(1,''));

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

            $order = ProductOrder::get($order_id);

            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }

            $order_status = $order->order_status;

            if ($order_status == 1){
                //返回库存
                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();

                foreach ($product as $k=>$v){
                    Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
                }

                //删除订单记录
                $result = $order->delete();

                Db::name('product_order_detail')->where('order_id',$order_id)->delete();

            }elseif($order_status == 3){
                //todo 此处原路退款
                if ($order->pay_type == '支付宝') {
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order->pay_order_no,$order_id,$order->pay_money);
                }elseif ($order->pay_type == '微信'){
                    $total_pay_money = Db::name('product_order')->where('pay_order_no',$order->pay_order_no)->sum('pay_money');
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($order->pay_order_no,$total_pay_money,$order->pay_money);
                }

                if ($res !== true){
                    return \json(self::callback(0,'取消订单退款失败'));
                }
                //3退款通知
                $msg_id = Db::name('user_msg')->insertGetId([
                    'title' => '退款通知',
                    'content' => '您的订单'.$order->order_no.'已取消,订单金额已原路返回',
                    'type' => 2,
                    'create_time' => time()
                ]);

                Db::name('user_msg_link')->insert([
                    'user_id' => $order->user_id,
                    'msg_id' => $msg_id
                ]);

                $order->order_status = -1;
                $order->cancel_time = time();

                $result = $order->allowField(true)->save();

            }elseif($order_status == 2){
                return \json(self::callback(0,'正在拼单中不能取消'));
            }else{
                return \json(self::callback(0,'该订单不支持此操作'));
            }

            if (!$result){
                return \json(self::callback(0,'操作失败'));
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){

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
                if ($order_detail) {
                    //增加用户余额 增加代购收支记录
                    foreach ($order_detail as $k=>$v){
                        $product_money = $v['number'] * $v['price'];
                        $total_product_money += $product_money;

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

                $store_shouru = $total_product_money + $order->total_freight - $order->platform_profit - $dg_money;  //商家实际收入 减去平台手续费和代购奖励金额

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

            }else{
                Db::rollback();
                return \json(self::callback(0,'该订单不支持此操作'));
            }

            //修改累计金额
            Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('leiji_money',$order->pay_money);
            $userinfo = Db::name('user')->where('user_id',$userInfo['user_id'])->find();

            //累计消费金额超过3000成为会员
            if ($userinfo['type'] == 1){
                if (($userinfo['leiji_money']) >= 3000){
                    Db::name('user')->where('user_id',$userInfo['user_id'])->setField('type',2);
                }
            }

            $result = $order->allowField(true)->save();

            if (!$result){
                Db::rollback();
                return \json(self::callback(0,'操作失败'));
            }

            Db::commit();
            return \json(self::callback(1,''));

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

            if (!$order_status) {
                return \json(self::callback(0,'参数错误'),400);
            }

            if ($order_status == 3){
                $where1['address_status'] = ['eq',$address_status];
                $where2['product_order.address_status'] = ['eq',$address_status];
            }
//------------------20190710增加以下过滤 对应以下的34
            if($order_status==1){
                //只查询微信的待支付订单
                $where3['pay_type'] = ['neq','微信小程序'];
                $where4['product_order.pay_type'] = ['neq','微信小程序'];
            }
//------------------
            $total = Db::name('product_order')
                ->where('user_id',$userInfo['user_id'])
                ->where('order_status',$order_status)
                ->where($where1)
                ->where($where3)
                ->where('user_is_delete',0)
                ->count();

            $list = Db::view('product_order','id,order_no,store_id,pay_money,total_freight,order_status,address_status,pt_id,order_type,pt_type')
                ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
                ->where('product_order.user_id',$userInfo['user_id'])
                ->where('product_order.order_status',$order_status)
                ->where($where2)
                ->where($where4)
                ->where('product_order.user_is_delete',0)
                ->page($page,$size)
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
                }

                $list[$k]['product_info'] = $product_info;
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {

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

            $res = $order->save();

            if (!$res){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

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

            return \json(self::callback(1,''));

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

            $order_info = Db::view('product_order','id,order_no,pay_order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,address_status,pay_type,pay_money,store_id,pay_money,total_freight,order_status,create_time,is_group_buy,logistics_info,order_type,total_freight,pt_type,pt_id')
                ->view('store','store_name,cover,type','store.id = product_order.store_id','left')
                ->where('product_order.id',$order_id)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->find();

            if (!$order_info) {
                throw new \Exception('订单不存在');
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