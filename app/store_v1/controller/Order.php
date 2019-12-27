<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\store_v1\controller;

use app\store_v1\model\ProductOrder;
use app\wxapi\common\UserLogic;
use app\wxapi\common\Weixin;
use templateMsg\CreateTemplate;
use think\Db;
use think\Exception;
use think\Loader;
use think\response\Json;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\store_v1\model\MaiDanOrder;
class Order extends Base
{

//    /**
//     * 订单列表  普通订单  待发货 已发货 已完成
//     */
//    public function orderList(){
//        try{
//            //token 验证
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
//
//            $page = input('page') ? intval(input('page')) : 1 ;
//            $size = input('size') ? intval(input('size')) : 10 ;
//            $status = input('status');
//            $address_status = input('address_status');  //0待处理
//            $address_status = isset($address_status) ? intval($address_status) : 1 ;
//            //订单状态 1待付款 2待团购 3待发货 4待收货 5待评价 6已完成 -1已取消
//
//            $where['distribution_mode'] = ['eq',2];
//
//            $where['store_id'] = $store_info['id'];
//
//            $data['daifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();
//
//            $data['yifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',4)->count();
//
//            $data['yiwancheng_number'] = Db::name('product_order')->where($where)->where('order_status','>',4)->count();
//
//            switch ($status){
//                case 1:
//                    $where['order_status'] = ['eq',3];
//                    $where['address_status'] = ['eq',$address_status];
//                    break;
//                case 2:
//                    $where['order_status'] = ['eq',4];
//                    break;
//                case 3:
//                    $where['order_status'] = ['gt',4];
//                    break;
//                default:
//                    $where['order_status'] = ['gt',2];
//                    break;
//            }
//
//            $total = Db::name('product_order')->where($where)->count();
//
//            $list = Db::name('product_order')
//                ->field('id,order_no,create_time,order_status,shouhuo_username,shouhuo_mobile,total_freight,address_status,total_platform_price')
//                ->where($where)
//                ->page($page,$size)
//                ->order('create_time','desc')
//                ->select();
//
//            foreach ($list as $k=>$v){
//                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
//                $product = Db::name('product_order_detail')
//                    ->field('cover,product_name,number,price,platform_price')
//                    ->where('product_order_detail.order_id',$v['id'])
//                    ->select();
//
//                $pay_money = 0;
//                foreach ($product as $k2=>$v2){
//                    $pay_money += $v2['number'] * $v2['price'];
//                }
//
//                $list[$k]['pay_money'] = $pay_money;
//
//                $list[$k]['product'] = $product;
//            }
//
//            $data['total'] = $total;
//            $data['max_page'] = ceil($total/$size);
//            $data['list'] = $list;
//
//            return \json(self::callback(1,'',$data));
//
//        }catch (\Exception $e){
//            return json(self::callback(0,$e->getMessage()));
//        }
//    }

    /**
     * 订单列表  普通订单  待发货 已发货 已完成 （2019.7.23改）
     */
    public function orderList(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');
            $address_status = input('address_status');  //0待处理
            $address_status = isset($address_status) ? intval($address_status) : 1 ;
            //订单状态 1待付款 2待团购 3待发货 4待收货 5待评价 6已完成 -1已取消

            $where['distribution_mode'] = ['eq',2];
            $where['store_id'] = $store_info['id'];
            $where1['product_order.distribution_mode'] = ['eq',2]; //统计
            $data['daifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();
            $data['yifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',4)->count();
            $data['yiwancheng_number'] = Db::name('product_order')->where($where)->where('order_status','>',4)->count();

            switch ($status){
                //已取消
                case -1:
                    $where['order_status'] = ['eq',-1];
                    $where['address_status'] = ['eq',$address_status];
                    $where1['product_order.order_status'] = ['eq',-1];
                    $where1['product_order.address_status'] = ['eq',$address_status];
                    break;
                case 1:
                    $where['order_status'] = ['eq',3];
                    $where['address_status'] = ['eq',$address_status];
                    $where1['product_order.order_status'] = ['eq',3];
                    $where1['product_order.address_status'] = ['eq',$address_status];
                    break;
                case 2:
                    $where['order_status'] = ['eq',4];
                    $where1['product_order.order_status'] = ['eq',4];
                    break;
                case 3:
                    $where['order_status'] = ['gt',4];
                    $where1['product_order.order_status'] = ['gt',4];
                    break;
                default:
                    $where['order_status'] = ['gt',2];
                    $where1['product_order.order_status'] = ['gt',2];
                    break;
            }
//--------------------------------------
            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where($where1) //加上条件
                ->where('product_order.order_status','neq',7)
                ->where('product_order_detail.is_shouhou','eq',0) //过滤掉售后订单
                ->group('product_order_detail.order_id')
                ->column('product_order_detail.order_id');
            $total = count($order_id);

//-------------------------------------
//            $total = Db::name('product_order')->where($where)->count();
            $list = Db::name('product_order')
                ->field('id,order_no,create_time,order_status,shouhuo_username,shouhuo_mobile,logistics_company,logistics_number,pay_money,total_freight,address_status,total_platform_price,coupon_id,coupon_money,store_coupon_id,store_coupon_money,product_coupon_id,product_coupon_money')
                ->where('id','in',$order_id)
                ->where($where)
                ->where('order_status','neq',7)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $product = Db::name('product_order_detail')
                    ->field('cover,product_name,number,price,platform_price,status,is_comment,is_shouhou,realpay_money,coupon_money,store_coupon_money,product_coupon_money,activity_id')
                    ->where('product_order_detail.order_id',$v['id'])
                    ->select();
                $pay_money = 0;
                foreach ($product as $k2=>$v2){
                    $pay_money += $v2['number'] * $v2['price'];
                }
                $list[$k]['pay_money'] = $pay_money;
                $list[$k]['product'] = $product;
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 自取订单列表  待取货 已取货
     */
    public function ziquOrderList(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');  //	状态 1待取货 2已取货 0全部
            $where['distribution_mode'] = ['eq',1];
            $where['store_id'] = $store_info['id'];
            $where1['product_order.distribution_mode'] = ['eq',1];//统计
            $data['daiquhuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();
            $data['yiquhuo_number'] = Db::name('product_order')->where($where)->where('order_status','>',3)->count();
            switch ($status){
                case 1:
                    $where['order_status'] = ['eq',3];
                    $where1['product_order.order_status'] = ['eq',3];
                    break;
                case 2:
                    $where['order_status'] = ['gt',3];
                    $where1['product_order.order_status'] = ['gt',3];
                    break;
                default:
                    $where['order_status'] = ['gt',2];
                    $where1['product_order.order_status'] = ['gt',2];
                    break;
            }
//----------------------
            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where($where1) //加上条件
                ->where('product_order_detail.is_shouhou','neq',1) //过滤掉售后订单
                ->group('product_order_detail.order_id')
                ->column('product_order_detail.order_id');
            $total = count($order_id);

//--------------------------
//            $total = Db::name('product_order')->where($where)->count();
            $list = Db::name('product_order')
                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,total_platform_price')
                ->where($where)
                ->page($page,$size)
                ->order(['order_status'=>'asc','create_time'=>'desc'])
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $list[$k]['product'] = Db::name('product_order_detail')
                    ->field('cover,product_name,platform_price')
                    ->where('order_id',$v['id'])
                    ->select();
            }
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 订单详情
     */
    public function orderDetail(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $order_id = input('order_id');
            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $product_order = Db::name('product_order')
                ->field('id,order_no,create_time,order_status,shouhuo_username,shouhuo_address,shouhuo_mobile,pay_money,logistics_company,logistics_number,total_freight,address_status,total_platform_price,coupon_id,coupon_money,store_coupon_id,store_coupon_money,product_coupon_id,product_coupon_money')
                ->where('id',$order_id)
                ->where('store_id',$store_info['id'])
                ->find();
            if (!$product_order) {
                throw new \Exception('订单不存在');
            }
            $product = Db::name('product_order_detail')
                ->field('cover,product_name,number,price,platform_price,status,is_comment,is_shouhou,realpay_money,coupon_money,store_coupon_money,product_coupon_money,activity_id')
                ->where('order_id',$product_order['id'])
                ->select();
            foreach ($product as $k=>$v){
                if ($v['is_comment'] == 1){
                    $comment = Db::name('product_comment')
                        ->field('id,content,create_time')
                        ->where('order_id',$v['order_id'])
                        ->where('specs_id',$v['specs_id'])
                        ->find();
                    $comment['comment_img'] = Db::name('product_comment_img')->where('comment_id',$comment['id'])->column('img_url');
                    $product[$k]['comment']  = $comment;
                }
            }
            $product_order['product'] = $product;
            return \json(self::callback(1,'',$product_order));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 发货
     */
    public function fahuo(){
        #throw new \Exception('禁止发货');
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
//            $logistics_info = input('logistics_info');  //物流信息
            $logistics_number = input('logistics_number');  //物流单号
            $logistics_company = input('logistics_company');  //物流公司
            if (!$order_id ){
                return \json(self::callback(0,'参数错误'),400);
            }
            $model = new ProductOrder();
            $product_order = $model->where('id',$order_id)->where('store_id',$store_info['id'])->where('')->find();
            if (!$product_order) {
                throw new \Exception('订单不存在');
            }
            if ($product_order->order_status != 3){
                throw new \Exception('该订单不支持该操作');
            }
            $product_order->order_status = 4;
            $product_order->fahuo_time = time();
            $product_order->operate_time = time();
            if ($logistics_number && $logistics_company) {
                $product_order->logistics_number = $logistics_number;
                $product_order->logistics_company = $logistics_company;
            }
            $res = $product_order->save();
            if (!$res){
                throw new \Exception('操作失败');
            }
            ##发送小程序消息通知
            if($product_order->pay_type == '微信小程序'){
                $user_id = $product_order->user_id;
                $type = 'order_send_notice';

                ##获取openid
                $open_id = UserLogic::getUserOpenId($user_id);
                if(!$open_id)return \json(self::callback(1,'操作成功,小程序消息通知发送失败[用户不存在]'));

                ##获取access_token
                $access_token = Weixin::getAccessToken();
                if(!$access_token)return \json(self::callback(1,'操作成功,小程序消息通知发送失败[获取access_token失败]'));

                ##获取用户的form_id
                $form_id = UserLogic::getUserFormId($user_id);
                if(!$form_id)return \json(self::callback(1,'操作成功,小程序消息通知发送失败[没有可用form_id]'));

                ##获取模板id
                ###获取商品名
                $productInfo = Db::name('product_order_detail')->where(['order_id'=>$order_id])->field('product_name')->find();
                $data = [
                    'logistics' => $logistics_company,
                    'product_name' => $productInfo['product_name'],
                    'order_no' => $product_order->order_no,
                    'address' => $product_order->shouhuo_address,
                ];
                $templateInfo = UserLogic::getTemplateInfo($type, $data);
                $templateInfo['page'] .= "?id={$order_id}";

                ##更新模板信息的状态
                Db::startTrans();
                $res = UserLogic::useFormId($form_id['id']);
                if($res === false)return \json(self::callback(1,'操作成功,小程序消息通知发送失败[模板信息更新失败]'));

                ##发送消息
                $res = CreateTemplate::sendTemplateMsg($open_id, $templateInfo, $form_id, $access_token);
                $result = json_decode($res, true);
                if($result && isset($result['errcode'])){
                    $errCode = $result['errcode'];
                    if($errCode > 0){
                        Db::rollback();
                        return \json(self::callback(1,"操作成功,小程序消息通知发送失败[模板消息发送失败,错误码{$errCode}]"));
                    }
                    Db::commit();
                }else{
                    Db::rollback();
                    return \json(self::callback(1,'操作成功,小程序消息通知发送失败[模板消息发送失败]'));
                }
            }
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 售后订单列表
     */
    public function shouhouOrderList(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where('product_order_detail.is_shouhou',1)
                ->group('order_id')
                ->column('order_id');

            $total = count($order_id);

            $list = Db::name('product_order')
                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile')
                ->where('id','in',$order_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $list[$k]['product'] = Db::name('product_order_detail')
                    ->field('cover,product_name')
                    ->where('is_shouhou',1)
                    ->where('order_id',$v['id'])
                    ->select();
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 售后订单详情
     */
    public function shouhouOrderDetail(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $order_id = input('order_id');

            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $product_order = Db::name('product_order')
                ->field('id,create_time,order_no,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address')
                ->where('id',$order_id)
                ->where('store_id',$store_info['id'])
                ->find();

            if (!$product_order) {
                throw new \Exception('订单不存在');
            }

            $product = Db::name('product_order_detail')
                ->field('cover,product_name,order_id,specs_id,number,price,freight,is_refund')
                ->where('order_id',$product_order['id'])
                ->where('is_shouhou',1)
                ->select();


            foreach ($product as $k=>$v){
                $shouhou = Db::name('product_shouhou')
                    ->field('id,link_name,link_mobile,description,refuse_description,create_time')
                    ->where('order_id',$v['order_id'])
                    ->where('specs_id',$v['specs_id'])
                    ->find();

                $shouhou['shouhou_img'] = Db::name('product_shouhou_img')->where('shouhou_id',$shouhou['id'])->column('img_url');

                $product[$k]['shouhou']  = $shouhou;
            }

            $product_order['product'] = $product;

            return \json(self::callback(1,'',$product_order));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 售后
     */
    public function shouhou(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;

            }
            $refuse_description = input('refuse_description');
            $specs_id = input('specs_id');
            $order_id = input('order_id');
            $is_refund = input('is_refund');
            if (!$specs_id || !$order_id || !$is_refund){
                return \json(self::callback(0,'参数错误'),400);
            }

            $order_info = Db::name('product_order')->where('id',$order_id)->find();
            $order_product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();
            Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->setField('is_refund',$is_refund);

            if ($is_refund == 1){
                $product_total_price = $order_product['number'] * $order_product['price'];
                //实际退款金额 = 退款单个商品金额 - 优惠券金额 * (退款单个商品金额/总订单金额) - 运费
                $refund_money = $product_total_price - $order_info['coupon_money'] * ($product_total_price/$order_info['pay_money']) - $order_product['freight'];
                $refund_money = round($refund_money,2);
                if ($refund_money > 0) {
                    if ($order_info['pay_type'] == '支付宝') {
                        $alipay = new AliPay();
                        $res = $alipay->alipay_refund($order_info['pay_order_no'],$refund_money);
                    }elseif ($order_info['pay_type'] == '微信'){
                        $wxpay = new WxPay();
                        $total_pay_money = Db::name('product_order')->where('pay_order_no',$order_info['pay_order_no'])->sum('pay_money');
                        $res = $wxpay->wxpay_refund($order_info['pay_order_no'],$total_pay_money,$refund_money);
                    }
                    /*//退款
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order_info['pay_order_no'],$refund_money);*/
                    if ($res){
                        //3退款通知
                        $msg_id = Db::name('user_msg')->insertGetId([
                            'title' => '退款通知',
                            'content' => '您的订单'.$order_info['order_no'].'拼团失败,订单金额已原路返回',
                            'type' => 2,
                            'create_time' => time()
                        ]);

                        Db::name('user_msg_link')->insert([
                            'user_id' => $order_info['user_id'],
                            'msg_id' => $msg_id
                        ]);
                    }
                }
            }else{
                if (!$refuse_description){ return \json(self::callback(0,'拒绝理由不能为空')); }

                Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->update(['refuse_description'=>$refuse_description]);
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 售后列表
     */
    public function shouhouList(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $param = $this->request->post();
            $status=$param['status'];
            if(!$status){
                return \json(self::callback(0,'参数错误'),400);
            }
        switch ($status){
            //查询已关闭
            case -2:
                $state=-2;
                $where1['refund_status'] = ['eq',-2];
                $where2['ps.refund_status'] = ['eq',-2];
                break;
                //查询已拒绝
            case -1:
                $state=-1;
                $where1['refund_status'] = ['eq',-1];
                $where2['ps.refund_status'] = ['eq',-1];
                break;
                //查询全部
            case 1:
                break;
                //查询待处理
            case 2:
                $state=1;
                $where1['refund_status'] = ['eq',1];
                $where2['ps.refund_status'] = ['eq',1];
                break;
                //查询待发货
            case 3:
                $state=2;
                $where1['refund_status'] = ['eq',2];
                $where2['ps.refund_status'] = ['eq',2];
                break;
                //查询待收货
            case 4:
                $state=3;
                $where1['refund_status'] = ['eq',3];
                $where2['ps.refund_status'] = ['eq',3];
                break;
                //查询已完成
            case 5:
                $state=4;
                $where1['refund_status'] = ['eq',4];
                $where2['ps.refund_status'] = ['eq',4];
                break;
                //报错
            default:
                return \json(self::callback(0,'参数错误'),400);
        }
            $store_id=$store_info['id'];
            //查询多少个售后订单
            if($status==1){
                $shouhou=Db::query("SELECT MAX(id) as id FROM `product_shouhou`  WHERE `store_id` = $store_id  GROUP BY `order_id`,`specs_id`");
            }else{
                $shouhou=Db::query("SELECT MAX(id) as id FROM `product_shouhou`  WHERE `store_id` = $store_id AND `refund_status`=$state  GROUP BY `order_id`,`specs_id`");
            }
            $ids = array_column($shouhou, 'id');
            $total=count($ids);
            $list = Db::name('product_shouhou ')->alias('ps')
                ->join('product_order_detail p','ps.order_id = p.order_id AND ps.specs_id = p.specs_id','left')
                ->join('product_order po','p.order_id = po.id','left')
                ->field('ps.id,po.order_no,po.pay_money,po.shouhuo_username,po.shouhuo_mobile,po.shouhuo_address,po.total_freight,po.pay_time,po.fahuo_time,po.logistics_company,po.total_platform_price,po.logistics_number,
                p.cover,p.product_name,p.product_id,p.specs_id,p.product_specs,p.number,p.price,p.is_shouhou,p.is_refund,p.refund_time,p.refund_money,p.realpay_money,ps.refuse_description,ps.return_mode,ps.create_time,ps.refund_type,ps.goods_status,ps.refund_status,ps.refund_reason')
                ->where('p.is_shouhou','neq',0)
                ->where('p.status','neq',-1)
                ->where('ps.id','in',$ids)
                ->where('po.store_id',$store_info['id'])
                ->where($where2)
                ->order('ps.id','desc')
                ->page($page,$size)
                ->select();
                $data['total'] = $total;
                $data['max_page'] = ceil($total/$size);
                $data['list'] = $list;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }
    /**
     * 处理退款/退货退款
     */
    public function handleshouhou(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $id=$param['order_id'];
            $refund_type=$param['refund_type'];//1退款 2退货退款
            $refuse_description=$param['refuse_description'];
            $status=$param['status'];//true同意 false拒绝
            $type=$param['type'];//1待处理同意拒绝 2待收货确认收货
            if (!$id ||!$status ||!$type){return \json(self::callback(0,'参数错误'),400);}
            if ($type!=1 && $type!=2){return \json(self::callback(0,'参数错误'),400);}
            if($type==1){
                if($status=='true'){
                    if (!$refund_type){
                        return \json(self::callback(0,'请选择退货类型'),400);
                    }
                }elseif($status=='false'){
                    if (!$refuse_description){
                        return \json(self::callback(0,'请填写拒绝理由'),400);
                    }
                }
            }
            if($type==2){
              if($status=='false'){
                    if (!$refuse_description){
                        return \json(self::callback(0,'请填写拒绝理由'),400);
                    }
                }
            }
            $order_status = Db::name('product_shouhou')->where('id',$id)->find();
            $order_info = Db::name('product_order')->where('id',$order_status['order_id'])->where('store_id',$store_info['id'])->find();
            $order_product = Db::name('product_order_detail')->where('order_id',$order_status['order_id'])->where('specs_id',$order_status['specs_id'])->find();
            if(empty($order_info)||empty($order_product)||empty($order_status)){
                return \json(self::callback(0,'没找到完整数据'),400);
            }
            if(($order_product['is_shouhou']!=1 && $order_product['is_shouhou']!=3)||$order_product['is_refund']==1 || $order_product['refund_time']>0 ||$order_product['refund_money']>0|| ($order_status['refund_status']!=1 && $order_status['refund_status']!=3)){
                return \json(self::callback(0,'该订单不支持该操作或已完成'),400);
            }
            if($status=='true'){
                //待处理
                    //判断退货退款类型
                    if (($type==1 && $refund_type==1) || ($type==2 && $order_status['refund_type']==2)){
                        //1退款
                        if ($order_product['realpay_money']>0) {
                            if($order_info['pay_scene']==1){
                                //继续支付
                                $pay_order_no=$order_info['order_no'];
                            }else{
                                $pay_order_no=$order_info['pay_order_no'];
                            }
                            Db::startTrans();
                            if ($order_info['pay_type'] == '支付宝') {
                                $alipay = new AliPay();
                                $res = $alipay->alipay_refund($pay_order_no,$order_product['id'],$order_product['realpay_money']);
                            }elseif ($order_info['pay_type'] == '微信'){
                                $wxpay = new WxPay();
                                $total_pay_money = Db::name('product_order')->where('pay_order_no',$pay_order_no)->sum('pay_money');
                                $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$order_product['realpay_money']);
                            }
                            if ($res){
                                //3退款通知
                                $msg_id = Db::name('user_msg')->insertGetId([
                                    'title' => '退款通知',
                                    'content' => '您的订单'.$order_info['order_no'].'商家已同意退款,订单金额已原路返回',
                                    'type' => 2,
                                    'create_time' => time()
                                ]);

                                Db::name('user_msg_link')->insert([
                                    'user_id' => $order_info['user_id'],
                                    'msg_id' => $msg_id
                                ]);
                                if($type==2 && $order_status['refund_type']==2){
                                    $genxin2 = [
                                        'refund_type' => 2,
                                        'refund_status' => 4,
                                        'store_goods_status'=>2,
                                        'finish_time' => time()
                                    ];
                                }elseif($type==1 && $refund_type==1){
                                    $genxin2 = [
                                        'refund_type' => 1,
                                        'refund_status' => 4,
                                        'finish_time' => time()
                                    ];
                                }
                                $rst2=Db::name('product_shouhou')->where('id',$id)->update($genxin2);//更新
                                $genxin = [
                                    'is_refund' => 1,
                                    'refund_time' => time(),
                                    'is_shouhou' => 4,
                                    'refund_money' => $order_product['realpay_money']
                                ];
                                $rst1=Db::name('product_order_detail')->where('id',$order_product['id'])->update($genxin);//更新
                                if($rst1 ===false || $rst2===false){return \json(self::callback(0,'更新退款信息状态失败!'),400);}
                                //判断是否该订单全部退款/退货退款
//                                $num = Db::name('product_order_detail')->where('order_id',$order_product['order_id'])->count();
//                                $num2 = Db::name('product_order_detail')->where('order_id',$order_product['order_id'])->where('is_shouhou','in','-1,-2,4')->count();
//                                if($num==$num2){
//                                    Db::name('product_order')->where('id',$order_info['id'])->setField('order_status',7);
//                                }
                                Db::commit();
                            }else{
                                Db::rollback();
                                return \json(self::callback(0,'退款失败!'),400);
                            }
                        }else{
                            return \json(self::callback(0,'退款金额小于等于0 不用退款'),400);
                        }
                    }elseif($type==1 && $refund_type==2 ){
                        //2退货退款
                        $genxin2 = [
                            'refund_type' => 2,
                            'refund_status' => 2,
                            'agree_time' => time()
                        ];
                        $rst1=Db::name('product_shouhou')->where('id',$id)->update($genxin2);//更新
                        if($rst1===false){
                            return \json(self::callback(0,'操作失败'),400);
                        }
                    }else{
                        return \json(self::callback(0,'状态错误'),400);
                    }
            }else if($status=='false'){
             //拒绝
                if($type==2){
                    $genxin = [
                        'refund_status' => -2,
                        'refuse_time'=>time(),
                        'store_goods_status'=>1,
                        'refuse_description' => $refuse_description
                    ];
                    $genxin2 = [
                        'is_shouhou' => -2,
                        'comment_standard_time'=>time(),
                        'finish_standard_time'=>time()
                    ];
                }elseif ($type==1){
                    $genxin = [
                        'refund_status' => -1,
                        'refuse_time'=>time(),
                        'refuse_description' => $refuse_description
                    ];
                    $genxin2 = [
                        'is_shouhou' => -1,
                        'comment_standard_time'=>time(),
                        'finish_standard_time'=>time()
                    ];
                }
                $rst1=Db::name('product_shouhou')->where('id',$id)->update($genxin);//更新
                $rst2=Db::name('product_order_detail')->where('order_id',$order_status['order_id'])->where('specs_id',$order_status['specs_id'])->update($genxin2);//更新
                if($rst1===false || $rst2===false){
                    return \json(self::callback(0,'操作失败'),400);
                }
                //判断是否该订单全部退款/退货退款
                $shouhou = Db::name('product_shouhou')->field('id,refund_status')->where('order_id',$order_status['order_id'])->where('specs_id',$order_status['specs_id'])->select();
                $num2=0;
                foreach ($shouhou as $k=>$v){
                    if($v['refund_status']==-1 || $v['refund_status']==-2 ){
                        $num2++;
                    }
                }
//                $num2 = Db::name('product_order_detail')->where('order_id',$order_product['order_id'])->where('is_shouhou','in','-1,-2,4')->count();
                if($num2==2){
                   // Db::name('product_order')->where('id',$order_info['id'])->setField('order_status',7);
                    Db::name('product_order_detail')->where('order_id',$order_status['order_id'])->where('specs_id',$order_status['specs_id'])->setField('is_platform_service',1);
                }

            }else{
                return \json(self::callback(0,'错误操作'),400);
            }
            return \json(self::callback(1,'操作成功',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 售后订单详情
     */
    public function shouhouDetail(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $id=$param['id'];
            if (!$id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $product_order = Db::view('product_shouhou','id,refuse_description,return_mode,create_time,refund_type,goods_status,refund_status,refund_reason,logistics_company,logistics_number')
                 ->view('product_order_detail','order_id,cover,product_name,product_id,specs_id,coupon_money,store_coupon_money,product_coupon_money,product_specs,number,price,is_shouhou,is_refund,refund_time,refund_money,realpay_money','product_shouhou.order_id = product_order_detail.order_id AND product_shouhou.specs_id = product_order_detail.specs_id','left')
                ->view('product_order','order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,user_id,coupon_id as user_css_coupon_id,coupon_money,total_freight,pay_type,pay_time,fahuo_time,logistics_info,total_platform_price','product_order_detail.order_id = product_order.id','left')
                ->where('product_shouhou.id',$id)
//                 ->where('product_order_detail.is_shouhou',1)
                 ->find();
            if (!$product_order){
                throw new \Exception('订单不存在');
            }
            //查询所有图片
            $images=Db::name('product_shouhou_img')->where('shouhou_id',$product_order['id'])->select();
            $data['order_info']=$product_order;
            $data['order_info']['images']=$images;
            return \json(self::callback(1,'返回成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 获取买单列表
     * @param MaiDanOrder $maiDanOrder
     * @return Json
     */
    public function maiDanOrderList(MaiDanOrder $maiDanOrder){
        try{
            $store_id = input('post.store_id',0,'intval');
            $order_sn = input('post.order_sn','','addslashes,strip_tags,trim');
            $user_mobile = input('post.user_mobile','','addslashes,strip_tags,trim');
            $start_time = input('post.start_time','','addslashes,strip_tags,trim');
            $end_time = input('post.end_time','','addslashes,strip_tags,trim');
            $page = input('post.page',0,'intval');
            $size = input('post.size',20,'intval');
            $size = max(10,$size);
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time)+86399;
            if(!$start_time || !$end_time)throw new Exception('时间格式错误');
            $where = [
                'store_id' => $store_id,
                'status' => 2,
                'pay_time' => ['BETWEEN', [$start_time,$end_time]],
            ];
            if($order_sn){
                $where['order_sn'] = ['LIKE',"%{$order_sn}%"];
            }
            if($user_mobile){
                if(!preg_match("/^\d*$/",$user_mobile) || strlen($user_mobile) > 11)throw new Exception('电话号码格式错误');
                $where['user_mobile'] = ['LIKE',"%{$user_mobile}%"];
            }
            ##查询
            $total = $maiDanOrder->where($where)->count('id');
            if(!$total)throw new Exception('暂无数据');
            $max_page = ceil($total/$size);
            $list = $maiDanOrder
                ->alias('md')
                ->join('store s', 'md.store_id = s.id and s.store_status = 1', 'left')
                ->where($where)
                ->field(['md.id','md.price_yj','md.price_maidan','md.user_mobile','md.discount_platform','md.coupon_money','md.is_member','md.platform_profit','md.order_sn','md.status','md.pay_time','md.platform_policy','md.store_policy','md.price_store','0+CONVERT(md.discount,CHAR) as discount','0+CONVERT(s.maidan_deduct,CHAR) as maidan_deduct'])
                ->limit(($page-1)*$size,$size)
                ->order('pay_time','desc')
                ->select();

//            foreach ($list as &$v){
//                $v['discount']=floatval( $v['discount']);
//
//            }
            return \json(self::callback(1,'',compact('max_page','total','list')));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}