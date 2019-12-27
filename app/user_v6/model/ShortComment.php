<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/14
 * Time: 15:41
 */

namespace app\user_v6\model;


use think\Model;

class ShortComment extends Model
{
    protected $resultSetType = "collection";

    public function houseShort()
    {
        return $this->belongsTo('HouseShort','id');
    }

    public function userInfo()
    {
        return $this->belongsTo('User')->field('user_id,nickname,avatar');
    }

    public static function getCommentList($where=[],$page='',$size='',$order=[]){
        $commentList = self::with('user_info')->where($where)->page($page,$size)->order($order)->select();
        return $commentList;
    }
}