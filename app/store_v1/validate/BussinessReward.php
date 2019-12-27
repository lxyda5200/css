<?php


namespace app\store_v1\validate;


use think\Validate;

class BussinessReward extends Validate
{
    protected $rule = [
        'id' => 'require',
        'condition|奖励条件' => 'require|number',
        'reward|奖励金额' => 'require|number'
    ];


    protected $scene = [
        'add' => ['condition', 'reward'],
        'update' => ['id', 'condition', 'reward'],
        'del' => ['id']
    ];
}