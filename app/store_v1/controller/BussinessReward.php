<?php


namespace app\store_v1\controller;


use app\store_v1\repository\implementses\RBussinessReward;
use app\store_v1\validate\BussinessReward as BussinessRewardValidate;

class BussinessReward extends Base
{
    /**
     * 获取奖励列表
     * @return \think\response\Json
     */
    public function getRewardList() {
        $params = input('post.');
        $list = RBussinessReward::getList($params['store_id']);
        if(!$list)
            return json(self::callback(0, '暂无数据'));

        return json(self::callback(1, 'success', $list));
    }


    /**
     * 添加阶段奖励
     * @return \think\response\Json
     */
    public function addReward() {
        $params = input('post.');
        # 数据验证
        $validate = new BussinessRewardValidate();
        if(!$validate->scene('add')->check($params))
            return json(self::callback(0, $validate->getError()));
        # 获取之前条件最大值
        $max = RBussinessReward::getMaxCondition($params['store_id'])? : 0;
        if($params['condition'] <= $max)
            return json(self::callback(0, '奖励条件必须大于上一阶段金额'));
        # 获取之前奖励最大值
        $max_reward = RBussinessReward::getPreReward($params['store_id'], $params['condition']);
        if($params['reward'] <= $max_reward)
            return json(self::callback(0, '奖励金额必须大于上一阶段金额'));

        $res = RBussinessReward::addReward($params['condition'], $params['store_id'], $params['reward']);
        if(!$res)
            return json(self::callback(0, '添加失败'));

        return json(self::callback(1, '添加成功'));
    }


    /**
     * 修改阶段奖励
     * @return \think\response\Json
     */
    public function editReward() {
        $params = input('post.');
        # 数据验证
        $validate = new BussinessRewardValidate();
        if(!$validate->scene('update')->check($params))
            return json(self::callback(0, $validate->getError()));

        # 获取上一阶段奖励最大值
        $max_reward = RBussinessReward::getPreReward($params['store_id'], $params['condition'])? : 0;
        if($params['reward'] <= $max_reward)
            return json(self::callback(0, '奖励金额必须大于上一阶段金额'));
        # 获取下一阶段奖励最小值
        $next_reward = RBussinessReward::getNextReward($params['store_id'], $params['condition'])? : 0;
        if($next_reward != 0 && ($params['reward'] >= $next_reward))
            return json(self::callback(0, '奖励金额必须小于下一阶段奖励金额'));

        $res = RBussinessReward::editReward($params['condition'], $params['id'], $params['reward']);
        if($res === false)
            return json(self::callback(0, '更新失败'));

        return json(self::callback(1, '更新成功'));
    }


    /**
     * 删除阶段奖励
     * @return \think\response\Json
     */
    public function delReward() {
        $params = input('post.');
        # 数据验证
        $validate = new BussinessRewardValidate();
        if(!$validate->scene('del')->check($params))
            return json(self::callback(0, $validate->getError()));

        $res = RBussinessReward::delReward($params['id']);
        if($res === false)
            return json(self::callback(0, '删除失败'));

        return json(self::callback(1,'删除成功'));
    }
}