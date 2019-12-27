<?php


namespace app\admin\validate;


use think\Validate;

class MaiDan extends Validate
{

    protected $rule = [

        'new_user_reward|单新用户推广奖励' => 'require|number|>=:0',

        'first_user_reward|首个用户额外奖励' => 'require|number|>=:0',

        'min_maidan_price|最小单笔买单金额' => 'require|number|>=:0',

        'reward_rule|奖励规则' => 'array'

    ];

    protected $scene = [

        'edit_reward_rule' => ['new_user_reward', 'first_user_reward', 'min_maidan_price', 'reward_rule']

    ];

}