<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class CateProduct extends Model
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
     * 新增
     * @param $data
     * @return false|int
     */
    public function add($data){
        return $this->isUpdate(false)->save($data);
    }

    /**
     * 更新
     * @param $id
     * @param $data
     * @return false|int
     */
    public function edit($id, $data){
        return $this->save($data, compact('id'));
    }

    /**
     * 获取一条数据
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo($id){
        return $this->where(['id'=>$id])->field('id,title,suit')->find();
    }

    /**
     * 删除(软删除)
     * @param $id
     * @return false|int
     */
    public function delCateProduct($id){
        return $this->save(['delete_time'=>time()],compact('id'));
    }

}