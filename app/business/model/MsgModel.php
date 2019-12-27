<?php


namespace app\business\model;


use think\Model;

class MsgModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_msg_link';

    /**
     *  消息数据接口
     * @param $business_id 员工ID
     * @param bool $read   是否将未读数据设置为已读【true是  false否】 默认否
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessMsg($business_id){
        $where['business_id'] = $business_id;
        $where['type'] = 1;
        $data['list'] = self::where($where)
            ->alias('bml')
            ->join('business_msg bm', 'bml.msg_id = bm.id','left')
            ->field(['min(bml.is_read) status','max(bml.create_time) create_time','bm.title','bm.content','msg_id'])
            ->group('msg_id')
            ->select();
        $where['is_read'] = 0;
        $data['count'] = self::where($where)
            ->alias('bml')
            ->join('business_msg bm', 'bml.msg_id = bm.id','left')
            ->count();

        return $data;
    }


    /**
     * 查看系统消息 修改状态
     * @param $business_id
     * @param $msg_id
     */
    public static function lookSysMsgList($business_id,$msg_id){
        $where['business_id'] = $business_id;
        $where['msg_id'] = $msg_id;

        $count = self::where($where)->where(['is_read'=>0])->count();
        if($count == 0) return true;
        $re = self::where($where)->update(['is_read' => 1]);

        if($re){
            return true;
        }else{
            return false;
        }

    }
}