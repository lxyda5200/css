<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/16
 * Time: 15:02
 */

namespace app\wxapi\model;

use think\Model;
class User extends Model
{

    public function goodsComment()
    {
        return $this->hasMany('GoodsComment','user_id','user_id');
    }

}