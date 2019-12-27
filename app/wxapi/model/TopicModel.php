<?php


namespace app\wxapi\model;


use think\Model;

class TopicModel extends Model
{
    protected $pk = 'id';

    protected $table = 'topic';

    /**
     *   ????û?d????L???????h??
     * @param $page
     * @param int $user_id
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getTopicOneDataBySort($page,$user_id = 0){
        $where = [];
        $topicWhere = [];
        if ($user_id){
            $where = ['user_id' => $user_id];
            $read = TopicReadModel::where($where) -> field(['topic_id']) -> select();
            if (!empty($read)){
                $topicWhere = ['id' => ['not in', array_column($read, 'topic_id')]];
            }

        };

        $data = self::where($topicWhere) -> field(['id', 'use_number', 'title', 'follow_number', 'create_time','bg_cover']) -> select();

        $sort_result = [];
        foreach ($data as $k => $v){
            // ????????÷?
            $hours = (time() - $v['create_time'])/3600;
            $score = $v['use_number']*1+$v['follow_number']*2/sqrt($hours+2);
            // ????÷?
            $sort_result[] = $score;
            unset($v['follow_number']);
            unset($v['use_number']);
            unset($v['create_time']);
        }
        array_multisort($sort_result, SORT_DESC, $data);
        if (array_key_exists($page-1, $data)){
            $dataOne = $data[$page];
        }else{
            $dataOne = $data[rand(0,count($data)-1)];
            if (empty($data)) $dataOne = [];
        }
        return $dataOne;
    }
}