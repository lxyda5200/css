<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 10:31
 */

namespace app\user_v4\model;


use think\Model;

class ShortTrafficTag extends Model
{
    public function getTrafficTagInfo($id){
        $data = $this->whereIn('id',$id)->field('id,name')->select();
        return $data;
    }
}