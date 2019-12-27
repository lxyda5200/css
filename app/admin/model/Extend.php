<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class Extend extends Model
{

    use SoftDelete;

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time'];

    protected $deleteTime = 'delete_time';

    public function add($data){
        return $this->save($data);
    }

    public function edit($data, $id){
        return $this->isUpdate(true)->save($data,compact('id'));
    }

    /**
     * 修改字段
     * @param $field
     * @param $value
     * @param $id
     * @return int
     */
    public function updateField($field, $value, $id){
        return $this->where(compact('id'))->setField($field, $value);
    }

    protected function setCreateTimeAttr(){
        return time();
    }

}