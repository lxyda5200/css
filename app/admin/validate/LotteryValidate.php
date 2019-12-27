<?php


namespace app\admin\validate;


use think\Validate;

class LotteryValidate extends Validate
{
    protected $rule = [
        'title|活动名称' => 'require',
        'description|活动说明' => 'require',
        'start_time|活动开始时间' => 'require',
        'end_time|活动结束时间' => 'require',
        'rule|活动规则' => 'require',
        'client|活动平台' => 'require',
        'number|预期抽奖次数' => 'require',
        'fake_user|虚拟中奖人数' => 'require',
        'type|中奖模式' => 'require',
        'per_user_max_number|用户最多抽取次数' => 'require',
        'bg_img|背景图' => 'require',
        'icon|icon' => 'require',
        'coupon_data|活动奖品信息' => 'require',
        'tactics_data|活动策略信息' => 'require',
    ];


    protected $scene = [
        'save' => ['title', 'description', 'start_time', 'end_time', 'rule', 'client', 'number', 'fake_user', 'type',
            'per_user_max_number', 'bg_img', 'icon', 'coupon_data', 'tactics_data']
    ];
}