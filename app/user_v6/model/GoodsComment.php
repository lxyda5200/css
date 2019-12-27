<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/3
 * Time: 14:37
 */

namespace app\user_v6\model;


use think\Model;

class GoodsComment extends Model
{
    public function goods()
    {
        return $this->belongsTo('Goods','id');
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