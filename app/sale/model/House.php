<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/30
 * Time: 13:53
 */

namespace app\sale\model;


use think\Model;

class House extends  Model
{

    protected $resultSetType = "collection";

    /*public function getRentModeAttr($value)
    {
        $data = [1=>'押一付一',2=>'押一付三',3=>'半年付',4=>'年付'];
        return $data[$value];
    }

    public function getFloorTypeAttr($value)
    {
        $data = [1=>'低楼层',2=>'中楼层',3=>'高楼层'];
        return $data[$value];
    }

    public function getDecorationModeAttr($value)
    {
        $data = [1=>'简装',2=>'精装'];
        return $data[$value];
    }*/

    public function houseImg()
    {
        return $this->hasMany('HouseImg','house_id');
    }

}