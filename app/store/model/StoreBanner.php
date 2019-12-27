<?php


namespace app\store\model;


use think\Model;

class StoreBanner extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'store_banner';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 关闭更新时间
     * @var bool
     */
    protected $updateTime = false;

    /**
     * banner类型获取器
     * @param $val
     * @return mixed
     */
    public function getBannerTypeAttr($val) {
        $banner_type = [
            1 => 'banner广告'
        ];
        return $banner_type[$val];
    }
}