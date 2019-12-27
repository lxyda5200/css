<?php


namespace app\admin\validate;


use think\Validate;

class Brand extends Validate
{

    protected $rule = [

        'brand_name' => 'require|min:1|max:16|unique:brand,brand_name',

        'cate_id' => 'require|number|>=:1',

        'is_open' => 'require|number',

        'logo' => 'require',

        'page' => 'number|>=:1',

        'banners' => 'require|array',

        'history' => 'max:500',

        'notion' => 'max:500',

        'products' => 'array',

        'brand_id' => 'require|number|>=:1',

        'url' => 'require',

        'type' => 'require|number',

        'product_id' => 'require|number|>=:1',

        'id' => 'require|number|>=:1',

        'brand_story_id' => 'require|number|>=:1',

        'title' => 'require|max:20',

        'link_type' => 'require|number',

        'sort' => 'require|number|>=:1',

        'articles' => 'array',

        'video_url' => 'require',

        'video_cover' => 'require',

        'media_id' => 'require',

        'imgs' => 'require|array',

        'content' => 'require|min:2',

        'desc' => 'require|min:2|max:200',

        'is_cover' => 'require|number',

        'article_id' => 'require|number|>=:1',

        'brand_dynamic_id' => 'require|number|>=:1',

        'status' => 'require|number|>=:0'

    ];

    protected $scene = [

        'add_brand' => ['brand_name', 'is_open', 'cate_id', 'logo'],

        'product_list' => ['page'],

        'add_brand_story' => ['brand_story_id', 'banners', 'history', 'notion', 'products'],

        'add_brand_store_ads' => ['url', 'type'],

        'add_brand_product' => ['product_id'],

        'brand_story_info' => ['id'],

        'edit_brand_story' => ['brand_story_id', 'brand_id', 'banners', 'history', 'notion', 'products'],

        'brand_info' => ['id'],

        'edit_brand' => ['id', 'brand_name', 'is_open', 'cate_id', 'logo'],

        'add_brand_dynamic' => ['brand_id', 'banners', 'articles'],

        'add_brand_dynamic_ads' => ['brand_dynamic_id', 'title', 'type', 'url', 'link_type'],

        'add_brand_dynamic_article_1' => ['brand_dynamic_id', 'title', 'cover', 'type', 'video_url', 'video_cover', 'media_id', 'media_desc'],

        'add_brand_dynamic_article_2' => ['brand_dynamic_id', 'title', 'cover', 'type', 'imgs'],

        'add_brand_dynamic_article_3' => ['brand_dynamic_id', 'title', 'cover', 'type', 'imgs', 'content'],

        'add_brand_dynamic_picture' => ['url', 'desc', 'is_cover', 'sort'],

        'add_brand_dynamic_news_imgs' => ['url', 'is_cover', 'sort'],

        'brand_dynamic_ads_list' => ['brand_dynamic_id'],

        'brand_dynamic_article_list' => ['brand_id', 'page'],

        'brand_dynamic_article_info' => ['article_id'],

        'edit_brand_dynamic_ads' => ['id', 'title', 'type', 'url', 'link_type'],

        'del_brand_dynamic_ads' => ['id'],

        'sort_brand_dynamic_ads' => ['id', 'sort'],

        'edit_brand_dynamic_article_1' => ['id', 'title', 'cover', 'type', 'video_url', 'video_cover', 'media_id', 'media_desc'],

        'edit_brand_dynamic_article_2' => ['id', 'title', 'cover', 'type', 'imgs'],

        'edit_brand_dynamic_article_3' => ['id', 'title', 'cover', 'type', 'imgs', 'content'],

        'sort_brand_dynamic_article' => ['id', 'sort', 'brand_dynamic_id'],

        'top_brand_dynamic_article' => ['brand_dynamic_id', 'id'],

        'del_brand_dynamic_article' => ['id'],

        'brand_list' => ['page'],

        'del_brand' => ['id'],

        'edit_brand_is_open' => ['id', 'is_open'],

        'edit_brand_dynamic_article_status' => ['id', 'status'],

        'review' => ['id', 'status']

    ];

}