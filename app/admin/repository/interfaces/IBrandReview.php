<?php


namespace app\admin\repository\interfaces;


interface IBrandReview
{
    /**
     * 品牌审核列表
     * @return mixed
     */
    public function brandReviewList($where);


    /**
     * 品牌审核详情
     * @param $id
     * @return mixed
     */
    public function brandReviewDetail($id);


    /**
     * 审核品牌
     * @param $id
     * @param $status
     * @param $review_note
     * @return mixed
     */
    public function brandReview($id, $status, $review_note);


    /**
     * 获取品牌链路
     * @param $store_id
     * @return mixed
     */
    public function getBrandLink($store_id);


    /**
     * 获取品牌证书
     * @param $store_id
     * @return mixed
     */
    public function getCerts($store_id);


    /**
     * 查看品牌审核状态
     * @param $store_id
     * @return mixed
     */
    public function reviewStatus($store_id);


    /**
     * 获取店铺id
     * @param $id
     * @return mixed
     */
    public function getStoreId($id);


    /**
     * 写入日志
     * @param $store_id
     * @return mixed
     */
    public function writeLog($store_id, $status);
}