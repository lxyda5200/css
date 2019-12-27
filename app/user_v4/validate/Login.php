<?php


namespace app\user_v4\validate;


use think\Validate;

class Login extends Validate
{

    protected $rule = [
        'wx_code' => 'require',
        'qq_openid' => 'require',
        'qq_nickname' => 'require',
        'qq_avatar' => 'require',
        'access_token' => 'require',
        'user_id' => 'require'
    ];

    protected $message = [];

    protected $scene = [

        'wx' => ['wx_code'],

        'qq' => ['qq_openid', 'qq_nickname', 'qq_avatar'],

        'sina' => ['access_token', 'user_id']

    ];

}