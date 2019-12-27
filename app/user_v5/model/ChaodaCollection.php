<?php


namespace app\user_v5\model;


use think\Model;

class ChaodaCollection extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 用户收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        $num = (new self())->alias('cc')
            ->join('chaoda c','c.id = cc.chaoda_id','RIGHT')
            ->join('store s','s.id = c.store_id')
            ->where(['cc.user_id'=>$user_id,'c.is_delete'=>0,'s.store_status'=>1])
            ->count('cc.id');
        return $num;
    }

}