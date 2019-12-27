<?php
/**
 * Created by PhpStorm.
 * User: win 10
 * Date: 2018/8/15
 * Time: 22:01
 */

namespace app\user_v6\model;


use think\Model;

class HouseTag extends Model
{

    public function getTagInfo($id){
        $data = $this->whereIn('id',$id)->where('type',1)->field('id,tag_name')->select();
        return $data;
    }
}
