<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/14
 * Time: 11:18
 */

namespace app\user_v5\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user_v5\common\Logic;
use app\user_v5\common\Orders;
use app\user_v5\common\UserLogic;
use app\user_v5\model\ProductOrder;
use app\user_v5\model\ProductShouhou as productshouhouModel;
use think\Db;
use app\user_v5\common\User;
use think\Exception;
use think\Log;
use think\response\Json;
use app\user_v5\validate\User as UserValidate;
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
    public function submitOrder_0815_(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            if(empty($postJson)){
                $postJson = $this->request->post('key');
                $postJson = str_replace("&quot;",'"',$postJson);
            }
            //print_r($postJson);die;
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
            $pay_type = isset($post['pay_type']) ? intval($post['pay_type']) : 0 ;     //支付类型
            $is_shopping_cart = isset($post['is_shopping_cart']) ? intval($post['is_shopping_cart']) : 0 ;   //是否从购物车加入 1是 0否
            $is_group_buy = isset($post['is_group_buy']) ? intval($post['is_group_buy']) : 0 ;    //是否团购商品  1是 0否
            $pt_type = isset($post['pt_type']) ? intval($post['pt_type']) : 0 ;   //拼团类型 0普通拼团 1潮搭拼团
            $chaoda_id = isset($post['chaoda_id']) ? intval($post['chaoda_id']) : 0 ;   //潮搭id  非潮搭拼团则传0
            $pt_id = $post['pt_id'] ? intval($post['pt_id']) : 0 ;  //拼团id
            $store_info = $post['store_info'];   //商品信息

            if (!$pay_type) {
                return \json(self::callback(0,'支付类型不能为空'));
            }
            if ($address_status == 1 && !$shouhuo_address) {
                return \json(self::callback(0,'收货地址不能为空'));
            }

            if ($pay_money < 0 || !$pay_money) {
                return \json(self::callback(0,'下单失败,支付金额错误'));
            }
            $fp = fopen(__DIR__."/lock.txt", "w+");
            if(!flock($fp,LOCK_EX | LOCK_NB)){
                return \json(self::callback(0,'系统繁忙，请稍后再试'));
            }
            $pay_order_no = build_order_no('C');   //生成支付订单号
            Db::startTrans();
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
                    //判断是不是会员用户
                    if($userInfo['type']==2){
                        $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    }else{
                        $product_info[$k2]['huoli_money'] =0;
                    }
                   //----结束
                    $product_info[$k2]['days'] = $product_specs['days'];
                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];

                }//第二个循环商品止于此

                $order_no = build_order_no('W');

                //拆分订单 单笔订单支付金额计算  商家商品总价格 + 运费 - (优惠券/商家数量)
//                $total_price = $total_price + $max_freight - round(($coupon_money/$store_number),2);
//                $total_price = $total_price + $max_freight;//加运费

                //支付金额最低一分钱
//                if ($pay_money < 0.01){
//                    $total_price = 0.01;
//                }
                //2019.8.7修改一个重大bug代码如上面
                if ($total_price < 0.01){
                    $total_price = 0.01;
                }
                //------结束

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
                        ->view('coupon_rule','store_id,is_open,start_time,end_time,type,satisfy_money,coupon_money,coupon_type','coupon.coupon_id = coupon_rule.id','left')
                        ->where('coupon.id',$v['coupon_id'])
                        ->where('coupon.user_id',$userInfo['user_id'])
                        ->find();

                    if (!$coupon_info) {
                        return \json(self::callback(0,'优惠券不存在'));
                    }

                    if ($coupon_info['status'] !=1 ){
                        return \json(self::callback(0,'优惠已使用'));
                    }

                    if ( $coupon_info['expiration_time'] < time()) {
                        return \json(self::callback(0,'优惠券已过期'));
                    }

                    if($coupon_info['satisfy_money']!=0 && $coupon_info['satisfy_money'] > $total_price){
                        //有门槛
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
                    'user_id' => $userInfo['user_id'],
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
                    'create_time' => time()
                ]);



                //-------------判断是否有优惠券 如果有按照等比例计算实际付款价格
                if($coupon_id>0 && $coupon_money>0 && $user_css_coupon_id>0){
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
                //--------------优惠券计算结束
                $order_id = intval($order_id);
                $result = Db::name('product_order_detail')->strict(false)->insertAll($product_info);

                if (!$order_id || !$result){
                    Db::rollback();
                    return \json(self::callback(0,'下单操作失败'));
                }

            }//第一个循环店铺止于此

            flock($fp,LOCK_UN);//释放锁
            Db::commit();
            fclose($fp);

            //----------------调起支付
            $orderModel = new ProductOrder();
            $order = $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->find();
            $data['order_id'] = $order->id;
            $data['pay_order_no'] = $order->pay_order_no;
            $pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');

            if (!$order){
                throw new \Exception('订单不存在');
            }
            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }
            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($pay_order_no,$pay_money,$notify_url);
                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }
            $orderModel->where('pay_order_no',$pay_order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);
            return \json(self::callback(1,'调起支付成功',$data));
           //---------------调起支付结束
//            return \json(self::callback(1,'',['order_id'=>$order_id,'pay_order_no'=>$pay_order_no]));
        }catch (\Exception $e) {
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 继续支付
     */
    public function Pay(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order_id = input('post.order_id',0,'intval');  //订单id
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_id || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }
            $orderModel = new ProductOrder();

            $order = $orderModel->where('id',$order_id)->where('user_id',$userInfo['user_id'])->find();

            if (!$order){
                throw new \Exception('订单不存在');
            }

            ##判断订单状态
            if($order->order_status != 1)throw new Exception('该订单不支持此操作');

            $data['order_id'] = $order->id;

            $data['pay_order_no'] = $order_no = $order->order_no;

            $pay_money = $order->pay_money;

            if($pay_money <= 0.01)$pay_money = 0.01;   //支付金额不能小于0.01

            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/repay_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$pay_money,$notify_url);

                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/repay_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            $orderModel->where('order_no',$order_no)->where('user_id',$userInfo['user_id'])->setField('pay_type',$pay_type);

            return \json(self::callback(1,'调起支付成功',$data));

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
            $pay_type= $order['pay_type'];
            //定义回调地址
            switch($pay_type){
                case '支付宝':
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$pay_money,$notify_url);
                    break;
                case '微信':
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }
            return \json(self::callback(1,'签名成功',$data));
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
            if (!$order_id || !$specs_id || !$content) {return \json(self::callback(0,'参数错误'),400);}
            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->find();
            if (!$order){throw new \Exception('该订单不存在');}
            if ($order->order_status != 5) {throw new \Exception('该订单不支持此操作');}
            $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();
            if (!$product) {throw new \Exception('该商品不存在');}
            if ($product['is_comment'] == 1){throw new \Exception('该商品已评论');}
            foreach ($files as $key=>$file){
                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(config('config_uploads.uploads_path') .'comment');
                if($info){
                    $img[$key]['comment_id'] = &$comment_id;
                    $img_url = config('config_uploads.img_path') .'comment'.DS.$info->getSaveName();
                    $img[$key]['img_url'] = str_replace(DS,"/",$img_url);
//                    Log::info(print_r($img_url,true));
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
//            $genxin=[
//                'is_comment'=>1,
//                'finish_standard_time'=>time()
//            ];
            Db::name('product_order_detail')->where('id',$product['id'])->setField('is_comment',1);//更改为已评论
            Db::name('product_order_detail')->where('order_id',$order_id)->where('is_shouhou',0)->where('status',0)->setField('finish_standard_time',time());//把所有正常（未申请售后）商品统一结转时间

            //订单全部评论完
            //查询评论数量
            $num=Db::name('product_order_detail')
                ->field('is_comment,status,is_shouhou')
                ->where('order_id',$order_id)
                ->select();
            $num1=count($num);
            $num2=0;
            foreach ($num as $k=>$v){
                if(($v['is_comment']==1 && $v['status']==0 && $v['is_shouhou']==0) || ($v['is_comment']==0 && $v['status']!=0 && $v['is_shouhou']==0) || ($v['is_comment']==0 && $v['status']==0 && $v['is_shouhou']!=0) || ($v['is_comment']==1 && $v['status']==0 && $v['is_shouhou']!=0)){
                    $num2+=1;
                }
            }
            //查询评论数量
//            $num2=Db::name('product_order_detail')
//                ->where('order_id',$order_id)
//                ->where(" status = 0  AND  product_order_detail.is_shouhou=0")
//                ->where('is_comment',1)
//                ->count();
            if($num1==$num2){
                Db::name('product_order')->where('id',$order_id)->update(['order_status'=>6,'finish_time'=>time()]);
            }
//            if (Db::name('product_order_detail')->where('id',$product['id'])->count()) {
//                $order->order_status = 6;
//                $order->finish_time = time();
//                $order->allowField(true)->save();
//            }
            return \json(self::callback(1,'评论成功',true));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));

        }
    }

//    /**
//     * 取消订单/退款
//     */
//    public function cancel(){
//        try{
//            //token 验证
//            $userInfo = User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
//            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
//            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
//            if (!$order_id){
//                return \json(self::callback(0,'参数错误'),400);
//            }
//            $product_order = Db::name('product_order')->where('id',$order_id)->find();
//
//            if (!$product_order){
//                return \json(self::callback(0,'订单不存在'));
//            }
//            $order_status = $product_order['order_status'];
//
//            if ($order_status == 1){
//                //待付款
//                //返回库存
//                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
//                foreach ($product as $k=>$v){
//                    Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
//                }
//                //删除订单记录
//                //------------------以前的删除
////                $result = $order->delete();
////
////                Db::name('product_order_detail')->where('order_id',$order_id)->delete();
//               //---------
//                $result=Db::name('product_order')->where('id',$order_id)->setField('order_status', -1);
//                Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
//
//                //判断是否有优惠券 如果有恢复优惠券
//
//            }elseif($order_status == 3){
                //待发货
                //---------退款开始
//                if (!$specs_id){
//                    return \json(self::callback(0,'参数错误，缺少规格id'),400);
//                }
//                $product_order_detail = Db::view('product_order_detail','order_id,product_id,specs_id,number,price,platform_price,is_shouhou,is_refund,freight,realpay_money')
//                    ->view('product_order','id,order_no,pay_order_no,pay_money,order_status,user_id,coupon_id,user_css_coupon_id,coupon_money,total_freight,pay_type,pay_time','product_order_detail.order_id = product_order.id','left')
//                    ->where('product_order_detail.order_id',$order_id)
//                    ->where('product_order_detail.is_shouhou',0)
//                    ->where('product_order_detail.specs_id',$specs_id)
//                    ->find();
//                if($product_order_detail){
//                    $productnumber = Db::name('product_order_detail')->where('order_id',$order_id)->count();
//                    if($product_order_detail['order_status']!=3){
//                        return \json(self::callback(0,'订单不支持该操作'),400);
//                    }
//                    $coupon_id=$product_order_detail['coupon_id'];
//                    $coupon_money=$product_order_detail['coupon_money'];
//                    $user_css_coupon_id=$product_order_detail['user_css_coupon_id'];
//                    if($productnumber==1){
//                        //只有一个商品
//                    }else{
//                        //多个商品
//                        //判断是否有优惠券 如果有则恢复为未使用
//                        if($coupon_id>0 && $coupon_money>0 && $user_css_coupon_id>0){
//                            //有优惠券
//                            $product_order_detail['pay_money']=$product_order_detail['realpay_money'];
//                        }else{
//                            //没有优惠券
//                            $product_order_detail['pay_money']=$product_order_detail['number']*$product_order_detail['price'];
//                        }
//                    }
//                    if ($product_order_detail['pay_type'] == '支付宝') {
//                        $alipay = new AliPay();
//                        $res = $alipay->alipay_refund($product_order_detail['pay_order_no'],$order_id,$product_order_detail['pay_money']);
//                    }elseif ($product_order_detail['pay_type'] == '微信'){
//                        $total_pay_money = Db::name('product_order')->where('pay_order_no',$product_order_detail['pay_order_no'])->sum('pay_money');
//                        $wxpay = new WxPay();
//                        $res = $wxpay->wxpay_refund($product_order_detail['pay_order_no'],$total_pay_money,$product_order_detail['pay_money']);
//                    }
//                    if ($res !== true){
//                        return \json(self::callback(0,'取消订单退款失败'));
//                    }
//                    //3退款通知
//                    $msg_id = Db::name('user_msg')->insertGetId([
//                        'title' => '退款通知',
//                        'content' => '您的订单 '.$product_order_detail['order_no'].' 已取消，订单金额已原路返回',
//                        'type' => 2,
//                        'create_time' => time()
//                    ]);
//
//                    Db::name('user_msg_link')->insert([
//                        'user_id' => $product_order_detail['user_id'],
//                        'msg_id' => $msg_id
//                    ]);
//                    //订单置为-1已取消
//                    $result= Db::name('product_order')->where('id',$product_order_detail['id'])->update(['order_status'=>-1,'cancel_time'=>time()]);
//                    //返回库存
//                    $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
//                    foreach ($product as $k=>$v){
//                        Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
//                    }
//                    //订单详情置为-1
//                    Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
//                    //操作成功
//                    //判断是否有优惠券 如果有则恢复为未使用
//                    if($coupon_id>0 && $coupon_money>0 && $user_css_coupon_id>0){
//                        $coupon_info = Db::view('user_css_coupon','id,user_id,coupon_id,status,create_time')
//                            ->view('css_coupon','store_id,satisfy_money,coupon_money,start_time,end_time,type,coupon_type','user_css_coupon.coupon_id = css_coupon.id','left')
//                            ->where('user_css_coupon.id',$user_css_coupon_id)
//                            ->where('user_css_coupon.user_id',$userInfo['user_id'])
//                            ->find();
//                        if($coupon_info && $coupon_info['status']==2){
//                            Db::name('user_css_coupon')->where('id',$coupon_info['id'])->update(['use_time'=>0,'status'=>1]);
//                        }
//                    }
//                }else{
//                    return \json(self::callback(0,'没有找到这个商品'),400);
//                }

                //----------退款结束
                //-----------以下是以前的整单取消订单2019.7.31改为上面的单个退款
                //todo 此处原路退款
//                if ($order->pay_type == '支付宝') {
//                    $alipay = new AliPay();
//                    $res = $alipay->alipay_refund($order->pay_order_no,$order_id,$order->pay_money);
//                }elseif ($order->pay_type == '微信'){
//                    $total_pay_money = Db::name('product_order')->where('pay_order_no',$order->pay_order_no)->sum('pay_money');
//                    $wxpay = new WxPay();
//                    $res = $wxpay->wxpay_refund($order->pay_order_no,$total_pay_money,$order->pay_money);
//                }
//                if ($res !== true){
//                    return \json(self::callback(0,'取消订单退款失败'));
//                }
//                //3退款通知
//                $msg_id = Db::name('user_msg')->insertGetId([
//                    'title' => '退款通知',
//                    'content' => '您的订单 '.$order->order_no.' 已取消，订单金额已原路返回',
//                    'type' => 2,
//                    'create_time' => time()
//                ]);
//
//                Db::name('user_msg_link')->insert([
//                    'user_id' => $order->user_id,
//                    'msg_id' => $msg_id
//                ]);
//
//                $order->order_status = -1;
//                $order->cancel_time = time();
//
//                $result = $order->allowField(true)->save();
//
//                //返回库存
//                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
//                foreach ($product as $k=>$v){
//                    Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
//                }
//                Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
//                //操作成功
//                //判断是否有优惠券 如果有则恢复为未使用
//                $coupon_id=$order->coupon_id;
//                $coupon_money=$order->coupon_money;
//                $user_css_coupon_id=$order->user_css_coupon_id;
//                if($coupon_id>0 && $coupon_money>0 && $user_css_coupon_id>0){
//                    $coupon_info = Db::view('user_css_coupon','id,user_id,coupon_id,status,create_time')
//                        ->view('css_coupon','store_id,satisfy_money,coupon_money,start_time,end_time,type,coupon_type','user_css_coupon.coupon_id = css_coupon.id','left')
//                        ->where('user_css_coupon.id',$user_css_coupon_id)
//                        ->where('user_css_coupon.user_id',$userInfo['user_id'])
//                        ->find();
//                    if($coupon_info && $coupon_info['status']==2){
//                        Db::name('user_css_coupon')->where('id',$coupon_info['id'])->update(['use_time'=>0,'status'=>1]);
//
//                    }
//                }
//                //-------------------
//
//            }elseif($order_status == 2){
//                return \json(self::callback(0,'正在拼单中不能取消'));
//            }else{
//                return \json(self::callback(0,'该订单不支持此操作'));
//            }
//
//            if ($result===false){
//                //操作失败
//                return \json(self::callback(0,'操作失败'));
//            }else{
//                return \json(self::callback(1,'取消成功',true));
//            }
//        }catch (\Exception $e){
//
//            return \json(self::callback(0,$e->getMessage()));
//        }
//
//    }
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
            $order_id = input('post.order_id',0,'intval');
            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }
            $order = ProductOrder::get($order_id);
            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }

            Db::startTrans();
            $order_status = $order->order_status;
            if ($order_status == 1){  //待支付->取消订单
                //返回库存
                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
                foreach ($product as $k=>$v){
                    $res = Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
                    if($res === false)throw new Exception('恢复库存失败');
                }

                ##修改订单状态
                $res = Db::name('product_order')->where('id',$order_id)->update(['order_status'=>-1,'cancel_time'=>time()]);
                if($res === false)throw new Exception('取消订单修改订单状态失败');
                $res = Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
                if($res === false)throw new Exception('取消订单修改订单详情状态失败');

                $store_coupon_id = $order->store_coupon_id;
                $product_coupon_id = $order->product_coupon_id;
                #返还店铺优惠券
                if($store_coupon_id  && !$product_coupon_id){
                    $res = UserLogic::returnCoupon(explode(',',$store_coupon_id));
                    if($res === false)throw new Exception('取消订单返还店铺优惠券失败');
                }

                #返还商品券
                if($product_coupon_id && !$store_coupon_id){
                    $res = UserLogic::returnCoupon(explode(',',$product_coupon_id));
                    if($res === false)throw new Exception('取消订单返还商品优惠券失败');
                }

                #返还平台券
                $coupon_id = $order->coupon_id;
                if($coupon_id){
                    $res = UserLogic::returnCoupon(explode(',',$coupon_id));
                    if($res === false)throw new Exception('取消订单返还平台优惠券失败');
                }

            }elseif($order_status == 3){  //已支付->取消订单

                $pay_order_no = $order->pay_scene?$order->order_no:$order->pay_order_no;

                ##退款通知
                $res = UserLogic::addUserMsg($order->user_id,0,'您的订单'.$order->order_no.'已取消,订单金额已原路返回');
                if($res === false)throw new Exception('取消订单失败[生成退款通知消息失败]');

                $order->order_status = -1;
                $order->cancel_time = time();
                $res = $order->allowField(true)->save();
                if($res === false)throw new Exception('取消订单失败[修改订单状态失败]');

                //返回库存
                $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
                foreach ($product as $k=>$v){
                    $res = UserLogic::returnStock($v['specs_id'],$v['number']);
                    if($res === false)throw new Exception('取消订单失败[恢复库存失败]');
                }
                $realpay_money = Db::name('product_order_detail')->where('order_id',$order_id)->where('status',-1)->sum('realpay_money');
                $rest_money=($order->pay_money)-$realpay_money;
                $res = Db::name('product_order_detail')->where('order_id',$order_id)->setField('status', -1);
                if($res === false)throw new Exception('取消订单失败[订单详情状态修改失败]');

                if($rest_money >= 0.01){
                    //todo 此处原路退款
                    if ($order->pay_type == '支付宝') {
                        $alipay = new AliPay();
                        $res = $alipay->alipay_refund($pay_order_no,$order_id,$rest_money);
                    }elseif ($order->pay_type == '微信'){
                        $total_pay_money = $order->pay_scene?$order->pay_money:UserLogic::sumOrderPayMoney($pay_order_no);
                        $wxpay = new WxPay();
                        $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$rest_money);
                    }
                    if ($res !== true){
                        return \json(self::callback(0,'取消订单退款失败'));
                    }
                }
                //发送短信提醒
                $mobile = Db::view('product_order','store_id,user_id')
                    ->view('store','mobile','product_order.store_id = store.id','left')
                    ->view('user','nickname','product_order.user_id = user.user_id','left')
                    ->where('product_order.id',$order_id)
                    ->find();
                if(isset($mobile['mobile'])){
                    //发送短信提示商家订单取消
                  //  IhuyiSMS::order_cancel($mobile['mobile'],$mobile['nickname'],$order->order_no);
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
     * 取消单商品订单
     */
    public function cancelproduct(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('post.order_id',0,'intval');
            $specs_id = input('post.specs_id',0,'intval');
            if (!$order_id || !$specs_id){
                return \json(self::callback(0,'参数错误'),400);
            }
            $order = ProductOrder::get($order_id);
            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }
            Db::startTrans();
            $order_status = $order->order_status;
            if ($order_status == 3){  //待支付->取消订单
                $product_order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->where('status',0)->where('is_shouhou',0)->find();
                if (!$product_order_detail){
                    return \json(self::callback(0,'订单详情不存在'));
                }
                //修改为取消状态
                $gen=[
                    'cancel_time'=>time(),
                    'status'=>-1
                ];
                $res = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->update($gen);
                if($res === false)throw new Exception('取消订单失败[订单详情状态修改失败]');
                //判断是否是全部退款
                $num = Db::name('product_order_detail')->where('order_id',$order_id)->count();
                $num1 = Db::name('product_order_detail')->where('order_id',$order_id)->where('status',-1)->count();
                if($num==$num1){
                    $total_freight=Db::name('product_order')->where('id',$order_id)->value('total_freight');
                    $refund_money=$product_order_detail['realpay_money']+$total_freight;//最后一单加运费
                }else{
                    $refund_money=$product_order_detail['realpay_money'];
                }
                //退款
                $pay_order_no = $order->pay_scene?$order->order_no:$order->pay_order_no;
                if($refund_money >0){
                    //todo 此处原路退款
                    if ($order->pay_type == '支付宝') {
                        $alipay = new AliPay();
                        $res = $alipay->alipay_refund($pay_order_no,$product_order_detail['id'],$refund_money);
                    }elseif ($order->pay_type == '微信'){
                        $total_pay_money = $order->pay_scene?$order->pay_money:UserLogic::sumOrderPayMoney($pay_order_no);
                        $wxpay = new WxPay();
                        $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$refund_money);
                    }
                    if ($res === false){
                        return \json(self::callback(0,'取消订单退款失败'));
                    }
                }
                //恢复库存
                $res = UserLogic::returnStock($product_order_detail['specs_id'],$product_order_detail['number']);
                if($res === false)throw new Exception('取消订单失败[恢复库存失败]');

                if($num==$num1){
                   $pay_order_no = $order->pay_scene?$order->order_no:$order->pay_order_no;
                ##退款通知
                $res = UserLogic::addUserMsg($order->user_id,0,'您的订单商品'.$product_order_detail['product_name'].'已取消,商品订单金额已原路返回');
                if($res === false)throw new Exception('取消订单失败[生成退款通知消息失败]');
                $order->order_status = -1;
                $order->cancel_time = time();
                $res = $order->allowField(true)->save();
                if($res === false)throw new Exception('取消订单失败[修改订单状态失败]');
                }
                //发送短信提醒
                $mobile = Db::view('product_order','store_id,user_id')
                    ->view('store','mobile','product_order.store_id = store.id','left')
                    ->view('user','nickname','product_order.user_id = user.user_id','left')
                    ->where('product_order.id',$order_id)
                    ->find();
                if(isset($mobile['mobile'])){
                    //发送短信提示商家商品取消
                   // IhuyiSMS::order_cancel_product($mobile['mobile'],$mobile['nickname'],$order->order_no,$product_order_detail['product_name']);
                }
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
//                $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',2)->select();
//                $dg_money = 0;
//                $total_product_money = 0;
//                if ($order_detail) {
//                    //增加用户余额 增加代购收支记录
//                    foreach ($order_detail as $k=>$v){
//                        $product_money = $v['number'] * $v['price'];
//                        $total_product_money += $product_money;
//
//                        //增加代购记录
//                        $money = Db::name('user')->where('user_id',$userInfo['user_id'])->value('money');
//                        Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('money',$v['huoli_money']);
//                        Db::name('user_money_detail')->insert([
//                            'user_id' => $userInfo['user_id'],
//                            'order_id' => $order_id,
//                            'order_detail_id' => $v['id'],
//                            'note' => '代购收入',
//                            'money' => $v['huoli_money'],
//                            'balance' => $money + $v['huoli_money'],
//                            'create_time' => time()
//                        ]);
//                        $dg_money += $v['huoli_money'];
//                    }
//                }else{
//                    $order_detail = Db::name('product_order_detail')->where('order_id',$order_id)->where('type',1)->select();
//                    foreach ($order_detail as $k=>$v){
//                        $product_money = $v['number'] * $v['price'];
//                        $total_product_money += $product_money;
//                    }
//                }

                ##商家承担的优惠券金额
//                $coupon_money = 0;
//                ###平台券
//                $coupon_info = Logic::getCouponRuleInfoByCouponId($order->coupon_id);
//                $coupon_money += (1 - $coupon_info['platform_bear']) * $order->coupon_money;
//                ###商品券
//                $pro_coupon_info = Logic::getCouponRuleInfoByCouponId($order->product_coupon_id);
//                $coupon_money += (1 - $pro_coupon_info['platform_bear']) * $order->product_coupon_money;
//                ###店铺券
//                $store_coupon_info = Logic::getCouponRuleInfoByCouponId($order->store_coupon_id);
//                $coupon_money += (1 - $store_coupon_info['platform_bear']) * $order->store_coupon_money;

                //增加商家余额 增加商家收益记录

                ##商家实际收入  (订单商品总价 + 订单运费 - 平台抽成 - 返利金额 - 商家承担的优惠券金额)
//                $store_shouru = $total_product_money + $order->total_freight - $order->platform_profit - $dg_money - $coupon_money;

                //日志记录

//                Log::init(['type' => 'File', 'path' => ROOT_PATH . 'runtime/log/debug/']);
//                Log::write('支付金额1:'.$total_product_money);
//                Log::write('支付金额2:'.$order->total_freight);
//                Log::write('支付金额3:'.$order->platform_profit);
//                Log::write('支付金额4:'.$dg_money);
//                Log::write($store_shouru);

//                $store_money = Db::name('store')->where('id',$order->store_id)->value('money');
//                Db::name('store')->where('id',$order->store_id)->setInc('money',$store_shouru);
//                Db::name('store_money_detail')->insert([
//                    'store_id' => $order->store_id,
//                    'order_id' => $order_id,
//                    'note' => '商品收入',
//                    'money' => $store_shouru,
//                    'balance' => $store_money + $store_shouru,
//                    'create_time' => time()
//                ]);
                $order->order_status = 5;
                $order->confirm_time = time();
                $res = Db::name('product_order_detail')->where('order_id',$order_id)->setField('comment_standard_time', time());
            }else{
                Db::rollback();
                return \json(self::callback(0,'该订单不支持此操作'));
            }
            //修改累计金额
//            Db::name('user')->where('user_id',$userInfo['user_id'])->setInc('leiji_money',$order->pay_money);
//            $userinfo = Db::name('user')->where('user_id',$userInfo['user_id'])->find();

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
            Db::commit();
            return \json(self::callback(1,'操作成功',true));
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
            $user_id=$userInfo['user_id'];
            $order_status = input('order_status') ? intval(input('order_status')) : 0 ;
            $address_status = input('address_status');
            $address_status = isset($address_status) ? intval(input('address_status')) : 1 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            if (!$order_status) {return \json(self::callback(0,'参数错误'),400);}
            //定义查询订单状态 全部:8 代付款:1 待发货:3 待收货:4 待评价:5 已完成:6 售后:7 已结转：8 已取消：-1 （接收传值  全部1 代付款2 待发货3 待收货4 待评价5 已完成6）
            if($order_status==7){
               $shouhou=Db::query("SELECT MAX(id) as id FROM `product_shouhou`  WHERE `user_id` = $user_id GROUP BY `order_id`,`specs_id`");
                $ids = array_column($shouhou, 'id');
                //查询售后订单
//                $total = Db::name('product_shouhou ')->alias('ps')
//                    ->join('product_order_detail p','ps.order_id = p.order_id AND ps.specs_id = p.specs_id','left')
//                    ->join('product_order po','p.order_id = po.id','left')
//                    ->field('p.id,po.order_no,ps.refund_reason')
//                    ->where('ps.id','in',$ids)
//                    ->where('p.is_shouhou',1)
//                    ->where('po.user_id',$userInfo['user_id'])
//                    ->where('p.status','neq',-1)
//                    ->count();
                $total=count($ids);
                $list = Db::name('product_shouhou ')->alias('ps')
                    ->join('product_order_detail p','ps.order_id = p.order_id AND ps.specs_id = p.specs_id','left')
                    ->join('product_order po','p.order_id = po.id','left')
                    ->join('store s','po.store_id = s.id','left')
                    ->field('p.id,po.order_no,po.id as order_id,po.pay_money,po.order_status,po.shouhuo_username,po.shouhuo_mobile,po.shouhuo_address,po.user_id,po.coupon_id,po.coupon_money,po.total_freight,po.pay_type,po.pay_time,po.fahuo_time,po.logistics_company,po.total_platform_price,po.logistics_number,
                p.cover,p.product_name,p.product_id,p.specs_id,p.product_specs,p.number,p.price,p.is_shouhou,s.store_name,p.status,s.cover as store_logo,p.is_refund,p.refund_time,p.refund_money,p.realpay_money,ps.refuse_description,ps.return_mode,ps.create_time,ps.refund_type,ps.goods_status,ps.refund_status,ps.refund_reason')
                    ->where('ps.id','in',$ids)
                    ->where('p.is_shouhou','neq',0)
                    ->where('p.status','neq',-1)
                    ->where('po.user_id',$userInfo['user_id'])
                    ->order('ps.create_time','desc')
                    ->page($page,$size)
                    ->select();
                foreach ($list as $k=>$v){
                    $list[$k]['price'] =$v['realpay_money']/$v['number'];
                    if($v['pay_time']>0 && ($v['status']==-1 || $v['status']==-2)){
                        $list[$k]['status']=-1;
                    }elseif($v['pay_time']==0){
                        $list[$k]['status']=0;
                    }
                    if($v['order_status']==8){
                        $list[$k]['order_status']=-1;
                    }
                }
                //返回售后类型1
                $data['is_shouhou']=1;
            }else if($order_status==1){
                //查询全部订单
                $total = Db::name('product_order')
                    ->where('user_id',$userInfo['user_id'])
                    ->where('order_status','neq',7)
                    ->where(("(pay_type = '微信' OR pay_type = '支付宝') OR (pay_type = '微信小程序' AND (order_status = 5 OR order_status = 6)) "))
                    ->where('user_is_delete',0)
                    ->count();

                $list = Db::view('product_order','id as order_id,order_no,pay_order_no,store_id,pay_money,pay_time,total_freight,order_status,address_status,pt_id,order_type,distribution_mode as express_mode,pt_type')
                    ->view('store','store_name,cover,type,type as store_type','store.id = product_order.store_id','left')
                    ->where('product_order.user_id',$userInfo['user_id'])
                    ->where('product_order.order_status','neq',7)
                    ->where(("(product_order.pay_type = '微信' OR product_order.pay_type = '支付宝') OR (product_order.pay_type = '微信小程序' AND (product_order.order_status = 5 OR product_order.order_status = 6)) "))
                    ->where('product_order.user_is_delete',0)
                    ->page($page,$size)
                    ->order('product_order.create_time','desc')
                    ->select();

                foreach ($list as $k=>$v) {
                    if($v['order_status']==8){
                        $list[$k]['order_status']=-1;
                    }
                    $list[$k]['pt_type'] = intval($v['pt_type']);
                    $list[$k]['pay_type'] = str_replace('小程序','',$list[$k]['pay_type']);
                    $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['order_id'])->count();
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
                    $product_info = Db::view('product_order_detail','product_id,specs_id,product_specs,product_name,number,realpay_money,status,price,platform_price,is_comment,is_shouhou,is_refund')
                        ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id','left')
                        ->where('product_order_detail.order_id',$v['order_id'])
                        ->select();
                    foreach ($product_info as $k2=>$v2){
                        $product_info[$k2]['realpay_money'] =$v2['realpay_money']/$v2['number'];
                        if($v2['realpay_money']==0){
                            $product_info[$k2]['realpay_money'] =$v2['price'];
                        }
                       // $product_info[$k2]['price'] = $v2['realpay_money']/$v2['number']+ $v2['platform_price'] ;
                        if($v['pay_time']>0 && ($v2['status']==-1 || $v2['status']==-2)){
                            $product_info[$k2]['status']=-1;
                        }elseif($v['pay_time']==0){
                            $product_info[$k2]['status']=0;
                        }
                        if($v['order_status']==8){
                            $product_info[$k2]['order_status']=-1;
                        }
                    }
                    $list[$k]['product_info'] = $product_info;

                }
                //返回不是售后类型0
                $data['is_shouhou']=0;
            }else{
                if($order_status == 2){
                    ##将未支付订单的平台券取消,价格恢复
                    UserLogic::cancelOrderNotPayPtCoupon($userInfo['user_id']);
                }
//------------------20190710增加以下过滤 对应以下的34
                if($order_status==2 || $order_status==3 || $order_status==4){
                    //只查询不是小程序端的待支付订单
                    $where3['pay_type'] = ['neq','微信小程序'];
                    $where2['product_order.pay_type'] = ['neq','微信小程序'];
                }
                if($order_status==2){
                    $order_status=1;//传递的代付款为2 转义代付款为1
                }
//------------------
                if($order_status==5){
                    //待评价订单列表
                    $order_ids = Db::name('product_order_detail')
                    ->join('product_order','product_order_detail.order_id = product_order.id','left')
                    ->where('product_order.order_status',$order_status)
                    ->where('product_order.user_is_delete',0)
                    ->where('product_order.order_status','neq',7)
                    ->where('product_order.user_id',$userInfo['user_id'])
                    ->where($where2)
                    ->where('product_order_detail.is_comment','eq',0)
                    ->where(" product_order_detail.status = 0  AND  product_order_detail.is_shouhou=0")
                    ->field('product_order_detail.order_id')
                    ->group('product_order_detail.order_id')
                    ->column('product_order_detail.order_id');
                $total = count($order_ids);
                $list =  Db::view('product_order','id as order_id,order_no,pay_order_no,store_id,pay_money,pay_time,total_freight,order_status,address_status,pt_id,order_type,distribution_mode as express_mode,pt_type')
                    ->view('store','store_name,cover,type,type as store_type','store.id = product_order.store_id','left')
                    ->where('product_order.id','in',$order_ids)
                    ->where('product_order.order_status',$order_status)
                    ->where('product_order.order_status','neq',7)
                    ->where($where2)
                    ->where('product_order.user_is_delete',0)
                    ->page($page,$size)
                    ->order('product_order.create_time','desc')
                    ->select();
                }else{
                    //其他状态
                    $total = Db::name('product_order')
                        ->where('user_id',$userInfo['user_id'])
                        ->where('order_status',$order_status)
                        ->where('product_order.order_status','neq',7)
//                    ->where($where1)
                        ->where($where3)
                        ->where('user_is_delete',0)
                        ->count();

                    $list = Db::view('product_order','id as order_id,order_no,pay_order_no,store_id,pay_money,pay_time,total_freight,order_status,address_status,pt_id,order_type,distribution_mode as express_mode,pt_type')
                        ->view('store','store_name,cover,type,type as store_type','store.id = product_order.store_id','left')
                        ->where('product_order.user_id',$userInfo['user_id'])
                        ->where('product_order.order_status',$order_status)
                        ->where('product_order.order_status','neq',7)
                        ->where($where2)
//                    ->where($where4)
                        ->where('product_order.user_is_delete',0)
                        ->page($page,$size)
                        ->order('product_order.create_time','desc')
                        ->select();
                }
                foreach ($list as $k=>$v) {
                    if($v['order_status']==8){
                        $list[$k]['order_status']=-1;
                    }
                    $list[$k]['pt_type'] = intval($v['pt_type']);
                    $list[$k]['pay_type'] = str_replace('小程序','',$list[$k]['pay_type']);
                    $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['order_id'])->count();
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
                    $product_info = Db::view('product_order_detail','product_id,specs_id,product_specs,product_name,status,number,price,platform_price,realpay_money,is_comment,is_shouhou,is_refund')
                        ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id','left')
                        ->where('product_order_detail.order_id',$v['order_id'])
                        ->select();
                    foreach ($product_info as $k2=>$v2){
                        $product_info[$k2]['realpay_money'] =$v2['realpay_money']/$v2['number'];
                        if($v2['realpay_money']==0){
                            $product_info[$k2]['realpay_money'] =$v2['price'];
                        }
                       // $product_info[$k2]['price'] = $v2['realpay_money']/$v2['number']+ $v2['platform_price'];
                        if($v['pay_time']>0 && ($v2['status']==-1 || $v2['status']==-2)){
                            $product_info[$k2]['status']=-1;
                        }elseif($v['pay_time']==0){
                            $product_info[$k2]['status']=0;
                        }
                        if($v['order_status']==8){
                            $product_info[$k2]['order_status']=-1;
                        }
                    }
                    $list[$k]['product_info'] = $product_info;
                }
                //返回不是售后类型0
                $data['is_shouhou']=0;
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list']= $list;
            return \json(self::callback(1,'查询成功',$data));

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

            return \json(self::callback(1,'删除成功',true));

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
     * 修改收货地址
     */
    public function editShouhuoAddress(){
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
                //更新收货地址
                $genxin = [
                    'shouhuo_address' => $shouhuo_address,
                    'shouhuo_mobile' =>$shouhuo_mobile,
                    'shouhuo_username' => $shouhuo_username
                ];
                $rst = Db::name('product_order')->where('id',$order_id)->update($genxin);
            }else{
                //新增收货地址
                $order->shouhuo_address = $shouhuo_address;
                $order->shouhuo_mobile = $shouhuo_mobile;
                $order->shouhuo_username = $shouhuo_username;
                $order->address_status = 1;
                $res = $order->save();
                if (!$res){
                    throw new \Exception('操作失败');
                }

            }
            return \json(self::callback(1,'操作成功',true));

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
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
    if(!$specs_id){
    //查询不是售后的订单详情
    $order_id = Db::name('product_order_detail')
        ->join('product_order','product_order.id = product_order_detail.order_id','left')
        ->where('product_order.user_id',$userInfo['user_id'])
        ->where('product_order_detail.order_id',$order_id)
        //->where('product_order_detail.is_shouhou','neq',1) //过滤掉售后订单
        ->group('product_order_detail.order_id')
        ->column('product_order_detail.order_id');

    $order_info = Db::view('product_order','id,order_no,pay_order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,coupon_id,coupon_money,store_coupon_id,store_coupon_money,distribution_mode,fahuo_time,confirm_time,address_status,pay_type,pay_time,store_id,pay_money,total_freight,order_status,create_time,is_group_buy,logistics_info,order_type,total_freight,pt_type,pt_id,finish_time,logistics_company,logistics_number,product_coupon_money,store_coupon_id')
        ->view('store','store_name,cover,type,type as store_type','store.id = product_order.store_id','left')
        ->where('product_order.id','in',$order_id)
        ->where('product_order.user_id',$userInfo['user_id'])
        ->find();
    $order_info['store_coupon_money'] += $order_info['product_coupon_money'];
    if (!$order_info) {
        throw new \Exception('订单不存在');
    }
        $order_info['pay_type'] = str_replace('小程序','',$order_info['pay_type']);

    //显示结束时间
    if($order_info['order_status']==1){
        $order_info['start_time']=$order_info['create_time'];
        $limit=config('config_order.hour_cancel_not_pay');
        $time_limit = $limit * 60 * 60;
        $order_info['end_time']=$order_info['start_time']+$time_limit;
    }elseif($order_info['order_status']==3){
        $order_info['start_time']=$order_info['pay_time'];
        $limit=config('config_order.hour_cancel_not_send');
        $time_limit = $limit * 60 * 60;
        $order_info['end_time']=$order_info['start_time']+$time_limit;
    }elseif ($order_info['order_status']==4){
        $order_info['start_time']=$order_info['fahuo_time'];
        $limit=config('config_order.hour_confirm_not_confirm');
        $time_limit = $limit * 60 * 60;
        $order_info['end_time']=$order_info['start_time']+$time_limit;
    }elseif($order_info['order_status']==5){
        //确认收货后3天自动评价时间
            $order_info['start_time']=$order_info['confirm_time'];
            $limit=config('config_order.hour_comment_not_comment');
            $time_limit = $limit * 60 * 60;
            $order_info['end_time']=$order_info['start_time']+$time_limit;
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
    $order_info['product_number'] = Db::name('product_order_detail')->where('order_id',$order_info['id'])->where('is_shouhou','neq',1)->count();
    $product_info = Db::view('product_order_detail','order_id,product_id,specs_id,product_specs,product_name,number,price,is_comment,is_shouhou,status,is_refund,realpay_money,comment_standard_time,finish_standard_time')
        ->view('product_specs','cover,platform_price','product_specs.id = product_order_detail.specs_id','left')
        ->where('product_order_detail.order_id',$order_info['id'])
       // ->where('is_shouhou','neq',1)
//        ->where('product_order_detail.status','neq',-1)
        ->select();

    if( $order_info['order_status'] ==8 ){
        $order_info['order_status'] =-1;
    }

    foreach ($product_info as $k=>$v){
       // $product_info[$k]['price'] = $v['price'] + $v['platform_price'];
        $product_info[$k]['price'] = $v['price'] ;
        if ($v['is_shouhou']!=0){
            //有售后订单
            $shouhou = Db::name('product_shouhou')
                ->field('id,description,refuse_description,refund_reason,goods_status,shouhuo_address,shouhuo_username,shouhuo_mobile,logistics_company,logistics_number,agree_time,fahuo_time,refuse_time')
                ->where('order_id',$v['order_id'])
                ->where('specs_id',$v['specs_id'])
                ->find();
            $product_info[$k]['description'] = $shouhou['description'];
            $product_info[$k]['refuse_description'] = $shouhou['refuse_description'];
            $product_info[$k]['refund_reason'] = $shouhou['refund_reason'];
            $product_info[$k]['goods_status'] = $shouhou['goods_status'];
            $product_info[$k]['shouhuo_address'] = $shouhou['shouhuo_address'];
            $product_info[$k]['shouhuo_username'] = $shouhou['shouhuo_username'];
            $product_info[$k]['shouhuo_mobile'] = $shouhou['shouhuo_mobile'];
            $product_info[$k]['logistics_company'] = $shouhou['logistics_company'];
            $product_info[$k]['logistics_number'] = $shouhou['logistics_number'];
            $product_info[$k]['agree_time'] = $shouhou['agree_time'];
            $product_info[$k]['fahuo_time'] = $shouhou['fahuo_time'];
            $product_info[$k]['refuse_time'] = $shouhou['refuse_time'];
            $product_info[$k]['shouhou_img'] = Db::name('product_shouhou_img')->where('shouhou_id',$shouhou['id'])->column('img_url');
        }
        if(isset($v['is_comment'] ) && $v['is_comment']==1){
            $order_info['start_time']=$v['finish_standard_time'];
            $limit=config('config_order.hour_finish');
            $time_limit = $limit * 60 * 60;
            $order_info['end_time']=$order_info['start_time']+$time_limit;
        }

        if($order_info['pay_time']>0 && ($v['status']==-1 || $v['status']==-2)){
            $product_info[$k]['status']=-1;
        }elseif($order_info['pay_time']==0){
            $product_info[$k]['status']=0;
        }
        if( $order_info['order_status'] ==8 ){
            $product_info['order_status'] =-1;
        }
    }
    $order_info['product_info'] = $product_info;
}else {
//查询售后订单详情

    if (!$order_id || !$specs_id) {
        return \json(self::callback(0,'参数错误'),400);
    }
    $product_order = Db::view('product_order_detail','order_id,cover,product_name,product_id,specs_id,product_specs,number,price,is_shouhou,is_refund,refund_time,refund_money,realpay_money')
        ->view('product_shouhou','id,description,refuse_description,return_mode,create_time,refund_type,goods_status,refund_status,refund_reason,logistics_company,logistics_number','product_order_detail.order_id = product_shouhou.order_id','left')
        ->view('product_order','order_no,create_time,pay_money,order_status,shouhuo_username,coupon_id,coupon_money,store_coupon_id,store_coupon_money,shouhuo_mobile,distribution_mode,shouhuo_address,store_id,coupon_money,total_freight,pay_type,pay_time,fahuo_time,logistics_info,total_platform_price,finish_time','product_order_detail.order_id = product_order.id','left')
        ->view('store','store_name,cover,type,refund_address,refund_name,refund_mobile','store.id = product_order.store_id','left')
        ->where('product_order_detail.order_id',$order_id)
        ->where('product_order_detail.is_shouhou',1)
        ->where('product_order_detail.specs_id',$specs_id)
        ->find();
    if (!$product_order) {
        throw new \Exception('订单不存在');
    }
    //查询所有图片
    $images=Db::name('product_shouhou_img')->where('shouhou_id',$product_order['id'])->select();
    $data['product_info']=$product_order;
    $data['product_info']['images']=$images;
    $order_info= $data;
        }
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
     * 申请售后
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
//            $goods_status = input('goods_status') ? intval(input('goods_status')) : 2 ;
//            $refund_type = input('refund_type');
//            $goods_status = input('goods_status');
            $refund_reason = trim(input('refund_reason'));
           // $refund_times = input('refund_times') ? intval(input('refund_times')) : 1 ;//次数
            $files = $this->request->file('img');

            if (!$order_id || !$specs_id ||  !$refund_reason ) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->find();
            if (!$order){
                throw new \Exception('该订单不存在');
            }
            if ($order->order_status != 5 && $order->order_status != 6 && $order->order_status != 7 && $order['order_status'] != 8) {
                throw new \Exception('该订单不支持此操作');
            }
            $shouhou_order_status=$order['order_status'];
            $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->where('status','neq',-1)->find();
            if (!$product) {
                throw new \Exception('该商品不存在');
            }
            //判断是否有申请且时间未过期
            $shouhou= Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->order('id','desc')->select();
            if($shouhou[0]['refund_status']==1 || $shouhou[0]['refund_status']==2 || $shouhou[0]['refund_status']==3 ||$shouhou[0]['refund_status']==4){
                throw new \Exception('售后已在处理中或已完成!');
            }
            if(count($shouhou)>=2){throw new \Exception('售后申请次数已达上限,请联系平台介入!');}
            if($shouhou[0]['refund_status']!=-1 && $shouhou[0]['refund_status']!=-2){
                $time=time()-(3*24*60*60);
                $oldtime=$shouhou[0]['create_time'];
                if($time<=$oldtime){return \json(self::callback(0,'你已提交过售后申请,请耐心等待商家审核!',false));}
            }
            if(isset($files)&&!empty($files)){
                //循环图片
                foreach ($files as $key=>$file){
                    $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.'shouhou');
                    if($info){
                        $img[$key]['shouhou_id'] = &$shouhou_id;
                        $img_url = DS.'uploads'.DS.'shouhou'.DS.$info->getSaveName();
                        $img[$key]['img_url'] = str_replace(DS,"/",$img_url);
                    }else{
                        return json(self::callback(0,$file->getError()));
                    }
                }
            }
            $shouhou_id = Db::name('product_shouhou')->insertGetId([
                'order_id' => $order_id,
                'product_id' => $product['product_id'],
                'specs_id' => $specs_id,
                'store_id' =>$order['store_id'],
                'user_id' =>$userInfo['user_id'],
                'goods_status' => 2,
                'description' => $refund_reason,
                'refund_reason' => $refund_reason,
                'refund_status' => 1,
                'create_time' => time()
            ]);
            $shouhou_id = intval($shouhou_id);
            Db::name('product_shouhou_img')->insertAll($img);
            $genxin=[
                'shouhou_order_status' => $shouhou_order_status,
                'is_shouhou'=>1
            ];
            Db::name('product_order_detail')->where('id',$product['id'])->update($genxin);
            //判断是否该订单全部退款/退货退款
            $num = Db::name('product_order_detail')->field('is_comment,status,is_shouhou')->where('order_id',$order_id)->select();
            $num1=count($num);
            $num2=0;
            $num3=0;
            $num4=0;
            foreach ($num as $k=>$v){
               if($v['status']!=0 || $v['is_shouhou']!=0){
                   $num2++;
               }
                if($v['is_comment']==1 && $v['status']==0 && $v['is_shouhou']==0){
                    $num3+=1;
                }
                if($v['is_comment']==0 && $v['status']==0 && $v['is_shouhou']==0){
                    $num4+=1;
                }
            }
            if($num1==$num2){
                Db::name('product_order')->where('id',$order_id)->setField('order_status',7);
            }
            //如果最后一个申请售后前面的都评论了也改为已完成
            if($num4==0 && $num3>0){
                Db::name('product_order')->where('id',$order_id)->setField('order_status',6);
            }
            return \json(self::callback(1,'售后提交成功',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 填写物流信息
     */
    public function logistics_info(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            $logistics_company = input('logistics_company');
            $logistics_number = input('logistics_number');
            if (!$order_id || !$specs_id || !$logistics_company || !$logistics_number) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $order = Db::view('product_order','id,order_no,store_id,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address')
             ->view('store','store_name,refund_name,refund_address,refund_mobile','store.id = product_order.store_id','left')
                ->where('product_order.id',$order_id)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->find();
            if (!$order){
                throw new \Exception('该订单不存在');
            }
            if ( $order['order_status'] != 5 && $order->order_status != 6 && $order['order_status'] != 7 && $order['order_status'] != 8) {
                throw new \Exception('该订单不支持此操作');
            }
            $product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->where('is_shouhou','neq',0)->where('status','neq',-1)->find();
            if (!$product) {
                throw new \Exception('该商品不存在');
            }
            $shouhou = Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->order('id','desc')->find();
            if ($shouhou['refund_status'] != 2) {
                throw new \Exception('商家还未同意，请等待...');
            }
            if ($shouhou['refund_type'] == 1) {
                throw new \Exception('无需退货...');
            }
            $genxin = [
                'shouhuo_username' => $order['shouhuo_username'],
                'shouhuo_mobile' => $order['shouhuo_mobile'],
                'shouhuo_address' => $order['shouhuo_address'],
                'logistics_company' => $logistics_company,
                'logistics_number' => $logistics_number,
                'fahuo_time' => time(),
                'refund_status'=>3   //订单改为已发货
            ];
            $rst = Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->update($genxin);
            if($rst===false){
                return \json(self::callback(0,'填写物流信息失败'));
            }else{
                return \json(self::callback(1,'填写物流信息成功'));
            }
            }catch (\Exception $e){
                return \json(self::callback(0,$e->getMessage()));
            }
    }
    /**
     * 售后详情
     */
    public function refundDetail(){
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            if (!$order_id || !$specs_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $orderModel = new ProductOrder();
            $order = $orderModel->where('id',$order_id)->find();
            if (!$order){throw new \Exception('该订单不存在');}
            if ( $order->order_status != 5 && $order->order_status != 6 && $order->order_status != 7 && $order->order_status != 8) {throw new \Exception('该订单不支持此操作');}
            $refund_info = Db::view('product_order_detail','order_id,product_id,specs_id,product_specs,cover,product_name,status,number,price,is_comment,is_shouhou,is_refund,realpay_money')
               ->view('product_shouhou','id,refund_reason,refuse_description,return_mode,create_time,refund_type,goods_status,refund_status,shouhuo_address,shouhuo_username,shouhuo_mobile,logistics_company,logistics_number,agree_time,fahuo_time,finish_time','product_order_detail.order_id = product_shouhou.order_id and  product_order_detail.specs_id = product_shouhou.specs_id','left')
                ->view('product_order','order_no,pay_order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,address_status,pay_type,pay_time,store_id,pay_money,coupon_id,coupon_money,total_freight,order_status,is_group_buy,logistics_company,logistics_number,order_type,total_freight,pt_type,pt_id','product_order_detail.order_id = product_order.id','left')
                ->view('store','store_name,cover as store_logo,type,refund_name,refund_address,refund_mobile','store.id = product_order.store_id','left')
                ->where('product_shouhou.order_id',$order_id)
                ->where('product_shouhou.specs_id',$specs_id)
                ->where('product_shouhou.user_id',$userInfo['user_id'])
                ->order('product_shouhou.id ','desc')
//                ->limit(1)
                ->find();

            if (!$refund_info) {
                throw new \Exception('售后订单不存在');
            }
            if( $refund_info['order_status'] ==8 ){
                $refund_info['order_status'] =-1;
            }
            if($refund_info['pay_time']>0 && ($refund_info['status']==-1 || $refund_info['status']==-2)){
                $refund_info['status']=-1;
            }elseif($refund_info['pay_time']==0){
                $refund_info['status']=0;
            }
            $refund_info['price']=$refund_info['realpay_money']/$refund_info['number'];
            $times = Db::view('product_order_detail','order_id')
                ->view('product_shouhou','id','product_order_detail.order_id = product_shouhou.order_id and  product_order_detail.specs_id = product_shouhou.specs_id','left')
                ->view('product_order','order_no','product_order_detail.order_id = product_order.id','left')
                ->view('store','store_name','store.id = product_order.store_id','left')
                ->where('product_shouhou.order_id',$order_id)
                ->where('product_shouhou.specs_id',$specs_id)
                ->where('product_shouhou.user_id',$userInfo['user_id'])
                ->count();
            if($times==1){
                //可再次申请
                $refund_info['can_shouhou']=1;
            }elseif ($times>=2){
                //不可再次申请
                $refund_info['can_shouhou']=-1;
            }
            if($times==1 && ($refund_info['refund_status']==-1 || $refund_info['refund_status']==-2)){
                $refund_info['can_shouhou']=1;
            }else{
                $time=(3*24*60*60);//三天
                $now=$time+$refund_info['create_time'];
                if($refund_info['refund_status']==1 && (time()>$now)){
                    //不可再次申请
                    $refund_info['can_shouhou']=-1;
                }
            }
            if( $refund_info['order_status'] ==-1 ){
                $refund_info['can_shouhou']=-1;
            }
            $refund_info['now_time']=time();//返回当前时间
            //查询售后电话
            $refund_info['kefu_mobile'] = Db::name('about_us')->where('id',1)->value('mobile');
            $refund_info['refund_img']=Db::name('product_shouhou_img')->where('shouhou_id',$refund_info['id'])->column('img_url');
            return \json(self::callback(1,'获取售后详情信息成功',$refund_info));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 提交订单优惠券列表
     */
    public function orderCouponlist(){

        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $type = input('type') ? intval(input('type')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            if (!$store_id){
                return \json(self::callback(0,'参数错误',false));
            }
            $time2=time();
            if($type==1){
            //查询平台
                $where1['coupon_rule.type'] = ['eq',$type];
            }else if($type==2){
                //查询商家
                $where1['coupon_rule.type'] = ['eq',$type];
                $where2['coupon_rule.store_id'] = ['eq',$store_id];
            }else{
                //全部
                $where1['coupon_rule.type'] = ['in','1,2'];
            }
            //查询用户优惠券
            $coupons =  Db::view('coupon','id,user_id')
                ->view('coupon_rule','store_id,store_name,coupon_name,satisfy_money,coupon_money,start_time,end_time,type,coupon_type','coupon_rule.id = coupon.coupon_id','left')
                ->where('coupon.user_id',$userInfo['user_id'])
                ->where('coupon_rule.coupon_type','in','1,2,3')
//                ->where('coupon_rule.end_time','gt',$time2)
                ->where(("(coupon_rule.end_time>$time2  OR coupon.expiration_time > $time2)"))
                ->where('coupon_rule.is_open',1)
                ->where('coupon.status',1)
                ->where($where1)
                ->where($where2)
                ->page($page,$size)
                ->order('coupon.id','desc')
                ->select();
            $total=count($coupons);//总条数
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            if($coupons){
                $data['list']=$coupons;
            }else{
                $data=[];
            }
            return \json(self::callback(1,'',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 选择优惠券
     */
    public function chooseCoupon(){

        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $store_id = $this->request->has('store_id') ? intval($this->request->param('store_id')) : 0 ;
            if (!$store_id){
                return \json(self::callback(0,'参数错误',false));
            }
            $time2=time();
            //查询平台优惠券
            $coupon['ptcoupon'] =  Db::view('user_css_coupon','user_id')
                ->view('css_coupon','id,store_id,store_name,coupon_name,satisfy_money,coupon_money,start_time,end_time,type,coupon_type','css_coupon.id = user_css_coupon.coupon_id','left')
                ->where('user_css_coupon.user_id',$userInfo['user_id'])
                ->where('css_coupon.coupon_type','in','1,2')
                ->where('css_coupon.end_time','gt',$time2)
                ->where('css_coupon.status',1)
                ->select();
            if($coupon['ptcoupon']){

            }else{
                $data['ptcoupon']=[];
            }
            //查询店铺优惠券
            $coupons = Db::name('user_css_coupon')->field('id,coupon_id,store_id')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->where('status',1)->select();
            if ($coupons) {
                foreach ($coupons as $k=>$v){
                    $time=time();
                    $coupon[$k]['storecoupon'] =  Db::view('css_coupon','id,store_id,store_name,coupon_name,satisfy_money,coupon_money,start_time,end_time,type,coupon_type')
                        ->view('user_css_coupon','user_id','css_coupon.id = user_css_coupon.coupon_id','left')
                        ->where('css_coupon.id',$v['coupon_id'])
                        ->where('css_coupon.end_time','gt',$time)
                        ->where('css_coupon.status',1)
                        ->find();
                }
                $data=$coupon;
            }else{
                $data['storecoupon']=[];
            }
            return \json(self::callback(1,'',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
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
            if(isset($param['store_id']) && isset($param['specs_id']) && isset($param['num'])){
            //单个商品购买
                $store_id=intval($param['store_id']);
                $num=intval($param['num']);
                $specs_id=intval($param['specs_id']);
                $store_info = Db::name('store')->field('id,store_name,cover as store_logo')->where('id', $store_id)->find();
                $order_info= Db::name('product_specs')->field('id as specs_id,cover,product_id,product_name,stock,product_specs,price')->where('id', $specs_id)->find();
                $order_info['number'] = $num;
                $order_info['show_product_specs'] = str_replace("{", '', $order_info['product_specs']);
                $order_info['show_product_specs'] = str_replace("}", '', $order_info['show_product_specs']);
                $order_info['show_product_specs'] = str_replace("\"", '', $order_info['show_product_specs']);
                //运费
                $order_info['freight'] = Db::name('product')->where('id', $order_info['product_id'])->value('freight');
                $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id', $order_info['product_id'])->order('id asc')->select();
                //查询默认id值
                $order_info['product_specs'] = json_decode($order_info['product_specs']);
                foreach ($key as $k2 => $v2) {
                    $key[$k2]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id', $v2['id'])->select();
                    $vv = $key[$k2]['value'];
                    foreach ($vv as $k4 => $v4) {
                        foreach ($order_info['product_specs'] as $k5 => $v5) {
                            if ($v5 == $v4['attribute_value']) {
                                $value_id[$k2]['id'] = $v2['id'];
                                $value_id[$k2]['value'] = $v4['id'];
                            }
                        }
                    }
                }
                $order_info['key_value'] = $value_id;
                $order_info['specs'] = $key;
                $store_info['order_info'] = $order_info;
                $list['$store_info'] = $store_info;
                //返回数据
                $data = $list['$store_info'];
                return \json(self::callback(1, '', $data));

            }else{
                $order_info = $param['order_info'];
                //循环取出对应商品信息
                $store_info = [];
                foreach ($order_info as $k => $v) {
                    if (empty($v)) {
                        //等于空不作处理
                    } else {
                        //循环取出每家店的商品信息
                        //查询店铺信息
                        $store_info[$k] = Db::name('store')->field('id,store_name,cover as store_logo')->where('id', $k)->find();
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
                                }
                            } else {
                                //报错
                            }
                            $store_info[$k]['freight'] = $freight;
                            $store_info[$k]['order_info'] = $order_info;
                            $list['$store_info'] = $store_info;
                            //返回数据
                            $data = $list['$store_info'];
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
                            }
                            //----店铺
                        }
                        $store_info[$k]['freight']=max($yunfei[$k]);
                        $store_info[$k]['order_info'] = $order_info;
                    }
                }
            }

            $list['$store_info'] = $store_info;
            //返回数据
            $data=$list['$store_info'];
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 生成买单订单并调起支付
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \WxPayException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function maidanOrder(UserValidate $UserValidate){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'请求失败'));

        $res = $UserValidate->scene('maidan')->check(input());
        if(!$res)return \json(self::callback(0,$UserValidate->getError()));

        ##token 验证
        $userInfo = User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $user_id = input('post.user_id',0,'intval');
        $store_id = input('post.store_id',0,'intval');
        $price_yj = input('post.price_yj',0,'floatval');
        $pay_type = input('post.pay_type',1,'addslashes,strip_tags,trim');  //支付方式
        $user_type = input('post.user_type',1,'intval');
        $pay_type = $pay_type?strtolower($pay_type):$pay_type;
        $is_discount = input('post.is_discount',false);  //true是折扣购买；false是原价购买
        $is_discount = $is_discount=='false'?false:true;
        $be_member = input('post.be_member',0,'intval'); //购买月卡会员 0.不购买会员；1.购买会员
        if($price_yj < 0.01)return \json(self::callback(0,'支付金额不能小于0.01元'));
        if($be_member && $userInfo['end_time'] > time())return \json(self::callback(0,'当前用户已经是会员了'));

        ##获取店铺信息
        $store_info = Logic::StoreInfo($store_id);
        if(!$store_info || !$store_info['store_status'])return \json(self::callback(0,'店铺不存在或已下架'));

        if($is_discount){
            ##获取折扣
            $maidan_info = Logic::maidanInfo($store_id);

            ##判断用户是否会员
            $is_member = $userInfo['end_time'] > time() ? 2 : 1;
            if($is_member<=1 && $user_type ==2)return \json(self::callback(0,'您的会员已到期'));
            $discount = $is_member>1?$maidan_info['member_user']:(!$be_member?$maidan_info['putong_user']:$maidan_info['member_user']);
            $price_maidan = $price_yj==0.01? $price_yj : (ceil($price_yj * $discount / 10 * 100) / 100);
            if($price_maidan < 0.01) return \json(self::callback(0,'支付金额不能小于0.01元'));
        }else{
            $price_maidan = $price_yj;
            $discount = 10;
            $is_member = 0;
        }

        Db::startTrans();
        try{
            $member_order_id = 0;
            if($be_member){  //勾选购买会员
                $price_member = Logic::monthMemberPrice();
                $data_member = [
                    'order_no' => build_order_no('M'),
                    'user_id' => $user_id,
                    'pay_money' => $price_member,
                    'create_time' => time(),
                    'member_card_id' => 1,
                    'status' => 1
                ];
                ##添加会员购买订单
                $res = UserLogic::addMemberOrder($data_member);
                if($res === false)throw new Exception('会员购买订单创建失败');

                $member_order_id = $res;
            }

            ##添加订单
            $order_sn = build_order_no('MD');
            $data = compact('price_yj','price_maidan','user_id','store_id','discount','is_member','order_sn','pay_type','member_order_id');
            $data['create_time'] = time();
            $res = UserLogic::addMaidanOrder($data);
            if($res === false)throw new Exception('订单生成失败');
            $data['order_id'] = $res;
            $data['price_pay'] = $price_maidan;
            if($be_member){
                $price_maidan += $price_member;
                $data['price_member'] = $price_member;
                $data['price_pay'] += $price_member;
            }
            ##调起支付
            switch($pay_type){
                case 'wx':
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/maidan_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_sn,$price_maidan,$notify_url);
                    break;
                case 'zfb':
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/maidan_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_sn,$price_maidan,$notify_url);
                    break;
                default:
                    throw new Exception('支付方式不存在');
                    break;
            }

            Db::commit();

            return \json(self::callback(1,'调起支付成功',$data));

        }catch(Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));

        }

    }
    /**
     * 生成买单订单并调起支付
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \WxPayException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function couponmaidanOrder(UserValidate $UserValidate){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'请求失败'));
        $res = $UserValidate->scene('maidan')->check(input());
        if(!$res)return \json(self::callback(0,$UserValidate->getError()));

        ##token 验证
        $userInfo = User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $user_id = input('post.user_id',0,'intval');
        $store_id = input('post.store_id',0,'intval');
        $price_yj = input('post.price_yj',0,'floatval');
        $pay_type = input('post.pay_type',1,'addslashes,strip_tags,trim');  //支付方式
        $user_type = input('post.user_type',1,'intval');
        $coupon_id = input('post.coupon_id',0,'intval');//优惠券id
        $pay_type = $pay_type?strtolower($pay_type):$pay_type;
        $is_discount = input('post.is_discount',false);  //true是折扣购买；false是原价购买
        $is_discount = $is_discount=='false'?false:true;
        $be_member = input('post.be_member',0,'intval'); //购买月卡会员 0.不购买会员；1.购买会员
        if($price_yj < 0.01)return \json(self::callback(0,'支付金额不能小于0.01元'));
        if($be_member && $userInfo['end_time'] > time())return \json(self::callback(0,'当前用户已经是会员了'));
        $price_yj_2 = $price_yj;
        if($coupon_id && $coupon_id>0){
            $coupon_info = Db::view('coupon','id,user_id,coupon_id,status,create_time,satisfy_money,coupon_money,expiration_time')
                ->view('coupon_rule','store_id,start_time,end_time,type,coupon_type,can_stacked,type','coupon.coupon_id = coupon_rule.id','left')
                ->where('coupon.id',$coupon_id)
                ->where('coupon.status',1)
                ->where('coupon_rule.can_stacked',1)
                ->where('coupon.expiration_time','gt',time())
                ->where('coupon.user_id',$userInfo['user_id'])
                ->find();
           if(!$coupon_info || $coupon_info['can_stacked'] == -1)return \json(self::callback(0,'该优惠券不存在或不可使用'));
            if($coupon_info['satisfy_money']==0 && $coupon_info['coupon_money']>=$price_yj)return \json(self::callback(0,'商品价格不能小于减的金额'));
            if($coupon_info['satisfy_money']>0 && $coupon_info['satisfy_money']>$price_yj)return \json(self::callback(0,'商品价格不能小于满的金额'));

            $coupon_money = $coupon_info['coupon_money'];

        }else{
            $coupon_money=0;
        }
        //判断小数位数
//        $lenth=getFloatLength($new_price);
//        if($lenth>=2){
//            $new_price=round($new_price,2);
//        }else{
//            $new_price=round($new_price,1);
//        }

        /**
         *
         * 使用店铺优惠券时：

        1、实付款=（商品原价-店铺优惠券）x店铺折扣x平台会员折扣；

        2、平台提成=（商品原价-店铺优惠券）x店铺折扣x平台扣点；

        3、商家实收=（商品原价-店铺优惠券）x店铺折扣-平台提成；

        4、可省=（商品原价-店铺优惠券）x店铺折扣x（1-平台会员折扣）

        使用平台优惠券时：

        1、实付款=（商品原价x店铺折扣-平台优惠券）x平台会员折扣；

        2、平台提成=商品原价x店铺折扣x平台扣点；

        3、商家实收=商品原价x店铺折扣-平台提成；

        4、可省=（商品原价x店铺折扣-平台优惠券）x（1-平台会员折扣）
         *
         */

        ##获取店铺信息
        $store_info = Logic::StoreInfo($store_id);
        if(!$store_info || !$store_info['store_status'])return \json(self::callback(0,'店铺不存在或已下架'));
        if($is_discount){
            ##获取折扣
            $maidan_info = Logic::maidanInfo($store_id);
            $maidan_info['putong_user'] = $maidan_info['putong_user'] / 10;
            $maidan_info['member_user'] = $maidan_info['member_user'] / 10;
            ##判断用户是否会员
            $is_member = $userInfo['end_time'] > time() ? 2 : 1;
            if($is_member<=1 && $user_type ==2)return \json(self::callback(0,'您的会员已到期'));

            $discount_type = $is_member>1?2:(!$be_member?1:2);

//            $discount = $is_member>1?$maidan_info['member_user'] * $maidan_info['putong_user']:(!$be_member?$maidan_info['putong_user']:$maidan_info['member_user'] * $maidan_info['putong_user']);
//            $lenth1=getFloatLength($discount);
//            if($lenth1>=2){
//                $discount=round($discount,2);
//            }else{
//                $discount=round($discount,1);
//            }

//            $price_maidan = $new_price == 0.01? $new_price : ($new_price * $discount / 10 * 100 / 100);
//            if($price_maidan < 0.01) return \json(self::callback(0,'支付金额不能小于0.01元'));
            $discount = $maidan_info['putong_user'];
            $discount_platform = $discount_type == 1?1:$maidan_info['member_user'];
            if($coupon_id > 0){  ##使用了优惠券
                if($coupon_info['type'] == 1){  ##平台券
                    ##计算平台提成
                    $platform_profit = round($price_yj_2 * $maidan_info['putong_user'] * $store_info['maidan_deduct'] / 100 + 0.00001,2);
                    ##计算商家实收
                    $price_store = round($price_yj_2 * $maidan_info['putong_user'] - $platform_profit + 0.00001,2);
                    ##计算用户实付
                    if($discount_type == 1){  ##普通折扣
                        $price_maidan = ($price_yj_2 * $maidan_info['putong_user'] - $coupon_money);
                    }else{  ##普通折扣 * 会员折扣
                        $price_maidan = ($price_yj_2 * $maidan_info['putong_user'] - $coupon_money) * $maidan_info['member_user'];
                    }

                    ##判断优惠券是否足够
                    if($coupon_info['satisfy_money'] > 0 && ($price_yj_2 * $maidan_info['putong_user']) < $coupon_info['satisfy_money'])return \json(self::callback(0,'买单折扣后价格小于优惠券使用金额'));
                    if($coupon_info['coupon_money'] >= ($price_yj_2 * $maidan_info['putong_user']))return \json(self::callback(0,'优惠券面额必须小于买单折扣后价格'));

                    ##计算商家优惠金额
                    $store_policy = round($price_yj_2 - $price_yj_2 * $maidan_info['putong_user'] + 0.00001,2);

                }else{  ##店铺券
                    ##计算平台提成
                    $platform_profit = round(($price_yj_2 - $coupon_money) * $maidan_info['putong_user'] * $store_info['maidan_deduct'] / 100 + 0.00001,2);
                    ##计算店铺实收
                    $price_store = round(($price_yj_2 - $coupon_money) * $maidan_info['putong_user'] - $platform_profit + 0.00001,2);
                    ##计算用户实付
                    if($discount_type == 1){  ##普通折扣
                        $price_maidan = ($price_yj_2 - $coupon_money) * $maidan_info['putong_user'];
                    }else{  ##普通折扣 * 会员折扣
                        $price_maidan = ($price_yj_2 - $coupon_money) * $maidan_info['putong_user'] * $maidan_info['member_user'];
                    }

                    ##计算商家优惠金额
                    $store_policy = round(($price_yj_2 - $coupon_money) * (1 - $maidan_info['putong_user']) + $coupon_money + 0.00001,2);

                }

            }else{  ##未使用优惠券
                ##计算平台提成
                $platform_profit = round($price_yj_2 * $maidan_info['putong_user'] * $store_info['maidan_deduct'] / 100 + 0.00001,2);
                ##计算店铺实收
                $price_store = round($price_yj_2 * $maidan_info['putong_user'] - $platform_profit + 0.00001,2);
                ##计算用户实付
                if($discount_type == 1) {  ##普通折扣
                    $price_maidan = $price_yj_2 * $maidan_info['putong_user'];
                }else{  ##普通折扣 * 会员折扣
                    $price_maidan = $price_yj_2 * $maidan_info['putong_user'] * $maidan_info['member_user'];
                }

                ##计算商家优惠金额
                $store_policy = round($price_yj_2 * (1 - $maidan_info['putong_user']) + 0.00001,2);
            }

            $discount = $discount * 10;
            $discount_platform = $discount_platform * 10;

        }else{  ##未使用折扣

            $discount = $discount_platform = 10;

            if($coupon_id > 0) {  ##使用了优惠券
                if($coupon_info['type'] == 1){  ##平台券
                    ##计算平台提成
                    $platform_profit = round($price_yj_2 * $store_info['maidan_deduct'] / 100 + 0.00001,2);
                    ##计算店铺实收
                    $price_store = $price_yj_2 - $platform_profit;

                    ##计算商家优惠金额
                    $store_policy = 0;
                }else{  ##商家券
                    ##计算平台提成
                    $platform_profit = round((($price_yj_2 - $coupon_money) * $store_info['maidan_deduct'] / 100) + 0.00001,2);
                    $test2 = ($price_yj_2 - $coupon_money) * $store_info['maidan_deduct'] / 100;
                    $test = compact('price_yj_2','coupon_money','platform_profit','test2');
                    $test['maidan_deduct'] = $store_info['maidan_deduct'];
                    
                    ##计算店铺实收
                    $price_store = $price_yj_2 - $coupon_money - $platform_profit;

                    ##计算商家优惠金额
                    $store_policy = $coupon_money;
                }

                ##计算用户实付
                $price_maidan = $price_yj_2 - $coupon_money;
            }else{  ##未使用优惠券
                ##计算平台提成
                $platform_profit = round($price_yj_2 * $store_info['maidan_deduct'] / 100 + 0.00001,2);
                ##计算商家实收
                $price_store = $price_yj_2 - $platform_profit;
                ##计算用户实付
                $price_maidan = $price_yj_2;

                ##计算商家优惠金额
                $store_policy = 0;
            }

            $is_member = 0;
        }

        $lenth2=getFloatLength($price_maidan);

        if($lenth2>=2){
            $price_maidan=round($price_maidan,2);
        }else{
            $price_maidan=round($price_maidan,1);
        }

        ##计算平台优惠金额
        $platform_policy = round($price_yj_2 - $price_maidan - $store_policy,2);

        if($price_maidan < 0.01)return \json(self::callback(0,'买单金额不能小于0.01元'));

        Db::startTrans();
        try{
            $member_order_id = 0;

            $price_maidan_front = input('post.price_maidan',0,'floatval');

            if($be_member){  //勾选购买会员
                $price_member = Logic::monthMemberPrice();
                $data_member = [
                    'order_no' => build_order_no('M'),
                    'user_id' => $user_id,
                    'pay_money' => $price_member,
                    'create_time' => time(),
                    'member_card_id' => 1,
                    'status' => 1
                ];

                ##判断前后端的计算金额

                if(round($price_maidan_front,2) != round($price_maidan + $price_member,2))throw new Exception("价格计算异常,传入价{$price_maidan_front},计算价" . ($price_maidan + $price_member));

                ##添加会员购买订单
                $res = UserLogic::addMemberOrder($data_member);
                if($res === false)throw new Exception('会员购买订单创建失败');
                $member_order_id = $res;
            }else{
                ##判断前后端的计算金额
                if(round($price_maidan_front,2) != round($price_maidan,2))throw new Exception("价格计算异常,传入价{$price_maidan_front},计算价{$price_maidan}");
            }

            ##添加订单
            $order_sn = build_order_no('MD');
            $user_mobile = $userInfo['mobile'];

            $data = compact('price_yj','price_maidan','user_id','store_id','discount','is_member','order_sn','pay_type','member_order_id','coupon_id','coupon_money','platform_profit','price_store','discount_platform','platform_policy','store_policy','user_mobile');
            $data['create_time'] = time();
            $res = UserLogic::addMaidanOrder($data);
            if($res === false)throw new Exception('订单生成失败');
            $data['order_id'] = $res;
            $data['price_pay'] = $price_maidan;
            if($be_member){
                $price_maidan += $price_member;
                $data['price_member'] = $price_member;
                $data['price_pay'] += $price_member;
            }
            ##调起支付
            switch($pay_type){
                case 'wx':
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/maidan_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_sn,$price_maidan,$notify_url);
                    break;
                case 'zfb':
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/maidan_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_sn,$price_maidan,$notify_url);
                    break;
                default:
                    throw new Exception('支付方式不存在');
                    break;
            }

            Db::commit();

            return \json(self::callback(1,'调起支付成功',$data));

        }catch(Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));

        }

    }

    /**
     * 提交订单
     */
    public function submitOrder_old201911(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $userInfo = User::checkToken($post['user_id'],$post['token']);
            if ($userInfo instanceof Json){
                return $userInfo;
            }
//            Log::info(print_r($post,true));
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
            $pay_type = $post['pay_type'] ? intval($post['pay_type']) : '';      //收货地址

            if(!$pay_type)throw new Exception('请选择支付方式');
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

//            $coupon_money = 0 ; //优惠券金额
            //是否有优惠券
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
//            $store_number = count($store_info);   //店铺数量

            $coupon_data = [];
            $store_coupon_ids = [];
            foreach ($store_info as $k=>$v){
                if(isset($v['store_coupon_id']) && $v['store_coupon_id'])$store_coupon_ids[] = $v['store_coupon_id'];
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
                    $product_info[$k2]['huoli_money'] = $product_specs['huoli_money'] * $v2['number'];
                    $product_info[$k2]['days'] = $product_specs['days'];
                    $total_huoli_money += $product_info[$k2]['huoli_money'];   //单个商品总代购费
                    $total_platform_price += $product_specs['platform_price'];
                    $coupon_data[$v['store_id']]['product'][] = $product_info[$k2];
                }
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
                $coupon_data[$v['store_id']]['data'] = [
                    'user_id' => $post['user_id'],
                    'store_coupon_id' => $v['store_coupon_id']?:0,
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
            }

            ##判断优惠券的叠加
            if($coupon_id && !empty($store_coupon_ids)){
                $is_superposition_pt = Logic::getCouponSuperpositionAndExpireTime($coupon_id);
                if(!$is_superposition_pt || $is_superposition_pt == 1)throw new Exception('该平台优惠券已不能使用');
                $store_coupon_count = Logic::getNotSuperpositionCoupon($store_coupon_ids);
                if($store_coupon_count < count($store_coupon_ids))throw new Exception('当前店铺优惠券已不可使用');
            }
            //Log::info(print_r($coupon_data,true));
            $last_money_tt = 0;  //剩余价格总价

            $pay_money_tt = 0 ; //支付总价格(产品总价 + 运费 - 优惠券)

            foreach($coupon_data as $k => &$v){
                ##第一次均摊(店铺券均摊)
                $store = $v['data'];
//                print_r($store);die;
                $price_tt = $store['pay_money'];
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
            ## 预支付
            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }
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
    public function submitOrder_0925(){
        try {
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $userInfo = User::checkToken($post['user_id'],$post['token']);
            if ($userInfo instanceof Json){
                return $userInfo;
            }
//            Log::info(print_r($post,true));
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
            $pay_type = $post['pay_type'] ? intval($post['pay_type']) : '';      //收货地址

            if(!$pay_type)throw new Exception('请选择支付方式');
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
                    }else{
                        $product_info[$k2]['huoli_money'] =0;
                    }
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
                ##优惠券使用确认
                if(isset($v['store_coupon_id']) && $v['store_coupon_id']){
                    $res_coupon = Db::name('coupon')->where(['id'=>$v['store_coupon_id']])->update(['status'=>2,'use_time'=>time()]);
                    if($res_coupon === false)throw new Exception('商家券使用失败');
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
            ## 预支付
            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }
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
            $pay_type = $post['pay_type'] ? intval($post['pay_type']) : '';      //收货地址

            if(!$pay_type)throw new Exception('请选择支付方式');
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
            $product_data = [];

            ##获取会改变价格的活动商品ids
            $acChangePriceProIds = Logic::getActivityPros();

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
                        ->field('product_specs.*,product.is_group_buy,product.pt_size,pt_validhours,product.type,product.huoli_money,product.product_type,product.days,product.freight')
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
                    if(in_array($product_specs['product_id'], $acChangePriceProIds)){
                        $price = $product_specs['price_activity_temp'];
                    }else{
                        $price = $product_specs['price'];
                    }

                    //非拼团潮搭商品
                    if ($is_group_buy == 0 && $chaoda_id != 0){
                        $price = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->where('product_id',$product_specs['product_id'])->value('price');
                    }

//                    $product_data[$product_specs['product_id']] = [
//                        'price' => $price,
//                        'product_id' => $product_specs['product_id'],
//                        'number' => $v2['number']
//                    ];

                    $product_data[$v2['specs_id']] = [
                        'price' => $price,
                        'product_id' => $product_specs['product_id'],
                        'number' => $v2['number'],
                        'specs_id' => $v2['specs_id']
                    ];

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
                    }else{
                        $product_info[$k2]['huoli_money'] =0;
                    }
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

            if(!$chaoda_id){  //潮搭不参加活动
                ##判断是否参与了有效活动
                $is_activity = false;
                $pro_ids = array_column($product_data,'product_id');
                $checkActivity = Logic::checkProductInActivity($pro_ids);
                $activity_pro = [];
                $discount_info= [];
                if(!empty($checkActivity)){
//                    ##获取订单中每个活动商品的优惠金额 & 每个店铺的优惠金额
//                    $discount_info = Logic::getOrderDiscountInfo();

                    $len = $len2 = 0;
                    foreach($checkActivity as $v){
                        foreach($product_data as $kk => $vv){
                            if($vv['product_id'] == $v['product_id']){  //商品在活动中
                                if($v['activity_type'] != 3 && $v['activity_type'] != 6){
                                    $activity_pro[$kk] = $v['id'];

                                    ##获取商品的优惠金额
                                    if($v['activity_type'] == 2 || $v['activity_type'] == 4){ ##抵扣&打折
                                        $price_data = Logic::getAcProInfo($kk);
                                        $discount_info[$kk]['discount_money'] = ($price_data['price'] - $price_data['price_activity_temp']) * $vv['number'];
                                        $discount_info[$kk]['activity_id'] = $v['id'];
                                        $discount_info[$kk]['return_money'] = 0;
                                        $discount_info[$kk]['return_coupon_id'] = 0;
                                        $discount_info[$kk]['activity_type'] = $v['activity_type'];
                                        $discount_info[$kk]['relpay_money'] = $vv['price'] * $vv['number'];
                                    }

                                    if($v['activity_type'] == 5){  ##返现
                                        ##获取返现比例和最高值
                                        $return_config = Logic::getAcReturnConf($v['id']);
                                        $return_money = $vv['price'] * ($return_config['return_prop'] / 100);
                                        if($return_money > $return_config['return_max'])$return_money = $return_config['return_max'];
                                        $discount_info[$kk]['discount_money'] = 0;
                                        $discount_info[$kk]['activity_id'] = $v['id'];
                                        $discount_info[$kk]['return_money'] = $return_money * $vv['number'];
                                        $discount_info[$kk]['return_coupon_id'] = 0;
                                        $discount_info[$kk]['activity_type'] = $v['activity_type'];
                                        $discount_info[$kk]['relpay_money'] = $vv['price'] * $vv['number'];
                                    }

                                    $len ++;
                                }
                                $len2 ++;
                            }
                        }
                    }

                    if($len){
                        $is_activity = true;
                    }

                    if($len < $len2){  //有满减或者满返优惠券活动
                        $pro_data = $product_data;

                        $acRtnData = Logic::getAcEnoughMoneyAndCouponIds($pro_data, $checkActivity);

                        if($acRtnData['enough_discount_money'] || !empty($acRtnData['enough_rtn_coupon_ids'])){ //满足满减或者满返
                            $is_activity = true;
                        }

                        $activity_pro = $activity_pro + $acRtnData['activity_pro'];

                        $discount_info = $discount_info + $acRtnData['discount_info'];

                        ###计算每个商品的满减金额
//                        $enoughDiscountData = Logic::getAcEnoughDiscountProPrice($pro_data, $checkActivity);
                    }
                }

                ##判断活动和优惠券(活动与优惠券不同时享受)
                if($is_activity && ($coupon_id || !empty($store_coupon_ids) || !empty($product_coupon_ids))){
                    throw new Exception('该订单已参加营销活动,不支持使用优惠券');
                }
            }

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

//            $enoughDiscountSpecsIds = isset($enoughDiscountData)?array_keys($enoughDiscountData):[]; //满减的商品id数组
            $activityInfoSpecsIds = isset($discount_info)?array_keys($discount_info):[];  //活动商品信息
//            $activityProIds = isset($activity_pro)?array_keys($activity_pro):[];  //参加活动的商品id数组

            $pay_money_tt = 0 ; //支付总价格(产品总价 + 运费 - 优惠券)

            if(isset($is_activity) && $is_activity){  //活动
                foreach($coupon_data as $k => &$v){
                    $store_discount_money = 0;
                    $store_activity_ids = [];
                    $store_return_coupon_ids = [];
                    $store_pay_money = 0;
                    $store_return_money = 0;
                    foreach($v['product'] as $kk => &$vv){
                        ##判断是否有满减
                        if(in_array($vv['specs_id'], $activityInfoSpecsIds)){
                            $vv['realpay_money'] = $discount_info[$vv['specs_id']]['relpay_money'];
                            $vv['discount_money'] = $discount_info[$vv['specs_id']]['discount_money'];
                            $vv['return_money'] = $discount_info[$vv['specs_id']]['return_money'];
                            $vv['return_coupon_id'] = $discount_info[$vv['specs_id']]['return_coupon_id'];
                            $vv['activity_id'] = $discount_info[$vv['specs_id']]['activity_id'];
                            $store_discount_money += $discount_info[$vv['specs_id']]['discount_money'];
                            $store_return_money += $discount_info[$vv['specs_id']]['return_money'];
                            $store_activity_ids[] = $discount_info[$vv['specs_id']]['activity_id'];
                            if($discount_info[$vv['specs_id']]['return_coupon_id'])$store_return_coupon_ids[] = $discount_info[$vv['specs_id']]['return_coupon_id'];
                        }else{
                            $vv['realpay_money'] = $vv['price'] * $vv['number'];
                            $vv['discount_money'] = 0;
                            $vv['return_money'] = 0;
                            $vv['return_coupon_id'] = 0;
                            $vv['activity_id'] = 0;
                        }

                        $pay_money_tt += $vv['realpay_money'];
                        $store_pay_money += $vv['realpay_money'];
                    }
                    $pay_money_tt += $v['data']['total_freight'];  //支付金额加上运费
                    $v['data']['pay_money'] = $store_pay_money + $v['data']['total_freight'];
                    $v['data']['discount_money'] = $store_discount_money;
                    $v['data']['return_money'] = $store_return_money;
                    $v['data']['activity_ids'] = implode(',',$store_activity_ids);
                    $v['data']['return_coupon_ids'] = implode(',',$store_return_coupon_ids);
                }
            }else{
                $last_money_tt = 0;  //剩余价格总价
                foreach($coupon_data as $k => &$v){
                    ##第一次均摊(店铺券均摊)
                    $store = $v['data'];
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
            }

            if($pay_money_tt <= 0.01)$pay_money_tt=0.01;

            ###round原因=》计算的值没有问题但是会在后面增加00000000001,导致和传入的值不一样

            $pay_money_tt = round($pay_money_tt,2);

//            print_r($coupon_data);

//            echo $pay_money_tt;die;

            if($pay_money_tt != $pay_money){
                throw new Exception('支付金额错误2' . "提交金额=>{$pay_money},计算金额=>{$pay_money_tt}");
            }

//            throw new Exception('恭喜');

            foreach($coupon_data as $k=>&$v){
                $order_data = $v['data'];
                unset($v['distribution_mode']);
                $order_data['version'] = 5;
                $order_id = Db::name('product_order')->insertGetId($order_data);
                if($order_id === false)throw new Exception('订单创建失败');
                $data['order_id'] = $order_id;
                foreach($v['product'] as $kk => &$vv){
                    $vv['store_id'] = $order_id;
                    unset($vv['activity_type']);
                    unset($vv['discount']);
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
                    unset($vv['discounts']);
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
            ## 预支付
            switch($pay_type){
                case 1:
                    $pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user_v5/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                case 2:
                    $pay_type = "微信";
                    $notify_url = SERVICE_FX."/user_v5/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($pay_order_no,$pay_money_tt,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }
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
     * 提交订单页取消适用平台券
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function orderCancelUsePtCoupon(){
        ##验证
        $userInfo = User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        ##恢复订单(不适用平台券)
        $res = UserLogic::cancelOrderNotPayPtCoupon($userInfo['user_id']);
        if(!$res)return \json(self::callback(0,'订单更新失败'));

        ##返回
        return \json(self::callback(1,'订单更新成功'));
    }

    /**
     * 获取订单支付状态
     * @return Json
     */
    public function checkPayStatus(){
        try{
            #验证
            $order_id = input('post.order_id',0,'intval');
            if(!$order_id)throw new Exception('参数错误');

            ##token验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $res = UserLogic::checkPayStatus($order_id);
            return \json(self::callback(1,'',(int)$res,true));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户订单可使用优惠券
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function autoOrderCouponNumAndAc(){
//        ##参数示例
//        $param = [
//            "is_chaoda" => 1,  //0.不是潮搭; 1.是潮搭
//            "user_id" => 10501,  //用户id
//            "token" => "dsajdosanncasnoiswa",  //token
//            "coupon_id" => 0,  //平台券id
//            "store_info" => [
//                0 => [
//                    'store_id' => 113,
//                    'product_info' => [
//                        0 => [
//                            'product_id' => 1001,
//                            'specs_id' => 2001,
//                            'price' => 2031,  //商品价格
//                            'number' => 2  //购买数量
//                        ],
//                        1 => [
//                            'product_id' => 1001,
//                            'specs_id' => 2001,
//                            'price' => 2031,
//                            'number' => 2
//                        ]
//                    ]
//                ],
//                1 => [
//                    'store_id' => 113,
//                    'product_info' => [
//                        0 => [
//                            'product_id' => 1001,
//                            'specs_id' => 2001,
//                            'price' => 2031,
//                            'number' => 2
//                        ],
//                        1 => [
//                            'product_id' => 1001,
//                            'specs_id' => 2001,
//                            'price' => 2031,
//                            'number' => 2
//                        ]
//                    ]
//                ],
//            ]
//        ];

        ##返回值
//        $rtn_data = [
//            "store_info" => [
//                '0' => 2,
//                '1' => 10
//            ],
//            "coupon_pt" => 2
//        ];

        try{
            $params = trim(file_get_contents('php://input'));
            $params = json_decode($params,true);
            if(!is_array($params) || !isset($params['user_id']) || !isset($params['token']))throw new Exception('参数错误');

            $user_id = $params['user_id'];
            $token = $params['token'];
            ##token验证
            $userInfo = User::checkToken($user_id, $token);
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑

            $rtn_data = [];

            if(!$params['is_chaoda']){ ##非潮搭
                ##获取商品id 和 商品券/店铺券 ID
                $pro_ids = $coup_ids = $product_data = [];
                foreach($params['store_info'] as $k => $v){
                    if(isset($v['product_coupon_id']) && $v['product_coupon_id'])$coup_ids[] = $v['product_coupon_id'];
                    if(isset($v['store_coupon_id']) && $v['store_coupon_id'])$coup_ids[] = $v['store_coupon_id'];
                    foreach($v['product_info'] as $vv){
                        $pro_ids[] = $vv['product_id'];
                        $product_data[$vv['specs_id']] = [
                            'price' => $vv['price'],
                            'product_id' => $vv['product_id'],
                            'number' => $vv['number'],
                            'specs_id' => $vv['specs_id']
                        ];
                    }
                }

                ##判断是否有商品在活动
                $checkActivity = Logic::checkProductInActivity($pro_ids);

                $discount_info = [];
                $discount_type = [];
                $enough_discount_money = 0;
                if(!empty($checkActivity)){

                    $len = $len2 = 0;
                    foreach($checkActivity as $v){
                        foreach($product_data as $kk => $vv){
                            if($vv['product_id'] == $v['product_id']){  //商品在活动中
                                if($v['activity_type'] != 3 && $v['activity_type'] != 6){

                                    ##获取商品的优惠金额
                                    if($v['activity_type'] == 2){  ##抵扣
                                        ##获取活动信息
                                        $ac_info = Logic::getAcRuleInfo($v['id']);
                                        $discount_info[$kk] = "全场直降{$ac_info['deduction_money']}";
                                        $discount_type[$kk] = 2;
                                    }

                                    if($v['activity_type'] == 4){  ##打折
                                        ##获取活动信息
                                        $ac_info = Logic::getAcRuleInfo($v['id']);
                                        $discount = str_replace("0","",(string)($ac_info['discount'] * 100));
                                        $discount_info[$kk] = "{$discount}折";
                                        $discount_type[$kk] = 4;
                                    }

                                    if($v['activity_type'] == 5){  ##返现
                                        ##获取返现比例和最高值
                                        $return_config = Logic::getAcReturnConf($v['id']);
                                        $return_money = $vv['price'] * ($return_config['return_prop'] / 100) * $vv['number'];
                                        $return_money = min($return_money, $return_config['return_max']);
                                        $discount_info[$kk] = "全场返现{$return_money}元";
                                        $discount_type[$kk] = 5;
                                    }

                                    $len ++;
                                }
                                $len2 ++;
                            }
                        }
                    }

                    if($len < $len2){  //有满减或者满返优惠券活动

                        $pro_data = $product_data;

                        $acRtnData = Logic::getAcEnoughMoneyAndCouponIds($pro_data, $checkActivity);

                        $enough_discount_money = $acRtnData['enough_discount_money'];

                        foreach($acRtnData['enough_discount_info'] as $k => $v){
                            $discount_info[$k] = "全场满{$v['satisfy_money']}减{$v['discount_money']}";
                            $discount_type[$k] = 3;
                        }

                        foreach($acRtnData['enough_rtn_info'] as $k => $v){
                            ##获取优惠券信息
                            $coupon_info = Logic::couponInfo($v['coupon_id']);
                            $discount_info[$k] = "全场满{$v['satisfy_money']}返{$coupon_info['coupon_money']}元优惠券";
                            $discount_type[$k] = 6;
                        }

                    }

                    $discount_keys = array_keys($discount_info);

                    foreach($params['store_info'] as $k => $v){
                        $rtn_data['store_info'][$k]['product_info']= [];
                        $rtn_data['store_info'][$k]['coupon_num'] = 0;
                        foreach($v['product_info'] as $kk => $vv){
                            if(in_array($vv['specs_id'], $discount_keys)){
                                $rtn_data['store_info'][$k]['product_info'][$kk]['discount'] = $discount_info[$vv['specs_id']];
                                $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = $discount_type[$vv['specs_id']];
                            }else{
                                $rtn_data['store_info'][$k]['product_info'][$kk]['discount'] = "";
                                $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = 0;
                            }
                        }
                    }

                    $rtn_data['pt_coupon_num'] = 0;
                    $rtn_data['enough_discount_money'] = $enough_discount_money;

                    return \json(self::callback(1,'',$rtn_data));
                }

            }

            ##计算优惠券数量

            //临时定义部分店铺只支持快点自取
            $arr_store = config('config_store_express.store_express');
            ###店铺 + 商品优惠券
            foreach($params['store_info'] as $k => $v){
                //判断店铺方式 1:到店自取，2：快递免邮，3:1+2
                $store_type = Db::name('store')
                    ->where('id','eq', $v['store_id'])
                    ->field('id,type,is_ziqu')
                    ->find();
                $rtn_data['store_info'][$k]['express_type']= 2;//默认 等于2
                $is_in=in_array($store_type['id'],$arr_store);
                if($is_in){
                    $rtn_data['store_info'][$k]['express_type']= 1;
                }else{
                    if($store_type['is_ziqu']==1 && $store_type['type']==1){
                        $rtn_data['store_info'][$k]['express_type']= 3;
                    }
                }
                ##获取店铺券数量
                $coupon_num = UserLogic::countUserStoreCoupons($user_id, $v['store_id']);

                $product_ids = array_keys(array_flip(array_column($v['product_info'],'product_id')));  //获取去重后的product_id数组
                $coupon_num += UserLogic::countUserProductCoupons($user_id, $v['store_id'], $product_ids);
                $rtn_data['store_info'][$k]['coupon_num'] = $coupon_num;

                $rtn_data['store_info'][$k]['product_info']= [];
                foreach($v['product_info'] as $kk => $vv){
                    $rtn_data['store_info'][$k]['product_info'][$kk]['discount'] = "";
                    $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = 0;
                }
            }

            ###平台券
            $pt_coupon_num = UserLogic::countUserPtCoupons($user_id);
            $rtn_data['pt_coupon_num'] = $pt_coupon_num;
            $rtn_data['enough_discount_money'] = 0;

            #返回
            return \json(self::callback(1,'',$rtn_data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

}