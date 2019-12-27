<?php


namespace app\user_v6\model;


use think\Model;

class DynamicGroupCollection extends Model
{
    protected $dateFormat=false;
    protected $resultSetType = '\think\Collection';

    /**
     * 获取用户收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        $num = (new self())->where(['user_id'=>$user_id])->count('id');
        return $num;
    }

}