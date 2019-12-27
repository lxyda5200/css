<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/19
 * Time: 17:43
 */

namespace app\user_v7\controller;


use app\common\controller\Base;
use app\common\controller\Character;
use app\user_v7\common\User as UserFunc;
use think\Config;
use think\Db;
use think\response\Json;

class Index extends Base
{

    /**
     * 获取版本号
     */
    public function getVersion(){

        $data = Db::name('version')->select();
        return json(self::callback(1,'',$data));
    }
    /**
     * APP更新
     */
    public function appUpdate(){
        return json(self::callback(1,'',true,true));
    }

    /**
     * banner
     */
    public function bannerList(){
        $data = Db::name('banner')
            ->field('id,title,cover')
            ->where('is_show',1)
            ->order('paixu desc')
            ->select();
        $web_path = Config::get('web_path');
        foreach ($data as $k=>$v){
            $data[$k]['url'] = "{$web_path}/user/index/banner_p/id/{$v['id']}.html";
        }
        return json(self::callback(1,'',$data));
    }

    /*
     * banner页
     * */
    public function banner_p(){
        $id = input("id") ? intval(input("id")) : 0 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('banner')
            ->where("id",$id)
            ->field('title,content')
            ->find();
        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/banner',['data'=>$data]);
    }

    /*
     * 商品详情
     * */
    public function product_content_p(){
        $id = input("id") ? intval(input("id")) : 0 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('product')
            ->where("id",$id)
            ->field('content')
            ->find();

        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/product_content_p',['data'=>$data]);
    }

    /**
     * 获取年龄段
     */
    public function GetAgeRange(){
        try{
            $list = Db::name('age_range')
                ->field('id,min_age,max_age')
                ->where('status',1)
                ->order('sort desc')
                ->select();
            foreach ($list as $k=>&$v){
                $min=$v['min_age'];
                $max=$v['max_age'];
                $v['age_range']="{$min}-{$max}岁";
              if($v['max_age']<=18){
                  $v['age_range']="<{$max}岁";
              }
              if($v['max_age']>30){
                  $v['age_range']="永远18岁";
                }
            unset($v['min_age']);
            unset($v['max_age']);
            }
            return json(self::callback(1,'返回成功!',$list));
        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }
    /*
     * 店铺banner
     * */
    public function store_banner_p(){
        $id = input("id") ? intval(input("id")) : 0 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('store_category_img')
            ->where("id",$id)
            ->field('content')
            ->find();
        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/store_banner',['data'=>$data]);
    }

    /*
     * 会员店铺页
     * */
    public function member_store_banner_p(){
        $id = input("id") ? intval(input("id")) : 0 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('member_store_banner')
            ->where("id",$id)
            ->field('content')
            ->find();
        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/store_banner',['data'=>$data]);
    }

    /*
     * 用户协议页
     * */
    public function user_p(){
        $id = input("id") ? intval(input("id")) : 0 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('about_us')
            ->where("id",$id)
            ->field('user_protocol')
            ->find();
        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['content'] = html_entity_decode($data['user_protocol']);
        return $this->fetch('index/user_protocol',['data'=>$data]);
    }

    /*
     * 用户协议页
     * */
    public function member_p(){
        $id = input("id") ? intval(input("id")) : 1 ;
        if(!$id){
            echo "内容不存在或者已被删除";die;
        }
        $data = Db::name('member_price')
            ->where("id",$id)
            ->field('content')
            ->find();
        if(!$data) {  echo "内容不存在或者已被删除";die; }

        $data['title'] = '会员中心';
        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/banner',['data'=>$data]);
    }

    /**
     * 获取省市区
     */
    public function getProvinceData(){

        $data = Db::name('province')->field('id,province_name')->order(['paixu'=>'asc','id'=>'asc'])->select();

        foreach ($data as $k=>$v){
            $city_list = Db::name('city')->field('id,city_name')->where('province_id',$v['id'])->order(['paixu'=>'asc','id'=>'asc'])->select();

            foreach ($city_list as $k2=>$v2){
                $city_list[$k2]['area_list'] = Db::name('area')->field('id,area_name1')->where('city_id',$v2['id'])->where('pid',0)->select();
            }

            $data[$k]['city_list'] = $city_list;

        }
        return json(self::callback(1,'',$data));
    }

    /**
     * 获取城市数据
     * @is_hot  是否热门城市 可选
     * @province_id  省份id  可选
     */
    public function getCityData(){

        $is_hot = input('is_hot') ? intval(input('is_hot')) : 0 ;
        if (!empty($is_hot)){
            $where['is_hot'] = array('eq',$is_hot);
        }
        $city = input('city');
        if (!empty($city)){
            $where['city_name'] = ['like',"%$city%"];
        }

        $province_id = input('province_id') ? intval(input('province_id')) : 0 ;
        if (!empty($province_id)){
            $where['province_id'] = array('eq',$province_id);
        }

        $data = Db::name('city')->field('id,city_name')->where('is_show',1)->where($where)->order(['paixu'=>'asc','id'=>'asc'])->select();

        $Character = new Character();
        $data = $Character->groupByInitials($data,'city_name');

        return json(self::callback(1,'',$data));
    }

    /**
     * 城市搜索
     */
    public function citySearch(){
        $city = input('city');
        if (!empty($city)){
            $where['city_name'] = ['like',"%$city%"];
        }

        $data = Db::name('city')->field('id,city_name')->where('is_show',1)->where($where)->order(['paixu'=>'asc','id'=>'asc'])->select();

        return json(self::callback(1,'',$data));
    }

    /**
     * 获取区域数据
     */
    public function getAreaData(){
        $city_id = input('city_id') ? intval(input('city_id')) : 0 ;

        if (!$city_id){
            return json(self::callback(0,'参数错误'),400);
        }

        $data = Db::name('city')->field('id,city_name')->where('id',$city_id)->find();

        if (!$data){
            return json(self::callback(0,'城市不存在'));
        }

        $area = Db::name('area')
            ->field('id,area_name1,lng,lat')
            ->where('city_id',$city_id)
            ->where('pid',0)
            ->order(['paixu'=>'asc','id'=>'asc'])
            ->select();

        foreach ($area as $k=>$v){
            $area[$k]['area2_list'] = Db::name('area')
                ->field('id,area_name2,lng,lat')
                ->where('pid',$v['id'])
                ->order(['paixu'=>'asc','id'=>'asc'])
                ->select();
        }

        $data['area1_list'] = $area;

        return json(self::callback(1,'',$data));
    }

    /**
     * 获取城市地铁数据
     */
    public function getSubwayData(){
        $city_id = input('city_id') ? intval(input('city_id')) : 0 ;
        if (!$city_id){
            return json(self::callback(0,'参数错误'),400);
        }
        $city_info = Db::name('city')->field('id,city_name')->where('id',$city_id)->find();
        $area = Db::name('subway_lines')
            ->field('id,lines_name')
            ->where('city_id',$city_id)
            ->order(['paixu'=>'asc','id'=>'asc'])
            ->select();
        foreach ($area as $k=>$v){
            $area[$k]['station_list'] = Db::name('subway_station')->field('id,station_name')->where('lines_id','eq',$v['id'])->order(['paixu'=>'asc','id'=>'asc'])->select();
        }
        $data['city_info'] = $city_info;
        $data['lines_list'] = $area;
        return json(self::callback(1,'',$data));
    }

    /**
     * 获取房源标签
     */
    public function getHouseTagData(){
        $type = input('type') ? intval(input('type')) : 1 ;
        $data = Db::name('house_tag')->field('id,tag_name')->where('status',1)->where('type',$type)->select();
        return json(self::callback(1,'',$data));
    }


    /**
     * 获取房源类型
     */
    public function getHouseTypeData(){
        $type = input('type');
        $data = Db::name('house_type')->field('id,name')->where('type',$type)->select();
        return json(self::callback(1,'',$data));
    }

    /**
     * 获取小区列表数据
     */
    public function getHouseXiaoquData(){
        $city_id = input('city_id');
        $where['city_id'] = ['eq',$city_id];
        $keyword = input('keyword');
        if (isset($keyword)){
            $where['xiaoqu_name'] = ['like',"%$keyword%"];
        }
        $data = Db::name('house_xiaoqu')->where($where)->select();
        return json(self::callback(1,'',$data));

    }

    /**
     * 获取房源配置数据
     */
    public function getHouseConfigData(){
        $type = input('type');
        $where['type'] = ['eq',$type];
        $where['status'] = ['eq',1];
        $data = Db::name('room_config')->field('id,name,icon')->where($where)->select();
        return json(self::callback(1,'',$data));
    }

    /**
     * 获取短租交通与位置标签
     */
    public function getTrafficTagData(){
        $data = Db::name('short_traffic_tag')->field('id,name')->where('status',1)->select();
        return json(self::callback(1,'',$data));
    }

    /**
     * 关于我们
     */
    public function aboutUs(){
        $data = Db::name('about_us')->where('id',1)->find();
        return json(self::callback(1,'',$data));
    }
}