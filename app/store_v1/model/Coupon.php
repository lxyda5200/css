<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;
//use app\store_v1\model\CouponRule;

class Coupon extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取优惠券核销信息
     * @param $code
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfoByValidateCode($code){
        $store_id = input('post.store_id',0,'intval');
        $info = $this->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where(['c.validate_code'=>$code,'cr.store_id'=>$store_id])
            ->field('
                c.id,c.expiration_time,c.status,c.coupon_id,c.user_id,c.coupon_money,c.validate_expiration_time,
                cr.coupon_type,cr.is_open,cr.store_id,cr.kind,cr.platform_bear,cr.check_num
            ')
            ->find();
        return $info;
    }
    /**
     * 使用优惠券
     * @param $info
     * @throws Exception
     */
    public function useCoupon($info){
        ##修改用户优惠券信息
        $res = $this->where(['id'=>$info['id']])->update(['status'=>2,'use_time'=>time()]);
        if($res === false)throw new Exception('优惠券核销失败-优惠券使用失败');
        ##更新优惠券规则使用数
        $res = CouponRule::addUseNum($info['coupon_id']);
        if($res === false)throw new Exception('优惠券核销失败-优惠券使用失败');
    }

}