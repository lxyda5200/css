<?php


namespace app\admin\validate;


use think\Validate;

class Operate extends Validate
{

    protected $rule = [

        'page|页码' => "number|>=:1",

        'title' => "require|max:16|min:2|unique:popular_products,title",

        'bg_img' => "require",

        'cover' => 'require',

        'desc|人气单品推荐理由(描述)' => 'min:2|max:30',

        'product_id|商品id' => 'require|number',

        'sort|排序' => 'require|number|>:0',

        'product_info' => "require|array",

        'status' => "require|number|>=:1",

        'id' => "require|number|>=:1",

        'sorts|排序' => 'require|array',

        'style_id|店铺风格' => 'number|>=:1',

        'cate_id|店铺分类' => 'number|>=:1',

        'keywords|搜索关键词' => 'min:2',

        'description|宿友推荐简介' => 'require|min:2|max:120',

        'bg_cover|宿友推荐背景图' => 'require',

        'store_list|宿友推荐店铺列表' => 'array',

        'store_id|店铺id' => 'require|number|>=:1',

        'star|推荐指数' => 'require|float|>=:0|<=:5',

        'recommended_reason|推荐理由' => 'require|min:2|max:100',

        'content' => 'require',

        'topic_id|话题id' => 'require|number|>=:1',

        'type' => 'require|number',

    ];

    protected $scene = [

        'popular_products_list' => ['page'],

        'add_popular_product' => ['title', 'bg_img', 'product_info'],

        'pop_pro' => ['title', 'cover', 'desc', 'product_id', 'sort'],

        'product_list' => ['page'],

        'edit_popular_product' => ['id', 'title', 'bg_img', 'product_info'],

        'del_pop_pro' => ['id'],

        'edit_pop_pro_status' => ['id', 'status'],

        'popular_product_info' => ['id'],

        'sort_popular_product' => ['sorts'],

        'edit_sort' => ['id', 'sort'],

        'roommate_recom_list' => ['page'],

        'top_popular_product' => ['id'],

        'store_list' => ['page', 'style_id', 'cate_id', 'keywords'],

        'add_roommate_recom' => ['title', 'description', 'bg_cover', 'store_list'],

        'roommate_recom_detail' => ['store_id', 'star', 'cover', 'title', 'recommended_reason', 'sort'],

        'edit_roommate_recom' => ['id', 'title', 'description', 'bg_cover', 'store_list'],

        'roommate_recom_info' => ['id'],

        'edit_roommate_status' => ['id', 'status'],

        'sort_roommate_recom' => ['id', 'sort'],

        'del_roommate_recom' => ['id'],

        'top_roommate_recom' => ['id'],

        'add_new_trend' => ['title', 'cover', 'content', 'topic_id'],

        'add_new_trend_store' => ['store_id', 'sort'],

        'add_new_trend_product' => ['product_id', 'sort'],

        'add_new_trend_style' => ['style_id', 'type'],

        'new_trend_info' => ['id'],

        'edit_new_trend' => ['id', 'title', 'cover', 'content', 'topic_id'],

        'new_trend_list' => ['page'],

        'edit_new_trend_status' => ['id', 'status'],

        'sort_new_trend' => ['id', 'sort'],

        'top_new_trend' => ['id'],

        'del_new_trend' => ['id']

    ];

}