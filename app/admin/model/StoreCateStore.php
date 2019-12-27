<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class StoreCateStore extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time','update_time'];

    protected $update = ['update_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setUpdateTimeAttr(){
        return time();
    }

    /**
     * 删除店铺的店铺主营(软删除)
     * @param $id
     * @return int
     */
    public function delByCateStore($id){
        return $this->where(['cate_store_id'=>$id])->setField('delete_time',time());
    }

}