<?php


namespace app\admin\model;


use think\Model;

class BrandReviewModel extends Model
{
    protected $table = 'brand_review';

    /**
     * 审核状态
     * @param $val
     * @return mixed
     */
    public function getStatusAttr($val) {
        $status = [
            0 => '待审核',
            1 => '审核通过',
            2 => '审核失败'
        ];
        return $status[$val];
    }
}