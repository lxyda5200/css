<?php


namespace app\user_v7\model;


use think\Model;

class StoreReadRecord extends Model
{

    protected $autoWriteTimestamp = false;

    protected $dateFormat = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取用户浏览店铺信息
     * @param $user_id
     * @param $store_ids
     * @return array
     */
    public static function getUserViewStore($user_id, $store_ids){
        $list = (new self())->alias('sr')
            ->join('store s','s.id = sr.store_id','LEFT')
            ->where([
                'sr.user_id' => $user_id,
                'sr.store_Id' => ['NOT IN', $store_ids],
                's.sh_status' => 1,
                's.store_status' => 1,
                's.type' => 1
            ])
            ->field('s.id,s.cover,s.address,s.store_name')
            ->limit(5)
            ->group('sr.store_id')
            ->select()
            ->toArray();
        return $list;
    }

}