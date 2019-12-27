<?php


namespace app\admin\model;


use think\Exception;
use think\Model;

class MaidanPlatformRewardRule extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    public static function getInfo($config_id){
        return (new self())->where(['maidan_config_id'=>$config_id])->select()->toArray();
    }

    /**
     * 新增平台买单阶梯奖励规则
     * @param $maidan_config_id
     * @param $rules
     * @throws Exception
     */
    public static function add($maidan_config_id, $rules){
        $level = 1;
        $data = [];
        $min_person_num = $min_reward = 0;
        foreach($rules as $v){
            if(!$v['person_num'] || !$v['reward'])throw new Exception('阶级奖励机制数据错误');
            if((int)$v['person_num'] <= $min_person_num || $v['reward'] <= $min_reward)throw new Exception('阶级奖励机制人数和奖励都应大于上一级');
            $min_person_num = (int)$v['person_num'];
            $min_reward = (float)$v['reward'];

            $data[] = [
                'person_num' => $min_person_num,
                'reward' => $min_reward,
                'level' => $level,
                'maidan_config_id' => $maidan_config_id
            ];
            $level ++ ;
        }
        $res = (new self())->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('操作失败');
    }

}