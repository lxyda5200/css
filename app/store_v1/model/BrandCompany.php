<?php


namespace app\store_v1\model;


use think\Model;

class BrandCompany extends Model
{
    protected $table = 'brand_company';

    protected $autoWriteTimestamp = true;

    protected $updateTime = false;

    /**
     * Ìí¼ÓµêÆÌÆ·ÅÆ
     */
    public function add_brand($data){
        return self::allowField(true)->insertGetId($data);
    }
}