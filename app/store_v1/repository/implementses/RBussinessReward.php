<?php


namespace app\store_v1\repository\implementses;


use app\store_v1\model\BussinessReward;
use app\store_v1\model\Store;
use app\store_v1\repository\interfaces\IBussinessReward;
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
            ->field('id, condition, reward')->order(`condition asc`)->select();
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
        $data = compact('condition', 'store_id', 'reward');
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
        $data = compact('condition', 'reward');
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
        return BussinessReward::where(['store_id' => $store_id, 'status' => 1])->order('condition', 'desc')
            ->value('condition');
    }

    /**
     * 获取上一阶段奖励金额
     * @param $condition
     * @return mixed
     */
    public static function getPreReward($store_id, $condition)
    {
        // TODO: Implement getPreReward() method.
        return BussinessReward::where(['store_id' => $store_id, 'condition' => ['lt', $condition]])
            ->order(`condition desc`)->value('reward');
    }

    /**
     * 获取下一阶段奖励金额
     * @param $store_id
     * @param $condition
     * @return mixed
     */
    public static function getNextReward($store_id, $condition)
    {
        // TODO: Implement getNextReward() method.
        return BussinessReward::where(['store_id' => $store_id, 'condition' => ['gt', $condition]])
            ->order(`condition asc`)->value('reward');
    }
}