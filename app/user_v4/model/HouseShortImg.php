<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/14
 * Time: 14:31
 */

namespace app\user_v4\model;


use think\Model;

class HouseShortImg extends Model
{
    protected $hidden = ['short_id'];

    public function houseShort()
    {
        return $this->belongsTo('HouseShort','id');
    }
}