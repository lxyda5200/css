<?php


namespace app\user_v7\model;


use think\Model;

class StoreCollection extends Model
{
    protected $resultSetType = '\think\Collection';
    protected $dateFormat=false;
    /**
     * 用户收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        $num = (new self())->alias('sc')
            ->join('store s','s.id = sc.store_id','RIGHT')
            ->where(['sc.user_id'=>$user_id,'s.store_status'=>1])
            ->count('sc.id');
        return $num;
    }

}