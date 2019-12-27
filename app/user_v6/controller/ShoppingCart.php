<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/3
 * Time: 17:23
 */

namespace app\user_v6\controller;

use app\common\controller\Base;
use app\user_v6\common\Logic;
use think\Db;
use think\Exception;
use think\Log;
use think\response\Json;
use app\user_v6\model\ShoppingCart as ShoppingCartModel;
use app\user_v6\common\User;

class ShoppingCart extends Base
{
    /**
     * 加入购物车
     */
    public function addShoppingCart(){
        try{
            $specs_id = $this->request->has('specs_id') ? intval($this->request->param('specs_id')) : 0 ;
            $number = $this->request->has('number') ? intval($this->request->param('number')) : 1 ;

            if (!$specs_id) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $product_specs = Db::name('product_specs')->where('id',$specs_id)->find();

            if (!$product_specs) {
                throw new \Exception('商品不存在');
            }

            if($product_specs['stock'] < $number)throw new Exception('库存不足');

            $product = Db::name('product')->where('id',$product_specs['product_id'])->find();

            if ($product['status'] != 1) {
                throw new \Exception('该商品已经下架');
            }

            ##检查以前的购物车数量
            $user_id = $userInfo['user_id'];
            $prev_number = intval(Db::name('shopping_cart')->where(['user_id'=>$user_id,'specs_id'=>$specs_id])->value('number'));
            if($prev_number)$number += $prev_number;

            ##判断库存
            if($product_specs['stock'] < $number)throw new Exception('库存不足');

            ##更新或新增购物车
            if($prev_number){##更新购物车
                $res = Db::name('shopping_cart')->where(['user_id'=>$user_id,'specs_id'=>$specs_id])->update(['number'=>$number,'update_time'=>time()]);
            }else{##新增购物车
                $data = [
                    'user_id'=>$user_id,
                    'store_id'=>$product['store_id'],
                    'product_id'=>$product['id'],
                    'specs_id'=>$specs_id,
                    'number'=>$number,
                    'create_time'=>time(),
                    'update_time' => time()
                ];
                $res = Db::name('shopping_cart')->insert($data);
            }

//            if (Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->count()) {
//                $result = Db::name('shopping_cart')->where('specs_id',$specs_id)->setInc('number',$number);
//            }else{
//                $result = Db::name('shopping_cart')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$product['store_id'],'product_id'=>$product['id'],'specs_id'=>$specs_id,'number'=>$number,'create_time'=>time()]);
//            }

            if(!$res){
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 修改购物车规格
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function editShoppingCartSpecs(){
        try{
            $prev_specs_id = input('post.prev_specs_id',0,'intval');
            if($prev_specs_id <= 0)throw new Exception('缺少参数购物车ID');
            $specs_id = $this->request->has('specs_id') ? intval($this->request->param('specs_id')) : 0 ;
            $number = $this->request->has('number') ? intval($this->request->param('number')) : 1 ;

            if (!$specs_id) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $product_specs = Db::name('product_specs')->where('id',$specs_id)->find();

            if (!$product_specs) {
                throw new \Exception('商品不存在');
            }

            if($product_specs['stock'] < $number)throw new Exception('库存不足');

            $product = Db::name('product')->where('id',$product_specs['product_id'])->find();

            if ($product['status'] != 1) {
                throw new \Exception('该商品已经下架');
            }

            ##删除购物车
            $res = Db::name('shopping_cart')->where(['specs_id'=>$prev_specs_id,'user_id'=>$userInfo['user_id']])->delete();
            if($res === false)throw new Exception('操作失败');

            ##检查以前的购物车数量
            $user_id = $userInfo['user_id'];
            $prev_number = intval(Db::name('shopping_cart')->where(['user_id'=>$user_id,'specs_id'=>$specs_id])->value('number'));
            if($prev_number)$number += $prev_number;

            ##判断库存
            if($product_specs['stock'] < $number)throw new Exception('库存不足');

            ##更新或新增购物车
            if($prev_number){##更新购物车
                $res = Db::name('shopping_cart')->where(['user_id'=>$user_id,'specs_id'=>$specs_id])->update(['number'=>$number,'update_time'=>time()]);
            }else{##新增购物车
                $data = [
                    'user_id'=>$user_id,
                    'store_id'=>$product['store_id'],
                    'product_id'=>$product['id'],
                    'specs_id'=>$specs_id,
                    'number'=>$number,
                    'create_time'=>time(),
                    'update_time' => time()
                ];
                $res = Db::name('shopping_cart')->insert($data);
            }

//            if (Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->count()) {
//                $result = Db::name('shopping_cart')->where('specs_id',$specs_id)->setInc('number',$number);
//            }else{
//                $result = Db::name('shopping_cart')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$product['store_id'],'product_id'=>$product['id'],'specs_id'=>$specs_id,'number'=>$number,'create_time'=>time()]);
//            }

            if(!$res){
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }


    /**
     * 购物车列表
     */
    public function shoppingCartList(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $list = Db::name('shopping_cart')
                ->alias('c')
                ->join('store s','s.id=c.store_id','left')
                ->join('product p','p.id = c.product_id','left')
                ->where('p.status',1)
                ->where('c.user_id',$userInfo['user_id'])
                ->field('c.store_id,s.store_name,s.cover,s.buy_type,s.type as store_type')
                ->group('c.store_id')
                ->order('c.update_time', 'desc')
                ->select();
//            echo Db::name('shopping_cart')->getLastSql();

            $sum_price = 0;

            if(!$list)return \json(self::callback(1,'购物车空空如也',compact('list','sum_price')));

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            foreach ($list as $k=>$v){

                $product_info = Db::view('shopping_cart','product_id,specs_id,number,update_time')
                    ->view('store','is_ziqu,type as store_type','store.id = shopping_cart.store_id','left')
                    ->view('product_specs','product_name,price,product_specs,cover,stock,price_activity_temp','product_specs.id = shopping_cart.specs_id','left')
                    ->view('product','freight','product.id = product_specs.product_id','left')
                    ->where('shopping_cart.store_id',$v['store_id'])
                    ->where('shopping_cart.user_id',$userInfo['user_id'])
                    ->select();

                $max_freight = 0;
                $update_time = 0;

                foreach ($product_info as $k1=>$v1) {
                    if($update_time < $v1['update_time']){
                        $update_time = $v1['update_time'];
                    }

                    if($v1['freight'] > $max_freight)$max_freight = $v1['freight'];

                    if (!Db::name('product_specs')->where('id',$v1['specs_id'])->count()){
                        unset($product_info[$k1]);

                        continue;
                    }else{
                        if(in_array($v1['product_id'],$product_ids)){
                            $product_info[$k1]['price'] = $v1['price_activity_temp'];
                            $sum_price += $v1['price_activity_temp'] * $v1['number'];
                        }else{
                            $sum_price += $v1['price'] * $v1['number'];
                        }

                        $specs = json_decode($v1['product_specs']);

                        foreach ($specs as $k2=>$v2){
                            $specs .= $k2.':'.$v2.',';
                        }

                        $product_info[$k1]['show_product_specs'] = trim($specs,',');
                    }

                }

                $list[$k]['update_time'] = $update_time;

                $product_info = array_values($product_info);

                $list[$k]['product_info'] = $product_info;
                $list[$k]['max_freight'] = (float)$max_freight;
                if (empty($product_info)){
                    unset($list[$k]);
                }


            }
            array_multisort(array_column($list,'update_time'),SORT_DESC,$list);
            $data['sum_price'] = $sum_price;
            $data['list'] = array_values($list);

            return json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除购物车商品
     */
    public function deleteShoppingCartGoods(){
        try{
            $param = $this->request->post();

            $specs_id = gettype($param['specs_id']) == 'array' ? $param['specs_id'] : ( intval($param['specs_id']) ? $param['specs_id'] : 0 );  //商品id  数组或者整型

            if (!$param || !$specs_id){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $result = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->whereIn('specs_id',$specs_id)->delete();

            if (!$result){
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 购物车编辑数量
     */
    public function shoppingCartEdit(){

        try {
            $param = $this->request->post();

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            if (empty($param['type']) || empty($param['specs_id'])){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!Db::name('product_specs')->where('id',$param['specs_id'])->count()){
                return \json(self::callback(0,'商品不存在'));
            }

            $shoppingCart = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->find();
            if (!$shoppingCart) {
                return \json(self::callback(0,'购物车不存在该商品'));
            }

            ##获取库存
            $stock = Db::name('product_specs')->where(['id'=>$param['specs_id']])->value('stock');
            if(!(int)$stock)return json(self::callback(0,'库存不足'));

            if ($param['type'] == 1) {
                ##判断库存
                if($stock < $shoppingCart['number'] + 1)return json(self::callback(0,'库存不足'));
                Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->update(['number'=>$shoppingCart['number']+1,'update_time'=>time()]);
            }elseif ($param['type'] == -1) {
                ##判断购买数
                if($shoppingCart['number'] <= 1)return json(self::callback(0,'最少购买一件商品'));
                Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->update(['number'=>$shoppingCart['number']-1,'update_time'=>time()]);
            }else{
                return \json(self::callback(0,'参数错误'),400);
            }

            return \json(self::callback(1,'',$stock));

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    public function cartProAcInfo(){

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

            if(!$params['is_chaoda'] && !empty($params['store_info'])){ ##非潮搭

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
                                        $discount_info[$kk] = ["全场商品直降{$ac_info['deduction_money']}"];
                                        $discount_type[$kk] = 2;
                                    }

                                    if($v['activity_type'] == 4){  ##打折
                                        ##获取活动信息
                                        $ac_info = Logic::getAcRuleInfo($v['id']);
                                        $discount = str_replace("0","",(string)($ac_info['discount'] * 100));
                                        $discount_info[$kk] = ["全场商品{$discount}折，最高可减{$ac_info['discount_max']}"];
                                        $discount_type[$kk] = 4;
                                    }

                                    if($v['activity_type'] == 5){  ##返现
                                        ##获取返现比例和最高值
                                        $return_config = Logic::getAcReturnConf($v['id']);
                                        $discount_info[$kk] = ["全场商品下单最高返{$return_config['return_max']}元现金"];
                                        $discount_type[$kk] = 5;
                                    }

                                    $len ++;
                                }
                                $len2 ++;
                            }else{
                                $discount_info[$vv['specs_id']] = [];
                                $discount_type[$vv['specs_id']] = 0;
                            }
                        }
                    }

                    if($len < $len2){  //有满减或者满返优惠券活动

                        $pro_data = $product_data;

                        $acRtnData = Logic::getAcEnoughMoneyAndCouponIds($pro_data, $checkActivity);

                        $enough_discount_money = $acRtnData['enough_discount_money'];

                        foreach($acRtnData['enough_discount_info'] as $k => $v){
                            $rule = Logic::getAcEnoughDiscountRule($acRtnData['activity_pro'][$k]);
                            $rule_list = [];
                            foreach($rule as $vv){
                                $rule_list[] = "全场商品满{$vv['satisfy_money']}减{$vv['discount_money']}";
                            }
                            $discount_info[$k] = $rule_list;
                            $discount_type[$k] = 3;
                        }

                        foreach($acRtnData['enough_rtn_info'] as $k => $v){
                            $rule = Logic::getAcEnoughRtnRule($acRtnData['activity_pro'][$k]);
                            $rule_list = [];
                            foreach($rule as $vv){
                                $coupon_info = Logic::couponInfo($vv['coupon_id']);
                                $rule_list[] = "全场商品满{$vv['satisfy_money']}立返{$coupon_info['coupon_money']}优惠券";
                            }

                            $discount_info[$k] = $rule_list;
                            $discount_type[$k] = 6;
                        }

                    }

                    $discount_keys = array_keys($discount_info);

                    foreach($params['store_info'] as $k => $v){
                        $rtn_data['store_info'][$k]['product_info']= [];
                        foreach($v['product_info'] as $kk => $vv){
                            if(in_array($vv['specs_id'], $discount_keys)){
                                $rtn_data['store_info'][$k]['product_info'][$kk]['discounts'] = $discount_info[$vv['specs_id']];
                                $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = $discount_type[$vv['specs_id']];
                            }else{
                                $rtn_data['store_info'][$k]['product_info'][$kk]['discounts'] = [];
                                $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = 0;
                            }
                        }
                    }

                    $rtn_data['pt_coupon_num'] = 0;
                    $rtn_data['enough_discount_money'] = $enough_discount_money;

                    return \json(self::callback(1,'',$rtn_data));
                }else{
                    foreach($params['store_info'] as $k => $v){
                        $rtn_data['store_info'][$k]['product_info']= [];
                        foreach($v['product_info'] as $kk => $vv){
                            $rtn_data['store_info'][$k]['product_info'][$kk]['discounts'] = [];
                            $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = 0;
                        }
                    }
                }

            }else{
                foreach($params['store_info'] as $k => $v){
                    $rtn_data['store_info'][$k]['product_info']= [];
                    foreach($v['product_info'] as $kk => $vv){
                        $rtn_data['store_info'][$k]['product_info'][$kk]['discounts'] = [];
                        $rtn_data['store_info'][$k]['product_info'][$kk]['activity_type'] = 0;
                    }
                }
                if(empty($rtn_data)){
                    $rtn_data['store_info'] = [];
                }
            }

            #返回
            return \json(self::callback(1,'',$rtn_data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }


    }

}