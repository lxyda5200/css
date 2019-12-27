<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/16
 * Time: 14:41
 */

namespace app\sale\model;


use think\Model;

class LongRentRecord extends Model
{
    public function longOrder(){
        return $this->belongsTo('LongOrder');
    }
}