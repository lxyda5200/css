<?php


namespace app\wxapi\common;


use think\Db;
use think\Log;

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
        return Db::name('coupon_rule')->where(['id'=>$coupon_id])->field('coupon_type,coupon_name,is_open,surplus_number,store_id,type,satisfy_money,coupon_money,end_time,coupon_type,zengsong_number')->find();
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
        return Db::name('store')->where(['id'=>$store_id])->field('id,store_status,mobile')->find();
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
        $in = $type == 1?[1, 3]:[1, 2];
        return Db::name('coupon_rule')->where(['coupon_type' => 7, 'is_open' => 1])->where(['grant_object'=>['IN',$in]])->select();
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
     * 获取优惠券优惠价和满足价
     * @param $coupon_id
     * @return mixed
     */
    public static function getCouponPrice($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('cr.coupon_money,cr.satisfy_money')->find();
        if(!$info)$info = ['coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
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
    public static function getCouponPrice2($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('c.coupon_money,c.satisfy_money')->find();
        if(!$info)$info = ['coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }

    public static function getCouponPrice3($coupon_id,$product_id=0,$store_id=0){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'cr.store_id'=>$store_id,'cr.product_id'=>$product_id,'c.expiration_time'=>['GT',time()],'c.status'=>0])->field('cr.type,cr.product_id,cr.coupon_money,cr.satisfy_money')->find();
        if(!$info)$info = ['type'=>0,'product_id'=>0,'coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }
    public static function getCouponPrice4($coupon_id){
        $info = Db::name('coupon')->alias('c')->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')->where(['c.id'=>$coupon_id,'c.expiration_time'=>['GT',time()],'c.status'=>1])->field('cr.product_ids,cr.is_solo,c.coupon_money,c.satisfy_money')->find();
        if(!$info)$info = ['product_ids'=>'','is_solo'=>0,'coupon_money'=>0, 'satisfy_money'=>0];
        return $info;
    }
    /**
     * 检查潮搭视频是否存在
     * @param $media_id
     * @return int|string
     */
    public static function checkChaodaExists($media_id){
        return Db::name('chaoda_img')->where(['media_id'=>$media_id])->count('id');
    }

    /**
     * 添加潮搭视频
     * @param $data
     * @return int|string
     */
    public static function addChaodaImg($data){
        return Db::name('chaoda_img')->insert($data);
    }

    /**
     * 更新潮搭视频封面
     * @param $media_id
     * @param $data
     * @return int|string
     */
    public static function updateChaodaImg($media_id,$data){
        $res = Db::name('chaoda_img')->where(['media_id'=>$media_id])->update($data);
        if($res === false)return false;
        ##判断是否添加潮搭
        $chaoda_id = self::getChaodaImgInfoByMediaId($media_id);
        if(!$chaoda_id)return $res;
        ##判断潮搭的图片是否只有一个
        $count = self::countChaodaImg($chaoda_id);
        if($count>1)return $res;
        ##修改潮搭的封面图
        return self::updateChaodaCover($chaoda_id,$data['cover']);
    }

    /**
     * 获取潮搭信息 通过media_id
     * @param $media_id
     * @return mixed
     */
    public static function getChaodaImgInfoByMediaId($media_id){
        return Db::name('chaoda_img')->where(['media_id'=>$media_id])->value('chaoda_id');
    }

    /**
     * 统计潮搭图片数量
     * @param $chaoda_id
     * @return int|string
     */
    public static function countChaodaImg($chaoda_id){
        return Db::name('chaoda_img')->where(['chaoda_id'=>$chaoda_id])->count('id');
    }

    /**
     * 更新潮搭封面图
     * @param $chaoda_id
     * @param $cover
     * @return int
     */
    public static function updateChaodaCover($chaoda_id,$cover){
        $data = [
            'cover' => $cover,
            'cover_thumb' => $cover
        ];
        return Db::name('chaoda')->where(['id'=>$chaoda_id])->update($data);
    }

    /**
     * 修改潮搭视频状态
     * @param $media_id
     * @return int
     */
    public static function shieldChaodaVideo($media_id){
        return Db::name('chaoda_img')->where(['media_id'=>$media_id])->setField('can_use',2);
    }

    /**
     * 通过media_id获取chaoda_img_id
     * @param $media_id
     * @return mixed
     */
    public static function getChaodaImgInfosByMediaId($media_id){
        return Db::name('chaoda_img')->where(['media_id'=>$media_id])->field('id,cover')->find();
    }

    /**
     * 填充潮搭id
     * @param $id
     * @param $chaoda_id
     * @return int
     */
    public static function updateChadaImgChaoId($id, $chaoda_id){
        return Db::name('chaoda_img')->where(['id'=>$id])->setField('chaoda_id',$chaoda_id);
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
     * 通过coupon_id获取卡券信息
     * @param $coupon_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getCouponRuleInfoByCouponId($coupon_id){
        return Db::name('coupon')->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where(['c.id'=>$coupon_id])
            ->field('cr.platform_bear')
            ->find();
    }
}