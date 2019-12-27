<?php


namespace app\admin\validate;


use think\Validate;

class Dynamic extends Validate
{

    protected $rule = [

        'dynamic_id' => 'number|>=:1',

        'handler' => 'min:3',

        'page' => 'number|>=:1',

        'is_recommend' => 'require|number',

        'recom_start_time' => '',

        'recom_end_time' => '',

        'recom_sort' => 'require|number|>=:0',

        'recom_remark' => 'max:200',

        'keywords' => '>=:1',

        'status' => 'require|number|>=:-1|<=:1',

        'type' => 'require|number|>=:1|<=:2',

        'start_time' => 'length:10',

        'end_time' => 'length:10',

        'days' => 'number|>=:1|<=:30'

    ];

    protected $scene = [

        'recommend_dynamic_list' => ['dynamic_id', 'handler', 'page'],

        'edit_recommend_dynamic_status' => ['dynamic_id', 'is_recommend'],

        'edit_recommend_dynamic' => ['dynamic_id', 'recom_sort', 'recom_remark', 'is_recommend'],

        'dynamic_list' => ['page'],

        'edit_dynamic_status' => ['dynamic_id', 'status'],

        'del_dynamic' => ['dynamic_id'],

        'dynamic_data' => ['dynamic_id', 'type', 'start_time', 'end_time', 'days']

    ];

}