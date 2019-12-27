<?php


namespace app\admin\controller\api;


use app\admin\controller\ApiBase;
use app\admin\model\UserInviteConfig;
use think\Exception;
use think\Validate;

class UserRewardApi extends ApiBase
{

    /**
     * 获取用户邀请奖励机制信息
     * @param UserInviteConfig $userInviteConfig
     * @return \think\response\Json
     */
    public function rewardInfo(UserInviteConfig $userInviteConfig){
        try{
            $info = $userInviteConfig->getInfo();
            if(!$info)$info = [];
            return json(self::callback(1,'', $info));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 修改用户邀请奖励机制
     * @param Validate $validate
     * @param UserInviteConfig $userInviteConfig
     * @return \think\response\Json
     */
    public function editRewardInfo(Validate $validate, UserInviteConfig $userInviteConfig){
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));
        try{
            #验证
            $rule = [
                'per_price|每次邀请一人奖励金额' => 'require|float|>=:0',
                'first_invite_price|首次邀请好友奖励金额' => 'require|float|>=:0',
                'max_invite_num|最高邀请可奖励人数' => 'require|number|>=:1',
                'id' => 'number|>=:1',
                'rules|阶梯奖励' => 'require|array'
            ];
            $res = $validate->rule($rule)->check($post);
            if(!$res)throw new Exception($validate->getError());
            #逻辑
            $res = $userInviteConfig->edit($post);
            if(!is_bool($res))throw new Exception($res);

            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

}