<?php


namespace app\business\model;


use think\Model;
use think\Db;
use app\user_v5\controller\AliPay;
use app\user\controller\WxPay;
class ProductShouHouModel extends Model
{

    protected $pk = 'id';

    protected $table = 'product_shouhou';

    /**
     * 售后 员工稍后处理
     */
    public static function businessGetShouOrder($s_id,$user_id){
        //检查订单是否被跟进
        $GetCheck = self::where(['id' => $s_id])->field(['id','business_id'])->find();
        if (!$GetCheck) return '未找到相关订单';
        if($GetCheck['business_id'] == 0){
            //关联售后表中员工id
            $order = self::where('id', $GetCheck['id'])->update(['business_id' => $user_id]);
            if($order){
                return true;
            }else{
                return '处理失败';
            }
        }
        return '订单已被跟进';
    }

    /**
     * 售后 拒绝申请
     */
    public static function  refuseApplication($s_id,$user_id){
        $checkData = self::where(['id' => $s_id]) -> field(['id','order_id', 'product_id','business_id']) -> find();
        if (!$checkData) return '未找到相关订单';
        if($checkData['business_id'] > 0 && $checkData['business_id'] != $user_id) return '订单已被跟进';
        Db::startTrans();
        $updateOrderStatus = self::where(['id' => $checkData['id']]) -> update(['refund_status' => -1,'business_id' => $user_id, 'refuse_time' => time()]);
        $orderBusiness = OrderDetailsModel::where(['order_id' => $checkData['order_id'],'product_id'=>$checkData['product_id']]) -> update(['is_shouhou' => -1]);
        if (!$updateOrderStatus){
            Db::rollback();
            // 处理失败
            return '处理失败';
        }
        Db::commit();
        // 处理成功
        return true;
    }
    /**
     * 售后拒绝退款
     * @param $details_id
     * @param $refuse_description
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function businessRefuseRefund($details_id, $refuse_description,$user_id){
        $checkData = self::where(['id' => $details_id]) -> field(['id','order_id', 'product_id','business_id']) -> find();
        if (!$checkData) return '未找到相关订单';
        if($checkData['business_id'] > 0 && $checkData['business_id'] != $user_id) return '订单已被跟进';
        Db::startTrans();
        $updateOrderStatus = self::where(['id' => $checkData['id']]) -> update(['refund_status' => -2,'business_id' => $user_id, 'refuse_description' => $refuse_description, 'refuse_time' => time()]);
        $orderBusiness = OrderDetailsModel::where(['order_id' => $checkData['order_id'],'product_id'=>$checkData['product_id']]) -> update(['is_shouhou' => -2]);

        if (!$updateOrderStatus){
            Db::rollback();
            // 处理失败
            return '处理失败';
        }
        Db::commit();
        // 处理成功
        return true;
    }

    /**
     * 售后 -同意退货
     * @param $s_id
     * @param $shouhuo_address
     * @param $shouhuo_username
     * @param $shouhuo_mobile
     * @param $user_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function salesReturn($s_id,$store_id,$user_id){
        $storeData = db('store')->where(['id' => $store_id]) -> field(['id','refund_address', 'refund_name','refund_mobile']) -> find();
        if (!$storeData) return '未找到相关订单';
        $checkData = self::where(['id' => $s_id]) -> field(['id','order_id', 'product_id','business_id']) -> find();
        if (!$checkData) return '未找到相关订单';
        if($checkData['business_id'] > 0 && $checkData['business_id'] != $user_id) return '订单已被跟进';
        Db::startTrans();
        $updateOrderStatus = self::where(['id' => $checkData['id']]) -> update(['refund_status' => 2,'refund_type'=>2,'business_id' => $user_id, 'shouhuo_address' => $storeData['refund_address'],'shouhuo_username' =>  $storeData['refund_name'],'shouhuo_mobile' =>  $storeData['refund_mobile'], 'agree_time' => time()]);
        $orderBusiness = OrderDetailsModel::where(['order_id' => $checkData['order_id'],'product_id'=>$checkData['product_id']]) -> update(['is_shouhou' => 2]);
        if (!$updateOrderStatus || !$orderBusiness){
            Db::rollback();
            // 处理失败
            return '处理失败';
        }
        Db::commit();
        // 处理成功
        return true;
    }

    /**
     * 售后 立即退款
     */
    public static function moneyBack($s_id,$user_id,$store_id){

        $GetCheck = self::where(['ps.id' => $s_id,'ps.store_id'=>$store_id])
            ->alias('ps')
            ->join('product_order o', 'o.id = ps.order_id','left')
            ->join('product_order_detail pod', 'ps.order_id = pod.order_id AND ps.product_id = pod.product_id','left')
            ->field([
                'ps.id','ps.order_id','ps.product_id','ps.business_id',
                'o.pay_order_no', 'o.order_no','o.pay_money','o.coupon_id','o.store_coupon_id','o.pay_type','o.pay_scene',
                'pod.realpay_money','pod.id as pod_id',
            ])
            ->find();
        if (!$GetCheck) return '未找到相关订单';
        if($GetCheck['business_id'] > 0 && $GetCheck['business_id'] != $user_id) return '订单已被跟进';
        $storeData = db('store')->where(['id' => $store_id]) -> field(['id','refund_address', 'refund_name','refund_mobile']) -> find();
        if (!$storeData) return '无店铺信息';
        $pay_order_no = $GetCheck['pay_scene']?$GetCheck['order_no']:$GetCheck['pay_order_no'];
        Db::startTrans();
        //todo 此处原路退款
        if ($GetCheck['pay_type'] == '支付宝') {
            //out_request_no：标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传。
            //也可以理解为同一笔交易退款，退款金额小于付款金额是，必须传这个参数，而且同一笔交易分多次退款的话，out_request_no每次传值都不能重复，必须保证唯一性
            $alipay = new AliPay();
            $res = $alipay->alipay_refund($pay_order_no,$GetCheck['id'],$GetCheck['realpay_money']);
        }elseif ($GetCheck['pay_type'] == '微信'){
            $wxpay = new WxPay();
            $res = $wxpay->wxpay_refund($pay_order_no,$GetCheck['pay_money'],$GetCheck['realpay_money'],2);
        }
        if ($res !== true){
            Db::rollback();
            // 处理失败
            return '处理失败';
        }



        //修改订单状态
        $updateOrderStatus1 = Db::name('product_order_detail')->where(['id'=>$GetCheck['pod_id']])->update(['is_shouhou'=>4,'is_refund'=> 1,'refund_time'=>time(),'refund_money'=>$GetCheck['realpay_money']]);
        $updateOrderStatus2 = Db::name('product_shouhou')->where(['id'=>$GetCheck['id']])->update(['refund_status'=>4,'refund_type'=> 1,'agree_time'=>time(),'finish_time'=>time(),'shouhuo_address' => $storeData['refund_address'],'shouhuo_username' =>  $storeData['refund_name'],'shouhuo_mobile' =>  $storeData['refund_mobile'],'business_id'=>$user_id]);
        //返回库存
        $updateOrderStatus3 = $product = Db::name('product_order_detail')->where('order_id',$GetCheck['order_id'])->select();
        foreach ($product as $k=>$v){
            Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
        }
        //3退款通知
        $msg_id = Db::name('user_msg')->insertGetId([
            'title' => '退款通知',
            'content' => '您的订单'.$GetCheck['order_no'].'售后已成功，订单金额将原路返回！',
            'type' => 2,
            'create_time' => time()
        ]);
        Db::name('user_msg_link')->insert([
            'user_id' => $user_id,
            'msg_id' => $msg_id
        ]);
        //判断是否有平台优惠券
        if($GetCheck['coupon_id']>0 ){
            Db::name('coupon')->where('id',$GetCheck['coupon_id'])->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }
        //判断是否有店铺优惠券
        if($GetCheck['store_coupon_id']>0){
            Db::name('coupon')->where('id',$GetCheck['store_coupon_id'])->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
        }

        if (!$updateOrderStatus1 || !$updateOrderStatus2|| !$updateOrderStatus3){
            Db::rollback();
            // 处理失败
            return '处理失败';
        }
        Db::commit();
            // 处理成功
        return true;
    }


    /**
     *  获取售后订单数据
     * @param $where
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*public static function getAfterSaleOrderData($where, $user_id ,$is_main_user){
        $data = ProductModel::where($where)
            ->alias('p')
            ->join('product_order_detail od', 'od.order_id = p.id')
            ->join('user u', 'u.user_id = p.user_id')
            ->join('order_business ob', 'ob.order_id = p.id')
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1')
            ->field([
                'p.order_status','p.order_no','p.user_id','p.id',
                'u.nickname', 'u.avatar',
                'od.is_shouhou','od.realpay_money','ob.buniess_id','od.product_name','od.cover','od.product_specs','od.id as after_sale_id',
                'IF(ob.buniess_id = '.$user_id.', \'我\', b.business_name) as business_name',
                'IF(ob.buniess_id = 0, 0, 1) as is_receive',
            ])
            ->select();
        if($is_main_user != '1'){
            foreach ($data as $k => $v){
                if($v['buniess_id'] != '0' && $v['buniess_id'] != $user_id){
                    unset($data[$k]);
                }
            }
        }

        return $data;
    }*/
}