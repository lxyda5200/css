<?php


namespace app\user_v4\controller;


use app\common\controller\Base;
use app\user_v4\model\ProCommentPraise;
use app\user_v4\validate\CommentValidate;
use app\user_v4\common\User as UserFunc;
use think\response\Json;

class Comment extends Base
{

    /**
     * 评论点赞
     * @param CommentValidate $CommentValidate
     * @param ProCommentPraise $ProCommPraiseModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function praiseComment(CommentValidate $CommentValidate, ProCommentPraise $ProCommPraiseModel){

        #验证
        if(!request()->isPost())return json(self::callback(0,'非法请求'));
        $res = $CommentValidate->scene('praise_comment')->check(input());
        if(!$res)return json(self::callback(0,$CommentValidate->getError()));

        #逻辑
        $comment_id = input('post.comment_id',0,'intval');
        $user_id = input('post.user_id',0,'intval');
        $token = input('post.token','','addslashes,strip_tags,trim');

        ##检查token
        if($user_id || $token) {
            $userInfo = UserFunc::checkToken($user_id,$token);
            if ($userInfo instanceof Json)return $userInfo;
        }

        ##检查是否已经点赞
        $check = $ProCommPraiseModel->check($user_id,$comment_id);
        if($check)return json(self::callback(0,'已点过赞了'));

        ##添加点赞记录
        $data = compact('comment_id','user_id');
        $res = $ProCommPraiseModel->add($data);
        if(!$res)return json(self::callback(0,'点赞失败'));

        #返回
        return json(self::callback(1,'点赞成功'));

    }

    /**
     * 取消评论点赞
     * @param CommentValidate $CommentValidate
     * @param ProCommentPraise $ProCommPraiseModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cancelPraiseComment(CommentValidate $CommentValidate, ProCommentPraise $ProCommPraiseModel){

        #验证
        if(!request()->isPost())return json(self::callback(0,'非法请求'));
        $res = $CommentValidate->scene('cancel_praise_comment')->check(input());
        if(!$res)return json(self::callback(0,$CommentValidate->getError()));

        #逻辑
        $comment_id = input('post.comment_id',0,'intval');
        $user_id = input('post.user_id',0,'intval');
        $token = input('post.token','','addslashes,strip_tags,trim');

        ##检查token
        if($user_id || $token) {
            $userInfo = UserFunc::checkToken($user_id,$token);
            if ($userInfo instanceof Json)return $userInfo;
        }

        ##取消点赞
        $res = $ProCommPraiseModel->cancel($user_id,$comment_id);
        if(!$res)return json(self::callback(0,'取消失败'));

        return json(self::callback(1,'取消成功'));

    }

}