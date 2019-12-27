<?php


namespace app\user_v4\validate;


use think\Validate;

class CommentValidate extends Validate
{

    protected $rule = [
        'comment_id' => 'require|number|min:1',
        'user_id' => 'require|number|min:1',
        'token' => 'require'
    ];

    protected $message = [

    ];

    protected $scene = [
        'praise_comment' => ['comment_id', 'user_id', 'token'],
        'cancel_praise_comment' => ['comment_id', 'user_id', 'token'],
    ];

}