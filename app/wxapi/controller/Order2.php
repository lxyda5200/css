<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/19
 * Time: 17:45
 */

namespace app\wxapi\controller;


use app\common\controller\Base;
use app\wxapi\model\GoodsComment;
use app\wxapi\model\GoodsOrder;
use app\wxapi\model\GoodsOrderDetail;
use think\Db;
use think\response\Json;

class Order2 extends Base
{

    /**
     * 订单列表
     */
    public function orderList(){

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $orderlist = GoodsOrder::where('user_id',$userInfo['user_id'])->where('user_is_del',0)->field('id,order_no,pay_money,order_status,create_time,shouhuo_address,shouhuo_username,shouhuo_mobile')->order('create_time desc')->select();

        $orderlist = $orderlist->toArray();
        foreach ($orderlist as $k=>$v){
            $goods_info = Db::view('goods_order_detail','goods_id,number,price')
                ->view('goods','goods_name,spec,unit,description','goods.id = goods_order_detail.goods_id','left')
                ->where('order_id','eq',$v['id'])
                ->select();
            foreach ($goods_info as $k2=>$v2){
                $goods_info[$k2]['goods_img'] = Db::name('goods_img')->field('img_url')->where('goods_id',$v2['goods_id'])->select();
            }
            $orderlist[$k]['goods_info'] = $goods_info;
        }

        return \json(self::callback(1,'',$orderlist));
    }


    /**
     * 订单详情
     */
    public function orderDetail(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = GoodsOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        $orderInfo = $order->toArray();

        $goods_info = Db::view('goods_order_detail','goods_id,number,price')
            ->view('goods','goods_name,spec,unit,description','goods.id = goods_order_detail.goods_id','left')
            ->where('order_id','eq',$orderInfo['id'])
            ->select();
        foreach ($goods_info as $k2=>$v2){
            $goods_info[$k2]['goods_img'] = Db::name('goods_img')->field('img_url')->where('goods_id',$v2['goods_id'])->select();
        }
        $orderInfo['goods_info'] = $goods_info;

        if ($orderInfo['order_status'] == 5){
            $orderInfo['comment_info'] = Db::view('goods_comment','user_id,content,score,create_time')
                ->view('user','nickname,avatar','user.user_id=goods_comment.user_id','left')
                ->where('goods_comment.order_id','eq',$order_id)
                ->select();
        }

        return \json(self::callback(1,'',$orderInfo));

    }

    /**
     * 提交订单 - 商城订单
     */
    public function submit(){
        try{
            $param = $this->request->post();

            if (!$param){
                return json(self::callback(0,'参数错误'),400);
            }

            $result = $this->validate($param,[
                'address_id' => 'require|number',   //地址id
                'goods_info' => 'require',          //商品信息
                'pay_money' => 'require',           //支付金额
                'submit_type' => 'require|in:1,2'          //提交类型  1直接提交 2购物车提交
            ]);
            if (!$result){
                return \json(self::callback(0,$result),400);
            }

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $addressInfo = Db::name('user_address')->where('user_id',$userInfo['user_id'])->where('id',$param['address_id'])->find();
            if (!$addressInfo){
                throw new \Exception('收货地址不存在');
            }

            $fp = fopen("/usr/share/nginx/html/tpl/public/lock.txt", "w+");


            if(!flock($fp,LOCK_EX | LOCK_NB)){
                throw new \Exception('系统繁忙，请稍后再试');
            }

            //验证商品信息与支付金额
            $goods_data = $this->verifyGoodsInfo($param['goods_info'],$param['pay_money']);
            if ($goods_data instanceof Json){
                return $goods_data;
            }


            $param['order_no'] = build_order_no('G');
            $param['shouhuo_username'] = $addressInfo['username'];
            $param['shouhuo_mobile'] = $addressInfo['mobile'];
            $param['shouhuo_address'] = $addressInfo['province'].$addressInfo['city'].$addressInfo['area'].$addressInfo['address'];

            //根据地址获取经纬度
            $latlng = addresstolatlng($param['shouhuo_address']);

            $param['lng'] = $latlng[0];
            $param['lat'] = $latlng[1];
            $param['pay_type'] = 0 ;
            $param['sale_id'] = 0 ;
            //todo 按距离分配给商家 否则随机分配
            $shop_id = $this->getShopId($param['lng'],$param['lat']);

            if (!$shop_id){
                $shop = Db::name('shop_info')->order('rand()')->find();
                $shop_id = $shop['id'];
            }

            $param['shop_id'] = $shop_id;

            Db::startTrans();
            $orderModel = new GoodsOrder();
            $result1 = $orderModel->allowField(true)->save($param);

            foreach ($goods_data as $k=>$v){
                $goods_data[$k]['order_id'] = $orderModel->id;
                //减少库存
                Db::name('goods')->where('id',$v['goods_id'])->setDec('number',$v['number']);
                $goods_id[] = $v['goods_id'];
            }

            $result2 = (new GoodsOrderDetail())->allowField(true)->saveAll($goods_data);

            if (!$result1 || !$result2){
                Db::rollback();
                return \json(self::callback(0,'操作失败'));
            }


            if ($param['submit_type'] == 2){
                $this->cleanShoppingCart($userInfo['user_id'],$goods_id);              //清理购物车
            }

            flock($fp,LOCK_UN);//释放锁
            Db::commit();


            fclose($fp);

            return \json(self::callback(1,'',['id'=>$orderModel->id,'order_no'=>$param['order_no']]));



        }catch (\Exception $e){

            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 提交订单验证
     * */
    public function verifyGoodsInfo($goods_info,$pay_money){
        //goods_info 商品信息 参数格式 商品id,数量|商品id,数量
        //pay_money 支付金额

        $total_money = 0;

        $goods_info = explode('|',$goods_info);
        foreach ($goods_info as $k){
            $goodsArr = explode(',',$k);
            $goods_id = isset($goodsArr[0]) ? intval($goodsArr[0]) : 0 ;
            $number = isset($goodsArr[1]) ? intval($goodsArr[1]) : 0 ;
            if (!$goods_id || !$number){
                return json(self::callback(0,'goods_info格式错误'));
            }

            $goodsData = Db::name('goods')->where(['id'=>$goods_id,'status'=>1,'is_delete'=>0])->field('id as goods_id,number,price')->find();
            if (!$goodsData){
                return json(self::callback(0,'商品不存在'));
            }

            if($goodsData['number'] < $number){
                return json(self::callBack(0, "商品库存不足"));
            }

            $total_money += $number * $goodsData['price'];
            $goodsData['number'] = $number;

            $data[] = $goodsData;
        }


        $pay_money = (float)$pay_money;


        $res = bccomp($pay_money,$total_money,2);

        if ($res != 0){
            return json(self::callback(0,'结算金额错误'));
        }

        return $data;
    }


    /*
     * 清理购物车
     */
    public function cleanShoppingCart($user_id,$goods_id){
        $res = Db::name('shopping_cart')
            ->where('user_id',$user_id)
            ->whereIn('goods_id',$goods_id)
            ->delete();
        return $res;
    }

    /**
     * 根据经纬度返回最近的商家id
     * @param $lng
     * @param $lat
     */
    public function getShopId($lng,$lat){
        $field = "ROUND(6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            $lat * PI() / 180 - lat * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS($lat * PI() / 180) * COS(lat * PI() / 180) * POW(
                    SIN(
                        (
                            $lng * PI() / 180 - lng * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS m";

        $shop = Db::name('shop_info')->field('id,'.$field)->order('m','asc')->limit(0,1)->select();


        return $shop[0]['id'];
    }

    /**
     * 获取支付信息
     */
    public function getPayInfo(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $data['order_no'] = $order_no = input('order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

            $orderModel = new GoodsOrder();
            $order = $orderModel->where('order_no',$order_no)->find();
            $data['order_id'] = $order->id;

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->order_status != 1){
                throw new \Exception('订单不支持该操作');
            }

            switch($pay_type){
                case 1:
                    $order->pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user/ali_pay/goods_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->pay_money,$notify_url);

                    break;
                case 2:
                    $order->pay_type = "微信";
                    $notify_url = SERVICE_FX."/user/wx_pay/goods_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$order->pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            $order->allowField(true)->save();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }



    /**
     * 取消订单
     */
    public function cancel(){
       try{
           $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
           if (!$order_id){
               return \json(self::callback(0,'参数错误'),400);
           }

           //token 验证
           $userInfo = \app\user\common\User::checkToken();
           if ($userInfo instanceof Json){
               return $userInfo;
           }

           $order = GoodsOrder::get($order_id);

           if (!$order){
               return \json(self::callback(0,'订单不存在'));
           }

           $order_status = $order->order_status;

           if ($order_status == 1){
               //返回库存
               $goods = Db::name('goods_order_detail')->where('order_id',$order_id)->select();

               foreach ($goods as $k=>$v){
                   Db::name('goods')->where('id',$v['goods_id'])->setInc('number',$v['number']);
               }

               //删除订单预定记录
               $result = $order->delete();


           }elseif($order_status == 2){
               //todo 此处原路退款
               if ($order->pay_type == '支付宝') {
                   $alipay = new AliPay();
                   $res = $alipay->alipay_refund($order->order_no,$order->pay_money);
               }elseif ($order->pay_type == '微信'){
                   $wxpay = new WxPay();
                   $res = $wxpay->wxpay_refund($order->order_no,$order->pay_money,$order->pay_money);
               }

               if ($res !== true){
                   return \json(self::callback(0,'取消订单退款失败'));
               }

               $order->order_status = -2;
               $order->cancel_time = time();

               $result = $order->allowField(true)->save();


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
     * 删除订单
     */
    public function deleteOrder(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = GoodsOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        $order->user_is_del = 1;

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));

    }

    /**
     * 确认收货
     */
    public function confirm(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = GoodsOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->order_status == 3 && $order->distribution_status == 2){
            $order->order_status = 4;
            $order->confirm_time = time();

        }else{
            return \json(self::callback(0,'该订单不支持此操作'));
        }

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));


    }


    /**
     * 评价商品
     */
    public function comment(){

    }

    /**
     * 评论订单
     */
    /*public function comment(){
        try{

            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

            $comment_info = input('comment_info');  // 商品id,,,评分,,,评论内容|||商品id,,,评分,,,评论内容

            if (!$order_id || !$comment_info){
                return \json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $arr = explode('|||',$comment_info);
            foreach ($arr as $k){

                $comment = explode(',,,',$k);
                $comment_data[$k]['user_id'] = $userInfo['user_id'];
                $comment_data[$k]['goods_id'] = $comment[0];
                $comment_data[$k]['order_id'] = $order_id;
                $comment_data[$k]['score'] = $comment[1];
                $comment_data[$k]['content'] = $comment[2];

            }

            $order = GoodsOrder::get($order_id);

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->order_status == 4){
                $order->order_status = 5;

            }else{
                throw new \Exception('该订单不支持此操作');
            }
            Db::startTrans();

            $result1 = $order->allowField(true)->save();

            $result2 = (new GoodsComment())->allowField(true)->saveAll($comment_data);

            if (!$result1 || !$result2){
                Db::rollback();
                return \json(self::callback(0,'操作失败'),400);
            }

            Db::commit();
            return \json(self::callback(1,''));

        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }

    }*/

}