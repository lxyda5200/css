<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use app\admin\model\CouponUseRule;

class CouponRule extends Model
{

    protected $autoWriteTimestamp = false;


    public function getNewTypeAttr($val) {
        $type = [
            1 => '平台优惠券',
            2 => '店铺优惠券',
            3 => '商品优惠券'
        ];
        return $type[$val];
    }


    public function getNewCouponTypeAttr($val) {
        $type = [
            1 => '新人优惠券',
            2 => '会员优惠券',
            3 => '店铺优惠券',
            4 => '商品优惠券',
            5 => '合作商家活动优惠券',
            6 => '商务推广券',
            7 => '邀请码券',
            8 => '领券中心优惠券',
            9 => '返优惠券',
            10 => '线下优惠券',
            11 => '抽奖线下券',
            12 => '抽奖线上券'
        ];
        return $type[$val];
    }


    /**
     * 修改字段
     * @param $field
     * @param $value
     * @param $id
     * @return int
     */
    public function updateField($field, $value, $id){
        return $this->where(compact('id'))->setField($field, $value);
    }

    /**
     * 获取推荐优惠券列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getCouponRecom(){
        $list = (new self())->where(['coupon_type'=>8, 'use_type'=>['IN',[1,3]],'is_open'=>1,'end_time'=>['GT',time()]])->field('id,coupon_name')->select();
        return $list;
    }

    /**
     * 获取返回优惠券列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getCouponRtn(){
        return (new self())->where(['coupon_type'=>9, 'use_type'=>['IN',[1,3]], 'is_open'=>1])->field('id,coupon_name')->select();
    }

    /**
     * 获取活动推荐的优惠券
     * @param $coupon_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityRecomCoupons($coupon_ids){
        if(!is_array($coupon_ids))$coupon_ids = explode(',',$coupon_ids);
        return (new self())->where(['id'=>['IN',$coupon_ids]])->field('id,coupon_name')->select();
    }

    /**
     * 获取线下优惠券信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOfflineCouponInfo(){
        $id = input('post.id',0,'intval');
        $info = $this->where(['id'=>$id,'coupon_type'=>['IN',[10,11]]])->field('id,coupon_name,is_open,satisfy_money,coupon_money,days,start_time,end_time,check_num,kind,platform_bear,rule_model_id')->find();
        if(!$info)throw new Exception('优惠券信息不存在或已删除');
        $info['rules'] = CouponUseRule::getCouponRules($info['rule_model_id']);
        $info['platform_bear'] = $info['platform_bear'] * 100;
        return $info;
    }

    public function getStartTimeAttr($val){
        return date('Y-m-d H:i', $val);
    }

    public function getEndTimeAttr($val){
        return date('Y-m-d H:i', $val);
    }

    /**
     * 修改线下优惠券(日审核数，平台提成)
     * @throws Exception
     */
    public function editOfflineCoupon(){
        $id = input('post.id',0,'intval');
        $check_num = input('post.check_num',0,'intval');
        $platform_bear = input('post.platform_bear',0,'intval');
        $platform_bear = $platform_bear / 100;

        $data = compact('check_num','platform_bear');
        $res = $this->save($data,['id'=>$id]);
        if($res === false)throw new Exception('操作失败');
    }

}