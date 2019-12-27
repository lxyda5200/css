<?php


namespace app\admin\model;


use think\Model;

class PopularProductsDetails extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setUpdateTimeAttr(){
        return time();
    }

    /**
     * 添加人气单品
     * @param $data
     * @return array|false
     */
    public function add($data){
        return $this->isUpdate(false)->saveAll($data);
    }

    /**
     * 删除单品--以热门id方式
     * @param $pop_pro_id
     * @return int
     */
    public function delByPopId($pop_pro_id){
        return $this->where(compact('pop_pro_id'))->delete();
    }

}