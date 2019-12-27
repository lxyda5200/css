<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/14
 * Time: 13:43
 */

namespace app\wxapi_test\model;


use think\Model;

class HouseShort extends Model
{
    protected $resultSetType = "collection";

    public function houseShortImg()
    {
        return $this->hasMany('HouseShortImg','short_id');
    }


    /**
     * 获取短租房源列表
     */
    public static function getHouseShortList($page='',$size='',$where=[],$order=[]){
        $data = self::with('houseShortImg')->where($where)->field('id,title,rent,bedroom_number,tag_id,traffic_tag_id,city_id')->page($page,$size)->order($order)->select();
        return $data;
    }

    /**
     * 获取短租房源详情
     */
    public static function getHouseShortDetail($id){
        $data = self::with(['houseShortImg'])
            ->where('id',$id)
            ->field('id,title,description,rent,bedroom_number,parlour_number,toilet_number,acreage,bed_number,people_number,house_type_id,sale_id,is_subway,xiaoqu_id,lines_id,station_id,tag_id,room_config_id,traffic_tag_id')
            ->find();
        return $data;
    }
}