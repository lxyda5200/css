<?php


namespace app\user_v7\model;


use think\Model;

class UserMsgLink extends Model
{
    protected $resultSetType = '\think\Collection';
    protected $dateFormat=false;
    /**
     * 获取用户待查看系统消息数
     * @param $user_id
     * @return int|string
     */
    public static function userNewSystemMsgNum($user_id){
        $time = User::getSystemMsgScanTime($user_id);
        return (new self())->alias('uml')
            ->join('user_msg um','uml.msg_id = um.id','LEFT')
            ->where(['uml.user_id'=>$user_id,'um.create_time'=>['GT',$time]])
            ->count('uml.id');
    }

    /**
     * 获取用户待查看系统消息的最新时间
     * @param $user_id
     * @return mixed
     */
    public static function userNewSystemMsgTime($user_id){
        $time = User::getSystemMsgScanTime($user_id);
        return (new self())->alias('uml')
            ->join('user_msg um','uml.msg_id = um.id','LEFT')
            ->where(['uml.user_id'=>$user_id,'um.create_time'=>['GT',$time]])
            ->order('um.create_time','desc')
            ->value('um.create_time');
    }

}