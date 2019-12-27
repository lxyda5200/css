<?php


namespace app\user_v6\model;


use my_redis\MRedis;
use think\Model;

class DrawLotteryUserNum extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time', 'use_num'];

    public function setCreateTimeAttr(){
        return time();
    }

    public function setUseNumAttr(){
        return 0;
    }

    /**
     * 添加用户抽奖数
     * @param $user_id
     * @param $money
     * @return bool
     */
    public static function addUserDrawLotteryNum($user_id, $money){
        ##获取有效活动列表
        $draw_list = DrawLottery::getValidList();
        $draw_list = json_decode(json_encode($draw_list),true);
        if(!$draw_list)return true;
        $draw_list = json_decode(json_encode($draw_list),true);
        $nums = [];
//        $expire_time = [];
        foreach($draw_list as $v){
            foreach($v['rules'] as $vv){
                if($vv['conditions'] <= $money){
                    $nums[$v['id']] += 1;
//                    $expire_time[$v['id']] = $v['end_time'];
                }
            }
        }
        if($nums){
//            $model = new self();
            $redis = new MRedis();
            $redis = $redis->getRedis();

            foreach($nums as $k => $v){
                $key = getUserDrawNumKey($k);
                $num = intval($redis->hGet($key, $user_id));
//                $check = self::check($user_id, $k);
//                if($check){
//                    $model->where(['id'=>$check])->setInc('num',$v);
//                }else{
//                    $data = [
//                        'user_id' => $user_id,
//                        'draw_lottery_id' => $k,
//                        'num' =>$v,
//                        'use_num' => 0,
//                        'create_time' => time()
//                    ];
//                    $model->insert($data);
//                }
                $num = (int)$num + $v;
                $redis->hSet($key, $user_id, $num);
            }
        }
        return true;
    }

    public static function check($user_id, $draw_lottery_id){
        return (new self())->where(compact('user_id','draw_lottery_id'))->value('id');
    }

}