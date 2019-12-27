<?php


namespace app\admin\validate;


use think\Validate;

class ProductBase extends Validate
{

    protected $rule = [

        'id' => "require|number|>=:1",

        'page|页码' => "require|number|>=:1",

        'title' => "require|max:6|min:1|unique:cate_product,title^suit",

        'suit|适用人群' => 'require|number|>=:1|<=:4|unique:cate_product,suit^title',

        'type|请求类型' => 'require'

    ];

    protected $scene = [

        'cate_product_list' => ['page'],

        'add_cate_product' => ['title', 'suit'],

        'edit_cate_product' => ['id', 'title', 'suit', 'type'],

        'edit_cate_product_info' => ['id', 'type'],

        'del_cate_product' => ['id'],

        'style_product_list' => ['page'],

        'edit_style_product' => ['id', 'type'],

        'del_style_product' => ['id'],

        'add_style_product' => ['title'],

    ];

}