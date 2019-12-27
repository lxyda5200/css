<?php


namespace app\store\model;


use think\Model;
use traits\model\SoftDelete;

class StoreStyleStore extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $delete_time = 'delete_time';

    protected $resultSetType = '\think\Collection';

    /**
     * 设置风格类型为店铺
     * @return int
     */
    public function getTypeAttr(){
        return 1;
    }

    /**
     * 店铺绑定的风格信息
     * @param $store_id
     * @return array
     */
    public function getStoreStyleList($store_id){
        return $this->alias('sss')
            ->join('style_store ss','ss.id = sss.style_store_id','LEFT')
            ->where(['sss.store_id'=>$store_id])
            ->field('ss.id,ss.title,ss.create_time as type')
            ->select()
            ->toArray();
    }

}