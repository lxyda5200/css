<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/3
 * Time: 17:23
 */

namespace app\user\controller;

use app\common\controller\Base;
use think\Db;
use think\response\Json;
use app\user\model\ShoppingCart as ShoppingCartModel;
use app\user\common\User;

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

            $product = Db::name('product')->where('id',$product_specs['product_id'])->find();

            if ($product['status'] != 1) {
                throw new \Exception('该商品已经下架');
            }

            if (Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->count()) {
                $result = Db::name('shopping_cart')->where('specs_id',$specs_id)->setInc('number',$number);
            }else{
                $result = Db::name('shopping_cart')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$product['store_id'],'product_id'=>$product['id'],'specs_id'=>$specs_id,'number'=>$number,'create_time'=>time()]);
            }

            if (!$result){
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
                ->field('c.store_id,s.store_name,s.buy_type,s.type')
                ->group('c.store_id')
                ->select();

            $sum_price = 0;

            foreach ($list as $k=>$v){


                $product_info = Db::view('shopping_cart','product_id,specs_id,number')
                    ->view('store','is_ziqu','store.id = shopping_cart.store_id','left')
                    ->view('product_specs','product_name,price,product_specs,cover','product_specs.id = shopping_cart.specs_id','left')
                    ->view('product','freight','product.id = product_specs.product_id','left')
                    ->where('shopping_cart.store_id',$v['store_id'])
                    ->where('shopping_cart.user_id',$userInfo['user_id'])
                    ->select();



                foreach ($product_info as $k1=>$v1) {

                    if (!Db::name('product_specs')->where('id',$v1['specs_id'])->count()){
                        unset($product_info[$k1]);

                        continue;
                    }else{
                        $sum_price += $v1['price'] * $v1['number'];
                        $specs = json_decode($v1['product_specs']);

                        foreach ($specs as $k2=>$v2){
                            $specs .= $k2.':'.$v2.',';
                        }

                        $product_info[$k1]['show_product_specs'] = trim($specs,',');
                    }



                }

                $product_info = array_values($product_info);

                $list[$k]['product_info'] = $product_info;

                if (empty($product_info)){
                    unset($list[$k]);
                }


            }

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

            if ($param['type'] == 1) {
                Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->setInc('number',1);
            }elseif ($param['type'] == -1) {
                Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->setDec('number',1);
            }else{
                return \json(self::callback(0,'参数错误'),400);
            }

            return \json(self::callback(1,''));

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }
}