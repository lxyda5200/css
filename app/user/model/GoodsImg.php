<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/3
 * Time: 10:50
 */

namespace app\user\model;


use think\Model;

class GoodsImg extends Model
{
    protected $hidden = ['id', 'goods_id'];

    public function goods()
    {
        return $this->belongsTo('Goods','id');
    }



}