<?php


namespace app\store\model;


use think\Db;
use think\Exception;
use think\Model;

class CouponValidate extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取目前的优惠券核销数
     * @param $coupon_rule_id
     * @return int|string
     */
    public function getCurValidateNum($coupon_rule_id){
        return $this->where(['coupon_rule_id'=>$coupon_rule_id])->count('id');
    }

    /**
     * 核销
     * @param $info
     * @throws Exception
     */
    public function validateCoupon($info){

        $staff_id = Db::name('business')->where(['store_id'=>$info['store_id'],'pid'=>0])->value('id');

        $data = [
            'user_id' => $info['user_id'],
            'store_id' => $info['store_id'],
            'coupon_id' => $info['id'],
            'coupon_rule_id' => $info['coupon_id'],
            'platform_bear' => $info['platform_bear'],
            'coupon_money' => $info['coupon_money'],
            'validate_no' => build_order_no('CV'),
            'staff_id' => $staff_id
        ];

        $platform_price = $info['platform_bear'] * $info['coupon_money'];
        $data['platform_price'] = $platform_price;
        $res = $this->isUpdate(false)->save($data);
        if($res === false)throw new Exception('核销失败-核销记录创建失败');
    }

}