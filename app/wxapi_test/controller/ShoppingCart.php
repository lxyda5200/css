<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/8/3
 * Time: 17:23
 */

namespace app\wxapi_test\controller;
use app\common\controller\Base;
use think\Db;
use think\response\Json;
use app\wxapi_test\model\ShoppingCart as ShoppingCartModel;
use app\wxapi_test\common\User;
class ShoppingCart extends Base
{
    /**
     * 加入购物车
     */
    public function addShoppingCart(){
        try{

            $specs_id = $this->request->has('specs_id') ? intval($this->request->param('specs_id')) : 0 ;
            $number = $this->request->has('number') ? intval($this->request->param('number')) : 1 ;
//            $state = $this->request->has('state') ? intval($this->request->param('state')) : true ;//状态默认
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
            if ($product_specs['stock']<=0) {
                throw new \Exception('库存不足');
            }
            $product = Db::name('product')->where('id',$product_specs['product_id'])->find();

            if ($product['status'] != 1) {
                throw new \Exception('该商品已经下架');
            }
            //统计是否有记录
           $rst= Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->find();
            $rst2= Db::name('product_specs')->where('id',$specs_id)->value('stock');
            if ($rst) {
                //查询到有这条记录
                if($rst['number']<$rst2){
                    //新增
                    $result = Db::name('shopping_cart')->where('specs_id',$specs_id)->setInc('number',$number);
                }else{
                    return json(self::callback(1,'库存不足',-1));
                }

            }else{
                $result = Db::name('shopping_cart')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$product['store_id'],'product_id'=>$product['id'],'specs_id'=>$specs_id,'state'=>'true','number'=>$number,'create_time'=>time()]);
            }

            if (!$result){
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,'添加成功！',true));

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

                //统计每个店铺的商品个数
                $rst=Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('store_id',$v['store_id'])->count();
                //统计每个店铺被选中的商品个数
                $rst1=Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('store_id',$v['store_id'])->where('state','true')->count();
                if($rst==$rst1){
                    $list[$k]['state']=true;
                }else{
                    $list[$k]['state']=false;
                }
                $product_info = Db::view('shopping_cart','product_id,specs_id,number,state')
                    ->view('store','is_ziqu','store.id = shopping_cart.store_id','left')
                    ->view('product_specs','product_name,price,stock,product_specs,cover','product_specs.id = shopping_cart.specs_id','left')
                    ->view('product','freight','product.id = product_specs.product_id','left')
                    ->where('shopping_cart.store_id',$v['store_id'])
                    ->where('shopping_cart.user_id',$userInfo['user_id'])
                    ->select();
                $numbers=0;
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
                        if($v1['stock']>0 && $v1['state']=='true'){
                            $numbers+=1;
                            $product_info[$k1]['state']=true;
                        }else if($v1['stock']>0 && $v1['state']=='false'){
                            $product_info[$k1]['state']=false;
                        }else if($v1['stock']==0 && $v1['state']=='true'){
                            $product_info[$k1]['state']=false;
                            $product_info[$k1]['number']=0;
                        }else if($v1['stock']==0 && $v1['state']=='false'){
                            $product_info[$k1]['number']=0;
                        }else{}
                        $product_info[$k1]['show_product_specs'] = trim($specs,',');
                    }
                }
                if($numbers==$rst1){
                    $list[$k]['state']=true;
                }else{
                    $list[$k]['state']=false;
                }
                $product_info = array_values($product_info);
                $list[$k]['product_info'] = $product_info;
                if (empty($product_info)){
                    unset($list[$k]);
                }
            }
            $data['sum_price'] = $sum_price;
            $data['list'] = array_values($list);
            return json(self::callback(1,'查询成功！',$data));
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

            return json(self::callback(1,'删除成功！',true));

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
            $userInfo = \app\wxapi_test\common\User::checkToken();
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
                //判断库存
                $rst= Db::table('product_specs')->field('id,stock')->where('id',$param['specs_id'])->find();
                $rst2= Db::table('shopping_cart')->field('id,number')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->find();

                if($rst['stock']<=$rst2['number'] ){
                    return \json(self::callback(1,'库存不足',-1));
                }
                $add=Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->setInc('number',1);
                if($add===false){
                    return \json(self::callback(0,'增加失败',false));
                }else{
                    return \json(self::callback(1,'增加成功',true));
                }
            }elseif ($param['type'] == -1) {
                //判断购物车数量 最少不能低于1
                $rst= Db::table('shopping_cart')->field('id,number')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->find();
                $nownumber=$rst['number'];
                if($nownumber==1){
                    //不能再减少
                    return \json(self::callback(0,'商品数量不能少于1',false));
                }else{

                    Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$param['specs_id'])->setDec('number',1);
                    return \json(self::callback(1,'减少成功',true));
                }

            }else{
                return \json(self::callback(0,'参数错误'),400);
            }

            return \json(self::callback(1,''));

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 自定义购物车数量
     */
    public function customShoppingCart(){

        try {

            //token 验证
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            //验证接收的数字
            $number = $this->request->post('number');
            $specs_id = $this->request->post('specs_id');
            if(!$number || !$specs_id){
                return \json(self::callback(0,'参数错误',false));
            }
            if(is_numeric($number)){
            //接收的是数字
                $number=intval($number);
                if($number==0 || $number<0){
                    return \json(self::callback(0,'不能为0或负数',false));
                }
                if (!Db::name('product_specs')->where('id',$specs_id)->count()){
                    return \json(self::callback(0,'商品不存在'));
                }
                $shoppingCart = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->find();
                if (!$shoppingCart) {
                    return \json(self::callback(0,'购物车不存在该商品'));
                }
                //判断库存
                $rst= Db::table('product_specs')->field('id,stock')->where('id',$specs_id)->find();
                if($rst['stock']<=$number ){
                    return \json(self::callback(1,'库存不足',-1));
                }
                $result=Db::table('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->setField('number', $number);

                if($result===false){
                    return \json(self::callback(0,'修改失败',false));
                }else{
                    return \json(self::callback(1,'修改成功',true));
                }
            }else{
                //报错
                return \json(self::callback(0,'参数错误',false));
            }

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 改变购物车选中状态
     */
    public function changeShoppingCartStatus(){

        try {

            //token 验证
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            //验证接收的数据
            $state = $this->request->post('state');
            $specs_id = $this->request->post('specs_id');
            $store_id = $this->request->post('store_id');
            if(!$state ){
                return \json(self::callback(0,'参数错误',false));
            }
            if(!$store_id && isset($specs_id)){
                //单个specs_id
                if (!Db::name('product_specs')->where('id',$specs_id)->count()){
                    return \json(self::callback(0,'商品不存在'));
                }
                $shoppingCart = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->find();
                if (!$shoppingCart) {
                    return \json(self::callback(0,'购物车不存在该商品'));
                }
                $result=Db::table('shopping_cart')->where('user_id',$userInfo['user_id'])->where('specs_id',$specs_id)->setField('state', $state);
                if($result===false){
                    return \json(self::callback(0,'修改失败',false));
                }else{
                    return \json(self::callback(1,'修改成功',true));
                }

            }else if(isset($store_id) && !$specs_id){
//整个店铺

                if (!Db::name('store')->where('id',$store_id)->count()){
                    return \json(self::callback(0,'店铺不存在'));
                }
                $shoppingCart = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->find();
                if (!$shoppingCart) {
                    return \json(self::callback(0,'购物车不存在该店铺商品'));
                }
                $result=Db::table('shopping_cart')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->setField('state', $state);
                if($result===false){
                    return \json(self::callback(0,'修改失败',false));
                }else{
                    return \json(self::callback(1,'修改成功',true));
                }


            }else if(!$store_id && !$specs_id){
//全选

                if (!Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->count()){
                    return \json(self::callback(0,'该用户购物车没有商品'));
                }
//                $shoppingCart = Db::name('shopping_cart')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->find();
//                if (!$shoppingCart) {
//                    return \json(self::callback(0,'购物车不存在该店铺商品'));
//                }
                $result=Db::table('shopping_cart')->where('user_id',$userInfo['user_id'])->setField('state', $state);
                if($result===false){
                    return \json(self::callback(0,'修改失败',false));
                }else{
                    return \json(self::callback(1,'修改成功',true));
                }

            }else{
                //报错
                return \json(self::callback(0,'未知错误',false));

            }

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }
}