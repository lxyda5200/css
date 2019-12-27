<?php


namespace app\business\model;


use think\Model;

class MsgReadModel extends Model
{

    protected $pk = 'id';

    protected $table = 'user_sys_msg_read';

    /**
     *  员工删除系统消息
     * @param $msg_id
     * @param $business_id
     * @return MsgReadModel
     */
    public static function delMsgData($msg_id,$business_id){
        $data = self::where(['sys_msg_id' => $msg_id, 'user_id' => $business_id])->update(['is_delete' => 1]);

        return $data;
    }
}