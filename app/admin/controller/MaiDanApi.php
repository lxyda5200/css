<?php


namespace app\admin\controller;


use app\admin\model\MaidanConfig;
use think\Db;
use think\Exception;
use app\admin\validate\MaiDan;

class MaiDanApi extends ApiBase
{

    /**
     * 获取买单配置信息
     * @param MaidanConfig $maidanConfig
     * @return \think\response\Json
     */
    public function rewardRuleInfo(MaidanConfig $maidanConfig){
        try{
            $info = $maidanConfig->getInfo();
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑奖励规则
     * @param MaiDan $maiDan
     * @param MaidanConfig $maidanConfig
     * @return \think\response\Json
     */
    public function editRewardRule(MaiDan $maiDan, MaidanConfig $maidanConfig){
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $res = $maiDan->scene('edit_reward_rule')->check($post);
        if(!$res)return json(self::callback(0,$maiDan->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##更新
            $maidanConfig->edit($post);
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

}