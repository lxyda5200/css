<?php


namespace app\store\repository\implementses;


use app\store\model\BussinessReward;
use app\store\model\Store;
use app\store\repository\interfaces\IBussinessReward;
use think\Db;
use think\model\relation\HasMany;

class RBussinessReward implements IBussinessReward
{

    /**
     * 获取销售总额奖励员工列表
     * @param $store_id
     * @return mixed
     */
    public static function getList($store_id)
    {
        // TODO: Implement getList() method.
        return BussinessReward::where(['store_id' => $store_id, 'status' => 1])
            ->field('id, conditions as `condition`, reward')->order('conditions', 'asc')->select();
    }

    /**
     * 添加员工销售总额奖励
     * @param $condition
     * @param $store_id
     * @param $reward
     * @return mixed
     */
    public static function addReward($condition, $store_id, $reward)
    {
        // TODO: Implement addReward() method.
        $data = compact('store_id', 'reward');
        $data['conditions'] = $condition;
        return BussinessReward::create($data);
    }

    /**
     * 修改员工销售总额奖励
     * @param $condition
     * @param $id
     * @param $reward
     * @return mixed
     */
    public static function editReward($condition, $id, $reward)
    {
        // TODO: Implement editReward() method.
        $data = compact('reward');
        $data['conditions'] = $condition;
        return BussinessReward::where(['id' => $id])->update($data);
    }

    /**
     * 删除员工销售总额奖励
     * @param $id
     * @return mixed
     */
    public static function delReward($id)
    {
        // TODO: Implement delReward() method.
        return BussinessReward::where(['id' => $id])->update(['status' => -1]);
    }

    /**
     * 获取最大条件
     * @param $store_id
     * @return mixed
     */
    public static function getMaxCondition($store_id)
    {
        // TODO: Implement getMaxCondition() method.
        return BussinessReward::where(['store_id' => $store_id, 'status' => 1])->order('conditions', 'desc')
            ->value('conditions');
    }

    /**
     * 获取上一阶段奖励金额
     * @param $condition
     * @return mixed
     */
    public static function getPreReward($store_id, $condition, $id)
    {
        // TODO: Implement getPreReward() method.
        $bussiness_reward = new BussinessReward();
        return $bussiness_reward->where(['store_id' => $store_id, 'status' => 1, 'id' => ['neq', $id], 'conditions' => ['elt', $condition]])
            ->order('conditions', 'desc')->value('reward');
    }


    public static function getPreRewardNotId($store_id, $condition)
    {
        // TODO: Implement getPreReward() method.
        return BussinessReward::where(['store_id' => $store_id, 'status' => 1, 'conditions' => ['lt', $condition]])
            ->order('conditions', 'desc')->value('reward');
    }


    /**
     * 获取下一阶段奖励金额
     * @param $store_id
     * @param $condition
     * @return mixed
     */
    public static function getNextReward($store_id, $condition, $id)
    {
        // TODO: Implement getNextReward() method.
        return BussinessReward::where(['store_id' => $store_id, 'status' => 1, 'id' => ['neq', $id], 'conditions' => ['egt', $condition]])
            ->order('conditions', 'asc')->value('reward');
    }
}