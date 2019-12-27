<?php


namespace app\user_v7\model;


use think\Model;

class ProductComment extends Model
{
    protected $name = 'product_comment';
    public function praise()
    {
        return $this->hasMany('ProductCommentPraise','comment_id','id')->count();
    }

}