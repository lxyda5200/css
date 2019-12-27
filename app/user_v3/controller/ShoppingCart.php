<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/3
 * Time: 17:23
 */

namespace app\user_v3\controller;

use app\common\controller\Base;
use think\Db;
use think\Exception;
use think\Log;
use think\response\Json;
use app\user_v3\model\ShoppingCart as ShoppingCartModel;
use app\user_v3\common\User;

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

            foreach ($list as $k=>$v){

                $product_info = Db::view('shopping_cart','product_id,specs_id,number,update_time')
                    ->view('store','is_ziqu','store.id = shopping_cart.store_id','left')
                    ->view('product_specs','product_name,price,product_specs,cover,stock','product_specs.id = shopping_cart.specs_id','left')
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
                        $sum_price += $v1['price'] * $v1['number'];
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
}