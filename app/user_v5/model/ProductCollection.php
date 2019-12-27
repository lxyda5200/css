<?php


namespace app\user_v5\model;


use think\Model;

class ProductCollection extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取用户商品收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        $num = (new self())->alias('pc')
            ->join('product_specs ps','pc.specs_id = ps.id','RIGHT')
            ->join('product p','p.id = pc.product_id','RIGHT')
            ->join('store s','s.id = p.store_id','RIGHT')
            ->where('pc.user_id',$user_id)
            ->where('ps.id',['GT',0])
            ->where(['p.status'=>1,'p.sh_status'=>1,'s.store_status'=>1])
            ->count();
        return $num;
    }

}