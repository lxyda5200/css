<?php

namespace app\openapi\validate;

use think\Validate;

class Check extends Validate
{

    protected $rule = [

        'product_id' => 'require|number|min:1',

    ];

    protected $scene = [

        'get_activity_start_time' => ['product_id']

    ];

}