<?php


namespace app\store\model;


use think\Model;

class TopicModel extends Model
{
    protected $pk = 'id';

    protected $table = 'topic';

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * ??APP??????
     * @return array
     */
    public function appTopicList(){
        return $this->where(['client_type'=>2, 'status'=>1])->field('id,title')->select()->toArray();
    }

}