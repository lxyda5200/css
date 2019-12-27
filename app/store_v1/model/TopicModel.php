<?php


namespace app\store_v1\model;


use think\Model;

class TopicModel extends Model
{
    protected $pk = 'id';

    protected $table = 'topic';

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * ��ȡAPP����ӵĻ���
     * @return array
     */
    public function appTopicList(){
        return $this->where(['client_type'=>2, 'status'=>1])->field('id,title')->select()->toArray();
    }

}