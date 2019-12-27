<?php


namespace app\store_v1\model;


use think\Db;
use think\Model;

class StoreGroup extends Model
{

    /**
     * 获取用户菜单列表
     * @param $store_id  //店铺ID
     * @param $id  //角色ID
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_list($store_id,$id){
        $ids = Db::table('store_group')->where(['store_id'=>$store_id,'id'=>$id,'status'=>1])->field('rules')->find();
        $list = [];
        if($ids){
            $where['id'] = ['in',$ids];
            $where['status']=1;
            $list = $this->where($where)->select();
            $list = $list->toArray();
        }
        return $list;
    }
}