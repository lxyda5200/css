<?php
/**
 * Created by PhpStorm.
 * User: win 10
 * Date: 2018/8/15
 * Time: 22:04
 */

namespace app\user_v2\model;


use think\Model;

class RoomConfig extends Model
{

    public function getRoomConfigInfo($id){
        $data = $this->whereIn('id',$id)->where('type',1)->field('id,name,icon')->select();
        return $data;
    }
}