<?php


namespace app\store_v1\model;


use think\Model;

class BrandReview extends Model
{
    protected $table = 'brand_review';

    protected $autoWriteTimestamp = true;

    protected $updateTime = false;
}