<?php


namespace app\user_v6\model;


use think\Exception;
use think\Model;
use app\user_v6\model\GiftLottery;

class DrawLotteryRecord extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time'];

    /**
     * 插入抽奖记录
     * @param $source
     * @return false|int
     */
    public static function addRecord($source){
        try{
            $reward_id = intval($source['reward_id']);

            ##更新奖品剩余数
            if($reward_id){
                GiftLottery::updateNum($reward_id);
            }

            $data = [
                'draw_lottery_id' => $source['draw_id'],
                'user_id' => $source['user_id'],
                'draw_time' => $source['draw_time'],
                'is_reward' => $source['is_reward'],
                'reward_id' => $reward_id
            ];

            if(intval($source['is_reward'])){ ##如果中奖,查询奖品信息
                $reward_data = GiftLottery::getRewardData($reward_id);
                $data['coupon_id'] = $reward_data['coupon_id'];
                $data['coupon_money'] = $reward_data['coupon_money'];
                $data['satisfy_money'] = $reward_data['satisfy_money'];
            }

            $data['create_time'] = time();

            return (new self())->insert($data);

        }catch(Exception $e){

            addErrLog($e->getMessage(),'插入抽奖记录异常',5);
            return false;

        }

    }

    public function setCreateTimeAttr(){
        return time();
    }
}