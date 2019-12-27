<?php


namespace app\store_v1\validate;


use think\Validate;

class Enter extends Validate
{
    protected $rule = [
        'has_brand|是否有品牌' => 'require',
        'is_brand|是否为品牌商' => 'requireIf:has_brand,1',
        'certs|商标证书' => 'requireIf:has_brand,1',
        'brand_time_start|品牌有效期开始时间' => 'requireIf:has_brand,1',
        'brand_time_end|品牌有效期结束时间' => 'requireIf:has_brand,1',
//        'brand_img|品牌授权证书' => 'requireIf:has_brand,1|requireIf:is_brand,2',
        'main_body_type|主体类型' => 'require',
        'paper_type|证件类型' => 'require',
        'license_img|营业执照电子版' => 'require',
        'license_name|营业执照名称' => 'require',
        'license_no|营业执照注册号' => 'require',
        'build_time|成立时间' => 'require',
        'open_start_time|营业开始时间' => 'require',
        'open_end_time|营业结束时间' => 'require',
        'card_type|经营者证件类型' => 'require',
        'card_img|经营者证件电子版' => 'require',
        'brand_id|品牌id' => 'requireIf:has_brand,1',
        'type|品牌类型' => 'require',
        'store_id' => 'require',
        'cate_store_id|主营分类' => 'require'
    ];


    protected $scene = [
        'addBrandInfo' => ['is_brand', 'certs', 'brand_time_start', 'brand_time_end'],   // 添加品牌信息
        'addCompanyInfo' => [            // 添加企业资质信息
            'main_body_type',
            'paper_type',
            'license_img',
            'license_name',
            'license_no',
            'build_time',
            'open_start_time',
            'open_end_time',
            'card_type',
            'card_img'
        ],
        'addBrandStore' => ['brand_id', 'type', 'store_id'],   // 添加品牌店铺关系
        'addStoreCate' => ['store_id', 'cate_store_id'],       // 添加店铺主营分类关系
        'addBrandReview' => ['store_id', 'brand_id'],          // 添加品牌审核信息
    ];
}