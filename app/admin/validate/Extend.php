<?php

namespace app\admin\validate;

use think\Validate;

class Extend extends Validate
{

    protected $rule = [
        'extend_name' => 'require',
        'type' => 'require|number|min:1',
        'status' => 'require|number|min:1',
        'mobile' => 'require|phone',
        'id' => 'require|number|min:1',
    ];

    protected $message = [
        'mobile.phone' => '手机号格式错误'
    ];

    protected $scene = [
        'publish' => ['extend_name', 'mobile', 'type', 'status'],

        'edit_status' => ['id', 'status'],

        'delete' => ['id']
    ];

    /**
     * 验证手机号
     * @param $value
     * @return bool
     */
    protected function phone($value){
        $reg = '/^1(3|4|5|6|7|8|9)\d{9}$/';
        return preg_match($reg,$value)?true:false;
    }

}