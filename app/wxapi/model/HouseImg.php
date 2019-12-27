<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/7
 * Time: 16:49
 */

namespace app\wxapi\model;


use think\Model;

class HouseImg extends Model
{
    protected $hidden = ['house_id'];

    public function house()
    {
        return $this->belongsTo('House','id');
    }

}