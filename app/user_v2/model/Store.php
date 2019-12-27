<?php


namespace app\user_v2\model;


use think\Model;

class Store extends Model
{

    protected $name = 'store';

    protected $autoWriteTimestamp = false;

    /**
     * 增加浏览量
     * @param $store_id
     * @return int|true
     */
    public function addViewNum($store_id){
        return $this->where(['id'=>$store_id])->setInc('read_number');
        return $this->where(['id'=>$store_id])->setInc('real_read_number');
    }

}