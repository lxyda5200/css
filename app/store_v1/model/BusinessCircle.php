<?php


namespace app\store_v1\model;


use think\Db;

class BusinessCircle
{
    public function get_list(){
         return Db::table('business_circle')->where(['status'=>1])->field('id,circle_name,province,city,area,sort')->order('sort desc')->select();
    }
}