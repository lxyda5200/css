<?php


namespace app\user_v5\common;


use think\Db;

class Logic
{

    /**
     * 获取评论图片
     * @param $comment_id
     * @return array
     */
    public static function getProCommentImages($comment_id){
        return Db::name('product_comment_img')->where(['comment_id'=>$comment_id])->column('img_url');
    }

    /**
     * 获取优惠券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function couponInfo($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->field('coupon_type,coupon_name,is_open,surplus_number,store_id,type,satisfy_money,coupon_money,end_time,coupon_type,zengsong_number,store_ids,product_ids,days')->find();
    }

    /**
     * 获取新人券列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function newUserCouponInfo(){
        return Db::name('css_coupon')
            ->where(['coupon_type'=>1,'status'=>1,'type'=>1])
            ->where(['surplus_number'=>['GT',0],'end_time'=>['GT',time()]])
            ->field('coupon_id,store_id')
            ->select();
    }

    /**
     * 更新优惠券数量
     * @param $coupon_id
     * @return int|true
     */
    public static function updateCouponNum($coupon_id){
        $res = self::updateCouponSurplusNum($coupon_id);
        if($res === false)return $res;
        return self::updateCouponUseNum($coupon_id);
    }

    /**
     * 增加新人券、邀请券、会员券的领取数量
     * @param $coupon_id
     * @param $num
     * @return int|true
     */
    public static function updateNoNumCouponNum($coupon_id, $num){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->setInc('use_number',$num);
    }

    /**
     * 注销兑换码(兑换成功后)
     * @param $id
     * @param $data
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function cancelCouponCode($id,$data){
        return Db::name('css_coupon_code')->where(compact('id'))->update($data);
    }

    /**
     * 增加优惠券领取数
     * @param $coupon_id
     * @return int|true
     */
    public static function updateCouponUseNum($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->setInc('use_number',1);
    }

    /**
     * 减少优惠券剩余数
     * @param $coupon_id
     * @return int|true
     */
    public static function updateCouponSurplusNum($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->setDec('surplus_number',1);
    }

    /**
     * 获取买单店铺信息
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function storeInfo($store_id){
        return Db::name('store')->where(['id'=>$store_id])->field('id,store_status,mobile,maidan_deduct')->find();
    }

    /**
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function maidanInfo($store_id){
        $info = Db::name('maidan')->where(['store_id'=>$store_id,'status'=>1])->field('putong_user,member_user')->find();
        if(!$info)$info = ['putong_user'=>10.00,'member_user'=>10.00];
        return $info;
    }

    /**
     * 获取月卡会员价格
     * @return mixed
     */
    public static function monthMemberPrice(){
        return Db::name('member_card')->where(['id'=>1])->value('price');
    }

    /**
     * 获取店铺余额
     * @param $store_id
     * @return mixed
     */
    public static function storeMoney($store_id){
        return Db::name('store')->where('id',$store_id)->value('money');
    }

    /**
     * 增加店铺余额
     * @param $store_id
     * @param $money
     * @return int|true
     */
    public static function IncStoreMoney($store_id,$money){
        return Db::name('store')->where(['id'=>$store_id])->setInc('money',$money);
    }

    /**
     * 增加店铺收入记录
     * @param $data
     * @return int|string
     */
    public static function addStoreIncomeRecord($data){
        return Db::name('store_money_detail')->insert($data);
    }

    /**
     * 批量确认收货
     * @param $order_ids
     * @return int
     */
    public static function confirmLotsOrder($order_ids){
        return Db::name('product_order')->where(['id'=>['IN',$order_ids]])->setField('order_status',5);
    }

    public static function couponCodeInfo_01($coupon_code){
//        return Db::name('css_coupon_code')->alias('ccc')
//            ->join('css_coupon cc','ccc.css_coupon_id = cc.id','LEFT')
//            ->where(['ccc.exchange_code'=>$coupon_code,'cc.status'=>1])
//            ->field('')
    }

    /**
     * 获取兑换码信息
     * @param $coupon_code
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function couponCodeInfo($coupon_code){
        return Db::name('css_coupon_code')->alias('c')
            ->join('coupon_rule cr','cr.id = c.css_coupon_id','LEFT')
            ->where(['exchange_code'=>$coupon_code])
            ->field('c.id,c.status,c.type,c.extend_id,cr.coupon_name,cr.satisfy_money,cr.coupon_money,c.css_coupon_id,cr.surplus_number,cr.use_number,cr.end_time,cr.is_open,cr.zengsong_number,cr.coupon_type')
            ->find();
    }

    /**
     * 获取推广人信息
     * @param $id
     * @return array|false|\PDOStatement|string|\think\ModelundException
     * @throws \think\exception\DbException
     */
    public static function getExtendInfo($id){
        return Db::name('extend')->where(['id'=>$id,'delete_time'=>null])->field('status')->find();
    }

    /**
     * 添加用户消息通知
     * @param $title
     * @param $content
     * @param int $type
     * @return int|string
     */
    public static function addMsg($title, $content, $type=2){
        $data = compact('title','content','type');
        $data['create_time'] = time();
        return Db::name('user_msg')->insertGetId($data);
    }

    /**
     * 获取邀请券 1=》被邀请人； 2.邀请人
     * @param $type
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getInvitationCoupons($type){
        $in = $type == 1?[1, 3]:[2, 3];
        return Db::name('coupon_rule')->where(['coupon_type' => 7, 'is_open' => 1])->where(['grant_object'=>['IN',$in]])->select();
    }

    /**
     * 获取卡券的可叠加性
     * @param $coupon_id
     * @return mixed
     */
    public static function getCouponSuperpositionAndExpireTime($coupon_id){
         return Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->value('cr.is_superposition');
    }
    
    /**
     * 获取商家券中可使用的可叠加券的数量
     * @param $coupon_ids
     * @return int|string
     */
    public static function getNotSuperpositionCoupon($coupon_ids){
        return Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_ids],'c.expiration_time'=>['GT',time()],'c.status'=>1,'cr.is_superposition' => 2])->count('c.id');
    }

    /**
     * 获取商品券中可使用的可叠加券的数量
     * @param $coupon_ids
     * @return int|string
     */
    public static function getNotSuperpositionproductCoupon($coupon_ids){
        return Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_ids],'c.expiration_time'=>['GT',time()],'c.status'=>1,'cr.is_superposition' => 2])->count('c.id');
    }

    /**
     * 获取商家券中可使用的可叠加券的数量
     * @param $coupon_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getNotSuperpositionCoupon2($coupon_ids){
        return Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_ids]])->field('c.expiration_time,c.status,cr.is_superposition,cr.coupon_name')->select();
    }

    /**
     * 获取商品券中可使用的可叠加券的数量
     * @param $coupon_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getNotSuperpositionproductCoupon2($coupon_ids){
        return Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>['IN',$coupon_ids]])->field('cr.is_superposition,c.status,c.expiration_time,cr.coupon_name')->select();
    }
    
    /**
     * 获取优惠券优惠价和满足价
     * @param $coupon_id
     * @return mixed
     */
    public static function getCouponPrice($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('cr.coupon_money,cr.satisfy_money')->find();
        if(!$info)$info = ['coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }

    public static function getCouponPrice2($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('cr.coupon_money,cr.satisfy_money')->find();
        if(!$info)$info = ['coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }
    
    public static function getCouponPrice4($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('cr.product_ids,cr.is_solo,c.coupon_money,c.satisfy_money')->find();
        if(!$info)$info = ['product_ids'=>'','is_solo'=>0,'coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }
    
    /**
     * 获取分享设置信息
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getShareInfo(){
        return Db::name('share_set')->where(['id'=>1])->find();
    }

    /**
     * 获取指定排序的产品信息
     * @param $ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityProductInfo($ids){
        $id_arr = explode(',',trim($ids,','));
        return DB::name('product')->alias('p')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->where(['p.id'=>['IN',$id_arr],'p.status'=>1,'p.sh_status'=>1])
            ->field('p.id as product_id,ps.product_specs,ps.price,ps.id as specs_id')
            ->order("field(p.id,{$ids}) asc")
            ->group('p.id')
            ->select();
    }

    /**
     *获取卡券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getActivityCoupon($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id,'is_open'=>1])->field('satisfy_money,coupon_money,id,type,is_solo')->find();
    }

    /**
     * 获取卡券对应商品列表
     * @param $product_ids
     * @param $page
     * @param $size
     */
    public static function getCouponProducts($product_ids,$page,$size){
        $list = Db::name('product')->alias('p')
            ->join('product_specs ps','ps.product_id = p.id','RIGHT')
            ->where(['p.id'=>['IN',$product_ids],'p.status'=>1,'p.sh_status'=>1,'ps.stock'=>['GT',0]])
            ->group('p.id')
            ->field('p.id as product_id,ps.product_specs,ps.product_name,ps.id as specs_id,ps.price,ps.cover,ps.huaxian_price')
            ->limit(($page-1)*$size,$size)
            ->select();
        return $list;
    }

    /**
     * 计算卡券对应商品总数
     * @param $product_ids
     * @return int|string
     */
    public static function countCouponProducts($product_ids){
        return Db::name('product')->alias('p')
            ->join('product_specs ps','ps.product_id = p.id','RIGHT')
            ->where(['p.id'=>['IN',$product_ids],'p.status'=>1,'p.sh_status'=>1,'ps.stock'=>['GT',0]])
            ->group('p.id')
            ->count('p.id');
    }

    /**
     * 获取卡券使用规则
     * @param $rule_models
     * @return array
     */
    public static function getCouponRules($rule_models){
        return Db::name('coupon_use_rule')->where(['id'=>['IN',$rule_models]])->column('title');
    }

    /**
     * 获取店铺活动商品列表
     * @param $ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityStoreProducts($ids){
        $id_arr = explode(',',trim($ids,','));
        return DB::name('product')->alias('p')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->where(['p.id'=>['IN',$id_arr],'p.status'=>1,'p.sh_status'=>1])
            ->field('p.id as product_id,ps.product_specs,ps.price,ps.id as specs_id,ps.cover,ps.product_name,ps.huaxian_price')
            ->order("field(p.id,{$ids}) asc")
            ->group('p.id')
            ->select();
    }

    /**
     * 通过coupon_id获取卡券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getCouponRuleInfoByCouponId($coupon_id){
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where(['c.id'=>$coupon_id])
            ->field('cr.platform_bear, cr.is_superposition, cr.coupon_money')
            ->find();
    }

    /**
     * 获取在活动中且需要改变价格的商品Id
     * @return array
     */
    public static function getActivityPros(){
        #获取在活动中的活动
        $activity_ids = self::getChangePriceActivityIds();

        #获取商品信息
        $product_ids = self::getActivityProIds($activity_ids);

        return $product_ids;
    }

    /**
     * 获取活动中会改变商品价格的活动id
     * @return array
     */
    public static function getChangePriceActivityIds(){
        $list = Db::name('activity')
            ->where(['status'=>2,'activity_type'=>['IN',[2,4]],'start_time'=>['ELT',time()],'end_time'=>['GT',time()]])
            ->field('id,is_limit_time')
            ->select();
        $now = date('H:i:s');
        $week = date('w');

        $limit_activity_ids = Db::name('activity_limit_time')->where(['week_day'=>$week,'start_time'=>['ELT',$now],'end_time'=>['GT',$now]])->group('activity_id')->column('activity_id');

        foreach($list as $k => $v){
            if($v['is_limit_time'] && !in_array($v['id'],$limit_activity_ids))unset($list[$k]);
        }
        $ids = array_column($list,'id');
        return $ids;
    }

    /**
     * 获取活动商品信息通过活动id
     * @param $activity_ids
     * @return array
     */
    public static function getActivityProIds($activity_ids){
        return Db::name('activity_product')->where(['activity_id'=>['IN',$activity_ids]])->column('product_id');
    }

    /**
     * 获取商品活动信息
     * @param $product_id
     * @return array
     */
    public static function productActivityInfo($product_id){

        ##获取有效的活动列表
        $activity = self::getProAcWaitOrOnline($product_id);
        $activity_data = ['status' => 3,'activity_id'=>0,'activity_type'=>0,'is_miao_sha'=>0,'activity_pro_type'=>0,'start_time'=>0,'end_time'=>0,'cur_time'=>time()];//默认无活动
        if(!$activity)return $activity_data;

        $activity_info = self::getActivityInfo($activity['id']);

        $activity_data = self::autoAcStartTime($activity_info);

        $activity_info2 = self::getAcRuleInfo($activity['id']);

        $rule = [];
        $type = "";
        $is_miao_sha = 0;
        switch((int)$activity_info['activity_type']){
            case 1 :  ##无优惠
                $type = $activity_info2['title'];
                break;
            case 2:  ##抵扣
                $is_miao_sha = 1;
                $type = "直降";
                $rule[] = "全场商品直降{$activity_info2['deduction_money']}元";
                break;
            case 3:  ##满减
                $type = "满减";
                $rule_list = Logic::getAcEnoughDiscountRule($activity['id']);
                foreach($rule_list as $v){
                    $rule[] = "全场商品满{$v['satisfy_money']}减{$v['discount_money']}";
                }
                break;
            case 4:  ##打折
                $is_miao_sha = 1;
                $type = "折扣";
                $discount = $activity_info2['discount'] * 100;
                $discount = str_replace("0","",(string)$discount);
                $rule[] = "全场商品{$discount}折，单品最高可减{$activity_info2['discount_max']}";
                break;
            case 5:  ##返现
                $type = "返现";
                $rule[] = "全场商品下单最高可返{$activity_info['return_max']}元现金";
                break;
            case 6:  ##满返还优惠券
                $type = "返优惠券";
                $rule_list = Logic::getAcEnoughRtnRuleDetail($activity['id']);
                foreach($rule_list as $v){
                    $rule[] = "全场商品满{$v['satisfy_money']}立返{$v['coupon_money']}元优惠券";
                }
                break;
        }

        $activity_data['type'] = $type;
        $activity_data['rule'] = $rule;
        $activity_data['is_miao_sha'] = $is_miao_sha;
        $activity_data['activity_type'] = $activity_info['activity_type'];
        $activity_data['activity_pro_type'] = $activity_info['activity_pro_type'];
        $activity_data['activity_id'] = $activity['id'];

        return $activity_data;

    }

    /**
     * 活动商品跳转的类型
     * @param $activity_id
     * @param $activity_pro_type
     * @return int
     */
    public static function getRecomAcInfoByAcId($activity_id, $activity_pro_type){
        $info = Db::name('recommend_banner')->where(['activity_id'=>$activity_id,'status'=>1])->field('type')->find();
        if($info){
            $type = $info['type'] == 1?3:($info['type']==4?1:2);
        }else{##没有绑定
            $type = $activity_pro_type==4?1:2;
        }
        return $type;
    }

    /**
     * 获取
     * @param $activity_id
     * @return bool|mixed|string
     */
    public static function getAcShowTitle($activity_id){
        $title = Db::name('recommend_banner')->where(['activity_id'=>$activity_id,'status'=>1])->value('title');
        if(!$title){ ##未绑定
            $title = self::getAcTitle($activity_id);
            $title = mb_substr($title,0,7);
        }
        return $title;
    }

    /**
     * 获取活动 title
     * @param $activity_id
     * @return mixed
     */
    public static function getAcTitle($activity_id){
        return Db::name('activity')->where(['id'=>$activity_id])->value('title');
    }

    /**
     * 获取当前商品在活动中的数据
     * @param $product_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProAcWaitOrOnline($product_id){
        return Db::name('activity')->alias('a')
            ->join('activity_product ap','ap.activity_id = a.id','RIGHT')
            ->where(['ap.product_id'=>$product_id,'a.status'=>2,'a.end_time'=>['GT', time()]])
            ->field('a.id,ap.product_id')
            ->find();
    }

    /**
     * 获取待开始和进行中的活动商品信息
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcWaitOrOnlineList(){
        return Db::name('activity')->alias('a')
            ->join('activity_product ap','ap.activity_id = a.id','LEFT')
            ->where(['a.status'=>2,'a.end_time'=>['GT', time()]])
            ->field('a.id,ap.product_id')
            ->select();
    }

    /**
     * 商品详情活动判断
     * @param $product_id
     */
    public static function productChangePriceActivityInfo($product_id)
    {
        $activity_info = Db::name('activity')->where(['status' => 2, 'activity_type' => ['IN', [2, 4]], 'start_time' => ['ELT', time()], 'end_time' => ['GT', time()]])->field('id,is_limit_time,title,end_time')->select();

        $now = date('H:i:s');
        $week = date('w');

        $activity_data = ['status' => 0];//默认无活动
        foreach($activity_info as $v){
            $pro_ids = self::getAcProIdsByAcId($v['id']);
            if(!in_array($product_id,$pro_ids))continue;
            if($v['is_limit_time']){  //限时上线
                ##判断是否在活动中
                $check = Db::name('activity_limit_time')->where(['activity_id'=>$v['id'],'week_day'=>$week,'start_time'=>['ELT',$now],'end_time'=>['GT',$now]])->field('end_time')->find();
                if($check){
                    $end_time = date('Y-m-d') . ' ' . $check['end_time'];
                    $end_time = strtotime($end_time);
                    $activity_data['activity_name'] = $v['title'];
                    $activity_data['end_time'] = $end_time;
                    $activity_data['status'] = 1;  //活动中
                }else{ //查询最近一次的活动时间
                    ##查找week_day比今天大的最近的一天
                    $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$v['id'],'week_day'=>['GT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                    if($limit_time1){
                        ##判断活动是否截止
                        $cha_day = $limit_time1['week_day'] - $week;
                    }else{
                        $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$v['id'],'week_day'=>['LT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                        if($limit_time1){
                            $cha_day = 6 - $week + $limit_time1['week_day'] + 1;
                        }else{
                            $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$v['id'],'week_day'=>$week])->field('week_day,start_time,end_time')->find();
                            $cha_day = 7;
                        }
                    }
                    $time1 = date('Y-m-d',time() + $cha_day * 24 * 60 * 60) . " " . $limit_time1['start_time'];
                    $time1 = strtotime($time1);
                    if($v['end_time'] > $time1){  //下一次限时时间已经活动尚未结束
                        $activity_data['activity_name'] = $v['title'];
                        $activity_data['start_time'] = $time1;
                        $activity_data['status'] = 2;  //下一轮限时等待中
                    }
                }
            }else{
                $activity_data['activity_name'] = $v['title'];
                $activity_data['end_time'] = $v['end_time'];
                $activity_data['status'] = 1;  //活动中
            }
            $activity_data['is_limit_time'] = $v['is_limit_time'];
            break;
        }

        return $activity_data;
    }

    /**
     * 获得某个活动下面的商品ids
     * @param $activity_id
     * @return array
     */
    public static function getAcProIdsByAcId($activity_id){
        return Db::name('activity_product')->where(['activity_id'=>$activity_id])->column('product_id');
    }

    /**
     * 更新店铺已完成订单数
     * @param $store_id
     * @return int
     */
    public static function updateStoreDealNum($store_id){
        ##获取订单数
        $deal_num = self::countStoreDealNum($store_id);

        return Db::name('store')->where(['id'=>$store_id])->setField('deal_num',$deal_num);
    }

    /**
     * 获取店铺已完成订单数
     * @param $store_id
     * @return int|string
     */
    public static function countStoreDealNum($store_id){
        return Db::name('product_order')->where(['store_id'=>$store_id,'order_status'=>6])->count('id');
    }

    /**
     * 更新店铺最近一单成交时间
     * @param $store_id
     * @return int
     */
    public static function updateStoreLatelyDealTime($store_id){
        return Db::name('store')->where(['id'=>$store_id])->setField('lately_deal_time',time());
    }

    /**
     * 判断商品是否在活动中(有效的活动【已开始并且活动类型不为1】)
     * @param $product_ids
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public static function checkProductInActivity($product_ids){

        if(!is_array($product_ids))$product_ids = explode(',',$product_ids);

        ##获取有效活动ids
        $activity_ids = self::getValidActivityIds();
        if(empty($activity_ids))return false;

        ##检查商品是否在活动
        $check = self::countProductInActivity($activity_ids, $product_ids);
        return $check;

    }

    /**
     * 获取在活动中的有效的活动id(排除活动类型为1的)
     * @return array
     */
    public static function getValidActivityIds(){
        ##所有已开始的活动
        $list = Db::name('activity')->where(['status'=>2,'start_time'=>['ELT',time()],'end_time'=>['GT',time()],'activity_type'=>['NEQ',1]])->field('id,is_limit_time,activity_type')->select();

        foreach($list as $k => $v){
            if($v['is_limit_time'] && ($v['activity_type'] == 2 || $v['activity_type'] == 4)){
                if(!self::checkAcLimitTime($v['id']))unset($list[$k]);
            }
        }

        $activity_ids = array_column($list,'id');

        return $activity_ids;
    }

    /**
     * 判断活动是否此刻在限时活动中
     * @param $activity_id
     * @return int|string
     */
    public static function checkAcLimitTime($activity_id){
        $now = date('H:i:s');
        $week = date('w');
        return Db::name('activity_limit_time')->where(['activity_id'=>$activity_id, 'week_day'=>$week,'start_time'=>['ELT',$now],'end_time'=>['GT',$now]])->count('id');
    }

    /**
     * 统计商品中在活动中的数量
     * @param $activity_ids
     * @param $product_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function countProductInActivity($activity_ids, $product_ids){
        return Db::name('activity_product')->alias('ap')
            ->join('activity a','ap.activity_id = a.id','LEFT')
            ->where(['ap.activity_id'=>['IN',$activity_ids],'ap.product_id'=>['IN',$product_ids]])
            ->field('ap.product_id,a.id,a.activity_type')
            ->select();
    }

    /**
     * 判断当前商品中在不同的满减活动的数组
     * @param $product_ids
     * @return array
     */
    public static function proInAcEnoughDiscount($product_ids){
        ##获取所有在进行中的满减活动
        $activity_ids = self::getEnoughDiscountAcIds();

        ##判断是否在满减中
        $ac = [];
        foreach($product_ids as $v){
            $data = self::getProAcInfo($v, $activity_ids);
            if($data)$ac[$data['activity_id']][] = $v;
        }
        return $ac;
    }

    /**
     * 获取在活动中的满减活动ids
     * @return array
     */
    public static function getEnoughDiscountAcIds(){
        return Db::name('activity')->where(['status'=>2, 'activity_type'=>3, 'start_time'=>['ELT',time()],'end_time'=>['GT',time()]])->column('id');
    }

    /**
     * 获取活动商品信息
     * @param $product_id
     * @param $activity_ids
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getProAcInfo($product_id, $activity_ids){
        return Db::name('activity_product')->where(['product_id'=>$product_id,'activity_id'=>['IN',$activity_ids]])->field('activity_id')->find();
    }

    /**
     * 获取商品售价
     * @param $specs_id
     * @return mixed
     */
    public static function getProPrice($specs_id){
        return Db::name('product_specs')->where(['id'=>$specs_id])->value('price');
    }

    /**
     * 获取满减活动满减规则(从大到小)
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcEnoughDiscountRule($activity_id){
        return Db::name('activity_enough_rule')->where(['activity_id'=>$activity_id])->field('satisfy_money,discount_money')->order('satisfy_money','desc')->select();
    }

    /**
     * 获取满返优惠券活动规则(从大到小)
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcEnoughRtnRule($activity_id){
        return Db::name('activity_return_coupon_rule')->where(['activity_id'=>$activity_id])->field('satisfy_money,coupon_id')->order('satisfy_money','desc')->select();
    }

    /**
     * 获取商品满减金额和满返优惠券ids
     * @param $pro_data
     * @param $checkActivity
     * @return array
     */
    public static function getAcEnoughMoneyAndCouponIds($pro_data, $checkActivity){

        $enough_discount_money = 0;
        $enough_rtn_coupon_ids = [];
        $ac_discount_data = $ac_rtn_data = [];
        $activity_pro = [];
        $discount_info = [];
        $enough_discount_info = [];
        $enough_rtn_info = [];

//        foreach($checkActivity as $v){ ##(id,activity_type,product_id)
//            if($v['activity_type'] == 3){
//                $ac_discount_data[$v['id']]['money'] += $pro_data[$v['product_id']]['price'] * $pro_data[$v['product_id']]['number'];
//                $ac_discount_data[$v['id']]['product_id'][] = $v['product_id'];
//            }elseif($v['activity_type'] == 6){
//                $ac_rtn_data[$v['id']]['money'] += $pro_data[$v['product_id']]['price'] * $pro_data[$v['product_id']]['number'];
//                $ac_rtn_data[$v['id']]['product_id'][] = $v['product_id'];
//            }
//        }

        $activity_pro_ids = array_column($checkActivity,'product_id');
        $activity_data = array_combine($activity_pro_ids, $checkActivity);
        foreach($pro_data as $k => $v){
            if(in_array($v['product_id'], $activity_pro_ids)){  ##商品在活动中
                $kk = $activity_data[$v['product_id']]['id'];
                if($activity_data[$v['product_id']]['activity_type'] == 3){  ##满减
                    $ac_discount_data[$kk]['money'] += $v['price'] * $v['number'];
                    $ac_discount_data[$kk]['product_id'][] = $v['product_id'];
                    $ac_discount_data[$kk]['specs_id'][] = $k;
                }elseif($activity_data[$v['product_id']]['activity_type'] == 6){  ##满返优惠券
                    $ac_rtn_data[$kk]['money'] += $v['price'] * $v['number'];
                    $ac_rtn_data[$kk]['product_id'][] = $v['product_id'];
                    $ac_rtn_data[$kk]['specs_id'][] = $k;
                }
            }
        }

        if(!empty($ac_discount_data)){
            foreach($ac_discount_data as $k => $v){
                ##获取满减规则
                $enough_rule = Logic::getAcEnoughDiscountRule($k);
                foreach($enough_rule as $vv){
                    if($vv['satisfy_money'] <= $v['money']){
                        $rest_discount_money = $vv['discount_money'];
                        $enough_discount_money += $vv['discount_money'];
                        $len = 1;
                        foreach($v['specs_id'] as $val){
                            $enough_discount_info[$val]['satisfy_money'] = $vv['satisfy_money'];
                            $enough_discount_info[$val]['discount_money'] = $vv['discount_money'];
                            $activity_pro[$val] = $k;
                            $price = $pro_data[$val]['price'] * $pro_data[$val]['number'];
                            ##计算每个规则的优惠金额
                            if($len >= count($v['specs_id'])){
                                $discount_info[$val]['discount_money'] = $rest_discount_money;
                            }else{
                                $discount_info[$val]['discount_money'] = round(($price / $v['money']) * $vv['discount_money'],2);
                                $rest_discount_money -= $discount_info[$val]['discount_money'];
                            }
                            $discount_info[$val]['return_money'] = 0;
                            $discount_info[$val]['return_coupon_id'] = 0;
                            $discount_info[$val]['activity_type'] = 3;
                            $discount_info[$val]['activity_id'] = $k;
                            $discount_info[$val]['relpay_money'] = $price - $discount_info[$val]['discount_money'];
                        }
                        break;
                    }
                }
            }
        }

        if(!empty($ac_rtn_data)){
            foreach($ac_rtn_data as $k => $v){
                ##获取满返规则
                $enough_rule = Logic::getAcEnoughRtnRule($k);
                foreach($enough_rule as $vv){
                    if($vv['satisfy_money'] <= $v['money']){
                        $enough_rtn_coupon_ids[] = $vv['coupon_id'];
                        foreach($v['specs_id'] as $val){
                            $enough_rtn_info[$val]['satisfy_money'] = $vv['satisfy_money'];
                            $enough_rtn_info[$val]['coupon_id'] = $vv['coupon_id'];
                            $activity_pro[$val] = $k;
                            $discount_info[$val]['discount_money'] = 0;
                            $discount_info[$val]['return_money'] = 0;
                            $discount_info[$val]['return_coupon_id'] = $vv['coupon_id'];
                            $discount_info[$val]['activity_type'] = 6;
                            $discount_info[$val]['activity_id'] = $k;
                            $discount_info[$val]['relpay_money'] = $pro_data[$val]['price'] * $pro_data[$val]['number'];
                        }
                        break;
                    }
                }
            }
        }

        return compact('enough_discount_money','enough_rtn_coupon_ids','activity_pro','discount_info','enough_discount_info','enough_rtn_info');

    }

    /**
     * 获取商品满减数据
     * @param $pro_data
     * @param $checkActivity
     * @return array
     */
    public static function getAcEnoughDiscountProPrice($pro_data, $checkActivity){

        $ac_discount_data = [];
        $activity_pro_ids = array_column($checkActivity,'id');
        $activity_data = array_combine($activity_pro_ids, $checkActivity);
        foreach($pro_data as $k => $v){
            if(in_array($v['product_id'], $activity_pro_ids)){  ##商品在活动中
                $kk = $activity_data[$v['product_id']]['id'];
                if($activity_data['activity_type'] == 3){  ##满减
                    $ac_discount_data[$kk]['money'] += $v['price'] * $v['number'];
                    $ac_discount_data[$kk]['product_id'][] = $v['product_id'];
                    $ac_discount_data[$kk]['specs_id'][] = $k;
                }
            }
        }

        foreach($ac_discount_data as $k => &$v){
            ##获取满减规则
            $enough_rule = Logic::getAcEnoughDiscountRule($k);
            foreach($enough_rule as $vv){
                if($vv['satisfy_money'] <= $v['money']){
                    $v['discount_money'] = $vv['discount_money'];
                    break;
                }
            }
        }

        $enough_discount_data = [];
        foreach($ac_discount_data as $v){
            if($v['discount_money'] > 0){  //
                $rest_money = $v['discount_money'];
                $len = 1;
                foreach($v['specs_id'] as $vv){
                    $pay_per_money = $pro_data[$vv]['price'] * $pro_data[$vv]['number'];
                    if($len == count($v['specs_id'])){  //最后一个
                        $enough_discount_data[$vv]['discount_money'] = $rest_money;
                        $enough_discount_data[$vv]['price'] = $pay_per_money  - $rest_money;
                    }else{
                        $discount_money = round(($pay_per_money/$v['money']) * $v['discount_money'],2);
                        $enough_discount_data[$vv]['discount_money'] = $discount_money;
                        $enough_discount_data[$vv]['price'] = $pay_per_money  - $discount_money;
                        $rest_money -= $discount_money;
                    }
                }
            }
        }

        return $enough_discount_data;

    }

    /**
     * 获取新人专区banner和coupon
     * @param $activity_id
     * @param $user_id
     * @return array
     */
    public static function getAcNewPersonInfo($activity_id, $user_id){
        ##获取活动信息
        $activity_info = self::getActivityInfo($activity_id);
        if(!$activity_info)return returnArr(0,'活动信息不存在');
//        if($activity_info['status'] != 2)return returnArr(0,'活动未上线或者已下线');

        ##活动状态和结束时间
        $activity = self::autoAcStartTime($activity_info);
//        if($activity['status'] == 3)return returnArr(0,'活动已结束');

        ##活动banner
        $banner_ids = explode(',', $activity_info['banner_ids']);
        $banners = self::getActivityBanners($banner_ids);

        ##活动推荐优惠券
        $recom_coupon_ids = explode(',',trimFunc($activity_info['recom_coupon_ids']));
        $recom_coupons = self::getActivityRecomCoupons($recom_coupon_ids);
        foreach($recom_coupons as $k => &$v){

            ##如果优惠券已领取完,并且用户没有领取优惠券就不显示
            $count = UserLogic::countUserCouponByCouponId($v['id'], $user_id);
            if($v['surplus_number'] <= 0 && !$count){
                unset($recom_coupons[$k]);
                continue;
            }

            if($v['type'] == 2)$v['is_solo'] = $v['store_id'];

            switch((int)$v['type']){
                case 1:
                    $v['desc'] = "全平台可用";
                    break;
                case 2:
                    $v['desc'] = self::getStoreNameByStoreId($v['store_id']) . "可用";
                    break;
                case 3:
                    $v['desc'] = "";
                    break;
            }

            if(!$user_id){
                $v['is_get'] = 0;
                continue;
            }
            ##判断是否领用

            $v['is_get'] = $count >= $v['zengsong_number']? 1:0;
        }

        $activity_type = $activity_info['activity_pro_type'];

        return returnArr(1,'',compact('banners','recom_coupons','activity','activity_type'));
    }

    /**
     * 获取店铺名
     * @param $store_id
     * @return mixed
     */
    public static function getStoreNameByStoreId($store_id){
        return Db::name('store')->where(['id'=>$store_id])->value('store_name');
    }

    /**
     * 获取活动推荐优惠券列表信息
     * @param $recom_coupon_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityRecomCoupons($recom_coupon_ids){
        return Db::name('coupon_rule')->where(['id'=>['IN', $recom_coupon_ids],'is_open'=>1,'client_type'=>['IN',[0,2]]])->field('id,coupon_name,satisfy_money,coupon_money,type,zengsong_number,store_id,surplus_number,is_solo,product_ids,store_name')->select();
    }

    /**
     * 获取活动信息
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getActivityInfo($activity_id){
        return Db::name('activity')->where(['id'=>$activity_id])->field('id,banner_ids,recom_coupon_ids,activity_type,activity_pro_type,start_time,end_time,is_limit_time,status,return_max')->find();
    }

    /**
     * status => 1.未开始；2.进行中；3.已结束
     * 获取活动状态[开始和结束时间]
     * @param $activity_info
     * @return array
     */
    public static function autoAcStartTime($activity_info){
        ##判断活动状态
        if($activity_info['start_time'] > time() && $activity_info['status'] != 3){  ##活动尚未开始
            $status = 1; //活动未开始
            if($activity_info['activity_type'] != 2 && $activity_info['activity_type'] != 4){  ##不可限时的活动
                $start_time = $activity_info['start_time'];
                $end_time = $activity_info['end_time'];
            }else{
                if($activity_info['is_limit_time']){  ##限时活动
                    ##获取最近的一次限时上线的时间
                    $time_start = $activity_info['start_time'];
                    $week = date('w', $time_start);
                    $time = date("H:i:s", $time_start);
                    $time_end = $activity_info['end_time'];
                    $week2 = date('w',$time_end);
                    if($week == $week2 && ($time_end - $time_start < 24 * 60 * 60)){ //截止日期在今天
                        $time2 = date("H:i:s",$time_end);
                    }else{
                        $time2 = "23:59:59";
                    }

                    ###查找有无开始日的限时
                    $limit_time_today = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>$week])->field('week_day,start_time,end_time')->find();
                    if($limit_time_today){
                        if($limit_time_today['start_time'] >= $time2 || $time >= $limit_time_today['end_time']){
                            $limit_time_today = false;
                        }
                    }
                    if($limit_time_today){
                        $start_time = date('Y-m-d',$time_start) . " " . $limit_time_today['start_time'];
                        $start_time = strtotime($start_time);
                        $end_time = date('Y-m-d',$time_start) . " " . $limit_time_today['end_time'];
                        $end_time = strtotime($end_time);
                    }else{ ##没有开始当天的限时(查找以后最近的一天)
                        $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>['GT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                        if($limit_time1){
                            ##和活动开始日的相隔天数
                            $cha_day = $limit_time1['week_day'] - $week;
                        }else{
                            $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>['LT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                            if($limit_time1){
                                $cha_day = 6 - $week + $limit_time1['week_day'] + 1;
                            }else{
                                $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>$week])->field('week_day,start_time,end_time')->find();
                                $cha_day = 7;
                            }
                        }
                        $time1 = date('Y-m-d',$time_start + $cha_day * 24 * 60 * 60) . " " . $limit_time1['start_time'];
                        $time2 = date('Y-m-d',$time_start + $cha_day * 24 * 60 * 60) . " " . $limit_time1['end_time'];
                        $time1 = strtotime($time1);
                        $time2 = strtotime($time2);
                        if($activity_info['end_time'] > $time1){  //下一次限时时间已经活动尚未结束
                            $start_time = $time1;
                            if($time2 >= $activity_info['end_time']){
                                $end_time = $activity_info['end_time'];
                            }else{
                                $end_time = $time2;
                            }
                        }else{ //活动已结束
                            $start_time = $end_time = 0;
                            $status = 3;
                        }
                    }
                }else{
                    $start_time = $activity_info['start_time'];
                    $end_time = $activity_info['end_time'];
                }
            }
        }else if($activity_info['start_time'] <= time() && $activity_info['end_time'] > time() && $activity_info['status'] != 3){  //活动进行中
            $status = 2;
            if($activity_info['activity_type'] != 2 && $activity_info['activity_type'] != 4){  ##不可限时的活动
                $start_time = $activity_info['start_time'];
                $end_time = $activity_info['end_time'];
            }else{
                if($activity_info['is_limit_time']){  ##限时活动
                    ##获取最近的一次限时上线的时间
                    $week = date('w', time());
                    $time = date("H:i:s", time());

                    ###查看今天有无限时(已开始)
                    $limit_time_today = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>$week,'start_time'=>['ELT',$time],'end_time'=>['GT',$time]])->field('week_day,start_time,end_time')->find();
                    if($limit_time_today){
                        $start_time = date('Y-m-d') . " " . $limit_time_today['start_time'];
                        $start_time = strtotime($start_time);
                        $end_time = date('Y-m-d'). " " . $limit_time_today['end_time'];
                        $end_time = strtotime($end_time);
                        if($end_time > $activity_info['end_time'])$end_time = $activity_info['end_time'];
                    }else{
                        ###查看今天未开始的限时
                        $limit_time_today2 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>$week,'start_time'=>['GT',$time]])->field('week_day,start_time,end_time')->find();
                        if($limit_time_today2){
                            $start_time = date("Y-m-d") . " " . $limit_time_today2['start_time'];
                            $start_time = strtotime($start_time);
                            $end_time = date("Y-m-d") . " " . $limit_time_today2['end_time'];
                            $end_time = strtotime($end_time);
                            $status = 1;
                        }else{
                            ##查找最近一天的限时时间
                            $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>['GT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                            if($limit_time1){
                                ##和活动开始日的相隔天数
                                $cha_day = $limit_time1['week_day'] - $week;
                            }else{
                                $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>['LT',$week]])->order('week_day','asc')->field('week_day,start_time,end_time')->find();
                                if($limit_time1){
                                    $cha_day = 6 - $week + $limit_time1['week_day'] + 1;
                                }else{
                                    $limit_time1 = Db::name('activity_limit_time')->where(['activity_id'=>$activity_info['id'],'week_day'=>$week])->field('week_day,start_time,end_time')->find();
                                    $cha_day = 7;
                                }
                            }
                            $time1 = date('Y-m-d',time() + $cha_day * 24 * 60 * 60) . " " . $limit_time1['start_time'];
                            $time2 = date('Y-m-d',time() + $cha_day * 24 * 60 * 60) . " " . $limit_time1['end_time'];
                            $time1 = strtotime($time1);
                            $time2 = strtotime($time2);
                            if($time1 >= $activity_info['end_time']){  //活动已经结束
                                $start_time = $end_time = 0;
                                $status = 3;
                            }else{
                                $start_time = $time1;
                                if($time2 > $activity_info['end_time']){
                                    $end_time = $activity_info['end_time'];
                                }else{
                                    $end_time = $time2;
                                }
                            }
                        }
                    }

                }else{  ## 非限时
                    $start_time = $activity_info['start_time'];
                    $end_time = $activity_info['end_time'];
                }
            }
        }else{  ##活动已结束
            $status = 3;
            $start_time = $end_time = 0;
        }

        $cur_time = time();

        return compact('status','start_time','end_time','cur_time');
    }

    /**
     * 获取活动banner
     * @param $banner_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityBanners($banner_ids){
        return Db::name('activity_banner')->where(['id'=>['IN', $banner_ids]])->field('id,img,type,link_id')->order('sort','asc')->select();
    }

    /**
     * 获取活动商品列表信息
     * @param $activity_id
     * @param $page
     * @param $size)
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityProductList($activity_id, $page, $size){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','RIGHT')
            ->join('product_specs ps','ps.product_id = ap.product_id','LEFT')
            ->where(['ap.activity_id'=>$activity_id,'p.status'=>1,'p.sh_status'=>1])
            ->field('ap.product_id,ps.product_name,ps.cover,ps.price,ps.price_activity_temp,ps.huaxian_price,ps.id as specs_id')
            ->group('p.id')
            ->limit(($page-1)*$size, $size)
            ->select();
    }

    /**
     * 统计活动商品数
     * @param $activity_id
     * @return int|string
     */
    public static function countActivityProduct($activity_id){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','RIGHT')
            ->where(['ap.activity_id'=>$activity_id,'p.status'=>1,'p.sh_status'=>1])
            ->group('p.id')
            ->count('ap.id');
    }

    /**
     * 获取摩登好店信息
     * @param $activity_id
     * @return array
     */
    public static function getAcModernInfo($activity_id){
        ##获取活动信息
        $activity_info = self::getActivityInfo($activity_id);
        if(!$activity_info)return returnArr(0,'活动信息不存在');
        if($activity_info['activity_pro_type'] != 4)return returnArr(0,'活动商品格式错误');
//        if($activity_info['status'] != 2)return returnArr(0,'活动未上线或者已下线');

        ##活动状态和结束时间
        $activity = self::autoAcStartTime($activity_info);

        ##活动banner
        $banner_ids = explode(',', $activity_info['banner_ids']);
        $banners = self::getActivityBanners($banner_ids);

        ##获取活动规则
        $rule = Logic::autoAcRule($activity_id);

        $activity_type = $activity_info['activity_pro_type'];

        return returnArr(1,'',compact('banners','activity','rule','activity_type'));

    }

    /**
     * 获取摩登好店活动数据
     * @param $activity_id
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityStoreTypeList($activity_id, $page, $size){
        ##获取活动店铺列表
        $list = self::getActivityStoreList($activity_id, $page, $size);

        ##获取每个店铺的活动商品
        foreach($list as &$v){
            $v['product'] = self::getAcStoreProLists($v['store_id'], $activity_id);
        }

        return $list;
    }

    /**
     * 获取活动店铺列表
     * @param $activity_id
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityStoreList($activity_id, $page, $size){
        return Db::name('activity_product')->alias('ap')
            ->join('store s','s.id = ap.store_id','LEFT')
            ->where(['ap.activity_id'=>$activity_id,'s.store_status'=>1])
            ->group('ap.store_id')
            ->field('ap.store_id,s.store_name,s.cover')
            ->limit(($page-1)*$size, $size)
            ->select();
    }

    /**
     * 统计活动店铺数量
     * @param $activity_id
     * @return int|string
     */
    public static function countActivityStore($activity_id){
        return Db::name('activity_product')->alias('ap')
            ->join('store s','s.id = ap.store_id','LEFT')
            ->where(['ap.activity_id'=>$activity_id,'s.store_status'=>1])
            ->group('ap.store_id')
            ->count('ap.store_id');
    }

    /**
     * 获取活动店铺商品限制(2)
     * @param $store_id
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcStoreProLists($store_id, $activity_id){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','RIGHT')
            ->join('product_specs ps','ps.product_id = ap.product_id','LEFT')
            ->where(['ap.activity_id'=>$activity_id, 'ap.store_id'=>$store_id, 'p.status'=>1, 'p.sh_status'=>1])
            ->field('ap.product_id,ps.product_name,ps.cover,ps.price,ps.price_activity_temp,ps.huaxian_price,ps.id as specs_id')
            ->group('p.id')
            ->limit(2)
            ->select();
    }

    /**
     * 获取大牌集合馆自定义类别列表
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    public static function getAcGreatBrandInfo($activity_id){

        ##获取活动信息
        $activity_info = self::getActivityInfo($activity_id);
        if(!$activity_info)return returnArr(0,'活动信息不存在');
        if($activity_info['activity_pro_type'] != 5)return returnArr(0,'活动商品格式错误');
//        if($activity_info['status'] != 2)return returnArr(0,'活动未上线或者已下线');

        ##活动状态和结束时间
        $activity = self::autoAcStartTime($activity_info);

        ##获取活动规则
        $rule = Logic::autoAcRule($activity_id);

        ##获取活动自定义类别
        $list = self::getAcDiyTypeList($activity_id);

        $activity_type = $activity_info['activity_pro_type'];

        return returnArr(1,'',compact('list','activity','rule','activity_type'));

    }

    /**
     * 获取活动规则提示列表详情
     * @param $activity_id
     * @return array
     */
    public static function autoAcRule($activity_id){

        $activity_info = Logic::getAcRuleInfo($activity_id);

        $rule = [];
        switch((int)$activity_info['activity_type']){
            case 1:
                $rule[] = $activity_info['title'];
                break;
            case 2:  ##抵扣
                $rule[] = "全场立减{$activity_info['deduction_money']}元";
                break;
            case 3:  ##满减
                $rule_list = Logic::getAcEnoughDiscountRule($activity_id);
                foreach($rule_list as $v){
                    $rule[] = "全场满{$v['satisfy_money']}元减{$v['discount_money']}元";
                }
                break;
            case 4:  ##打折
                $discount = $activity_info['discount'] * 100;
                $discount = str_replace("0","",(string)$discount);
                $rule[] = "全场商品享受{$discount}折";
                break;
            case 5:  ##返现
                $rule[] = "全场商品单品最高可返现{$activity_info['return_max']}元";
                break;
            case 6:  ##满返还优惠券
                $rule_list = Logic::getAcEnoughRtnRuleDetail($activity_id);
                foreach($rule_list as $v){
                    $rule[] = "全场满{$v['satisfy_money']}元立返{$v['coupon_money']}元优惠券";
                }
                break;
        }

        return $rule;

    }

    /**
     * 获取活动规则相关信息
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getAcRuleInfo($activity_id){
        return Db::name('activity')->where(['id'=>$activity_id])->field('title,activity_type,deduction_money,discount,discount_max,return_max')->find();
    }

    /**
     * 获取满返优惠券规则详情
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcEnoughRtnRuleDetail($activity_id){
        return Db::name('activity_return_coupon_rule')->alias('arcr')
            ->join('coupon_rule cr','arcr.coupon_id = cr.id','LEFT')
            ->where(['arcr.activity_id'=>$activity_id])
            ->field('arcr.satisfy_money,cr.coupon_money')
            ->order('arcr.satisfy_money','desc')
            ->select();
    }

    /**
     * 获取活动自定义类别列表信息
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcDiyTypeList($activity_id){
        return Db::name('activity_type')->where(['activity_id'=>$activity_id,'status'=>1])->field('id,type_name')->select();
    }

    /**
     * 统计活动自定义类别下的商品数量
     * @param $type_id
     * @return int|string
     */
    public static function countActivityTypePro($type_id){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','RIGHT')
            ->join('product_specs ps','ps.product_id = ap.product_id','LEFT')
            ->where(['ap.type_id'=>$type_id, 'p.status'=>1, 'p.sh_status'=>1])
            ->group('p.id')
            ->count('ap.id');
    }

    /**
     * 获取大牌集合地商品列表
     * @param $type_id
     * @param $page
     * @param $size
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcDiyTypeProList($type_id, $page, $size){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','RIGHT')
            ->join('product_specs ps','ps.product_id = ap.product_id','LEFT')
            ->where(['ap.type_id'=>$type_id, 'p.status'=>1, 'p.sh_status'=>1])
            ->group('p.id')
            ->field('ap.product_id,ps.product_name,ps.cover,ps.price,ps.price_activity_temp,ps.huaxian_price,ps.id as specs_id')
            ->limit(($page-1)*$size, $size)
            ->select();
    }

    /**
     * 返回商品规格价格和活动价
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getAcProInfo($id){
        return Db::name('product_specs')->where(['id'=>$id])->field('price_activity_temp,price')->find();
    }

    /**
     * 活动满返规则
     * @param $id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getAcReturnConf($id){
        return Db::name('activity')->where(['id'=>$id])->field('return_prop,return_max')->find();
    }

    /**
     * 获取商品名
     * @param $product_id
     * @return mixed
     */
    public static function getProInfo($product_id){
        return Db::name('product')->where(['id'=>$product_id])->value('product_name');
    }

    /**
     * 获取活动优惠承担方
     * @param $activity_id
     * @return mixed
     */
    public static function getAcPercent($activity_id){
        return Db::name('activity')->where(['id'=>$activity_id])->value('preferential_type');
    }

    /**
     * 获取满返优惠券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getCouponInfo($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->field('id,coupon_name,is_open,satisfy_money,coupon_money,end_time,coupon_type,store_id')->find();
    }

    /**
     * 获取优惠券承担比例
     * @param $coupon_id
     * @return mixed
     */
    public static function getCouponPlatformBear($coupon_id){
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->value('platform_bear');
    }

    /**
     * 增加商品销量
     * @param $product_id
     * @param $number
     * @return int|true
     */
    public static function updateProSales($product_id, $number){
        return Db::name('product')->where(['id'=>$product_id])->setInc('sales', $number);
    }

    /**
     * 增加商品规格销量
     * @param $specs_id
     * @param $number
     * @return int|true
     */
    public static function updateSpecsSales($specs_id, $number){
        return Db::name('product_specs')->where(['id'=>$specs_id])->setInc('sales', $number);
    }

    /**
     * 获取当前在活动中的活动id(包括限时活动)
     * @return array
     */
    public static function getOnlineAcIds(){
        ##通过状态筛选
        $list = self::getAcIdsByStatus();

        $now = date('H:i:s');
        $week = date('w');

        $limit_activity_ids = Db::name('activity_limit_time')->where(['week_day'=>$week,'start_time'=>['ELT',$now],'end_time'=>['GT',$now]])->group('activity_id')->column('activity_id');

        foreach($list as $k => $v){
            if($v['is_limit_time'] && !in_array($v['id'],$limit_activity_ids))unset($list[$k]);
        }

        $activity_ids = array_column($list,'id');

        return $activity_ids;
    }

    /**
     * 获取活动信息中在活动中的活动信息
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcIdsByStatus(){
        return Db::name('activity')->where(['status'=>2,'start_time'=>['ELT',time()],'end_time'=>['GT',time()]])->field('id,is_limit_time')->select();
    }

    /**
     * 获在活动中的店铺ids
     * @return array
     */
    public static function getOnlineAcStoreIds(){
        ##获取在线活动ids
        $ac_ids = self::getOnlineAcIds();

        ##获取活动中的店铺ids
        $store_ids = self::getAcStoreIds($ac_ids);

        return $store_ids;
    }

    /**
     * 获当前活动中的店铺ids
     * @param $ac_ids
     * @return array
     */
    public static function getAcStoreIds($ac_ids){
        return Db::name('activity_product')->alias('ap')
            ->join('product p','ap.product_id = p.id','LEFT')
            ->where([
                'ap.activity_id' => ['IN',$ac_ids]
            ])
            ->group('p.store_id')
            ->column('p.store_id');
    }

    /**
     * 获取在活动中的商品ids
     * @return array
     */
    public static function getOnlineAcProIds(){
        ##获取在线活动ids
        $ac_ids = self::getOnlineAcIds();

        ##获取活动中商品ids
        $product_ac = self::getAcProIds($ac_ids);

        $product_ids = array_column($product_ac,'product_id');

        ##获取活动规则
        $ac_pro = [];
        foreach($product_ac as $v){
            switch((int)$v['activity_type']){
                case 1:
                    $ac_pro[$v['product_id']] = "";
                    break;
                case 2:  ##抵扣
                    $ac_pro[$v['product_id']] = "直降{$v['deduction_money']}元";
                    break;
                case 3:  ##满减
                    ##获取满减规则
                    $rule = self::getAcEnoughDiscountRuleMax($v['activity_id']);
                    $ac_pro[$v['product_id']] = "满{$rule['satisfy_money']}减{$rule['discount_money']}";
                    break;
                case 4:  ##打折
                    $discount = $v['discount'] * 10;
                    $ac_pro[$v['product_id']] = $discount==10?"":"{$discount}折";
                    break;
                case 5:  ##返现
                    $ac_pro[$v['product_id']] = "返现{$v['return_max']}元";
                    break;
                case 6:  ##返优惠券
                    ##获取满返优惠券规则
                    $rule = self::getAcEnoughRtnRuleMax($v['activity_id']);
                    $ac_pro[$v['product_id']] = "返{$rule['coupon_money']}元优惠券";
                    break;
            }
        }

        return compact('product_ids','ac_pro');
    }

    /**
     * 获取当前活动的商品ids
     * @param $ac_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAcProIds($ac_ids){
//        return Db::name('activity_product')->where(['activity_id'=>['IN',$ac_ids]])->column('product_id');
        return Db::name('activity_product')->alias('ap')
            ->join('activity a','ap.activity_id = a.id','LEFT')
            ->where([
                'ap.activity_id'=>['IN',$ac_ids]
            ])
            ->field('
                ap.product_id,ap.activity_id,
                a.activity_type,a.deduction_money,a.discount,a.return_max
            ')
            ->select();
    }

    /**
     * 获取满减活动的最高优惠
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getAcEnoughDiscountRuleMax($activity_id){
        return Db::name('activity_enough_rule')->where(['activity_id'=>$activity_id])->field('satisfy_money,discount_money')->order('discount_money','desc')->find();
    }

    /**
     * 获取满返优惠券的最高优惠
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getAcEnoughRtnRuleMax($activity_id){
        return Db::name('activity_return_coupon_rule')->alias('arcr')
            ->join('coupon_rule cr','cr.id = arcr.coupon_id','LEFT')
            ->where([
                'activity_id'=>$activity_id
            ])
            ->field('
                arcr.satisfy_money,
                cr.coupon_money
            ')
            ->order('cr.coupon_money','desc')
            ->find();
    }

}