<?php


namespace app\admin\model;


use think\Model;

class DrawLottery extends Model
{
    protected $table = 'draw_lottery';

    protected $updateTime = false;

    public function getCreateTimeAttr($val) {
        return date('Y-m-d H:i:s', $val);
    }

    public function getStartTimeAttr($val) {
        return date('Y-m-d H:i:s', $val);
    }

    public function getEndTimeAttr($val) {
        return $val == 0 ? "未填写" : date('Y-m-d H:i:s', $val);
    }

    /**
     * 活动状态
     * @param $val
     * @return mixed
     */
//    public function getActiveStatusAttr($val) {
//        $active_status = [
//            1 => "未开始",
//            2 => "进行中",
//            3 => "已完成",
//            4 => "已失效"
//        ];
//        return $active_status[$val];
//    }


    public function getStatusAttr($val) {
        $status = [
            1 => '启用',
            -1 => '禁用'
        ];
        return $status[$val];
    }
}