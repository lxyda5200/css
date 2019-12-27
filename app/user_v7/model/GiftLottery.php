<?php


namespace app\user_v7\model;


use think\Model;

class GiftLottery extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取奖品信息
     * @param $reward_id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getRewardData($reward_id){
        $data = (new self())->alias('gl')
            ->join('coupon_rule cr','cr.id = gl.gift_id')
            ->where([
                'gl.id'=>$reward_id
            ])
            ->field([
                'gl.gift_id as coupon_id', 'cr.coupon_money', 'cr.satisfy_money'
            ])
            ->find();
        return $data;
    }

    /**
     * 获取奖池奖品信息
     * @param $draw_id
     * @return array
     */
    public static function getLotteryData($draw_id){
        $data = (new self())->where(['lottery_id' => $draw_id])->field('manic,id,actual_gift_count')->select()->toArray();
        return $data;
    }

    /**
     * 减少奖品剩余次数
     * @param $reward_id
     */
    public static function updateNum($reward_id){
        self::where(['id'=>$reward_id])->setDec('remain');
    }

}