<?php


namespace app\admin\controller;


use think\Exception;
use app\admin\validate\CouponRule;
use app\admin\model\CouponRule as CouponRuleModel;
use app\admin\model\Coupon;

class CouponApi extends ApiBase
{

    /**
     * 线下优惠券信息
     * @param CouponRule $couponRule
     * @param CouponRuleModel $couponRuleModel
     * @return \think\response\Json
     */
    public function offlineCouponInfo(CouponRule $couponRule, CouponRuleModel $couponRuleModel){
        try{
            #验证
            $res = $couponRule->scene('offline_coupon_info')->check(input());
            if(!$res)throw new Exception($couponRule->getError());
            #逻辑
            $info = $couponRuleModel->getOfflineCouponInfo();
            #返回
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取优惠券领取记录
     * @param CouponRule $couponRule
     * @param Coupon $coupon
     * @return \think\response\Json
     */
    public function couponGetList(CouponRule $couponRule, Coupon $coupon){
        try{
            #验证
            $res = $couponRule->scene('coupon_get_list')->check(input());
            if(!$res)throw new Exception($couponRule->getError());
            #逻辑
            $data = $coupon->getCouponGetList();

            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改线下优惠券信息(日核销数，平台承担比例)
     * @param CouponRule $couponRule
     * @param CouponRuleModel $couponRuleModel
     * @return \think\response\Json
     */
    public function editOfflineCoupon(CouponRule $couponRule, CouponRuleModel $couponRuleModel){
        try{
            #验证
            $res = $couponRule->scene('edit_offline_coupon')->check(input());
            if(!$res)throw new Exception($couponRule->getError());
            #逻辑
            $couponRuleModel->editOfflineCoupon();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}