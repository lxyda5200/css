<?php


namespace app\business\model;


use think\Db;
use think\Exception;
use think\Model;
use app\user_v5\controller\AliPay;
use app\user\controller\WxPay;
class ProductModel extends Model
{

    protected $pk = 'id';

    protected $table = 'product_order';

    /**
     * 获取商店总览数据
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException  'IFNULL(pi.img_url, ps.cover) as img_url',
     */
    public function getPandect($store_id){
        $data['one'] = self::where(['order_status' => 1,'store_id'=>$store_id])->count();
        $data['three'] = self::where(['order_status' => 3,'store_id'=>$store_id])->count();
        $data['five'] = self::where(['order_status' => 5,'store_id'=>$store_id])->count();
        return $data;
    }

    /**
     * 获取商铺售后订单数量
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getShouNum($store_id){
        $where['store_id'] = $store_id;
        $where['refund_status'] = ['in', [1, 2, 3]];
        $num = ProductShouHouModel::where($where)->count();
        return $num;
    }

    /**
     * 获取本月商城订单以及买单数量
     * @param $store_id
     * @param $user_info
     * @return array
     */
    public function getOrderNum($store_id,$user_info){

        if ($user_info['is_main_user'] == 1){
            // 商城订单查询条件
            $shopWhere = ['p.store_id' => $store_id, 'p.order_status' => ['egt', 3]];
            // 买单订单查询条件
            $maiWhere = ['store_id' => $store_id, 'status' => 2];
        }else{
            $shopWhere = ['p.store_id' => $store_id, 'ob.buniess_id' => $user_info['id'], 'p.order_status' => ['egt', 3],'ob.type' => 1];
            $maiWhere = ['store_id' => $store_id,  'staff_id' => $user_info['id'], 'status' => 2];
        }
        // 本月数据条件
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end = strtotime(date('Y-m-d H:i:s'));
        $monthWhere1 = ['p.create_time' => ['between', "{$start},{$end}"]];
        $monthWhere2 = ['create_time' => ['between', "{$start},{$end}"]];
        $shopWhere = array_merge($shopWhere,$monthWhere1);
        $maiWhere = array_merge($maiWhere,$monthWhere2);
        // 商城订单总数 order_status >= 6
        $shopOrderNum = OrderBusinessModel::getOrderNumByWhere($shopWhere);
        // 到店订单总数
        $maiOrderNum = OrderBusinessModel::getOrderNumByWhereMai($maiWhere);

        return compact('shopOrderNum', 'maiOrderNum');
    }

    /**
     * 我的任务-待发货统计
     */
    public static function getOrderHomeTask($where){
        $data = self::where($where)
            ->alias('o')
            ->join('order_business ob', 'o.id = ob.order_id','left')
            ->field(['o.id','ob.buniess_id AND ob.type = 1'])
            ->count();

        return $data;
    }

    /**
     * 我的任务-售后统计
     */
    public static function getOrderHomeTaskShou($store_id,$user_id){
        $where['store_id'] = $store_id;
        $where['refund_status'] = ['in', [1, 2, 3]];
        $where['business_id'] = $user_id;
        $num = ProductShouHouModel::where($where)
            ->count();
        return $num;
    }

    /**
     * 订单已取消列表
     * @param $where
     * @param $user_id
     * @param $page
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderListByWhereQuxiao($where,$user_id,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = ProductModel::where($where)
            ->alias('o')
            ->join('user u', 'u.user_id = o.user_id')
            ->join('order_business ob', 'ob.order_id = o.id','left')
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1','left')
            ->join('product_order_detail pod', 'o.id = pod.order_id AND pod.status < 0')
            //->with('products')
            ->field([
                'o.id','o.order_no', 'o.pay_money', 'o.total_freight','o.user_id','o.order_status',
                'u.nickname', 'u.avatar','o.distribution_mode',
                'o.pay_type',
                'IF(ob.buniess_id = 0, 0, 1) as is_receive',
                'IFNULL(ob.buniess_id,"") as buniess_id',
                'IF(ob.buniess_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name',
                'o.create_time',
                'FROM_UNIXTIME(o.create_time,\'%Y-%m-%d %H:%i\') as create_at',
                'FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time'
            ])
            ->order('o.create_time desc')
            ->group('o.id')
            ->limit($pre,$limit)
            ->select();

        foreach ($list as &$v){
            $v['is_fahou'] = 0;
            $v['products'] = [];
            $where_detail['order_id'] = $v['id'];
            $where_detail['status'] = ['in',['-1','-2']];
            $v['products'] = OrderDetailsModel::where($where_detail)->field('product_name,price,cover,freight,order_id,product_id,number,product_specs,is_shouhou,is_refund,status')->select();
        }
        return $list;
    }

    /**
     * 订单列表
     * @param $where
     * @param $user_id
     * @param $page
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderListByWhereNew($where,$user_id,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = ProductModel::where($where)
            ->alias('o')
            ->join('user u', 'u.user_id = o.user_id')
            ->join('order_business ob', 'ob.order_id = o.id','left')
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1','left')
            ->with('products')
            ->field([
                'o.id','o.order_no', 'o.pay_money', 'o.total_freight','o.user_id','o.order_status',
                'u.nickname', 'u.avatar','o.distribution_mode',
                'IF(ob.buniess_id = 0, 0, 1) as is_receive',
                'IFNULL(ob.buniess_id,"") as buniess_id',
                'IF(ob.buniess_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name',
                'o.create_time','o.pay_type','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time'
            ])
            ->group('o.id')
            ->order('o.create_time desc')
            ->limit($pre,$limit)
            ->select();
        foreach ($list as $k => $v){
            $v['is_fahou'] = 0;
            if($v['order_status'] == 3){
                if($v['business_name'] == '' || $v['business_name'] == '我') $v['is_fahou'] = 1;
            }
        }
        return $list;
    }

    /**
     * 订单列表 -售后
     * @param $where
     * @param $user_id
     * @param $page
     * @param $limit
     * @return mixed
     */
    public static function getOrderListByWhereNewShou($where,$user_id,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = ProductShouHouModel::where($where)
            ->alias('ps')
            ->join('product_order o', 'ps.order_id = o.id','left')
            ->join('product_order_detail pod', 'ps.order_id = pod.order_id AND ps.product_id = pod.product_id','left')
            ->join('user u', 'ps.user_id = u.user_id','left')
            ->join('business b', 'ps.business_id = b.id','left')
            ->field([
                'ps.id as s_id','o.id as order_id','o.user_id','o.create_time','ps.product_id', 'o.order_status','o.order_no',
                'pod.id','pod.product_id','pod.cover','o.distribution_mode',
                'u.nickname', 'u.avatar','pod.product_name','pod.product_specs',
                'pod.number','pod.realpay_money','ps.refund_status as is_shouhou','ps.product_id as product_id',
                'IFNULL(ps.business_id,"") as business_id',
                'IF(ps.business_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name',
                '(pod.price * pod.number) as price'
            ])
            ->group('ps.id')
            ->order('ps.create_time desc')
            ->limit($pre,$limit)
            ->select();
        foreach ($list as $k => $v){
            $list[$k]['product_specs'] = json_decode($v['product_specs'], true);
            $list[$k]['is_fahou'] = 0;
                if($v['business_name'] == '' || $v['business_name'] == '我') $list[$k]['is_fahou'] = 1;
        }
        return $list;
    }


    /**
     *  商城订单详情
     * @param $order_id      订单ID
     * @param $order_status  订单状态
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderDetailsById($order_id, $order_status,$user_id){
        $where = ['o.id' => $order_id, 'o.order_status' => $order_status];
        $data = ProductModel::where($where)
            ->alias('o')
            //->join('product_order_detail pod', 'pod.order_id = o.id') // 查看订单详情
            ->join('user u', 'u.user_id = o.user_id','left') // 查看订单用户信息
            ->join('order_business ob', 'ob.order_id = o.id', 'left') // 查看是否有员工接手订单
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1', 'left') // 查看接手员工信息
            ->with('products')
            ->field([
                'o.id','o.order_status','o.pay_type','o.order_no','o.create_time as c_time','o.pay_time as p_time',
                'o.shouhuo_username','o.pay_money','o.shouhuo_mobile','o.shouhuo_address','o.coupon_id','o.coupon_money',
                'o.store_coupon_money','o.product_coupon_money','o.discount_money','o.return_money','o.total_freight',
                'o.create_time',
                'FROM_UNIXTIME(o.create_time,\'%Y-%m-%d %H:%i\') as create_at',
                'if(o.pay_time =0,"",FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\')) as pay_time',
                'if(o.fahuo_time =0,"",FROM_UNIXTIME(o.fahuo_time,\'%Y-%m-%d %H:%i\')) as fahuo_time',
                'if(o.confirm_time =0,"",FROM_UNIXTIME(o.confirm_time,\'%Y-%m-%d %H:%i\')) as finish_time',
                'o.logistics_number','o.logistics_company','o.distribution_mode',
                'u.user_id','u.nickname','u.mobile','u.jig_id',
                'IFNULL(ob.buniess_id,"") as buniess_id',
                'IF(ob.buniess_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name'
            ])
            ->find();

        if($data){
            $data['is_fahou'] = 0;
            if($order_status == 3){
                if($data['business_name'] == '' || $data['business_name'] == '我') $data['is_fahou'] = 1;
            }
            $coupon_type = 0;
            $coupon_type_momey = '0.00';
            if($data['store_coupon_money'] > 0){ //店铺
                $coupon_type = 2;
                $coupon_type_momey = $data['store_coupon_money'];
            }elseif ($data['product_coupon_money'] > 0){  //商品
                $coupon_type = 3;
                $coupon_type_momey = $data['product_coupon_money'];
            }elseif ($data['discount_money'] > 0){ //满减
                $coupon_type = 4;
                $coupon_type_momey = $data['discount_money'];
            }elseif ($data['return_money'] > 0){ //返现
                $coupon_type = 5;
                $coupon_type_momey = $data['return_money'];
            }
            $data['coupon_type']       = $coupon_type;
            $data['coupon_type_momey'] = $coupon_type_momey;
            $data['distribution_mode'] = intval($data['distribution_mode']);
            unset($data['store_coupon_money']);
            unset($data['product_coupon_money']);
            unset($data['discount_money']);
            unset($data['return_money']);
            $now_time = time();
            $end_time = time();
            if($order_status == 1){
                $limit = config('config_order.hour_cancel_not_pay');
                $end_time =$data['c_time'] + $limit*60*60;
            }elseif ($order_status == 3){
                $limit = config('config_order.hour_cancel_not_send');
                $end_time =$data['p_time'] + $limit*60*60;
            }
            unset($data['c_time']);
            unset($data['p_time']);
            $data['now_time'] = $now_time;
            $data['end_time'] = $end_time;
        }
        return $data;
    }

    /**
     *  商城订单详情-已取消
     * @param $order_id      订单ID
     * @param $order_status  订单状态
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderDetailsByIdQuxiao($order_id, $order_status,$user_id){
        $where = ['o.id' => $order_id];
        $data = ProductModel::where($where)
            ->alias('o')
            //->join('product_order_detail pod', 'pod.order_id = o.id') // 查看订单详情
            ->join('user u', 'u.user_id = o.user_id','left') // 查看订单用户信息
            ->join('order_business ob', 'ob.order_id = o.id', 'left') // 查看是否有员工接手订单
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1', 'left') // 查看接手员工信息
            ->join('product_order_detail pod', 'o.id = pod.order_id AND pod.status < 0')
            //->with('products')
            ->field([
                'o.id','o.order_status','o.pay_type','o.order_no','o.create_time as c_time','o.pay_time as p_time','o.shouhuo_username','o.pay_money','o.shouhuo_mobile','o.shouhuo_address','o.coupon_id','o.coupon_money','o.store_coupon_money','o.product_coupon_money','o.discount_money','o.return_money','o.total_freight',
                'o.create_time',
                'FROM_UNIXTIME(o.create_time,\'%Y-%m-%d %H:%i\') as create_at',
                'if(o.pay_time =0,"",FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\')) as pay_time',
                'if(o.fahuo_time =0,"",FROM_UNIXTIME(o.fahuo_time,\'%Y-%m-%d %H:%i\')) as fahuo_time',
                'if(o.confirm_time =0,"",FROM_UNIXTIME(o.confirm_time,\'%Y-%m-%d %H:%i\')) as finish_time',
                'if(o.cancel_time =0,"",FROM_UNIXTIME(o.cancel_time,\'%Y-%m-%d %H:%i\')) as cancel_time',
                'o.logistics_number','o.logistics_company','o.distribution_mode',
                'u.user_id','u.nickname','u.mobile','u.jig_id',
                'IFNULL(ob.buniess_id,"") as buniess_id',
                'IF(ob.buniess_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name'
            ])
            ->find();
        $data['products'] = [];
        $where_detail['order_id'] = $data['id'];
        $where_detail['status'] = ['in',['-1','-2']];
        $data['products'] = OrderDetailsModel::where($where_detail)->field('product_name,price,cover,freight,order_id,product_id,number,product_specs,is_shouhou,is_refund,status')->select();


        if($data){
            $data['is_fahou'] = 0;
            $coupon_type = 0;
            $coupon_type_momey = '0.00';
            if($data['store_coupon_money'] > 0){
                $coupon_type = 1;
                $coupon_type_momey = $data['store_coupon_money'];
            }elseif ($data['product_coupon_money'] > 0){
                $coupon_type = 2;
                $coupon_type_momey = $data['product_coupon_money'];
            }elseif ($data['discount_money'] > 0){
                $coupon_type = 3;
                $coupon_type_momey = $data['discount_money'];
            }elseif ($data['return_money'] > 0){
                $coupon_type = 4;
                $coupon_type_momey = $data['return_money'];
            }
            $data['coupon_type']       = $coupon_type;
            $data['coupon_type_momey'] = $coupon_type_momey;
            $data['distribution_mode'] = intval($data['distribution_mode']);
            unset($data['store_coupon_money']);
            unset($data['product_coupon_money']);
            unset($data['discount_money']);
            unset($data['return_money']);
            $now_time = time();
            $end_time = time();
            if($order_status == 1){
                $limit = config('config_order.hour_cancel_not_pay');
                $end_time =$data['c_time'] + $limit*60*60;
            }elseif ($order_status == 3){
                $limit = config('config_order.hour_cancel_not_pay');
                $end_time =$data['p_time'] + $limit*60*60;
            }
            unset($data['c_time']);
            unset($data['p_time']);
            $data['now_time'] = $now_time;
            $data['end_time'] = $end_time;
        }
        return $data;
    }

    /**
     * 商城订单详情 -售后
     * @param $order_id
     * @param $user_id
     * @param $store_id
     * @param $s_id
     * @param $product_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getAfterSaleOrderDetails($order_id,$user_id,$store_id,$s_id,$product_id){
        $checkData = ProductShouHouModel::where(['order_id' => $order_id,'product_id'=>$product_id])->count();
        if($checkData == 0) return false;
        $shouhou_num = $checkData;
        //判断同一商品 第二次申请售后
        if ($shouhou_num > 1){
            $re = ProductShouHouModel::where(['order_id' => $order_id,'product_id'=>$product_id])->field('max(id) as id')->find();
            if($re['id'] != $s_id) $shouhou_num = 1;
        }

        $where = ['o.id' => $order_id, 'o.store_id'=>$store_id,'ps.id'=>$s_id];
        $data = ProductShouHouModel::where($where)
            ->alias('ps')
            ->join('product_order o', 'ps.order_id = o.id','left')
            ->join('product_order_detail pod', 'ps.order_id = pod.order_id AND ps.product_id = pod.product_id','left')
            ->join('user u', 'ps.user_id = u.user_id','left')
            ->join('business b', 'ps.business_id = b.id','left')
            ->field([
                'ps.id as s_id','o.id as order_id','o.user_id', 'o.order_no','o.pay_type', 'pod.id','pod.product_id','pod.cover',
                'u.nickname', 'u.avatar','u.mobile','u.jig_id','pod.product_name','pod.product_specs',
                'pod.number','pod.realpay_money','ps.refund_status as is_shouhou','ps.refund_status',
                'ps.refund_type','ps.goods_status','ps.refund_reason','ps.shouhuo_address','ps.shouhuo_username',
                'ps.shouhuo_mobile','ps.logistics_company','ps.logistics_number','ps.business_id','ps.store_goods_status','ps.refuse_description',
                'ps.create_time',
                'FROM_UNIXTIME(ps.create_time,\'%Y-%m-%d %H:%i\') as create_at',
                'if(ps.agree_time =0,"",FROM_UNIXTIME(ps.agree_time,\'%Y-%m-%d %H:%i\')) as agree_time',
                'if(ps.fahuo_time =0,"",FROM_UNIXTIME(ps.fahuo_time,\'%Y-%m-%d %H:%i\')) as fahuo_time',
                'if(ps.refuse_time =0,"",FROM_UNIXTIME(ps.refuse_time,\'%Y-%m-%d %H:%i\')) as refuse_time',
                'if(o.confirm_time =0,"",FROM_UNIXTIME(o.confirm_time,\'%Y-%m-%d %H:%i\')) as finish_time',
                'if(o.cancel_time =0,"",FROM_UNIXTIME(o.cancel_time,\'%Y-%m-%d %H:%i\')) as cancel_time',
                'IFNULL(ps.business_id,"") as business_id',
                'IF(ps.business_id = '.$user_id.', \'我\', IFNULL(b.business_name,"")) as business_name',
            ])
            ->find();
        $data['shouhou_num'] = $shouhou_num;
        $data['product_specs'] = json_decode($data['product_specs'], true);
        $now_time = time();
        $end_time = time();

        if($data){
            $data['is_fahou'] = 0;
            if($data['refund_status'] == 1){
                $limit = config('config_order.hour_shouhou_platform_service');
                $end_time =strtotime ($data['create_time']) + $limit*60*60;
            }elseif ($data['refund_status'] == 3){
                $limit = config('config_order.hour_receive_shouhou_pro');
                $end_time =$data['fahuo_time'] + $limit*60*60;
            }
            $data['now_time'] = $now_time;
            $data['end_time'] = $end_time;
        }
        return $data;
    }


    /**
     *  员工稍后发货
     * @param $order_id  订单ID
     * @return ProductModel
     */
    public static function businessGetOrder($order_id,$user_id){
        //检查订单是否被跟进
        $GetCheck = self::where(['o.id' => $order_id,'o.order_status'=>'3'])
            ->alias('o')
            ->join('order_business ob', 'o.id = ob.order_id AND ob.type = 1','left')
            ->field(['o.id','ob.buniess_id'])
            ->find();
        if (!$GetCheck) return '未找到相关订单';
        if(empty($GetCheck['buniess_id'])){
            Db::startTrans();
            $orderBusiness = OrderBusinessModel::insert([
                'order_id' => $order_id,
                'type' => 1,
                'buniess_id' => $user_id,
                'after_send' => 1
            ]);
            $order = Db::name('product_order')->where('id',$order_id)->setField('after_send', 1);
            if(!$orderBusiness || !$order){
                Db::rollback();
                return '失败';
            }
            Db::commit();
            return true;
        }
        return '订单已被跟进';
    }

    /**
     *  订单发货
     * @param $params order_id-订单ID  after_send-发送方式【1-稍后发货 0-立即发货】 logistics_number-物流编号 logistics_company-物流公司
     * @param $user_id 发货人ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function businessSendOrder($params,$user_id){
        $sendCheck = self::where(['o.id' => $params['order_id'],'o.order_status'=>'3'])
            ->alias('o')
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
            ->field([
                'o.order_status',
                'ob.buniess_id',
            ])->find();
        // 检测订单是否已发货
        if (!$sendCheck) return '未找到相关订单';
        if(!empty($sendCheck['buniess_id']) && $sendCheck['buniess_id'] != $user_id){
            return '订单已被跟进';
        }
        Db::startTrans();
        // 修改订单状态为已发货
        $updateOrderStatus = self::where(['id' => $params['order_id']])->update(['order_status' => 4,'fahuo_time' => time(), 'logistics_number' => $params['logistics_number'], 'logistics_company' => $params['logistics_company']]);
        // 添加或修改订单员工数据
        $checkBusiness = OrderBusinessModel::where(['order_id' => $params['order_id']])->find();
        if ($checkBusiness) {
            $orderBusiness = 1;
            if($checkBusiness['after_send'] == 1){
                $orderBusiness = OrderBusinessModel::where('id', $checkBusiness['id'])->update(['buniess_id' => $user_id, 'after_send' => 0]);
            }
        } else {
            $orderBusiness = OrderBusinessModel::insert([
                'order_id' => $params['order_id'],
                'type' => 1,
                'buniess_id' => $user_id,
                'after_send' => 0
            ]);
        }

        if (!$updateOrderStatus || !$orderBusiness){
            Db::rollback();
            // 发货失败
            return '发货失败';
        }
        Db::commit();
        // 发货成功
        return true;

    }

    /**
     *  员工取消订单
     * @param $order_id  店铺ID
     * @return ProductModel
     */
    public static function businessCancelOrder($order_id,$user_id,$store_id){
        //return '取消订单失败[生成退款通知消息失败]';
        $order = db('product_order')->where(['id'=>$order_id,'store_id' =>$store_id,'order_status' =>3]) -> find();
        if(empty($order)) return '未找到相关订单';
        $pay_order_no = $order['pay_scene']?$order['order_no']:$order['pay_order_no'];

        Db::startTrans();
        //3退款通知
        $msg_id = Db::name('user_msg')->insertGetId([
            'title' => '退款通知',
            'content' => '您的订单'.$order['order_no'].'取消已成功，订单金额将原路返回！',
            'type' => 2,
            'create_time' => time()
        ]);
        $msg = Db::name('user_msg_link')->insert([
            'user_id' => $user_id,
            'msg_id' => $msg_id
        ]);
        if(!$msg_id || !$msg){
            Db::rollback();
            return '取消订单失败[生成退款通知消息失败]';
        }
        //修改订单状态
        $re1 = self::where(['id'=>$order_id,'order_status'=>'3']) -> update([
            'order_status' => -1,
            'cancel_time'=>time()
        ]);
        $re2 = db('product_order_detail')->where(['order_id'=>$order_id,'status'=>0]) -> update([
            'is_refund'=>1,
            'refund_time'=>time(),
            'status' => -2,
            'cancel_time'=>time()
        ]);
        //将取消的员工绑定订单id
        $re3 = OrderBusinessModel::insert([
            'order_id' => $order_id,
            'type' => 1,
            'buniess_id' => $user_id,
            'after_send' => 0
        ]);
        if(!$re1 || !$re2|| !$re3){
            Db::rollback();
            return '取消订单失败[修改订单状态失败]';
        }
        //判断是否有平台优惠券
        if($order['coupon_id']>0 ){
            $re4 = Db::name('coupon')->where('id',$order['coupon_id'])->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
            if($re4 === false){
                Db::rollback();
                return '取消订单失败[平台优惠券]';
            }
        }
        //判断是否有店铺优惠券
        if($order['store_coupon_id']>0){
            $re5 = Db::name('coupon')->where('id',$order['store_coupon_id'])->where('user_id',$user_id)->update(['status'=>1,'use_time'=>0]);
            if($re5 === false){
                Db::rollback();
                return '取消订单失败[店铺优惠券]';
            }
        }
        //返回库存
        $product = Db::name('product_order_detail')->where('order_id',$order_id)->select();
        foreach ($product as $k=>$v){
            $re6 = Db::name('product_specs')->where('id',$v['specs_id'])->setInc('stock',$v['number']);
            if($re6 === false){
                Db::rollback();
                return '取消订单失败[恢复库存失败]';
            }
        }

        $realpay_money = Db::name('product_order_detail')->where('order_id',$order_id)->where('status',-1)->sum('realpay_money');
        $rest_money=($order['pay_money'])-$realpay_money;//需要返回的金额
        if($rest_money >= 0.01){
            //todo 此处原路退款
            if ($order['pay_type'] == '支付宝') {
                $alipay = new AliPay();
                $res = $alipay->alipay_refund($pay_order_no,$order_id,$rest_money);
            }elseif ($order['pay_type'] == '微信'){
                $total_pay_money = $order['pay_scene']?$order['pay_money']:self::sumOrderPayMoney($pay_order_no);
                $wxpay = new WxPay();
                $res = $wxpay->wxpay_refund($pay_order_no,$total_pay_money,$rest_money,2);
            }
            if ($res !== true){
                Db::rollback();
                return '退款处理失败';
            }
        }
        Db::commit();
        // 处理成功
        return true;

    }

    /**
     * 获取订单支付总金额 -微信支付 取消订单使用
     * @param $pay_order_no
     * @return float|int
     */
    public static function sumOrderPayMoney($pay_order_no){
        return Db::name('product_order')->where(compact('pay_order_no'))->sum('pay_money');
    }


    /**
     * 收益统计
     * @param $where
     * @param $kind  1买单   2商城
     * @param $page
     * @param $limit
     */
    public static function IncomeStatistics($where,$kind,$page,$limit,$kind){
        $pre = ($page-1)*$limit;
        //获取总的收益
        $totals = (string)self::totalIncome($where,$kind);
        if($kind == 1){ //买单数据列表
            $where['o.status'] = 2;
            $list = Db('maidan_order')->where($where)
                ->alias('o')
                ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }else{//商城数据列表
            $where['o.order_status'] = ['in', ['6','7','8']];
            $pre = ($page-1)*$limit;
            $list = self::where($where)
                ->alias('o')
                ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }

        return compact('totals', 'list');
    }

    /**
     * 总的收益
     * @param $where
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function totalIncome($where,$kind){
        if($kind == 1){ //买单
            $list = Db('maidan_order')->where($where)->where(['o.status'=>2]) //买单收益
            ->alias('o')
                ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
                ->order('o.pay_time desc')
                ->select();
        }else{
            $list = self::where($where)->where(['o.order_status'=>['in', ['6','7','8']]]) //商城收益
            ->alias('o')
                ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                ->order('o.pay_time desc')
                ->select();
        }
        $totalPrice = 0;
        foreach ($list as $k => $v){
            $totalPrice += $v['pay_money'];
        }

        return $totalPrice;
    }

    /**
     * 订单数量统计
     * @param $where
     * @param $kind
     * @param $page
     * @param $limit
     */
    public static function numStatistics($where,$kind,$page,$limit,$kind){
        $pre = ($page-1)*$limit;
        //获取总的订单数量
        $totals = (string)self::totalNum($where,$kind);
        if($kind == 1){ //买单数据列表
            $where['o.status'] = 2;
            $pre = ($page-1)*$limit;
            $list = Db('maidan_order')->where($where)
                ->alias('o')
                ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }else{ //商城数据列表
            $where['o.order_status'] = ['in', ['3','4','5','6','7','8']];
            $pre = ($page-1)*$limit;
            $list = self::where($where)
                ->alias('o')
                ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }
        return compact('totals', 'list');
    }

    /**
     * 总的订单
     * @param $where
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function totalNum($where,$kind){
        $where1 = $where2 = $where;
        if($kind == 1){ //买单
            if(isset($where['o.staff_id'])){
                $user_id = $where['o.staff_id'];
                unset($where['o.staff_id']);
                $where['ob.buniess_id'] = $user_id;
                $where2 = $where;
            }
        }else{
            if(isset($where['ob.buniess_id'])){
                $user_id = $where['ob.buniess_id'];
                unset($where['ob.buniess_id']);
                $where['o.staff_id'] = $user_id;
                $where1 = $where;
            }
        }
        $list = self::where($where2)->where(['o.order_status'=>['in', ['3','4','5','6','7','8']]]) //商城收益
        ->alias('o')
            ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
            ->order('o.pay_time desc')
            ->select();

        $list_mai = Db('maidan_order')->where($where1)->where(['o.status'=>2]) //买单收益
        ->alias('o')
            ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
            ->order('o.pay_time desc')
            ->select();

        $totalNum1 = count($list);
        $totalNum2 = count($list_mai);
        $totalNum = $totalNum1 + $totalNum2;
        return $totalNum;
    }

    /**
     * 员工相关订单
     * @param $where
     * @param $kind  1买单   2商城
     * @param $page
     * @param $limit
     */
    public static function BusinessNumStatistics($where,$kind,$page,$limit,$type,$business_id){
        $pre = ($page-1)*$limit;
        //获取总的订单数量
        $totals = (string)self::BusinessTotalNum($where,$type,$business_id);
        if($kind == 1){ //买单数据列表
            $where['o.status'] = 2;
            $where['o.staff_id'] = $business_id;
            $pre = ($page-1)*$limit;
            $list = Db('maidan_order')->where($where)
                ->alias('o')
                ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }else{ //商城数据列表
            $where['o.order_status'] = ['in', ['3','4','5','6','7','8']];
            $where['ob.buniess_id'] = $business_id;
            $pre = ($page-1)*$limit;
            $list = self::where($where)
                ->alias('o')
                ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                ->order('o.pay_time desc')
                ->limit($pre,$limit)
                ->select();
        }
        $business_name = Db::name('business')
            ->where('id','EQ',$business_id)
            ->value('business_name');
        return compact('totals','business_name', 'list');
    }

    /**
     * 员工总的订单
     * @param $where
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function BusinessTotalNum($where,$type,$business_id){
        $list_mai = Db('maidan_order')->where($where)->where(['o.status'=>2,'o.staff_id'=>$business_id]) //买单
        ->alias('o')
            ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
            ->order('o.pay_time desc')
            ->select();
        if($type == 3){ //结算中  status != 8
            $where['o.order_status'] = ['in', ['3','4','5','6','7']];
        }else{
            $where['o.order_status'] = ['in', ['3','4','5','6','7','8']];
        }
        $list = self::where($where)->where(['ob.buniess_id'=>$business_id]) //商城
        ->alias('o')
            ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
            ->order('o.pay_time desc')
            ->select();


        $totalNum1 = count($list);
        $totalNum2 = count($list_mai);
        $totalNum = $totalNum1 + $totalNum2;
        return $totalNum;
    }

    /**
     *  一对多关联订单包含的商品
     * @return \think\model\relation\HasMany
     */
    public function products(){
        return $this -> hasMany('OrderDetailsModel', 'order_id', 'id')
            -> field('product_name,price,cover,freight,order_id,product_id,number,product_specs,is_shouhou,is_refund,status');
    }
























    /**
     *  根据where查询收益数据并统计总金额
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderDetailsByWhere($where,$kind,$page,$limit,$status,$type = 0,$close = 0){
        //获取总的价格以及订单数量
        $total = self::getOrderDetailsByWhereAll($where,$type,$close);
        //数据列表
        if($kind == 1){//到店买单
            $where['o.status'] = 2;
            $list = self::getOrderDetailsByWherePageMai($where,$page,$limit);
        }else{//商城订单
            if($type == 1){ //商城订单收益
                $where['o.order_status'] = ['in', ['6','7','8']];
            }else{//商城订单统计
                if($close == 1){
                    $where['o.order_status'] = ['in', ['3','4','5','6','7']];
                }else{
                    $where['o.order_status'] = ['in', ['3','4','5','6','7','8']];
                }
            }
            $list = self::getOrderDetailsByWherePage($where,$page,$limit);
        }
        if($status == 1){
            //总的价格
            $totals = number_format($total['totalPrice'],2);
            return compact('totals', 'list');
        }else{
            $totals = strval($total['totalNum']);
            return compact('totals', 'list');
        }
    }
    //获取总的数据
    public static function getOrderDetailsByWhereAll($where,$type,$close = 0){
        if($type == 1){
            $list = self::where($where)->where(['o.order_status'=>['in', ['6','7','8']]]) //商城订单-订单收益
            ->alias('o')
                ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                ->order('o.pay_time desc')
                ->select();
        }else{
            if($close == 1){
                $list = self::where($where)->where(['o.order_status'=>['in', ['3','4','5','6','7']]]) //商城订单-订单统计-结算中
                ->alias('o')
                    ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                    ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                    ->order('o.pay_time desc')
                    ->select();
            }else{
                $list = self::where($where)->where(['o.order_status'=>['in', ['3','4','5','6','7','8']]]) //商城订单-订单统计
                ->alias('o')
                    ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
                    ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
                    ->order('o.pay_time desc')
                    ->select();
            }

        }

        $list_mai = Db('maidan_order')->where($where)->where(['o.status'=>2]) //买单订单
        ->alias('o')
            ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
            ->order('o.pay_time desc')
            ->select();
        //总金额数
        $totalPrice = 0;
        foreach ($list as $k => $v){
            $totalPrice += $v['pay_money'];
        }
        foreach ($list_mai as $k => $v){
            $totalPrice += $v['pay_money'];
        }
        //总订单数
        $totalNum1 = count($list);
        $totalNum2 = count($list_mai);
        $totalNum = $totalNum1 + $totalNum2;
        return compact('list', 'totalPrice','totalNum');
    }
    //获取总的数据-maidan
    public static function getOrderDetailsByWhereAllMai($where){
        $list = self::where($where) //商城订单
        ->alias('o')
            ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
            ->order('o.pay_time desc')
            ->select();
        $list_mai = Db('maidan_order')->where($where) //买单订单
        ->alias('o')
            ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
            ->order('o.pay_time desc')
            ->select();
        //总金额数
        $totalPrice = 0;
        foreach ($list as $k => $v){
            $totalPrice += $v['pay_money'];
        }
        foreach ($list_mai as $k => $v){
            $totalPrice += $v['pay_money'];
        }
        //总订单数
        $totalNum1 = count($list);
        $totalNum2 = count($list_mai);
        $totalNum = $totalNum1 + $totalNum2;
        return compact('list', 'totalPrice','totalNum');
    }
    //获取数据列表-商城
    public static function getOrderDetailsByWherePage($where,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = self::where($where)
            ->alias('o')
            ->field(['o.pay_money','o.id as order_id','o.order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 1','left')
            ->order('o.pay_time desc')
            ->limit($pre,$limit)
            ->select();
        return $list;
    }
    //获取数据列表-买单
    public static function getOrderDetailsByWherePageMai($where,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = Db('maidan_order')->where($where)
            ->alias('o')
            ->field(['o.price_maidan as pay_money','o.id as order_id','o.order_sn as order_no','FROM_UNIXTIME(o.pay_time,\'%Y-%m-%d %H:%i\') as pay_time','o.status as order_status'])
            ->join('order_business ob', 'ob.order_id = o.id AND ob.type = 2','left')
            ->order('o.pay_time desc')
            ->limit($pre,$limit)
            ->select();
        return $list;
    }
    /**
     *  订单统计
     * @param array $where
     * @param $whereOr
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderStatistics($where = [],$whereOr){
        $data = self::where($where)->whereOr($whereOr)
            -> field([
                'id','order_no as order_sn','order_status as status','FROM_UNIXTIME(pay_time,\'%Y-%m-%d %H:%i\') as pay_time','pay_money as price_maidan'
            ])
            -> select();

        $totalPrice = number_format(array_sum(array_column($data,'price_maidan')),2);
        return compact('totalPrice', 'data');
    }
    /**
     *  关联查询售后商品
     * @return \think\model\relation\HasMany
     */
    public function saleProduct(){
        return $this -> hasMany('OrderDetailsModel', 'order_id', 'id')
            -> field('product_name,price,cover,freight,order_id,product_id,number,product_specs');
    }
    /**
     *  获取售后订单ID
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*public static function getAfterSaleOrderId($store_id){
        $data = self::where(['o.store_id' => $store_id, 'ob.is_shouhou' => ['neq', 0]])
            ->alias('o')
            ->join('product_order_detail ob', 'o.id = ob.order_id')
            ->field(['o.id'])
            ->select();
        $ids = array_unique(array_column($data, 'id'));
        return $ids;
    }*/
    /**
     *  我的任务-订单列表数据
     * @param $where  条件有订单状态  店铺ID  员工所管订单ID（查询用户为非主账号的情况下有此判断）
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*public static function getOrderListByWhere($where,$user_id,$page,$limit){
        $pre = ($page-1)*$limit;
        $list = ProductModel::where($where)
            ->alias('o')
            ->join('user u', 'u.user_id = o.user_id')
            ->join('order_business ob', 'ob.order_id = o.id','left')
            ->join('business b', 'b.id = ob.buniess_id AND ob.type = 1','left')
            ->with('products')
            ->field([
                'o.id','o.order_no', 'o.pay_money', 'o.total_freight','o.user_id','o.order_status',
                'u.nickname', 'u.avatar','o.create_time',
                'ob.buniess_id',
                'IF(ob.buniess_id = 0, 0, 1) as is_receive',
                'IF(ob.buniess_id = '.$user_id.', \'我\', b.business_name) as business_name',
            ])
            ->order('o.create_time desc')
            ->limit($pre,$limit)
            ->select();
        return $list;
    }*/
    /**
     *  售后订单数据
     * @param $where
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*public static function getAfterSaleOrderData($where, $user_id,$page,$limit){
        $pre = ($page-1)*$limit;
        $data = ProductShouHouModel::where($where)
            ->alias('ps')
            ->join('product_order p', 'ps.order_id = p.id','left')
            ->join('product_order_detail od', 'ps.order_id = od.order_id AND ps.product_id = od.product_id','left')
            ->join('user u', 'ps.user_id = u.user_id','left')
            ->join('business b', 'ps.business_id = b.id','left')
            ->field([
                'ps.id as s_id','p.order_status','p.order_no','p.user_id','p.id as order_id',
                'u.nickname', 'u.avatar',
                'ps.refund_status as is_shouhou','ps.business_id','od.product_name','od.cover','od.product_specs','od.id as after_sale_id',
                'IF(ps.business_id = '.$user_id.', \'我\', b.business_name) as business_name',
                'IF(ps.business_id = 0, 0, 1) as is_receive','od.product_id'
            ])
            ->order('ps.create_time desc')
            ->limit($pre,$limit)
            ->select();
        return $data;
    }*/


}