<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class StyleProduct extends Model
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
        return $this->save($data,compact('id'));
    }

    /**
     * 获取一条信息
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo($id){
        return $this->where(compact('id'))->field('id,title')->find();
    }

    /**
     * 删除(软删除)
     * @param $id
     * @return false|int
     */
    public function delCateProduct($id){
        return $this->save(['delete_time'=>time()],compact('id'));
    }

    /**
     * 获取风格名
     * @param $id
     * @return mixed
     */
    public static function getStyleTitle($id){
        return (new self())->where(['id'=>$id])->value('title');
    }

}