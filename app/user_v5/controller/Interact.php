<?php


namespace app\user_v5\controller;

use app\user_v5\common\Base;
use app\user_v5\common\User as UserFunc;
use app\user_v5\common\UserLogic;
use app\user_v5\model\CommentModel;
use think\Db;
use jiguang\JiG;
use think\Exception;
use think\response\Json;
use think\Validate;
use app\user_v5\model\User;

class Interact extends Base
{

    /**
     * 获取客服
     * @param $user_id
     * @param $store_id
     * @return array
     */
    public function getStoreService($user_id, $store_id){

            $userInfo = Db::name('user')->where(['user_id'=>$user_id])->field('user_id,jig_id,jig_pwd')->find();
            if(!$userInfo['jig_id']){  //注册
                $jig_info = JiG::registerUser($userInfo['user_id']);
                if($jig_info['status'] == 0){
                    addErrLog($jig_info,'注册极光用户失败');
                    return ['status'=>0,'err'=>$jig_info['err']];
                }
                $jig_id = $jig_info['jig_id'];
                $jig_pwd = $jig_info['jig_pwd'];
            }else{
                $jig_id = $userInfo['jig_id'];
                $jig_pwd = $userInfo['jig_pwd'];
            }

            ##获取客服
            $service_info = JiG::getCustomerServiceInfo($userInfo['user_id'],$store_id);
            if($service_info['status'] == 0)return ['status'=>0,'err'=>$service_info['err']];
            $store_jig_id = $service_info['data']['jig_id'];

            return ['status'=>1,'data'=>compact('jig_id','jig_pwd','store_jig_id')];

    }

    /**
     * 获取互动信息
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function interactData(){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #逻辑
            $user_id = $userInfo['user_id'];
            ##获取关注数
            $follow_num = UserLogic::userFollowNum($user_id);
            ##获取收藏数
            $collect_num = User::getUserCollectNum($user_id);
            ##获取评论数
            $comment_num = User::getCommentNum($user_id);
            ##判断用户有没有待查看评论数
            $new_comment = User::newCommentNum($user_id);
            ##获取未读系统消息数
            $system_num = User::userNewSystemMsgNum($user_id);
            ##最近一条未读系统消息时间
            $system_msg_time = $system_num?(User::userNewSystemMsgTime($user_id)):0;
            #返回
            return json(self::callback(1,'',compact('follow_num','collect_num','comment_num','new_comment','system_num','system_msg_time')));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 动态消息回复列表
     * @param Validate $validate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function dynamicComments(Validate $validate){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #验证参数
            $rule = [
                'page' => 'number|>=:1',
                'size' => 'number|>=:1|<=:20'
            ];
            $res = $validate->rule($rule)->check(input());
            if(!$res)throw new Exception($validate->getError());
            #逻辑
            $data = CommentModel::userCommentRecoveryList($userInfo['user_id']);
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}