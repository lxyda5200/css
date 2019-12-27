<?php


namespace app\admin\validate;


use think\Validate;

class Store extends Validate
{

    protected $rule = [

        'id' => 'require|integer|min:1',

        'member_user' => 'require|float|min:0.01'

    ];

    protected $scene = [

        'edit_maidan_info' => ['id', 'member_user'],

    ];

}