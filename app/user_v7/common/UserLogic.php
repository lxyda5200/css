<?php


namespace app\user_v7\common;


use app\user_v7\controller\Task as TaskCon;
use think\Cache;
use think\Db;
use think\Exception;
use think\Log;

class UserLogic
{

    /**
     * 获取用户的收藏总数【普通商品 + 超大商品】
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        return intval(self::userCollectProNum($user_id)) + intval(self::userChaoDaCollectNum($user_id)) + intval(self::UserStoreCollectCount($user_id));
    }

    /**
     * 获取用户收藏产品总数
     * @param $user_id
     */
    public static function userCollectProNum($user_id){
        return Db::name('product_collection')->alias('pc')
            ->join('product_specs ps','pc.specs_id = ps.id','RIGHT')
            ->join('product p','p.id = pc.product_id','RIGHT')
            ->join('store s','s.id = p.store_id','RIGHT')
            ->where('pc.user_id',$user_id)
            ->where('ps.id',['GT',0])
            ->where(['p.status'=>1,'p.sh_status'=>1,'s.store_status'=>1])
            ->count();
    }

    /**
     * 获取用户的关注店铺总数
     * @param $user_id
     * @return int|string
     */
    public static function userFollowNum($user_id){
        return Db::name('store_follow')->alias('sf')
            ->join('store s','sf.store_id = s.id','LEFT')
            ->where(['sf.user_id'=>$user_id])
            ->where('s.store_status',1)
            ->where('sf.store_id','GT',0) //先只查询关注的店铺等后期APP加入个人主页再开放
            ->count('sf.id');
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
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
//            ->where(['end_time'=>['GT',time()]])
            ->count('ucc.id'));
    }

    /**
     * 获取用户收藏潮搭总数
     * @param $user_id
     * @return int|string
     */
    public static function userChaoDaCollectNum($user_id){
        $data = Db::name('chaoda_collection')->alias('cc')
            ->join('chaoda c','c.id = cc.chaoda_id','RIGHT')
            ->join('store s','s.id = c.store_id')
            ->where(['cc.user_id'=>$user_id,'c.is_delete'=>0,'s.store_status'=>1])
            ->count('cc.id');
        return $data;
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
        return Db::name('store_collection')->alias('sc')
            ->join('store s','s.id = sc.store_id','RIGHT')
            ->where(['sc.user_id'=>$user_id,'s.store_status'=>1])
            ->count('sc.id');
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
            ->join('store s','s.id = sc.store_id','RIGHT')
            ->where(['sc.user_id'=>$user_id,'s.store_status'=>1])
            ->field('sc.id,sc.store_id,s.store_name,s.cover')
            ->order('sc.create_time','desc')
            ->limit(($page-1)*$size,$size)
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
     * 查询用户信息
     * @param $mobile
     * @return int|string
     */
    public static function findUser($mobile){
        return Db::name('user')->where(['mobile'=>$mobile])->find();
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
        if($type)$where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
//            ->where(['cc.is_open'=>1])
//            ->where(['cc.id'=>['NEQ',null]])
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.start_time,ucc.expiration_time as end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name,cc.coupon_name')
            ->order('ucc.expiration_time','asc')
//            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();

        $data_expire = [];
        $data_can = [];
        foreach($list as &$v){
            if($v['end_time'] <= time()){
                $v['status'] = -1;
                $data_expire[] = $v;
            }else{
                $data_can[] = $v;
            }
        }
        $list = array_merge($data_can,$data_expire);

        return $list;
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
        foreach($list as &$v){
            $rule_models = explode(',',$v['rule_model_id']);
            $v['rule'] = Logic::getCouponRules($rule_models);
            if($v['type'] == 3 && $v['is_solo'] == 0){
                $v['coupon_name'] = '平台下部分商品满'. $v['satisfy_money'] .'可使用';
            }

            if($v['coupon_type']==11){$v['coupon_type']=10;} //抽奖线下优惠券转为线下优惠券
            $v['product_ids'] = explode(',',trimFunc($v['product_ids']));

        }
        return $list;
    }

    public static function getCanUseCouponList($user_id,$page,$size,$type){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];
        if($type)$where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->join('coupon_use_rule cur','cur.id = cc.rule_model_id','LEFT')
            ->where($where)
            ->where(['ucc.expiration_time'=>['GT',time()]])
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.kind,cc.start_time,cc.end_time,cc.days,ucc.create_time as user_start_time,ucc.expiration_time as user_end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,p.product_name,cc.coupon_name,cc.product_ids,cc.is_solo,cc.rule_model_id')
            ->order('ucc.expiration_time','asc')
//            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();
        return $list;
    }

    public static function getUserCountList($user_id,$page,$size,$type){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1
        ];
        if($type)$where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
//            ->where(['cc.is_open'=>1])
//            ->where(['cc.id'=>['NEQ',null]])
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.kind,cc.start_time,cc.end_time,cc.days,ucc.create_time as user_start_time,ucc.expiration_time as user_end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,p.product_name,cc.coupon_name,cc.product_ids,cc.is_solo,cc.rule_model_id')
            ->order('ucc.expiration_time','desc')
//            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();
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
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.start_time,ucc.expiration_time as end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name')
            ->order('cc.end_time','asc')
            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
            ->select();

        $data_expire = [];
        $data_can = [];

        foreach($list as &$v){
            if($v['end_time'] <= time()){
                $v['status'] = -1;
                $data_expire[] = $v;
            }else{
                $data_can[] = $v;
            }
        }
        $list = array_merge($data_can,$data_expire);
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
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.start_time,cc.end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,cc.product_id,p.product_name')
            ->order('ucc.create_time','desc')
            ->limit($page*$size,$size)
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
            'ucc.status' => 1,
        ];
        if($type)$where['cc.type'] = $type;
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->where($where)
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
//            ->where(['cc.is_open'=>1])
//            ->where(['cc.id'=>['NEQ',null]])
            ->count('ucc.id');
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
//            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
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
//            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
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
//            ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
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
        return Db::name('coupon')->insertGetId($data);
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
        return self::orderListWithStatus(1,$limit_time,$field_time='create_time');
    }

    /**
     * 待支付=》获取自动取消普通订单订单号
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderNosNotPay($limit_time){
        $limit_time = time() - $limit_time;
        $safe_time = config('config_order.time_safe');
        return Db::name('product_order')
            ->where(['order_status'=>1,'is_group_buy'=>0,'user_is_delete'=>0])
            ->where(['create_time'=>['BETWEEN',[$safe_time,$limit_time]],'pay_type'=>['NEQ','微信小程序']])
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
        return Db::name('product_order_detail')->where(['order_id'=>['IN',$order_ids]])->setField('status',-2);
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
    public static function createReturnMsg($user_id,$order_no,$type=0){
        $res = self::createMsg($order_no, $type);
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
            ->where(['status'=>0,'is_refund'=>0,'is_shouhou'=>0])
            ->field('product_id,specs_id,number,price')
            ->select();
    }

    /**
     * 生成信息
     * @param $order_no
     * @param $type
     * @return int|string
     */
    protected static function createMsg($order_no ,$type=0){
        $data = [
            'title' => '退款通知',
            'content' => "您的订单 {$order_no} 系统已自动取消，订单金额已原路返回",
            'type' => 2,
            'create_time' => time()
        ];
        if($type){
            $data['content'] = "您的订单 {$order_no} 系统已自动取消";
            $data['title'] = "订单通知";
        }
        return Db::name('user_msg')->insertGetId($data);
    }

    /**
     * 生成自动确认收货信息内容
     * @param $order_no
     * @return int|string
     */
    protected static function makeConfirmMsg($order_no){
        $data = [
            'title' => '自动确认收货通知',
            'content' => "由于您长时间未确认收货，您的订单 {$order_no} 系统已自动确认收货",
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
        return self::orderListWithStatus(3,$limit_time,'pay_time');
    }

    /**
     * 待发货=》获取自动取消普通订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListWaitSend($order_ids){
        return Db::name('product_order_detail')
            ->where(['order_id'=>['IN',$order_ids]])
            ->where(['status'=>0,'is_refund'=>0,'is_shouhou'=>0])
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
            ->where(['pay_time'=>['LT',$limit_time],'pay_type'=>['NEQ','微信小程序']])
            ->group('order_no')
            ->field('user_id,order_no,id')
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
        return self::orderListWithStatus(4,$limit_time,'fahuo_time');
    }

    /**
     * 获取不同状态下的订单列表
     * @param $status
     * @param $limit_time
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    protected static function orderListWithStatus($status,$limit_time,$field){
        $limit_time = time() - $limit_time;
        $safe_time = config('config_order.time_safe');
        if($field == 'create_time'){
            $where = [
                "{$field}" => ['BETWEEN',[$safe_time,$limit_time]],
                'pay_type'=>['NEQ','微信小程序']
            ];
        }else{
            $where = ["{$field}"=>['LT',$limit_time],'pay_type'=>['NEQ','微信小程序'],'create_time'=>['GT',$safe_time]];
        }
        return Db::name('product_order')
            ->where(['order_status'=>$status,'is_group_buy'=>0,'user_is_delete'=>0])
            ->where($where)
            ->field('id,user_css_coupon_id,user_id,pay_money,pay_type,pay_order_no,store_id,total_freight,coupon_money,platform_profit,store_coupon_id,coupon_id,pay_scene,order_no,store_coupon_money,product_coupon_id,product_coupon_money')
            ->select();
    }

    /**
     * 获取不同状态下的订单列表
     * @param $status
     * @param $limit_time
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    protected static function notpayorderList($status,$limit_time,$field){
        $limit_time = time() - $limit_time;
        $limit_time2 = time() - 2.2 * 60 * 60;
        return Db::name('product_order')->alias('p')
            ->join('user u','p.user_id = u.user_id','LEFT')
            ->where(['p.order_status'=>$status,'p.is_group_buy'=>0,'p.user_is_delete'=>0])
            ->where(["{$field}"=>['BETWEEN',[$limit_time2,$limit_time]],'p.pay_type'=>['NEQ','微信小程序']])
            ->field('p.id,p.user_css_coupon_id,u.mobile,p.user_id,p.pay_money,p.pay_type,p.pay_order_no,p.store_id,p.total_freight,p.coupon_money,p.platform_profit,p.store_coupon_id,p.coupon_id,p.pay_scene,p.order_no,p.store_coupon_money,p.product_coupon_id,p.product_coupon_money')
            ->group('p.pay_order_no')
            ->select();
    }
    public static function NotPaylist($limit_time){
        return self::notpayorderList(1,$limit_time,$field_time='p.create_time');
    }

    /**
     * 待收货=》获取会员商品订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListMemberBuy($order_ids){
        return Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','pod.order_id = po.id','LEFT')
            ->join('user u','u.user_id = po.user_id','LEFT')
            ->where(['pod.order_id'=>['IN',$order_ids]])
            ->where(['pod.status'=>0, 'pod.type'=>2, 'pod.is_refund'=>0, 'pod.is_shouhou'=>0])
            ->where(['po.is_member'=>1,'pod.huoli_money'=>['GT',0]])
            ->field('pod.id,pod.order_id,pod.product_id,pod.specs_id,pod.number,pod.price,pod.huoli_money,po.store_id,po.user_id,po.coupon_id,pod.realpay_money,pod.store_coupon_money')
            ->select();
    }

    /**
     * 待收货=》获取普通商品订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListNormalBuy($order_ids){
        return Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.order_id','LEFT')
            ->where(function($query) use($order_ids){
                $query->where(['pod.order_id'=>['IN',$order_ids]])
                    ->where(['po.is_member'=>0, 'pod.status'=>0, 'pod.is_refund'=>0, 'pod.is_shouhou'=>0]);
            })
            ->whereOr(function($query) use($order_ids){
                $query->where(['pod.order_id'=>['IN',$order_ids]])
                    ->where(['pod.huoli_money'=>0, 'pod.status'=>0, 'pod.is_refund'=>0, 'pod.is_shouhou'=>0]);
            })
            ->field('pod.product_id,pod.specs_id,pod.number,pod.price,po.store_id,pod.store_coupon_money')
            ->select();
    }

    /**
     * 获取用户余额
     * @param $user_id
     * @return float\
     */
    public static function userMoney($user_id){
        return floatval(Db::name('user')->where(['user_id'=>$user_id])->value('money'));
    }

    /**
     * 增加用户余额
     * @param $user_id
     * @param $money
     * @return int|true
     */
    public static function addUserMoney($user_id,$money){
        return Db::name('user')->where(['user_id'=>$user_id])->setInc('money',$money);
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
        return Db::name('user')->where(['user_id'=>$user_id])->setInc('leiji_money',$money);
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
            ->where(['ps.create_time'=>['LT',$limit_time],'po.pay_type'=>['NEQ','微信小程序']])
            ->field('pod.id,ps.id as shouhou_id,pod.order_id,pod.price,number,pod.realpay_money,ps.refund_type,po.coupon_id,po.pay_type,po.pay_scene,po.pay_order_no,po.order_no,po.pay_money')
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
        return Db::name('product_shouhou')->where(['id'=>['IN',$shouhou_ids]])->where(['refund_status'=>1])->update($data);
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
        return Db::name('product_order_detail')->where(['id'=>$order_detail_id,'is_refund'=>0])->update($data);
    }

    /**
     * 售后=>用户已退货，商户未收货列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function proListAfterSaleWaitArrive($limit_time){
        $safe_time = config('time_safe');
        $limit_time = time() - $limit_time;
        return Db::name('product_shouhou')->alias('ps')
            ->join('product_order_detail pod','ps.order_id = pod.order_id and ps.specs_id = pod.specs_id')
            ->join('product_order po','po.id = pod.order_id','LEFT')
            ->where(['ps.refund_status'=>3,'ps.refund_type'=>2,'pod.is_refund'=>0,'pod.status'=>0])
            ->where(['ps.fahuo_time'=>['LT',$limit_time],'po.pay_type'=>['NEQ','微信小程序'],'po.create_time'=>['GT',$safe_time]])
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
    public static function userOrderCouponLists0812($coupon_type, $coupon_id, $user_id, $store_id=0, $page=0, $size=10){
        $where = [
            'c.user_id' => $user_id,
            'cr.type' => $coupon_type,
            'c.status' => 1,
            'c.expiration_time' => ['GT',time()]
        ];
        if($coupon_type == 2)$where['cr.store_id'] = $store_id;

        $total = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where($where)
            ->where(['cr.client_type'=>['IN',[0,2]],'cr.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->count('c.id');

        $list = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->join('store s','cr.store_id = s.id','LEFT')
            ->join('product p','p.id = cr.product_id','LEFT')
            ->where($where)
            ->where(['cr.client_type'=>['IN',[0,2]],'cr.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
            ->field('c.id,cr.coupon_name,cr.satisfy_money,cr.coupon_money,c.expiration_time,cr.coupon_type,cr.type,s.store_name,p.product_name,cr.is_superposition,cr.store_id,cr.coupon_name')
            ->limit($page*$size,$size)
            ->order('c.expiration_time','asc')
            ->order('cr.coupon_money','desc')
            ->select();

        $is_superposition = 2;  //默认可叠加

        if($coupon_type == 2 && $coupon_id){  //商家券  叠加  平台券
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

            if(($coupon_type == 1 && $is_superposition == 1) || ($v['is_superposition'] == 1 && $coupon_id)){  //平台券
                $can_use = 2;
            }
            if($coupon_type == 2 && $is_superposition == 1){  //商家券
                $can_use = 2;
            }
            $v['can_use'] = $can_use;
        }

        $max_page = ceil($total/$size);

        return compact('list','max_page','total','coupon_type');
    }

    public static function userOrderCouponLists0906($coupon_type, $coupon_id, $user_id, $store_id=0, $product_ids=[], $page=0, $size=10){

        if($coupon_type == 3){ //商品券
            $time = time();
            $where = " (c.user_id = {$user_id} AND cr.type = {$coupon_type} AND c.status = 1 AND c.expiration_time > {$time} AND cr.client_type IN (0,2) AND cr.use_type IN (1,3)) AND cr.mall_stacked = 1 AND cr.coupon_type NOT IN (10,11) AND (";
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
                'cr.client_type' => ['IN',[0,2]],
                'cr.use_type' => ['IN',[0,1,3]],
                'cr.coupon_type' => ['NOT IN',[10, 11]],
                'cr.mall_stacked' => 1
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
            ->field('c.id,cr.coupon_name,cr.satisfy_money,cr.coupon_money,cr.start_time,c.expiration_time as end_time,c.expiration_time as user_end_time,c.expiration_time,c.create_time as user_start_time,cr.days,cr.coupon_type,cr.type,s.store_name,p.product_name,cr.is_superposition,cr.store_id,cr.coupon_name,cr.product_ids,cr.rule_model_id,cr.is_solo,c.status')
            ->limit($page*$size,$size)
            ->order('c.expiration_time','asc')
            ->order('cr.coupon_money','desc')
            ->select();

        if($coupon_type == 2 || $coupon_type == 3){  //商品||商家券
            if($coupon_id){
                $pt_superposition = Db::name('coupon')->alias('c')
                    ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                    ->where(['c.id'=>$coupon_id])
                    ->value('cr.is_superposition');
            }
            foreach($list as &$v){
                if($coupon_id){
                    $v['can_use'] = ($v['is_superposition'] == 2 && $pt_superposition == 2)?1:2;
                }else{
                    $v['can_use'] = 1;
                }
                if($coupon_type == 3 && $v['is_solo'] == 0)$v['coupon_name'] = "平台下部分商品满{$v['satisfy_money']}可使用";
                $v['product_ids'] = explode(',',trimFunc($v['product_ids']));

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

                $rule_models = explode(',',$v['rule_model_id']);
                $v['rule'] = Logic::getCouponRules($rule_models);
                if($v['type'] == 3 && $v['is_solo'] == 0){
                    $v['coupon_name'] = '平台下部分商品满'. $v['satisfy_money'] .'可使用';
                }

                $v['product_ids'] = explode(',',trimFunc($v['product_ids']));
            }
        }

//        $is_superposition = 2;  //默认可叠加
//
//        if(($coupon_type == 2 || $coupon_type == 3) && $coupon_id){  //商家券  叠加  平台券
//            $is_superposition = Db::name('coupon')->alias('c')
//                ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
//                ->where(['c.id'=>$coupon_id])
//                ->value('cr.is_superposition');
//            $is_superposition = (int)$is_superposition == 2?2:1;
//        }
//
//        if($coupon_type == 1 && is_array($coupon_id)){  //平台券  叠加  商家券
//            ##获取商家券的叠加性
//            $is_superposition = (Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_id]])->where(['cr.is_superposition'=>1])->count('c.id'))>0?1:2;
//        }
//
//        foreach($list as $k => &$v){
//            $can_use = 1;
//
//            if(($coupon_type == 1 && $is_superposition == 1) || ($v['is_superposition'] == 1 && $coupon_id)){  //平台券
//                $can_use = 2;
//            }
//            if($coupon_type == 2 && $is_superposition == 1){  //商家券
//                $can_use = 2;
//            }
//            $v['can_use'] = $can_use;
//        }

        $max_page = ceil($total/$size);

        return compact('list','max_page','total','coupon_type');
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
     * 获取订单支付总金额
     * @param $pay_order_no
     * @return float|int
     */
    public static function sumOrderPayMoney($pay_order_no){
        return Db::name('product_order')->where(compact('pay_order_no'))->sum('pay_money');
    }

    /**
     * 更新待付款定的中的使用平台券的订单(店铺数大于1)
     * @param $user_id
     */
    public static function cancelOrderNotPayPtCoupon($user_id){
        $list = self::getNotPayPtCouponOrderList($user_id);
        if(empty($list))return true;
        try{
            Db::startTrans();
            ##返还平台优惠券
            $coupon_ids = [];
            foreach($list as $v){
                if($v['coupon_id'])$coupon_ids[] = $v['coupon_id'];
            }
            $res = UserLogic::returnCoupon($coupon_ids);
            if($res === false)throw new Exception('卡券返还失败');

            ##恢复订单金额
            $pay_order_nos = array_column($list,'pay_order_no');
            $order_list = self::getOrderListInfoByPayOrderNo($pay_order_nos,$user_id);
            foreach($order_list as $v){
                $res = self::updateOrderPayMoney($user_id,$v['id'],$v['pay_money'],$v['coupon_money']);
                if($res === false)throw new Exception('订单信息更新失败');
            }

            ##恢复订单详情金额
            $order_ids = array_column($order_list,'id');
            $order_detail_list = self::getOrderDetailListByOrderId($order_ids);
            foreach($order_detail_list as $v){
                $res = self::updateOrderDetailRelPayMoney($v['id'],$v['realpay_money'],$v['coupon_money']);
                if($res === false)throw new Exception('订单详情更新失败');
            }
            Db::commit();

        }catch(Exception $e){
            Db::rollback();
            Log::error("订单撤销平台券失败[".$e->getMessage()."]====>".print_r($list,true));
            return false;
        }
        return true;
    }

    /**
     * 获取待支付,需要返还平台券的总订单编号
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getNotPayPtCouponOrderList($user_id){
        return Db::name('product_order')->where(['user_id'=>$user_id,'order_status'=>1,'coupon_id'=>['GT',0],'pay_type'=>['NEQ','小程序支付']])->group('pay_order_no')->having("count(pay_order_no)>1")->field('id,pay_order_no,coupon_id')->select();
    }

    /**
     * 通过总订单号获取订单信息
     * @param $pay_order_nos
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getOrderListInfoByPayOrderNo($pay_order_nos,$user_id){
        return Db::name('product_order')->where(['user_id'=>$user_id,'pay_order_no'=>['IN',$pay_order_nos]])->field('pay_money,coupon_money,id')->select();
    }

    /**
     * 更新订单的支付金额、平台券分摊金额、平台券id
     * @param $user_id
     * @param $order_id
     * @param $coupon_money
     * @param $pay_money
     * @return int|string
     */
    public static function updateOrderPayMoney($user_id,$order_id,$pay_money,$coupon_money){
        return self::updateOrderInfo($order_id,$user_id,['pay_money'=>$pay_money+$coupon_money,'coupon_money'=>0]);
    }

    /**
     * 更新订单信息
     * @param $order_id
     * @param $user_id
     * @param $data
     * @return int|string
     */
    public static function updateOrderInfo($order_id,$user_id,$data){
        $data['coupon_id'] = 0;
        return Db::name('product_order')->where(['id'=>$order_id,'user_id'=>$user_id])->update($data);
    }

    /**
     * 获取订单详情列表
     * @param $order_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getOrderDetailListByOrderId($order_ids){
        return Db::name('product_order_detail')->where(['order_id'=>['IN',$order_ids]])->field('id,realpay_money,coupon_money')->select();
    }

    /**
     *修改订单详情
     * @param $id
     * @param $relpay_money
     * @param $coupon_money
     * @return int|string
     */
    public static function updateOrderDetailRelPayMoney($id,$relpay_money,$coupon_money){
        return Db::name('product_order_detail')->where(['id'=>$id])->update(['realpay_money'=>$relpay_money+$coupon_money,'coupon_money'=>$coupon_money]);
    }

    /**
     * 获取用户资金记录数
     * @param $where
     * @return int|string
     */
    public static function countUserMoneyLog($where){
        return Db::name('user_money_detail')->where($where)->count('id');
    }

    /**
     * 获取用户资金记录列表
     * @param $where
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getUserMoneyList($where,$page,$size){
        return Db::name('user_money_detail')
            ->field('id,note,money,create_time,order_id')
            ->where($where)
            ->order('create_time','desc')
            ->limit(($page-1)*$size,$size)
            ->select();
    }

    /**
     * 获取用户推广人数
     * @param $user_id
     * @param $type
     * @return int|string
     */
    public static function getUserInviteCount($user_id, $type){
        $where = ['invitation_user_id'=>$user_id];
        switch($type){
            case 1:
                $where['start_time'] = ['EGT', dayStartTimestamp()];
//                $where['end_time'] = ['LT', dayEndTimestamp()];
                break;
            case 2:
                $where['start_time'] = ['EGT', weekStartTimestamp()];
//                $where['end_time'] = weekEndTimestamp();
                break;
        }
        return Db::name('user')->where($where)->count('user_id');
    }

    public static function checkCoupon($coupon_id, $user_id){
        ##判断用户是否是用户优惠券
        $coupon_id2 = Db::name('coupon')->where(['user_id'=>$user_id,'id'=>$coupon_id])->value('coupon_id');
        return $coupon_id2?:$coupon_id;

    }

    /**
     * 通过用户卡券获取卡券规则信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function userCouponInfo($coupon_id){
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where(['c.id'=>$coupon_id])
            ->field('cr.product_ids,cr.satisfy_money,cr.coupon_money')
            ->find();
    }

    /**
     * 优惠券商品列表优惠券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function userCouponInfo2($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->field('product_ids,satisfy_money,coupon_money')->find();
    }

    /**
     * 获取用户首页弹窗优惠券列表
     * @param $user_id
     * @param $login_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getUserCouponToastList($user_id, $login_time){
        $where = [
            'ucc.user_id' => $user_id,
            'ucc.status' => 1,
            'ucc.create_time' => ['EGT',$login_time],
            'cc.coupon_type' => ['NEQ',10]
        ];
        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->join('coupon_use_rule cur','cur.id = cc.rule_model_id','LEFT')
            ->where($where)
            ->where(['ucc.expiration_time'=>['GT',time()]])
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.kind,cc.start_time,ucc.expiration_time as end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,p.product_name,cc.coupon_name,cc.product_ids,cc.is_solo,cc.rule_model_id')
            ->order('ucc.create_time','desc')
//            ->limit(2)
            ->select();
        foreach($list as &$v){
            if($v['type'] == 3 && $v['is_solo'] == 0)$v['coupon_name'] = "平台下多商品满{$v['satisfy_money']}可使用";
            $v['product_ids'] = explode(',',trimFunc($v['product_ids']));
        }
        return $list;
    }

    /**
     * 更新用户登录时间
     * @param $user_id
     * @return int
     */
    public static function updateUserLoginTime($user_id){
        return Db::name('user')->where(['user_id'=>$user_id])->setField('login_time',time());
    }

    /**
     * 检查订单支付状态
     * @param $order_id
     * @return mixed
     */
    public static function checkPayStatus($order_id){
        return Db::name('product_order')->where(['id'=>$order_id])->value('pay_time');
    }

    /**
     * 获取用户该店铺可使用的店铺券信息
     * @param $user_id
     * @param $store_id
     * @return int|string
     */
    public static function countUserStoreCoupons($user_id, $store_id){
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
            ->where([
                'c.user_id'=>$user_id,
                'c.status'=>1,
                'c.expiration_time'=>['GT', time()],
                'cr.type'=>2,
                'cr.client_type'=>['IN',[0,2]],
                'cr.use_type'=>['IN',[1,3]],
                'cr.store_id'=>$store_id,
                'cr.coupon_type' => ['NOT IN',[10,11]],
                'cr.mall_stacked' => 1
            ])
            ->count('c.id');
    }

    /**
     * 获取用户该店铺可使用的商品券信息
     * @param $user_id
     * @param $store_id
     * @param $product_ids
     * @return int|string
     */
    public static function countUserProductCoupons($user_id, $store_id, $product_ids){
        $time = time();
        $where = " (c.user_id = {$user_id} AND cr.store_id = {$store_id} AND cr.type = 3 AND c.status = 1 AND c.expiration_time > {$time} AND cr.client_type IN (0,2) AND cr.use_type IN (1,3)) AND cr.coupon_type NOT IN (10,11) AND cr.mall_stacked = 1 AND (";
        foreach($product_ids as &$v){
            $where .= " cr.product_ids like '%[{$v}]%' or";
        };
        $where = rtrim($where,'or');
        $where .= ")";

        $count = Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where($where)
//            ->group('cr.id')
            ->count('c.id');

        return $count;
    }

    /**
     * 获取用户可使用平台券数
     * @param $user_id
     * @return int|string
     */
    public static function countUserPtCoupons($user_id){
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where([
                'c.user_id'=>$user_id,
                'c.status'=>1,
                'c.expiration_time'=>['GT', time()],
                'cr.type'=>1,
                'cr.client_type'=>['IN',[0,2]],
                'cr.use_type'=>['IN',[1,3]],
                'cr.coupon_type' => ['NOT IN',[10, 11]],
                'cr.mall_stacked' => 1
            ])
            ->count('c.id');
    }

    /**
     * 计算用户获取的某张优惠券的张数
     * @param $coupon_id
     * @param $user_id
     * @return int|string
     */
    public static function countUserCouponByCouponId($coupon_id, $user_id){
        return Db::name('coupon')->where(['coupon_id'=>$coupon_id,'user_id'=>$user_id])->count('id');
    }

    /**
     * 取消活动上线提醒
     * @param $user_id
     * @param $activity_id
     * @param $product_id
     * @return int
     */
    public static function cancelActivityClock($user_id, $activity_id, $product_id){
        return Db::name('activity_clock')->where(['user_id'=>$user_id,'activity_id'=>$activity_id,'product_id'=>$product_id])->delete();
    }

    /**
     * 添加活动上线提醒
     * @param $user_id
     * @param $activity_id
     * @param $product_id
     * @return bool|int|string
     */
    public static function addActivityClock($user_id, $activity_id, $product_id){

        #判断用户是否已添加提醒(待提醒)
        if(self::checkUserAcClock($user_id, $activity_id, $product_id)) return false;

        #增加活动提醒
        $create_time = time();
        ##活动上线时间
        $activity_info = Logic::getActivityInfo($activity_id);
        $ac_time = Logic::autoAcStartTime($activity_info);
        $clock_time = $ac_time['start_time'] - 10 * 60;

        ##商品名
        $product_name = Logic::getProInfo($product_id);
        ##构造提醒内容
        $message = config('config_common.activity_clock_message');
        $message = sprintf($message, $product_name, $product_id);

        $data = compact('user_id','activity_id','product_id','message','create_time','clock_time');
        $res = Db::name('activity_clock')->insert($data);

        return $res;
    }

    /**
     * 统计用户活动商品的上线提醒
     * @param $user_id
     * @param $activity_id
     * @param $product_id
     * @return int|string
     */
    public static function checkUserAcClock($user_id, $activity_id, $product_id){
        return Db::name('activity_clock')->where(['user_id'=>$user_id,'activity_id'=>$activity_id,'product_id'=>$product_id,'status'=>1])->count('id');
    }

    /**
     * 获取需要提醒的活动的列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcNeedClockList(){
        return Db::name('activity_clock')->alias('ac')
            ->join('user u','u.user_id = ac.user_id','LEFT')
            ->join('product p','ac.product_id = p.id','LEFT')
            ->where(['ac.status'=>1,'ac.clock_time'=>['ELT',time()]])
            ->field('ac.id,ac.message,u.mobile,ac.product_id,p.product_name')
            ->select();
    }

    /**
     * 更新活动提醒为已提醒
     * @param $id
     * @return int
     */
    public static function updateAcClockStatus($id){
        return Db::name('activity_clock')->where(['id'=>$id])->setField('status',2);
    }

    /**
     * 确认收货,修改订单状态为待评价
     * @param $order_ids
     * @return int
     */
    public static function confirmOrder($order_ids){
        return Db::name('product_order')->where(['id'=>['IN',$order_ids]])->update(['order_status'=>5,'confirm_time'=>time()]);
    }

    /**
     * 创建自动确认订单消息通知
     * @param $user_id
     * @param $order_no
     * @return int|string
     */
    public static function createConfirmMsg($user_id, $order_no){
        $res = self::makeConfirmMsg($order_no);
        if($res === false)return $res;
        return self::createMsgUserLink($user_id, $res);
    }

    /**
     * 获取需要自动评论的订单商品详情
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderDetailAutoComment($limit_time){
        $safe_time = config('config_order.time_safe');
        ##获取所有待评价的订单详情列表
        $order_detail_list = Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.order_id','RIGHT')
            ->where(['pod.status'=>0,'po.order_status'=>5,'pod.is_shouhou'=>0,'pod.is_comment'=>0,'po.confirm_time'=>['ELT',$limit_time],'pod.is_platform_service'=>0,'po.pay_type'=>['NEQ','微信小程序'],'po.create_time'=>['GT',$safe_time]])
            ->field('pod.id,pod.order_id')
            ->select();
        return $order_detail_list;
    }

    /**
     * 获取需要自动评论的售后订单商品详情
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderDetailAutoShouhouComment($limit_time){
        $safe_time = config('config_order.time_safe');
        ##获取所有待评价的售后拒绝未客服介入的订单详情列表
        $order_detail_list = Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.order_id','RIGHT')
            ->where(['pod.status'=>0,'pod.is_shouhou'=>['IN',[-1,-2]],'pod.comment_standard_time'=>['ELT',$limit_time],'pod.is_platform_service'=>0,'pod.shouhou_order_status'=>5,'po.pay_type'=>['NEQ','微信小程序'],'po.create_time'=>['GT',$safe_time]])
            ->field('pod.id,pod.order_id')
            ->select();
        return $order_detail_list;
    }

    /**
     * 更新售后拒绝3天后的订单商品详情
     * @param $detail_ids
     * @return mixed
     */
    public static function autoEditCommentAndShouhouOrderStatus($detail_ids){
        return Db::name('product_order_detail')->where(['id'=>['IN',$detail_ids]])->update(['is_comment'=>1,'finish_standard_time'=>time(),'shouhou_order_status'=>6]);
    }

    /**
     * 更新订单详情 已评价
     * @param $ids
     * @return int|string
     */
    public static function autoCommentOrderDetail($ids){
        return Db::name('product_order_detail')->where(['id'=>['IN',$ids]])->update(['finish_standard_time'=>time(),'is_comment'=>1]);
    }

    /**
     * 获取需要自动结转的订单商品详情
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderDetailAutoFinish($limit_time){
        $safe_time = config('config_order.time_safe');
        $list = Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.order_id','RIGHT')
            ->join('store s','s.id = po.store_id','LEFT')
            ->where(['pod.status'=>0,'pod.is_shouhou'=>0,'pod.is_comment'=>1,'pod.is_finish'=>0,'pod.finish_standard_time'=>['ELT',$limit_time],'po.order_status'=>6,'po.create_time'=>['GT',$safe_time],'pod.is_platform_service'=>0,'po.version'=>['EGT',5],'po.pay_type'=>['NEQ','微信小程序']])
            ->field('
                pod.id,pod.order_id,pod.huoli_money,pod.realpay_money,pod.coupon_money,pod.store_coupon_money,pod.product_coupon_money,pod.activity_id,pod.return_money,pod.return_coupon_id,pod.product_id,pod.specs_id,pod.number,pod.platform_profit,
                po.user_id,po.total_freight,po.order_no,po.coupon_id,po.store_coupon_id,po.product_coupon_id,po.store_id,po.total_freight,po.order_status,po.discount_money,
                s.platform_ticheng')
            ->select();
        return $list;
    }

    /**
     * 获取需要结转的售后订单商品详情
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function orderDetailShouhouAutoFinish($limit_time){
        $safe_time = config('config_order.time_safe');
        $list = Db::name('product_order_detail')->alias('pod')
            ->join('product_order po','po.id = pod.order_id','RIGHT')
            ->join('store s','s.id = po.store_id','LEFT')
            ->where(['pod.status'=>0,'pod.is_shouhou'=>['IN',[-1,-2]],'pod.is_comment'=>1,'pod.is_finish'=>0,'pod.finish_standard_time'=>['ELT',$limit_time],'po.order_status'=>['IN',[5,6,7,8]],'po.create_time'=>['GT',$safe_time],'pod.is_platform_service'=>0,'pod.shouhou_order_status'=>6,'po.version'=>['EGT',5],'po.pay_type'=>['NEQ','微信小程序']])
            ->field('
                pod.id,pod.order_id,pod.huoli_money,pod.realpay_money,pod.coupon_money,pod.store_coupon_money,pod.product_coupon_money,pod.activity_id,pod.return_money,pod.return_coupon_id,pod.product_id,pod.specs_id,pod.number,pod.platform_profit,
                po.user_id,po.total_freight,po.order_no,po.coupon_id,po.store_coupon_id,po.product_coupon_id,po.store_id,po.order_status,po.total_freight,po.discount_money,
                s.platform_ticheng')
            ->select();
        return $list;
    }

    /**
     * 判断是否结转运费 （0结转，大于0不结转）
     * @param $order_id
     * @return int|string
     */
    public static function checkFinishFreight($order_id){
        return Db::name('product_order_detail')->where([
                'order_id' => $order_id,
                'is_refund' => 0,
                'is_finish' => 1,
            ])
            ->count('id');
    }

    /**
     * 更新订单商品详情 已完成(结转)
     * @param $order_detail_ids
     * @return int|string
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public static function finishOrderDetail($order_detail_ids){
        return Db::name('product_order_detail')->where(['id'=>['IN',$order_detail_ids]])->update(['is_finish'=>1,'finish_time'=>time()]);
    }

    /**
     * 检查订单是否可以更新状态为已结转
     * @param $order_id
     * @return bool
     */
    public static function checkOrderFinish($order_id){
        $total_count = self::countPerOrderDetail($order_id);
        $finish_count = self::countPerOrderFinishDetail($order_id);

        if(!$total_count)return false;
        return $total_count == $finish_count?true:false;
    }

    /**
     * 获取订单总订单商品详情数
     * @param $order_id
     * @return int|string
     */
    public static function countPerOrderDetail($order_id){
        return Db::name('product_order_detail')->where(['order_id'=>$order_id,'status'=>0,'is_shouhou'=>['IN',[0,-1,-2]],'is_platform_service'=>0])->count('id');
    }

    /**
     * 获取订单已结转的订单商品详情数
     * @param $order_id
     * @return int|string
     */
    public static function countPerOrderFinishDetail($order_id){
        $count1 = Db::name('product_order_detail')->where(['order_id'=>$order_id,'status'=>0,'is_finish'=>1,'is_shouhou'=>['IN',[0,-1,-2]],'is_platform_service'=>0])->count('id');
//        $count2 = Db::name('product_order_detail')->where(['order_id'=>$order_id,'status'=>0,'is_shouhou'=>['IN',[-1,4]]])->count('id');
        return $count1;
    }

    /**
     * 更新订单
     * @param $order_id
     * @return int
     */
    public static function finishOrder($order_id){
        return Db::name('product_order')->where(['id'=>$order_id,'order_status'=>6])->setField('order_status',8);
    }

    /**
     * 获取售后用户已发货 7 天商户未处理的售后订单列表
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getShouhouAutoReceiveList($limit_time){
        $safe_time = config('config_order.time_safe');
        $list = Db::name('product_shouhou')->alias('ps')
            ->join('product_order po','ps.order_id = po.id','LEFT')
            ->join('product_order_detail pod','ps.order_id = pod.order_id and ps.specs_id = pod.specs_id','LEFT')
            ->where(['ps.refund_type'=>2,'ps.refund_status'=>3,'ps.fahuo_time'=>['ELT',$limit_time],'po.pay_type'=>['NEQ','微信小程序'],'po.create_time'=>['GT',$safe_time]])
            ->field('ps.id,pod.id as detail_id,pod.realpay_money,po.order_no,po.pay_order_no,po.pay_scene,po.pay_type,po.pay_money,po.user_id,po.id as order_id')
            ->select();
        return $list;
    }

    /**
     * 更新售后为系统默认收货
     * @param $shouhou_id
     * @return int|string
     */
    public static function editProShouhouReceive($shouhou_id){
        return Db::name('product_shouhou')->where(['id'=>$shouhou_id])->update(['store_goods_status'=>3,'refund_status'=>4,'finish_time'=>time()]);
    }

    /**
     * 更新订单商品详情为已售后退款
     * @param $detail_id
     * @param $refund_money
     * @return int|string
     */
    public static function editProOrderDetailShouhou($detail_id, $refund_money){
        return Db::name('product_order_detail')->where(['id'=>$detail_id])->update(['is_shouhou'=>4,'is_refund'=>1,'refund_time'=>time(),'refund_money'=>$refund_money]);
    }

    /**
     * 判断订单是否需要修改状态为已完成
     * @param $order_id
     * @return bool
     */
    public static function checkOrderComment($order_id){
        #获取订单下的所有应评论订单详情数
        $count1 = self::countPerOrderComment($order_id);
        #获取订单下已评论的订单详情数
        $count2 = self::countPerOrderCommentDetail($order_id);

        #比较
        if(!$count1)return false;
        return $count1 == $count2?true:false;
    }

    /**
     * 获取订单商品详情中的需要评价数
     * @param $order_id
     * @return int|string
     */
    public static function countPerOrderComment($order_id){
        return Db::name('product_order_detail')->where(['order_id'=>$order_id,'status'=>0,'is_shouhou'=>0])->count('id');
    }

    /**
     * 获取订单商品详情中的已评价数
     * @param $order_id
     * @return int|string
     */
    public static function countPerOrderCommentDetail($order_id){
        return Db::name('product_order_detail')->where(['order_id'=>$order_id,'status'=>0,'is_shouhou'=>0,'is_comment'=>1])->count('id');
    }

    /**
     * 更新订单状态为已完成
     * @param $order
     * @return int
     */
    public static function updateOrderStatusToFinish($order){
        return Db::name('product_order')->where(['id'=>$order])->setField('order_status',6);
    }

    /**
     * 获取待售后规定时间内未处理的订单详情id
     * @param $limit_time
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getShouhouAutoPlatformServiceList($limit_time){
        return Db::name('product_shouhou')->alias('ps')
            ->join('product_order_detail pod','pod.order_id = ps.order_id and pod.specs_id = ps.specs_id','LEFT')
            ->where(['ps.refund_status'=>1,'ps.create_time'=>['ELT',$limit_time],'pod.is_shouhou'=>1])
            ->field('pod.id as order_detail_id')
            ->select();
    }

    /**
     * 修改订单商品详情状态为客服介入
     * @param $order_detail_ids
     * @return int
     */
    public static function updateOrderDetailPlatformService($order_detail_ids){
        return Db::name('product_order_detail')->where(['id'=>['IN',$order_detail_ids]])->setField('is_platform_service',1);
    }

    /**
     * 获取订单可退款金额
     * @param $order_id
     * @param $total_freight
     * @return float|int
     */
    public static function getOrderCanRefundMoney($order_id, $total_freight){
        ##获取订单商品详情可退款的金额之和
        $money = Db::name('product_order_detail')
            ->where([
                'order_id'=>$order_id,
                'is_refund'=>0,
                'status'=>0,
                'is_shouhou'=>0
            ])
            ->sum('realpay_money');
        $money += $total_freight;
        return $money;
    }

    /**
     * 抽奖定时任务
     */
    public static function executeDrawTask(){
        try{
            $auto_time = Cache::get('auto_add_draw_lottery_record',0);
            if(!$auto_time || (time() - $auto_time > 1 * 40)){
                Cache::set('auto_add_draw_lottery_record',time());
                $task = new TaskCon();
                ##导入数据库抽奖记录
                $task->importDrawRecord();
            }
        }catch(Exception $e){
            addErrLog($e->getMessage(),'抽奖定时任务执行失败',8);
        }
    }

    /**
     * 执行定时任务
     * @throws \Exception
     */
    public static function executeTask(){
        try{
            $auto_time_message = Cache::get('auto_time_message',0);
            if(!$auto_time_message || (time() - $auto_time_message > 1 * 60)){
                Cache::set('auto_time_message',time());
                $task = new TaskCon();
                ##一小时未支付发送短信
                $task->onehourSendMessageNotPayOrder();
                ##1.5小时未支付发送短信
                $task->oneandahalfhourssendMessageNotPayOrder();
                ##自动取消订单
                $task->autoCancelNotPayOrder();
                ##发送活动提醒
                $task->autoActivityClock();
            }
            $auto_time_order = Cache::get('auto_time_order',0);
            if(!$auto_time_order || (time() - $auto_time_order > 5 * 60)){
                Cache::set('auto_time_order',time());
                if(!isset($task))$task = new TaskCon();
                ##待发货12小时未发货自动取消订单
                //$task->autoCancelWaitSendOrder();
            }
            $auto_time_other = Cache::get('auto_time_other',0);
            if(!$auto_time_other || (time() - $auto_time_other > 10 * 60)){
                Cache::set('auto_time_other',time());
                if(!isset($task))$task = new TaskCon();
                ##申请售后3天商家未处理自动转为客服介入
                $task->autoShouhouPlatformService();
                ##待收货-7天自动确认收货
                $task->autoConfirmOrder();
                ##待评价-超过3天未评价自动评价(未在售后中)
                $task->autoComment();
                ##已评价-4天自动完成订单
                $task->autoFinishOrder();
                ##售后用户发货 7 天后店铺未作处理，自动确认收货
                $task->autoReceiveShouHouPro();
                ##被打回的[在主单为待评价状态]售后订单3天未再次申请售后自动评价
                $task->autoShouhouComment();
            }
        }catch(Exception $e){
            addErrLog($e->getMessage(),'定时任务执行失败',8);
        }

    }
    /**
     * 查询店铺分类id
     * @return array
     */
    public static function GetStoreCateIds($v2){
        return Db::name('store_cate_store')
            ->where('cate_store_id','in',$v2)
            ->where('delete_time is null')
            ->group('store_id')
            ->column('store_id');
    }
    /**
     * 查询店铺风格id
     * @return array
     */
    public static function GetStoreStyleIds($v2){
        return Db::name('store_style_store')
            ->where('style_store_id','in',$v2)
            ->where('delete_time is null')
            ->group('store_id')
            ->column('store_id');
    }
    /**
     * 查询商圈店铺id
     * @return array
     */
    public static function GetBusinessCircleIds($v2){
        return Db::name('business_circle_store')
            ->where('business_circle_id','in',$v2)
            ->group('store_id')
            ->column('store_id');
    }
    /**
     * 查询人数
     * @return
     */
    public static function GetPerson($v2){
        $list = Db::view('store_read_record','store_id,user_id')
            ->view('user','avatar','store_read_record.user_id = user.user_id','LEFT')
            ->where('store_read_record.store_id',$v2)
            ->where('user.user_status','in','1,3')
            ->group('store_read_record.user_id')
            ->limit(10)
            ->select();
        foreach($list as $k=> $v){
            if(!$v['avatar'] || strstr($v['avatar'],'default'))unset($list[$k]);
        }
        $total = count($list);
        if($total<10){
            $list2 = getDefaultHeadPic(10 - $total);
            $list = array_merge($list, $list2);
        }
        $path = __FILE__;
        $host = strstr($path,'csswx')?"wx":"appwx";
        foreach($list as &$v){
            if(!strstr($v['avatar'],'http')){
                $v['avatar'] = "https://" . "{$host}.supersg.cn" . $v['avatar'];
            }
        }
        $list = array_values($list);
        return $list;
    }
    /**
     * 查询风格
     * @return
     */
    public static function GetStoreStyle($v2){
        return Db::view('store_style_store','id as store_style_store_id')
            ->view('style_store','id,title','store_style_store.style_store_id = style_store.id','LEFT')
            ->where('store_style_store.store_id',$v2)
            ->where('style_store.delete_time is null ')
            ->select();
    }
    /**
     * 查询活动
     * @return
     */
    public static function GetActivity($v2){
        return Db::view('activity_product','store_id')
            ->view('activity','id,activity_type','activity_product.activity_id = activity.id','LEFT')
            ->where('activity_product.store_id',$v2)
            ->where('activity.start_time','<=',time())
            ->where('activity.end_time','>=',time())
            ->group('activity_product.activity_id')
            ->select();
    }
    /**
     * 获取标签
     * @return array
     */
    public static function GetTagsStore($activityids,$maidan){
        $av=[];
        foreach ($activityids as &$v3){
            unset($v3['store_id']);
            unset($v3['id']);
            switch ($v3['activity_type']){
                case 2:
                    $av[]="直降";
                    break;
                case 3:
                    $av[]="满减";
                    break;
                case 4:
                    $av[]="打折";
                    break;
                case 5:
                    $av[]="返现";
                    break;
                case 6:
                    $av[]="返券";//优惠券
                    break;
                default:
                   // $av[]="";
            }
            unset($v3['activity_type']);
        }
        if($maidan>0){$av[]="买单";}
        return $av;
    }

    /**
     * 获取店铺主图
     * @return array|string
     */
    public static function getStoreImg($store_id){
        return Db::name('store_img')
            ->field('id,img_url,product_id,product_specs,chaoda_id')
            ->where('store_id',$store_id)
            ->select();
    }

    /**
     * 获取主图商品价格规格
     * @return array|string
     */
    public static function getStoreImgPrice($img){
        foreach ($img as $k=>$v){
                    $produc[$k]=Db::name('product')->alias('p')
                        ->join('product_specs ps','p.id = ps.product_id','LEFT')
                        ->where('ps.product_id',$v['product_id'])->where('ps.product_specs','eq',"{$v['product_specs']}")
                        ->field('p.id as product_id,p.product_name,ps.cover,ps.product_specs,ps.id as specs_id,ps.price')
                        ->find();
                    if($produc[$k]){
                        $img[$k]['product_info'][]= $produc[$k];
                    }else{
                        $img[$k]['product_info']=[];
                    }
                    unset($img[$k]['product_id']);
                    unset($img[$k]['product_specs']);
                    unset($img[$k]['chaoda_id']);
                }
        return $img;
    }
    /**
     * 获取动态主图
     * @return array|string
     */
    public static function getDynamicImg($dynamic_id){
        return Db::name('dynamic_img')
            ->where('dynamic_id','EQ',$dynamic_id)
            ->where('can_use','EQ',1)
            ->field('id,img_url')
            ->order('id asc')
            ->select();
    }
    /**
     * 获取动态主图商品价格规格
     * @return array|string
     */
    public static function getDynamicImgPrice($img){
        foreach ($img as $k=>$v ){
            $products=Db::name('dynamic_product')->field('product_id,dynamic_id,img_id')->where('img_id',$v['id'])->select();

            if($products){
                foreach ($products as $k2=>$v2){
                   $img[$k]['product_info'][]= Db::name('product')->alias('p')
                        ->join('product_specs ps','p.id = ps.product_id','LEFT')
                        ->where('p.id','EQ',$v2['product_id'])
                        ->field('p.id as product_id,ps.cover,p.product_name,ps.product_specs,ps.id as specs_id,ps.price')
                        ->find();
                }
            }else{
                $img[$k]['product_info']=[];
            }
        }
        return $img;
    }

    /**
     * 发放优惠券
     * @param $user_id
     * @param $reward_id
     * @param $draw_id
     * @return array
     */
    public static function userDrawGetCoupon($user_id, $reward_id, $draw_id){
        $coupon_info = Db::name('gift_lottery')->alias('gl')
            ->join('coupon_rule cr','cr.id = gl.gift_id','LEFT')
            ->where([
                'gl.id' => $reward_id,
                'cr.is_open' => 1
            ])
            ->field([
                'cr.id as coupon_id','cr.satisfy_money','cr.coupon_money','cr.start_time','cr.end_time','cr.days','cr.coupon_name','cr.store_id','cr.coupon_type','cr.surplus_number','cr.use_number','cr.is_solo','cr.type'
            ])
            ->find();
        $user_coupon_id = 0;
        if($coupon_info){
            $data = [
                'user_id' => $user_id,
                'coupon_id' => $coupon_info['coupon_id'],
                'coupon_name' => $coupon_info['coupon_name'],
                'store_id' => $coupon_info['store_id'],
                'satisfy_money' => $coupon_info['satisfy_money'],
                'coupon_money' => $coupon_info['coupon_money'],
                'coupon_type' => $coupon_info['coupon_type'],
                'expiration_time' => $coupon_info['end_time'],
                'create_time' => time(),
                'status' => 1,
                'draw_lottery_id' => $draw_id
            ];
            if($coupon_info['days'] > 0){
                $data['expiration_time'] = time() + $coupon_info['days'] * 24 * 60 * 60;
            }
            $user_coupon_id =  Db::name('coupon')->insertGetId($data);
            ##减少优惠券剩余张数
            $edit_data = [
                'surplus_number' => $coupon_info['surplus_number'] - 1,
                'use_number' => $coupon_info['use_number'] + 1
            ];
            Db::name('coupon_rule')->where(['id'=>$coupon_info['coupon_id']])->update($edit_data);
        }
        $is_online = $coupon_info['coupon_type'] == 11?"0":"1";
        return ['user_coupon_id'=>$coupon_info['coupon_id'], 'coupon_id'=>$user_coupon_id, 'is_solo'=>$coupon_info['is_solo'], 'type'=>$coupon_info['type'], 'store_id'=>$coupon_info['store_id'], 'is_online'=>$is_online];
    }

    /**
     * 获取用户抽奖的优惠券列表
     * @param $user_id
     * @param $draw_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getUserDrawCouponList($user_id, $draw_id){

        $list = Db::name('coupon')->alias('ucc')
            ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
            ->join('product p','p.id = cc.product_id','LEFT')
            ->join('store s','s.id = cc.store_id','LEFT')
            ->join('coupon_use_rule cur','cur.id = cc.rule_model_id','LEFT')
            ->where([
                'ucc.user_id' => $user_id,
                'ucc.draw_lottery_id' => $draw_id
            ])
            ->where(['ucc.expiration_time'=>['GT',time()]])
            ->where(['cc.client_type'=>['IN',[0,2]],'cc.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
            ->field('ucc.id,s.store_name,cc.coupon_type,cc.kind,cc.start_time,cc.end_time,cc.days,ucc.create_time as user_start_time,ucc.expiration_time as user_end_time,cc.satisfy_money,cc.coupon_money,ucc.status,cc.type,cc.store_id,p.product_name,cc.coupon_name,cc.product_ids,cc.is_solo,cc.rule_model_id')
            ->select();

        foreach($list as &$v){
            $rule_models = explode(',',$v['rule_model_id']);
            $v['rule'] = Logic::getCouponRules($rule_models);
            if($v['type'] == 3 && $v['is_solo'] == 0){
                $v['coupon_name'] = '平台下部分商品满'. $v['satisfy_money'] .'可使用';
            }

            if($v['coupon_type']==11){$v['coupon_type']=10;} //抽奖线下优惠券转为线下优惠券

            $v['product_ids'] = explode(',',trimFunc($v['product_ids']));
        }
        return $list;

    }

}