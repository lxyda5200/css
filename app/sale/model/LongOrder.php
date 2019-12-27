<?php
/**
 * Created by PhpStorm.
 * User: win 10
 * Date: 2018/8/15
 * Time: 21:09
 */

namespace app\sale\model;


use think\Model;

class LongOrder extends Model
{

    public function longRentRecord(){
        return $this->hasMany('LongRentRecord');
    }

    public function house(){
        return $this->hasOne('House');
    }
}