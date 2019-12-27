<?php


namespace app\wxapi_test\common;


use templateMsg\CreateTemplate;
use think\Db;
use think\Loader;

class UserLogic
{

    /**
     * 获取用户的收藏总数【普通商品 + 超大商品】
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        return  intval(self::userProCollectNum($user_id)) + intval(self::userChaoDaCollectNum($user_id)) + intval(self::userStoreCollectNum($user_id));
    }
    /**
     * 获取用户的关注店铺总数
     * @param $user_id
     * @return int|string
     */
    public static function userFollowNum($user_id){
        return Db::name('store_follow')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户卡券数
     * @param $user_id
     * @return int
     */
    public static function userCouponNum($user_id){
        return intval(Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->where(['user_id'=>$user_id,'ucc.status'=>1])
            ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
//            ->where(['end_time'=>['GT',time()]])
            ->count('ucc.id'));
    }

    /**
     * 获取用户收藏潮搭总数
     * @param $user_id
     * @return int|string
     */
    public static function userChaoDaCollectNum($user_id){
        return Db::name('chaoda_collection')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户普通商品收藏总数
     * @param $user_id
     * @return int|string
     */
    public static function userProCollectNum($user_id){
        return Db::name('product_collection')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户店铺收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userStoreCollectNum($user_id){
        return Db::name('store_collection')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户会员到期时间
     * @param $user_id
     * @return mixed
     */
    public static function userMemberEndTime($user_id){
        return Db::name('member_order')->where(['user_id'=>$user_id,'status'=>2])->order('create_time','desc')->value('end_time');
    }

    /**
     * 获取用户收藏店铺总数
     * @param $user_id
     * @return int|string
     */
    public static function UserStoreCollectCount($user_id){
        return Db::name('store_collection')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户收藏店铺列表
     * @param $user_id
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function UserStoreCollectList($user_id,$page,$size){
        return Db::name('store_collection')->alias('sc')
            ->join('store s','s.id = sc.store_id','LEFT')
            ->where(['sc.user_id'=>$user_id])
            ->field('sc.id,sc.store_id,s.store_name,s.cover')
            ->order('sc.create_time','desc')
            ->limit(($page-1)*$size,$size)
            ->select();
    }
    public static function getOptimizeCouponList($user_id,$page,$size,$type){
        $list = self::getCanUseCouponList($user_id,$page,$size,$type);
        $can_use_tt = count($list);
        if($can_use_tt < $size){
            $list2 = self::getUserCountList($user_id,$page,$size,$type);
//            $list3 = $list2;
//            foreach($list3 as &$v)$v['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
//            print_r($list3);
//            echo $can_use_tt;
            $list2 = array_splice($list2,$can_use_tt,$size-$can_use_tt);
            $list = array_merge($list,$list2);
        }
        return $list;
    }
    public static function getCanUseCouponList($user_id,$page,$size,$type){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];
        if($type)$where['cc.type'] = $type;
        return Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->where($where)
            ->where(['ucc.expiration_time'=>['GT',time()]])
            ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.start_time,ucc.expiration_time,cc.end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name,cc.coupon_name')
            ->order('ucc.expiration_time','asc')
//            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();
    }
    public static function getUserCountList($user_id,$page,$size,$type){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];
        if($type)$where['cc.type'] = $type;
        return Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
//            ->where(['cc.is_open'=>1])
//            ->where(['cc.id'=>['NEQ',null]])
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.start_time,ucc.expiration_time,cc.end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name,cc.coupon_name')
            ->order('ucc.expiration_time','desc')
//            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();
    }

    /**
     * 检查手机号是否已注册
     * @param $mobile
     * @return int|string
     */
    public static function checkRegister($mobile){
        return Db::name('user')->where(['mobile'=>$mobile])->count('user_id');
    }

    /**
     * 获取用户优惠券
     * @param $user_id
     * @param $type
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function userCssCouponLists($user_id,$type,$page,$size){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];
        if($type==1){
            $where['cc.type'] = ['eq',1];
        }elseif ($type==2){
            $where['cc.type'] = ['eq',2];
        }elseif ($type==3){
            $where['cc.type'] = ['eq',3];
        }else{
            $where['cc.type'] = ['in','1,2,3'];
        }
        $where['ucc.expiration_time'] = ['gt',time()];
//            $where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->field('ucc.id,ucc.coupon_name,ucc.expiration_time,cc.coupon_type,cc.start_time,cc.end_time,cc.rule_model_id,ucc.satisfy_money,ucc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_ids,cc.is_solo')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->order('ucc.coupon_money','desc')
            ->order('ucc.expiration_time','desc')
            ->limit(($page-1)*$size,$size)
            ->select();
        foreach($list as &$v){
            if($v['type']==3 && $v['is_solo']==0){
                //商品
                $v['coupon_name']='平台下多商品满'.$v['satisfy_money'].'可用';
            }
           $rule= explode(",",$v['rule_model_id']);
           if($rule){
               foreach ($rule as $k1=>$v1){
                   $v['rule'][$k1] = Db::name('coupon_use_rule')->where(['id'=>$v1,'status'=>1])->value('title');
               }
           }
            if($v['expiration_time'] <= time() && $v['expiration_time'] != 0)$v['status'] = -1;
            unset ($v['rule_model_id']);//销毁rule_model_id
        }
        return $list;
    }
    public static function userPlatformCouponList($user_id){
        $list = Db::name('coupon')->where(['user_id'=>$user_id])->field('coupon_name,satisfy_money,coupon_money,status,use_time,expiration_time as end_time')->select();
        foreach($list as &$v){
            $v['type'] = 1;
            $v['coupon_type'] = 1;
        }
        return $list;
    }
    public static function userCouponLists($user_id,$type,$page,$size){
        return self::userCssCouponLists($user_id,$type,$page,$size);
    }

    /**
     * 获取用户订单卡券列表
     * @param $user_id
     * @param $store_id
     * @param $type
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function userOrderCouponLists($user_id,$store_id,$type,$page,$size){
        return $type == 0?(self::userOrderPlatformCouponLists($user_id,$store_id,$page,$size)):(self::userOrderTypeCouponLists($user_id,$store_id,$type,$page,$size));
    }

    /**
     * 获取用户订单全部卡券列表
     * @param $user_id
     * @param $store_id
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function userOrderPlatformCouponLists($user_id,$store_id,$page,$size){
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
//            ->where(['ucc.user_id'=>$user_id,'cc.is_open'=>1,'cc.type'=>1])
            ->where(function($query) use ($user_id) {
//                $query->where(['ucc.status'=>1,'ucc.user_id'=>$user_id,'cc.is_open'=>1,'cc.type'=>1])->where(['cc.id'=>['NEQ',null]]);
                $query->where(['ucc.status'=>1,'ucc.user_id'=>$user_id,'cc.type'=>1]);
            })
            ->whereOr(function($query) use ($user_id,$store_id){
//                $query->where(['ucc.status'=>1,'ucc.user_id'=>$user_id,'cc.is_open'=>1,'cc.type'=>2,'ucc.store_id'=>$store_id])->where(['cc.id'=>['NEQ',null]]);
                $query->where(['ucc.status'=>1,'ucc.user_id'=>$user_id,'cc.type'=>2,'ucc.store_id'=>$store_id]);
            })
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->field('ucc.id,ucc.coupon_name,s.store_name,cc.coupon_type,cc.start_time,cc.end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name')
            ->order('ucc.create_time','desc')
            ->limit(($page-1)*$size,$size)
            ->select();
        foreach($list as &$v){
            if($v['end_time'] <= time() && $v['end_time'] != 0)$v['status'] = -1;
        }
//        echo Db::name('')->getLastSql();die;
        return $list;
    }

    /**
     * 获取用户订单类型卡券
     * @param $user_id
     * @param $store_id
     * @param $type
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function userOrderTypeCouponLists($user_id,$store_id,$type,$page,$size){
        $where = ['ucc.status'=>1,'ucc.user_id'=>$user_id,'cc.type'=>$type];
        if($type == 2)$where['ucc.store_id'] = $store_id;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->where($where)
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->field('ucc.id,ucc.coupon_name,s.store_name,cc.coupon_type,cc.start_time,cc.end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name')
            ->order('ucc.create_time','desc')
            ->limit(($page-1)*$size,$size)
            ->select();
        foreach($list as &$v){
            if($v['end_time'] <= time() && $v['end_time'] != 0)$v['status'] = -1;
        }
        return $list;
    }

    /**
     * 获取用户当前状态的券总数
     * @param $user_id
     * @param $type
     * @return int|string
     */
    public static function userStoreCouponCount($user_id,$type){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];

        if($type==1){
            $where['cc.type'] = ['eq',1];
        }elseif ($type==2){
            $where['cc.type'] = ['eq',2];
        }elseif ($type==3){
            $where['cc.type'] = ['eq',3];
        }else{
            $where['cc.type'] = ['in','1,2,3'];
        }
        $where['ucc.expiration_time'] = ['gt',time()];
//        if($type)$where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
//            ->where(['cc.is_open'=>1])
//            ->where(['cc.id'=>['NEQ',null]])
            ->count('ucc.id');

//        echo Db::name('coupon')->getLastSql();
        return $list;
    }

    /**
     * 获取订单用户优惠券总数
     * @param $user_id
     * @param $type
     * @param $store_id
     * @return int|string
     */
    public static function userOrderCouponCount($user_id,$type,$store_id){
        return $type == 0?(self::userOrderAllCouponCount($user_id,$store_id)):(self::userOrderTypeCouponCount($user_id,$type,$store_id));
    }

    /**
     * 获取订单总券数
     * @param $user_id
     * @param $store_id
     * @return int|string
     */
    public static function userOrderAllCouponCount($user_id,$store_id){
        return self::userOrderTypeCouponCount($user_id,1) + self::userOrderTypeCouponCount($user_id,2,$store_id);
    }

    /**
     * 获取订单平台券总数
     * @param $user_id
     * @param $type
     * @param $store_id
     * @return int|string
     */
    public static function userOrderTypeCouponCount($user_id,$type,$store_id=0){
        $where = ['cr.type'=>$type,'c.user_id'=>$user_id,'c.status'=>1];
        if($type == 2)$where['cr.store_id'] = $store_id;
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where($where)
//            ->where(['cr.id'=>['NEQ',null]])
            ->count('c.id');
    }

    /**
     * 获取新人券数量
     * @param $user_id
     * @return int|string
     */
    public static function userPlatformCouponCount($user_id){
        return Db::name('coupon')->where(['user_id'=>$user_id])->count('id');
    }

    /**
     * 获取用户券总数
     * @param $user_id
     * @param $type
     * @return int|string
     */
    public static function userCouponCount($user_id,$type){
//        return $type==2?(self::userStoreCouponCount($user_id,$type)):(self::userStoreCouponCount($user_id,$type) + self::userPlatformCouponCount($user_id));

        return self::userStoreCouponCount($user_id,$type);

    }

    /**
     * 获取用户总收入
     * @param $user_id
     * @return float|int
     */
    public static function getUserMoneyTotal($user_id,$month){
        return intval(Db::name('user_money_detail')
            ->where(['user_id'=>$user_id])
            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
            ->where(['money'=>['GT',0]])
            ->sum('money'));
    }

    /**
     * 获取用户待提现金额
     * @param $user_id
     * @return float|int
     */
    public static function getUserMoneyDis($user_id,$month){
        return intval(Db::name('user_money_detail')
            ->where(['user_id'=>$user_id])
            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
            ->sum('money'));
    }

    /**
     * 获取用户已提现金额
     * @param $user_id
     * @return float|int
     */
    public static function getUserMoneyHad($user_id,$month){
        return intval(Db::name('user_money_detail')
            ->where(['user_id'=>$user_id])
            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
            ->where(['money'=>['LT',0]])
            ->sum('money'));
    }

    /**
     * 获取用户收入列表的订单号
     * @param $order_id
     * @param $type
     * @return mixed
     */
    public static function getUserMoneyOrderNo($order_id,$type){
        return $type==1?(Db::name('product_order')->where(['id'=>$order_id])->value('order_no')):(Db::name('user_tixian_record')->where(['id'=>$order_id])->value('order_no'));
    }

    /**
     * 获取用户提现详情
     * @param $user_id
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function userTixianDetail($id,$user_id){
        return Db::name('user_money_detail')->where(['id'=>$id,'user_id'=>$user_id])->field('note,money,create_time')->find();
    }

    /**
     * 检查用户是否领用优惠券
     * @param $user_id
     * @param $coupon_id
     * @return int|string
     */
    public static function checkUserCoupon($user_id,$coupon_id){
        return Db::name('coupon')->where(compact('user_id','coupon_id'))->count('id');
    }

    /**
     * 增加领券记录（用户领券）
     * @param $data
     * @return int|string
     */
    public static function userGetCoupon($data){
        return Db::name('coupon')->insert($data);
    }

    /**
     *  用户领券返回id
     * @param $data
     * @return int|string
     */
    public static function userGetCouponRtnId($data){
        return Db::name('coupon')->insertGetId($data);
    }

    /**
     * 用户领取新人券
     * @param $data
     * @return int|string
     */
    public static function userGetNewUserCoupon($data){
        return Db::name('user_css_coupon')->insertAll($data);
    }

    /**
     * 获取用户的会员类型
     * @param $user_id
     * @return int
     */
    public static function checkMember($user_id){
        return intval(Db::name('user')->where(['user_id'=>$user_id])->value('type'));
    }

    /**
     * 添加买单订单
     * @param $data
     * @return int|string
     */
    public static function addMaidanOrder($data){
        return Db::name('maidan_order')->insertGetId($data);
    }

    /**
     * 添加会员购买订单
     * @param $data
     * @return int|string
     */
    public static  function addMemberOrder($data){
        return Db::name('member_order')->insertGetId($data);
    }

    /**
     * 检查用户是否关注店铺表
     * @param $user_id
     * @param $store_id
     * @return int|string
     */
    public static function checkFollowStore($user_id,$store_id){
        return Db::name('store_follow')->where(compact('user_id','store_id'))->count('id');
    }

    /**
     * 添加用户关注店铺记录
     * @param $data
     * @return int|string
     */
    public static function userFollowStore($data){
        return Db::name('store_follow')->insert($data);
    }

    /**
     * 待支付=》获取自动取消普通订单列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderListNotPay($limit_time){
        return self::orderListWithStatus(1,$limit_time);
    }

    /**
     * 待支付=》获取自动取消普通订单订单号
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderNosNotPay($limit_time){
        $limit_time = time() - $limit_time;
        return Db::name('product_order')
            ->where(['order_status'=>1,'is_group_buy'=>0,'user_is_delete'=>0])
            ->where(['create_time'=>['LT',$limit_time]])
            ->group('order_no')
            ->field('user_id,order_no')
            ->select();
    }

    /**
     * 取消订单
     * @param $order_ids
     * @return int|string
     */
    public static function autoCancelOrder($order_ids){
        $data = [
            'order_status' => -1,
            'cancel_time' => time()
        ];
        return Db::name('product_order')->where(['id'=>['IN',$order_ids]])->update($data);
    }

    /**
     * 修改订单详情为取消
     * @param $order_ids
     * @return int
     */
    public static function autoCancelOrderDetail($order_ids){
        return Db::name('product_order_detail')->where(['order_id'=>['IN',$order_ids]])->setField('status',1);
    }

    /**
     * 返还优惠券
     * @param $coupon_ids
     * @return int
     */
    public static function returnCoupon($coupon_ids){
        return Db::name('coupon')->where(['id'=>['IN',$coupon_ids]])->setField('status',1);
    }

    /**
     * 返还库存
     * @param $specs_id
     * @param $num
     * @return int|true
     */
    public static function returnStock($specs_id,$num){
        return Db::name('product_specs')->where(['id'=>$specs_id])->setInc('stock',$num);
    }

    /**
     * 生成退款信息
     * @param $user_id
     * @param $order_no
     * @return int|string
     */
    public static function createReturnMsg($user_id,$order_no){
        $res = self::createMsg($order_no);
        if($res === false)return $res;
        return self::createMsgUserLink($user_id,$res);
    }

    /**
     * 待付款=》获取自动取消普通订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListNotPay($order_ids){
        return Db::name('product_order_detail')
            ->where(['order_id'=>['IN',$order_ids]])
            ->where(['status'=>null,'is_refund'=>0,'is_shouhou'=>0])
            ->field('product_id,specs_id,number,price')
            ->select();
    }

    /**
     * 生成信息
     * @param $order_no
     * @return int|string
     */
    protected static function createMsg($order_no){
        $data = [
            'title' => '退款通知',
            'content' => "您的订单 {$order_no} 系统已自动取消，订单金额已原路返回",
            'type' => 2,
            'create_time' => time()
        ];
        return Db::name('user_msg')->insertGetId($data);
    }

    /**
     * 生成信息用户链接
     * @param $user_id
     * @param $msg_id
     * @return int|string
     */
    protected static function createMsgUserLink($user_id,$msg_id){
        $data = compact('user_id','msg_id');
        return Db::name('user_msg_link')->insert($data);
    }

    /**
     * 待发货=》获取自动取消普通订单列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderListWaitSend($limit_time){
        return self::orderListWithStatus(3,$limit_time);
    }

    /**
     * 待发货=》获取自动取消普通订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListWaitSend($order_ids){
        return Db::name('product_order_detail')
            ->where(['order_id'=>['IN',$order_ids]])
            ->where(['status'=>null,'is_refund'=>0,'is_shouhou'=>0])
            ->field('product_id,specs_id,number,price')
            ->select();
    }

    /**
     * 待发货=》获取订单号列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderNosWaitSend($limit_time){
        $limit_time = time() - $limit_time;
        return Db::name('product_order')
            ->where(['order_status'=>3,'is_group_buy'=>0,'user_is_delete'=>0])
            ->where(['pay_time'=>['LT',$limit_time]])
            ->group('order_no')
            ->field('user_id,order_no')
            ->select();
    }

    /**
     *总订单支付金额
     * @param $pay_order_no
     * @return float|int
     */
    public static function orderTotalPayMoney($pay_order_no){
        return Db::name('product_order')->where(['pay_order_no'=>$pay_order_no])->sum('pay_money');
    }

    /**
     * 待收货=》获取自动取消普通订单列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderListWaitFetch($limit_time){
        return self::orderListWithStatus(4,$limit_time);
    }

    /**
     * 获取不同状态下的订单列表
     * @param $status
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    protected static function orderListWithStatus($status,$limit_time){
        $limit_time = time() - $limit_time;
        return Db::name('product_order')
            ->where(['order_status'=>$status,'is_group_buy'=>0,'user_is_delete'=>0])
            ->where(['create_time'=>['LT',$limit_time]])
            ->field('id,user_css_coupon_id,user_id,pay_money,pay_type,pay_order_no,store_id,total_freight,coupon_money,platform_profit')
            ->select();
    }

    /**
     * 待收货=》获取会员商品订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListMemberBuy($order_ids){
        return Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','pod.order_id = po.id','LEFT')
            ->where(['pod.order_id'=>['IN',$order_ids]])
            ->where(['pod.status'=>null, 'pod.type'=>2, 'pod.is_refund'=>0, 'pod.is_shouhou'=>0])
            ->field('pod.product_id,pod.specs_id,pod.number,pod.price,pod.huoli_money,po.store_id,po.user_id,po.coupon_id,pod.realpay_money')
            ->select();
    }

    /**
     * 待收货=》获取普通商品订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListNormalBuy($order_ids){
        return Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.store_id','LEFT')
            ->where(['pod.order_id'=>['IN',$order_ids]])
            ->where(['pod.status'=>null, 'pod.type'=>1, 'pod.is_refund'=>0, 'pod.is_shouhou'=>0])
            ->field('pod.product_id,pod.specs_id,pod.number,pod.price,po.store_id')
            ->select();
    }

    /**
     * 获取用户余额
     * @param $user_id
     * @return float\
     */
    public static function userMoney($user_id){
        return floatval(Db::name('user')->where(['id'=>$user_id])->value('money'));
    }

    /**
     * 增加用户余额
     * @param $user_id
     * @param $money
     * @return int|true
     */
    public static function addUserMoney($user_id,$money){
        return Db::name('user')->where(['id'=>$user_id])->setInc('money',$money);
    }

    /**
     * 添加用户获利记录
     * @param $data
     * @return int|string
     */
    public static function addUserHuoliRecord($data){
        return Db::name('user_money_detail')->insertAll($data);
    }

    /**
     * 增肌用户累积消费金额
     * @param $user_id
     * @param $money
     * @return int|true
     * @throws \think\Exception
     */
    public static function userIncLeijiMoney($user_id,$money){
        return Db::name('user')->where(['id'=>$user_id])->setInc('leiji_money',$money);
    }

    /**
     * 待售后=》获取7天未同意列表
     * @param $limit_time
     * @param $refund_type
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListAfterSaleWaitAgree($limit_time,$refund_type){
        $limit_time = time() - $limit_time;
        return Db::name('product_order_detail')->alias('pod')
            ->join('product_shouhou ps','ps.order_id = pod.order_id and ps.specs_id = pod.specs_id','LEFT')
            ->join('product_order po','po.id = pod.order_id','LEFT')
            ->where(['pod.is_shouhou'=>1,'pod.is_refund'=>0,'pod.status'=>0,'ps.refund_status'=>1,'ps.refund_type'=>$refund_type])
            ->where(['ps.create_time'=>['LT',$limit_time]])
            ->field('pod.id,ps.id as shouhou_id,pod.order_id,pod.price,number,pod.realpay_money,ps.refund_type,po.coupon_id,po.pay_type')
            ->select();
    }

    /**
     * 同意售后
     * @param $shouhou_ids
     * @return mixed
     */
    public static function agreeShouhou($shouhou_ids){
        return self::updateShouhou($shouhou_ids,2);
    }

    /**
     * 同意售后并直接退款
     * @param $shouhou_ids
     * @return mixed
     */
    public static function agreeShouhouRefund($shouhou_ids){
        return self::updateShouhou($shouhou_ids,4);
    }

    /**
     * 更新售后状态
     * @param $shouhou_ids
     * @param $refund_status
     * @return mixed
     */
    protected static function updateShouhou($shouhou_ids,$refund_status){
        $data = compact('refund_status');
        $data['agree_time'] = time();
        return Db::name('product_shouhou')->where(['id'=>['IN',$shouhou_ids]])->where(['refund_status'=>1])->save($data);
    }

    /**
     * 修改订单详情退货金额与退货时间
     * @param $order_detail_id
     * @param $refund_money
     * @return mixed
     */
    public static function editOrderDetailRefund($order_detail_id,$refund_money){
        $data = compact('refund_money');
        $data['refund_time'] = time();
        return Db::name('product_order_detail')->where(['id'=>$order_detail_id,'is_refund'=>0])->save($data);
    }

    /**
     * 售后=>用户已退货，商户未收货列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListAfterSaleWaitArrive($limit_time){
        $limit_time = time() - $limit_time;
        return Db::name('product_shouhou')->alias('ps')
            ->join('product_order_detail pod','ps.order_id = pod.order_id and ps.specs_id = pod.specs_id')
            ->join('product_order po','po.id = pod.order_id','LEFT')
            ->where(['ps.refund_status'=>3,'ps.refund_type'=>2,'pod.is_refund'=>0,'pod.status'=>0])
            ->where(['ps.fahuo_time'=>['LT',$limit_time]])
            ->field('pod.id,ps.id as shouhou_id,pod.order_id,pod.price,number,pod.realpay_money,ps.refund_type,po.coupon_id,po.pay_type')
            ->select();
    }

    /**
     * 修改售后状态为商铺已收货，退款
     * @param $shouhou_ids
     * @return int
     */
    public static function arriveShouhouPro($shouhou_ids){
        return Db::name('product_shouhou')->where(['id'=>['IN',$shouhou_ids]])->setField('refund_status',4);
    }

    /**
     * 统计该优惠券兑换次数
     * @param $coupon_rule_id
     * @param $user_id
     * @return int|string
     */
    public static function countExchangeRecord($coupon_rule_id,$user_id){
        return Db::name('coupon_exchange_record')->where(compact('coupon_rule_id','user_id'))->count('id');
    }

    /**
     * 增加优惠券兑换次数
     * @param $data
     * @return int|string
     */
    public static function addExchangeCouponLog($data){
        $data['create_time'] = time();
        $data['exchange_type'] = 1;
        return Db::name('coupon_exchange_record')->insert($data);
    }

    /**
     * 删除通知消息
     * @param $user_id
     * @param $id
     * @return int
     */
    public static function deleteMsg($user_id, $id){
        return Db::name('user_msg_link')->where(compact('user_id','id'))->delete();
    }

    /**
     * 获取订单可用优惠券列表
     * @param $coupon_type
     * @param $coupon_id
     * @param $user_id
     * @param int $page
     * @param int $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function userOrderCouponLists0812($coupon_type,$money, $coupon_id, $user_id, $store_id=0,$product_id=[],$page=1, $size=10){
        $where = [
            'c.user_id' => $user_id,
            'cr.type' => $coupon_type,
            'c.status' => 1,
            'c.expiration_time' => ['GT',time()]
        ];
        if($coupon_type == 2)$where['cr.store_id'] = $store_id;
        if($coupon_type == 3){
            $where['cr.store_id'] = $store_id;
            $where1['cr.product_id'] = ['in', $product_id];
        }

        $total = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where($where)
            ->where($where1)
            ->where(['cr.client_type'=>['IN',[0,1]],'cr.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->count('c.id');
        $list = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->join('coupon_use_rule e','cr.rule_model_id = e.id','LEFT')
            ->join('store s','cr.store_id = s.id','LEFT')
            ->join('product p','p.id = cr.product_id','LEFT')
            ->where($where)
            ->where($where1)
            ->where(['cr.client_type'=>['IN',[0,1]],'cr.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->field('c.id,c.coupon_name,c.status,c.satisfy_money,cr.product_id,c.coupon_money,e.content,c.expiration_time,cr.coupon_type,cr.type,s.store_name,p.product_name,cr.is_superposition')
            ->limit(($page-1)*$size,$size)
            ->order('c.expiration_time','desc')
            ->order('c.coupon_money','desc')
            ->select();
        $is_superposition = 2;  //默认可叠加
        if($coupon_type != 1){
            $coupon_id=empty($coupon_id) ?0:$coupon_id[0];
        }

        if($coupon_type != 1 && $coupon_id){  //商家券  叠加  平台券
            $is_superposition = Db::name('coupon')->alias('c')
                ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                ->where(['c.id'=>$coupon_id])
                ->value('cr.is_superposition');
            $is_superposition = (int)$is_superposition == 2?2:1;
        }
        if($coupon_type == 1 && is_array($coupon_id)){  //平台券  叠加  商家券
            ##获取商家券的叠加性
            $is_superposition = (Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_id]])->where(['cr.is_superposition'=>1])->count('c.id'))>0?1:2;
        }
        foreach($list as $k => &$v){
            $can_use = 1;
            if(($coupon_type == 1 && $is_superposition == 1) || ($v['is_superposition'] == 1 && $coupon_id )){  //平台券
                $can_use = 2;
            }
            if($coupon_type != 1 && $is_superposition == 1 || ($v['is_superposition'] == 1 && $coupon_id )){  //商家券
                $can_use = 2;
            }
            if( $v['satisfy_money']==0 && $v['coupon_money']>$money){
                $can_use=2;
            }
            if( $v['satisfy_money']>0  && $v['satisfy_money']>$money  || ($v['satisfy_money']==$money && $v['coupon_money']>$money)){
                $can_use=2;
            }
            $v['can_use'] = $can_use;
            $v['rule']= explode("||",$v['content']);
        }
        $max_page = ceil($total/$size);
        return compact('list','max_page','total','coupon_type');
    }
//提交订单优惠券列表
    public static function userOrderCouponLists0906($money,$coupon_type, $coupon_id, $user_id, $store_id=0, $product_ids=[], $page=0, $size=10){

        if($coupon_type == 3){ //商品券
            $time = time();
            $where = " (c.user_id = {$user_id} AND cr.type = {$coupon_type} AND c.status = 1 AND c.expiration_time > {$time} AND cr.client_type IN (0,1) AND cr.use_type IN (1,3)) AND (";
            foreach($product_ids as &$v){
                $where .= " cr.product_ids like '%[{$v}]%' or";
            };
            $where = rtrim($where,'or');
            $where .= ")";

        }else{
            $where = [
                'c.user_id' => $user_id,
                'cr.type' => $coupon_type,
                'c.status' => 1,
                'c.expiration_time' => ['GT',time()],
                'cr.client_type' => ['IN',[0,1]],
                'cr.use_type' => ['IN',[1,3]]
            ];
            if($coupon_type == 2)$where['cr.store_id'] = $store_id;
        }

        $total = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where($where)
            ->count('c.id');

        $list = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->join('store s','cr.store_id = s.id','LEFT')
            ->join('product p','p.id = cr.product_id','LEFT')
            ->where($where)
            ->field('c.id,cr.coupon_name,c.satisfy_money,c.coupon_money,c.expiration_time,cr.coupon_type,cr.type,s.store_name,p.product_name,cr.is_superposition,cr.store_id,cr.coupon_name,cr.product_ids,cr.rule_model_id,cr.is_solo')
            ->limit(($page-1)*$size,$size)
            ->order('c.expiration_time','asc')
            ->order('cr.coupon_money','desc')
            ->select();
        if($coupon_type == 2 || $coupon_type == 3){  //商品||商家券
            if($coupon_id){
                $pt_superposition = Db::name('coupon')->alias('c')
                    ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                    ->where(['c.id'=>['IN',$coupon_id]])
                    ->value('cr.is_superposition');
            }
            foreach($list as &$v){
                if($coupon_id){
                    $v['can_use'] = ($v['is_superposition'] == 2 && $pt_superposition == 2)?1:2;
                }else{
                    $v['can_use'] = 1;
                }
                //判断金额
                if( $v['satisfy_money']==0 && $v['coupon_money']>=$money){
                    $v['can_use']=2;
                }
                if( $v['satisfy_money']>0  && $v['satisfy_money']>$money  || ($v['satisfy_money']==$money && $v['coupon_money']>$money)){
                    $v['can_use']=2;
                }
                if($coupon_type == 3 && $v['is_solo'] == 0)$v['coupon_name'] = "平台下部分商品满{$v['satisfy_money']}可使用";
                if($coupon_type == 3)$v['product_ids'] = explode(',',trimFunc($v['product_ids']));

                $rule_models = explode(',',$v['rule_model_id']);
                $v['rule'] = Logic::getCouponRules($rule_models);
                if($v['type'] == 3 && $v['is_solo'] == 0){
                    $v['coupon_name'] = '平台下部分商品满'. $v['satisfy_money'] .'可使用';
                }
            }
        }else{
            if($coupon_id){
                $per_superpositions = Db::name('coupon')->alias('c')
                    ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                    ->where(['c.id'=>['IN',$coupon_id]])
                    ->column('cr.is_superposition');
            }
            foreach($list as &$v){
                if($coupon_id){
                    $v['can_use'] = (in_array(1,$per_superpositions) || $v['is_superposition'] == 1)?2:1;
                }else{
                    $v['can_use'] = 1;
                }
                //判断金额
                if( $v['satisfy_money']==0 && $v['coupon_money']>=$money){
                    $v['can_use']=2;
                }
                if( $v['satisfy_money']>0  && $v['satisfy_money']>$money  || ($v['satisfy_money']==$money && $v['coupon_money']>$money)){
                    $v['can_use']=2;
                }
                $rule_models = explode(',',$v['rule_model_id']);
                $v['rule'] = Logic::getCouponRules($rule_models);
                if($v['type'] == 3 && $v['is_solo'] == 0){
                    $v['coupon_name'] = '平台下部分商品满'. $v['satisfy_money'] .'可使用';
                }
            }
        }
        $max_page = ceil($total/$size);
        return compact('list','max_page','total','coupon_type');
    }
    //查询商品优惠券数量
    public static function productCoupos($money,$coupon_type,$user_id,$store_id,$product_id){

        if($coupon_type == 3){ //商品券
            $time = time();
            $where = " (c.user_id = {$user_id} AND cr.type = {$coupon_type} AND c.status = 1 AND c.expiration_time > {$time} AND cr.client_type IN (0,1) AND cr.use_type IN (1,3)) AND (";
            $where .= " cr.product_ids like '%[{$product_id}]%' or";
            $where = rtrim($where,'or');
            $where .= ")";
        }else{
            $where = [
                'c.user_id' => $user_id,
                'cr.type' => $coupon_type,
                'c.status' => 1,
                'c.expiration_time' => ['GT',time()],
                'cr.client_type' => ['IN',[0,1]],
                'cr.use_type' => ['IN',[1,3]]
            ];
            if($coupon_type == 2)$where['cr.store_id'] = $store_id;
        }
            if($coupon_type == 3){

                //商品券
                $list = Db::name('coupon')->alias('c')
                    ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                    ->join('store s','cr.store_id = s.id','LEFT')
                    ->join('product p','p.id = cr.product_id','LEFT')
                    ->where($where)
                    ->field('c.id,c.satisfy_money,c.coupon_money')
                    ->select();

             if($list){
                 foreach ($list as $k=>$v){
                     $v['can_use']=1;
                     if( $v['satisfy_money']==0 && $v['coupon_money']>=$money ){
                         $v['can_use']=2;}
                     if( $v['satisfy_money']>0  && $v['satisfy_money']>$money  || ($v['satisfy_money']==$money && $v['coupon_money']>$money)){
                         $v['can_use']=2;}
                     if($v['can_use']==1){
                         $arr[]= $v['id'];
                     }
                 }
              return $arr;
             }
                return 0;
            }else{
//店铺和平台
                $list = Db::name('coupon')->alias('c')
                    ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                    ->join('store s','cr.store_id = s.id','LEFT')
                    ->join('product p','p.id = cr.product_id','LEFT')
                    ->where($where)
                    ->field('c.id,c.satisfy_money,c.coupon_money')
                    ->select();
                $num=0;
                foreach ($list as $k=>$v){
                    $v['can_use']=1;
                    if( $v['satisfy_money']==0 && $v['coupon_money']>=$money){
                        $v['can_use']=2;}
                    if( $v['satisfy_money']>0  && $v['satisfy_money']>$money  || ($v['satisfy_money']==$money && $v['coupon_money']>$money)){
                        $v['can_use']=2;}
                    if($v['can_use']==1){
                        $num+=1;
                    }
                }
                return $num;
            }

    }
    public static function addUserMsg($user_id, $title, $content){
        $title_arr = [
            0 => '退款通知',
            1 => '支付成功通知',
            2 => '订单取消通知',
            3 => '会员购买成功',
        ];

        $res = Logic::addMsg($title_arr[$title], $content, 2);
        if($res === false)return false;
        ##增加用户信息连接
        return self::addUserMsgLink($res, $user_id);
    }

    /**
     * 添加用户消息通知连接
     * @param $msg_id
     * @param $user_id
     * @return int|string
     */
    public static function addUserMsgLink($msg_id,$user_id){
        $data = compact('user_id','msg_id');
        $data['is_read'] = 0;
        return Db::name('user_msg_link')->insert($data);
    }

    /**
     * 保存用户的form_id
     * @param $user_id
     * @param $form_id
     * @return int|string
     */
    public static function keepUserFormId($user_id, $form_id){
        $data = compact('user_id','form_id');
        $data['create_time'] = time();
        return Db::name('user_template')->insert($data);
    }

    /**
     * 获取用户openid
     * @param $user_id
     * @return mixed
     */
    public static function getUserOpenId($user_id){
        return Db::name('user')->where(['user_id'=>$user_id])->value('wx_openid');
    }

    /**
     * 获取模板id（存储最久但是未过期的）
     * @param $user_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getUserFormId($user_id){
        #过期时间
        $limit_time = time() - (7 * 24 * 60 *60) + 300;
        return Db::name('user_template')->where(['user_id'=>$user_id,'status'=>1,'create_time'=>['GT',$limit_time]])->order('create_time','asc')->field('id,form_id')->find();
    }

    protected static $template_arr = [
        1 => '',     //订单提交成功,
        2 => '',     //支付成功,
        3 => '',     //
    ];

    /**
     * 获取模板信息
     * @param $type
     * @param array $data
     * @return string
     */
    public static function getTemplateInfo($type, $data=[]){
        Loader::import('templateMsg.CreateTemplate');

        #模板id
        $template_id = CreateTemplate::getModelId($type);

        #小程序路径
        $page = CreateTemplate::getPage($type);

        #模板内容
        switch($type){
            case 'audit_notice':
                $data = CreateTemplate::createAuditMsgData($data['title'], $data['status']);
                break;
            case 'profit_get_notice':
                $data = CreateTemplate::createProfitGetMsgData($data['price_profit'], $data['order_no'], $data['product_name']);
                break;
            case 'refund_notice':
                $data = CreateTemplate::createRefundMsgData($data['money_refund'], $data['product_name'], $data['store_name'], $data['refund_type']);
                break;
            case 'order_send_notice':
                $data = CreateTemplate::createOrderSendMsgData($data['logistics'], $data['product_name'], $data['order_no'], $data['address']);
                break;
            case 'order_pay_notice':
                $data = CreateTemplate::createOrderPayMsgData($data['order_no'], $data['price_order'], $data['product_name']);
                break;
            case 'coupon_get_notice':
                $data = CreateTemplate::createCouponGetMsgData($data['coupon_name'], $data['store_name'], $data['use_desc'], $data['use_limit'], $data['expiration_time'], $data['number']);
                break;
            default:
                return '暂未添加该消息通知模板';
                break;
        }

        #关键词放大
//        $emphasis_keyword = [];
        return compact('template_id','data','page');
    }

    /**
     * 获取用户模板消息公共用户数据
     * @param $user_id
     * @return array
     */
    public static function getTemplateUserData($user_id){

        ##获取openid
        $open_id = self::getUserOpenId($user_id);
        if(!$open_id)return ['status'=>0, 'msg'=>'小程序消息通知发送失败[用户不存在]'];

        ##获取access_token
        $access_token = Weixin::getAccessToken();
        if(!$access_token)return ['status'=>0, 'msg'=>'小程序消息通知发送失败[获取access_token失败]'];

        ##获取用户的form_id
        $form_id = UserLogic::getUserFormId($user_id);
        if(!$form_id)return ['status'=>0, 'msg'=>'小程序消息通知发送失败[没有可用form_id]'];

        return ['status'=>1, 'data'=>compact('open_id','access_token','form_id')];

    }

    /**
     *使用formId
     * @param $id
     * @return int|string
     */
    public static function useFormId($id){
        return Db::name('user_template')->where(['id'=>$id])->update(['status'=>2,'use_time'=>time()]);
    }

}