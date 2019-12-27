<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class StyleStore extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time','update_time'];

    protected $update = ['update_time'];

    protected $resultSetType = '\think\Collection';

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setUpdateTimeAttr(){
        return time();
    }

    /**
     * ����
     * @param $data
     * @return false|int
     */
    public function add($data){
        return $this->isUpdate(false)->save($data);
    }

    /**
     * �޸�
     * @param $id
     * @param $title
     * @return int
     */
    public function edit($id, $title){
        return $this->save(compact('title'),compact('id'));
    }

    /**
     * ��ȡһ����Ϣ
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo($id){
        return $this->where(['id'=>$id])->field('id,title')->find();
    }

    /**
     * ��ȡ����б�
     * @return array
     */
    public function getList(){
        return $this->field('id,title')->select()->toArray();
    }

    /**
     * ������
     * @param $id
     * @return mixed
     */
    public static function getStyleTitle($id){
        return (new self())->where(['id'=>$id])->value('title');
    }

}