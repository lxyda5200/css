<?php


namespace app\user_v7\model;


use think\Model;

class StoreFollow extends Model
{

    protected $autoWriteTimestamp = false;

    protected $dateFormat = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取用户关注店铺信息
     * @param $user_id
     * @param $store_ids
     * @return array
     */
    public static function getUserFollowStore($user_id, $store_ids){
        $store_list = (new self())->alias('sf')
            ->join('store s','s.id = sf.store_id','LEFT')
            ->where([
                'sf.user_id' => $user_id,
                'sf.type' => 1,
                'sf.store_id' => ['NOT IN', $store_ids],
                's.sh_status' => 1,
                's.store_status' => 1,
                's.type' => 1
            ])
            ->order('sf.create_time','desc')
            ->field('s.id,s.cover,s.address,s.store_name')
            ->group('sf.store_id')
            ->select()
            ->toArray();
        return $store_list;
    }

}