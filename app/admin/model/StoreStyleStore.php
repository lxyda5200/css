<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class StoreStyleStore extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time','update_time'];

    protected $update = ['update_time'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    public function setUpdateTimeAttr(){
        return time();
    }

    public function getTypeAttr(){
        return 1;
    }

    /**
     * 删除店铺主营风格
     * @param $id
     * @return int
     */
    public function delByCateStore($id){
        return $this->where(['style_store_id'=>$id])->setField('delete_time',time());
    }

    /**
     * 获取店铺风格列表
     * @param $store_ids
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreStyleList($store_ids){
        $store_ids = explode(',',$store_ids);
        $list = $this->alias('sss')
            ->join('style_store ss','ss.id = sss.style_store_id','LEFT')
            ->where(['sss.store_id'=>['IN',$store_ids],'ss.delete_time'=>null])
            ->field('ss.id as style_id,ss.title,ss.create_time as type')
            ->distinct(true)
            ->select()
            ->toArray();
        return $list;
    }

}