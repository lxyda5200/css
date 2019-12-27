<?php


namespace app\admin\model;


use think\Model;

class StoreCompanyInfo extends Model
{
    protected $table = 'store_company_info';

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


    /**
     * 营业开始时间
     * @param $val
     * @return false|string
     */
    public function getOpenStartTimeAttr($val) {
        return date('Y-m-d', $val);
    }


    /**
     * 营业结束时间
     * @param $val
     * @return false|string
     */
    public function getOpenEndTimeAttr($val) {
        return $val==0?'长期':date('Y-m-d', $val);
    }


    /**
     * 成立时间
     * @param $val
     * @return false|string
     */
    public function getBuildTimeAttr($val) {
        return date('Y-m-d', $val);
    }


    /**
     * 主体类型
     * @param $val
     * @return mixed
     */
    public function getMainBodyTypeAttr($val) {
        $type = [
            1 => '个体工商户',
            2 => '公司'
        ];
        return $type[$val];
    }


    /**
     * 证件类型
     * @param $val
     * @return mixed
     */
    public function getPaperTypeAttr($val) {
        $type = [
            1 => '普通营业执照',
            2 => '多证合一营业执照（统一社会信用代码）'
        ];
        return $type[$val];
    }


    /**
     * 经营者证件类型
     * @param $val
     * @return mixed
     */
    public function getCardTypeAttr($val) {
        $type = [
            1 => '身份证',
            2 => '护照'
        ];
        return $type[$val];
    }
}