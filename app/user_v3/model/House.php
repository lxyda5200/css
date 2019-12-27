<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/23
 * Time: 10:24
 */

namespace app\user_v3\model;


use phpDocumentor\Reflection\DocBlock\Tag;
use think\Model;

class House extends Model
{

    protected $resultSetType = "collection";

    public function getRentModeAttr($value)
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
    }

    public function houseImg()
    {
        return $this->hasMany('HouseImg','house_id');
    }

    public function houseXiaoqu(){
        return $this->hasOne('HouseXiaoqu','id')->field('id,xiaoqu_name,address,lng,lat');
    }

    /**
     * 获取长租房源列表
     */
    public static function getHouseList($page='',$size='',$where=[],$order=[]){
        $data = self::with('houseImg')->where($where)->field('id,title,rent,bedroom_number,parlour_number,acreage,tag_id,lines_id')->page($page,$size)->order($order)->select();
        return $data;
    }




    /**
     * 获取长租房源详情
     */
    public static function getHouseDetail($id){
        $data = self::with('houseImg')
            ->where('id',$id)
            ->field('id,title,description,rent,rent_mode,type,decoration_mode,bedroom_number,parlour_number,toilet_number,acreage,floor_type,floor,total_floor,orientation,house_type_id,years,is_elevator,is_subway,lines_id,xiaoqu_id,station_id,tag_id,room_config_id,create_time')
            ->find();
        return $data;
    }
}