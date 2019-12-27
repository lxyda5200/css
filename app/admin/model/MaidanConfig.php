<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use think\model\relation\HasMany;
use traits\model\SoftDelete;

class MaidanConfig extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected  $dateFormat = false;

    protected $insert = ['create_time'];

    use SoftDelete;

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取买单主配置
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo(){
        return $this
            ->field('new_user_reward,first_user_reward,min_maidan_price, id')
            ->with([
                'rewordRules' => function(HasMany $hasMany){
                    $hasMany->field('maidan_config_id,person_num,reward')->order('level','asc');
                }
            ])
            ->order('id','desc')
            ->find();
    }

    /**
     * 一对多 配置规则
     * @return HasMany
     */
    public function rewordRules(){
        return $this->hasMany('MaidanPlatformRewardRule','maidan_config_id','id');
    }

    /**
     * 编辑员工奖励机制
     * @param $post
     * @throws Exception
     */
    public function edit($post){
        $new_user_reward = floatval($post['new_user_reward']);
        $first_user_reward = floatval($post['first_user_reward']);
        $min_maidan_price = floatval($post['min_maidan_price']);

        $id = $this->value('id');
        if($id){
            ##删除以前的信息
            $res = MaidanConfig::destroy($id);
            if($res === false)throw new Exception('操作失败');
        }
        ##新增信息
        $res = $this->isUpdate(false)->save(compact('new_user_reward','first_user_reward','min_maidan_price'));
        if($res === false)throw new Exception('操作失败');
        $maidan_config_id = $this->getLastInsID();

        ##新增规则
        if(isset($post['reward_rule']) && !empty($post['reward_rule'])){
            MaidanPlatformRewardRule::add($maidan_config_id, $post['reward_rule']);
        }
    }

}