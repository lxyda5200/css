<?php


namespace app\user_v7\validate;


use think\Validate;

class Login extends Validate
{

    protected $rule = [
        'code' => 'require',
        'qq_openid' => 'require',
        'qq_nickname' => 'require',
        'qq_avatar' => 'require',
        'access_token' => 'require',
        'user_id' => 'require'
    ];

    protected $message = [];

    protected $scene = [

        'wx' => ['code'],

        'qq' => ['qq_openid', 'qq_nickname', 'qq_avatar'],

        'sina' => ['access_token', 'user_id']

    ];

}