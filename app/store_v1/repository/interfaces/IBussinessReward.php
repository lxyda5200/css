<?php


namespace app\store_v1\repository\interfaces;


interface IBussinessReward
{
    /**
     * 获取销售总额奖励员工列表
     * @param $store_id
     * @return mixed
     */
    public static function getList($store_id);

    /**
     * 添加员工销售总额奖励
     * @param $condition
     * @param $store_id
     * @param $reward
     * @return mixed
     */
    public static function addReward($condition, $store_id, $reward);

    /**
     * 修改员工销售总额奖励
     * @param $condition
     * @param $id
     * @param $reward
     * @return mixed
     */
    public static function editReward($condition, $id, $reward);

    /**
     * 删除员工销售总额奖励
     * @param $id
     * @return mixed
     */
    public static function delReward($id);


    /**
     * 获取最大条件
     * @param $store_id
     * @return mixed
     */
    public static function getMaxCondition($store_id);


    /**
     * 获取上一阶段奖励金额
     * @param $condition
     * @return mixed
     */
    public static function getPreReward($store_id, $condition);


    /**
     * 获取下一阶段奖励金额
     * @param $store_id
     * @param $condition
     * @return mixed
     */
    public static function getNextReward($store_id, $condition);
}