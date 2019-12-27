<?php


namespace app\admin\model;


use think\Model;

class DrawLotteryRecord extends Model
{
    protected $table = 'draw_lottery_record';

    public function getDrawTimeAttr($val) {
        return date('Y-m-d H:i:s', $val);
    }
}