<?php


namespace app\admin\repository\implementses;


use app\admin\model\BrandLinkModel;
use app\admin\model\BrandReviewModel;
use app\admin\model\StoreStatusLog;
use app\admin\model\TrademarkCert;
use app\admin\repository\interfaces\IBrandReview;

class RBrandReview implements IBrandReview
{

    /**
     * 品牌审核列表
     * @return mixed
     */
    public function brandReviewList($where)
    {
        // TODO: Implement brandReviewList() method.
        $brand_review_model = new BrandReviewModel();
        return $brand_review_model->alias('br')
            ->join(['brand' => 'b'], 'b.id=br.brand_id')
            ->join(['brand_company' => 's'], 's.brand_id=br.brand_id')
            ->where($where)
            ->field('br.status, br.status, br.create_time, br.id, b.brand_name, b.logo, s.is_brand')
            ->paginate(10);
    }

    /**
     * 品牌审核详情
     * @param $id
     * @return mixed
     */
    public function brandReviewDetail($id)
    {
        // TODO: Implement brandReviewDetail() method.
        $brand_review_model = new BrandReviewModel();
        $data = $brand_review_model->alias('br')
            ->join(['brand' => 'b'], 'b.id=br.brand_id', 'left')
            ->join(['brand_story' => 'bs'], 'bs.brand_id=br.brand_id', 'left')
            ->join(['goods_category' => 'bc'], 'bc.id=b.cate_id', 'left')
//            ->join(['brand_company' => 's'], 's.company_id=br.store_id')
            ->join(['brand_company' => 'bcom'], 'b.id=bcom.brand_id', 'left')
            ->where(['br.id' => $id])
            ->field('bcom.brand_url, bs.notion, br.store_id, b.brand_name, b.id as brand_id, bc.cate_name, b.logo, bcom.is_brand, bcom.brand_img, bcom.brand_time_start, bcom.brand_time_end, bcom.certs')
            ->find();
        # 品牌链路
//        $brand_link = $this->getBrandLink($data['store_id']);
//        # 证书
//        $certs = $this->getCerts($data['store_id']);
        $data['notion'] = $data['notion'] == null?"":$data['notion'];
        $data['brand_url'] = empty($data['brand_url'])? [] : explode(',', $data['brand_url']);
        $data['certs'] = empty($data['certs'])?[]:explode(',', $data['certs']);
        unset($data['store_id']);
        return $data;
    }



    /**
     * 审核品牌
     * @param $id
     * @param $status
     * @param $review_note
     * @return mixed
     */
    public function brandReview($id, $status, $review_note)
    {
        // TODO: Implement brandReview() method.
        return BrandReviewModel::where(['id' => $id])
            ->update(['status' => $status, 'review_note' => $review_note, 'review_time' => time()]);
    }

    /**
     * 获取品牌链路
     * @param $store_id
     * @return mixed
     */
    public function getBrandLink($store_id)
    {
        // TODO: Implement getBrandLink() method.
        return BrandLinkModel::where(['store_id' => $store_id])
            ->field('name, sort')->order('sort', 'asc')->select();
    }

    /**
     * 获取品牌证书
     * @param $store_id
     * @return mixed
     */
    public function getCerts($store_id)
    {
        // TODO: Implement getCerts() method.
        return TrademarkCert::where(['store_id' => $store_id, 'status' => 1])
            ->field('trademark_cert')->select();
    }

    /**
     * 查看品牌审核状态
     * @param $store_id
     * @return mixed
     */
    public function reviewStatus($store_id)
    {
        // TODO: Implement reviewStatus() method.
        return BrandReviewModel::where(['store_id' => $store_id])->value('status');
    }

    /**
     * 获取店铺id
     * @param $id
     * @return mixed
     */
    public function getStoreId($id)
    {
        // TODO: Implement getStoreId() method.
        return BrandReviewModel::where(['id' => $id])->value('store_id');
    }

    /**
     * 写入日志
     * @param $store_id
     * @return mixed
     */
    public function writeLog($store_id, $status)
    {
        // TODO: Implement writeLog() method.
        return StoreStatusLog::create([
            'store_id' => $store_id,
            'status' => $status
        ]);
    }
}