<?php


namespace app\admin\validate;


use think\Validate;

class StoreBase extends Validate
{

    protected $rule = [

        'id' => "require|number|>=:1",

        'page' => "require|number|>=:1",

        'title' => "require|max:6|min:1|unique:cate_store,title",

        'type|请求类型' => 'require'

    ];

    protected $message = [

        'title.unique' => '主营分类已经存在'

    ];

    protected $scene = [

        'cate_store_list' => ['page'],

        'add_cate_store' => ['title'],

        'edit_cate_store' => ['id', 'title', 'type'],

        'edit_cate_store_info' => ['id', 'type'],

        'del_cate_store' => ['id'],

        'style_store_list' => ['page'],

        'add_style_store' => ['title'],

        'edit_style_store' => ['id', 'title', 'type'],

        'edit_style_store_info' => ['id', 'type'],

        'del_style_store' => ['id']

    ];

}