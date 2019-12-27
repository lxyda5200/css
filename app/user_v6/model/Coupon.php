<?php


namespace app\user_v6\model;


use think\Exception;
use think\Model;
use aes\Aes;

class Coupon extends Model
{
    protected $autoWriteTimestamp = false;
    protected $dateFormat=false;
    protected $resultSetType = '\think\Collection';

    /**
     * 更新核销码
     * @return array
     */
    public function getValidateCode(){
        $id = input('post.coupon_id',0,'intval');
        $info = $this->where(['id'=>$id])->field('id,status,expiration_time,validate_code,validate_expiration_time')->find();
        if(!$info)throw new Exception('优惠券信息不存在');
        if($info['status'] != 1)return ['status'=>2,'msg'=>'优惠券已使用'];
        if($info['expiration_time']<time())return ['status'=>3,'msg'=>'优惠券已过期'];

        ##生成新的核销码
        $validate_code = createCouponValidateCode($id);
        ##更新核销码
        $validate_expiration_time = time() + 2 * 60;
        $res = $this->save(['validate_code'=>$validate_code,'validate_expiration_time'=>$validate_expiration_time],['id'=>$id]);
        if($res === false)throw new Exception('核销码生成失败');

        $aes = new Aes();
        $encrypt_code = $aes->encrypt($validate_code);

        return ['status'=>1,'validate_code'=>$validate_code,'encrypt_code'=>$encrypt_code,'expiration_time'=>$validate_expiration_time,'cur_time'=>time()];
    }

}