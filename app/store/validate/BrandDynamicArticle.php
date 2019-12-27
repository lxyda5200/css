<?php


namespace app\store\validate;


use think\Validate;

class BrandDynamicArticle extends Validate
{
    protected $rule = [
        'title|主题' => 'require|unique:brand_dynamic_article',
        'cover|封面' => 'require',
        'type|类型' => 'require',
        'sort|排序' => 'require',
        'status|状态' => 'require',
        'video_url|视频地址' => 'require',
        'video_cover|视频封面' => 'require',
        'media_id|媒体id' => 'require',
        'media_desc|视频描述' => 'require',
        'content|新闻详情' => 'require',
        'img|图片链接' => 'require',
        'url|图片地址' => 'require',
        'desc|描述' => 'require',
        'imgs|图片集' => 'require',
        'id|id' => 'require'
    ];



    protected $scene = [
        'addNews' => ['title', 'cover', 'content', 'imgs'],
        'addVideos' => ['title', 'cover', 'imgs'],
        'addVideo' => ['title', 'cover', 'video_url', 'video_cover', 'media_id'],
        'checkType' => ['type'],
        'del' => ['id']
    ];
}