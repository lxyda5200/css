<?php


namespace app\store\validate;


use think\Validate;

class Dynamic extends Validate
{

    protected $rule = [

        'page|页码' => 'number|>=:1',

        'title' => 'require|min:2|max:18',

        'scene_id' => 'require|number|>=:1',

        'cover' => 'require',

        'type' => 'require|number',

        'imgs' => 'array',

        'description' => 'require|min:2|max:200',

        'tags' => 'require',

        'is_group_buy' => 'require|number|>=:0|<=:1',

        'is_cover' => 'require|number|>=:0|<=:1',

        'video_id' => 'require',

        'src' => 'require',

        'styles|风格' => 'array',

        'style_id' => 'require|number|>=:1',

        'product_id' => 'require|number|>=:1',

        'is_batch_setup' => 'require|number',

        'id' => 'require|number|>=:1',

        'status' => 'require|number',

        'product_ids' => 'require|min:1',

        'img_url' => 'require'

    ];

    protected $scene = [
        'recom_product_list' => ['page'],
        'add_dynamic' => ['title', 'scene_id', 'cover', 'type', 'imgs', 'description', 'is_group_buy', 'styles'],
        'add_dynamic_img' => ['src', 'is_cover'],
        'add_dynamic_video' => ['src', 'is_cover', 'cover', 'video_id'],
        'add_dynamic_style' => ['style_id', 'type'],
        'dynamic_product' => ['product_id', 'is_batch_setup'],
        'dynamic_product_specs' => ['specs_id'],
        'dynamic_list' => ['page'],
        'edit_status' => ['id', 'status'],
        'del_dynamic' => ['id'],
        'dynamic_info' => ['id'],
        'edit_dynamic' => ['id', 'title', 'scene_id', 'cover', 'type', 'imgs', 'description', 'is_group_buy', 'styles'],
        'product_specs' => ['product_ids'],
        'dynamic_img_list' => ['id'],
        'edit_dynamic_img' => ['id', 'img_url'],
        'dynamic_product_info' => ['id'],
        'edit_dynamic_product_info' => ['id', 'is_group_buy']
    ];

}