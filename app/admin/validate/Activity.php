<?php


namespace app\admin\validate;


use think\Validate;

class Activity extends Validate
{

    protected $rule = [

        'banner' => 'require|notEmpty',

        'turn_type' => 'require|integer|max:2|min:1',

        'turn_link_id' => 'require|integer|min:1',

        'title' => 'require|notEmpty',

        'user_type' => 'require|integer',

        'client' => 'require|integer|min:1|max:3',

        'cover' => 'require|notEmpty',

        'preferential_type' => 'require|integer',

        'message_type' => 'require|integer',

        'message_model_id' => 'require|integer',

        'activity_type' => 'require|integer',

        'activity_pro_type' => 'require|integer',

        'line_type' => 'require|integer',

        'activity_long' => 'require|integer'

    ];

    protected $scene = [

        'add_banner' => ['banner', 'turn_type', 'turn_link_id'],

        'add_activity' => ['title', 'user_type', 'client', 'cover', 'preferential_type', 'message_type', 'message_model_id', 'activity_type', 'activity_pro_type', 'line_type', 'activity_long']

    ];

    /**
     * 判断banner不能为空
     * @param $val
     * @return bool|string
     */
    protected function notEmpty($val){

        return trim($val)?true:"请选择banner图";

    }

}