<?php


namespace app\user_v2\model;


use think\Model;

class ProCommentPraise extends Model
{

    protected $name = 'product_comment_dianzan';

    protected $insert = ['create_time'];

    protected $autoWriteTimestamp = false;

    /**
     * 添加评论点赞
     * @param $data
     * @return false|int
     */
    public function add($data){
        return $this->isUpdate(false)->save($data);
    }

    /**
     * 取消评论点赞
     * @param $user_id
     * @param $comment_id
     * @return int
     */
    public function cancel($user_id,$comment_id){
        return $this->where(['user_id'=>$user_id,'comment_id'=>$comment_id])->delete();
    }

    /**
     * 检查是否已点赞
     * @param $user_id
     * @param $comment_id
     * @return int|string
     */
    public function check($user_id, $comment_id){
        return $this->where(['user_id'=>$user_id,'comment_id'=>$comment_id])->count('id');
    }

    /**
     * 自动生成创建时间
     * @return int
     */
    protected function setCreateTimeAttr(){
        return time();
    }

}