<?php


namespace app\admin\repository\interfaces;


interface IStoreCompanyInfo
{
    /**
     * 审核列表
     * @param $where
     * @return mixed
     */
    public function reviewList($where, $per_page);


    /**
     * 审核详情
     * @param $id
     * @return mixed
     */
    public function reviewDetail($id);


    /**
     * 审核
     * @param $id
     * @param $status
     * @param $review_note
     * @return mixed
     */
    public function review($id, $status, $review_note);


    /**
     * 获取店铺id
     * @param $id
     * @return mixed
     */
    public function getStoreId($id);


    /**
     * 查看企业资质审核状态
     * @param $store_id
     * @return mixed
     */
    public function reviewStatus($store_id);


    /**
     * 记录日志
     * @param $store_id
     * @param $status
     * @return mixed
     */
    public function writeLog($store_id, $status);
}