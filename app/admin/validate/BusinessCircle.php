<?php


namespace app\admin\validate;



use think\Validate;

class BusinessCircle extends Validate
{

    protected $rule = [

        'id' => 'number|>=:0',

        'level' => 'require|number|in:1,2,3',

        'page' => 'number|>=:1',

        'circle_name' => 'require|max:15',

        'province' => 'require|number|>=:1',

        'city' => 'require|number|>=:1',

        'area' => 'require|number|>=:1',

        'address' => 'require',

        'status' => 'require|number|in:1,-1',

        'lng' => 'require',

        'lat' => 'require',

        'description' => 'require|max:100',

        'imgs' => 'array',

        'circle_id' => 'number|>=:1'

    ];

    protected $scene = [

        'region_list' => ['id', 'level'],

        'add_business_circle' => ['circle_name', 'province', 'city', 'area', 'address', 'status', 'lng', 'lat', 'description', 'imgs'],

        'business_circle_list' => ['page', 'circle_id', 'province', 'city', 'area'],

        'business_circle_detail' => ['id'],

        'business_circle_store_list' => ['circle_id', 'page'],

        'edit_business_circle_status' => ['id', 'status'],

        'business_circle_info' => ['id'],

        'del_business_circle' => ['id'],

        'edit_business_circle' => ['id', 'circle_name', 'province', 'city', 'area', 'address', 'status', 'lng', 'lat', 'description', 'imgs', 'add_store_ids', 'del_store_ids']

    ];

}