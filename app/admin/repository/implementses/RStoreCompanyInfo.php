<?php


namespace app\admin\repository\implementses;


use app\admin\model\StoreCompanyInfo;
use app\admin\model\StoreStatusLog;
use app\admin\repository\interfaces\IStoreCompanyInfo;

class RStoreCompanyInfo implements IStoreCompanyInfo
{

    /**
     * 审核列表
     * @param $where
     * @return mixed
     */
    public function reviewList($where, $per_page)
    {
        // TODO: Implement reviewList() method.
        $store_conpany_info = new StoreCompanyInfo();
        return $store_conpany_info->alias('sci')
            ->join(['store' => 's'], 's.id=sci.store_id')
            ->field('s.type, sci.id, sci.main_body_type, sci.license_name, sci.license_no, sci.open_start_time, sci.open_end_time, sci.build_time, sci.create_time, sci.status')
            ->order('sci.create_time')
            ->paginate($per_page)->toArray();
    }

    /**
     * 审核详情
     * @param $id
     * @return mixed
     */
    public function reviewDetail($id)
    {
        // TODO: Implement reviewDetail() method.
        $store_company_info = new StoreCompanyInfo();
        return $store_company_info->alias('sci')
            ->join(['store' => 's'], 'sci.store_id=s.id')
            ->where(['sci.id' => $id])
            ->field('s.type, sci.main_body_type, sci.paper_type, sci.license_img, sci.license_name, sci.license_no, sci.build_time, sci.open_start_time, sci.open_end_time, sci.card_type, sci.card_img')
            ->find();
    }

    /**
     * 审核
     * @param $id
     * @param $status
     * @param $review_note
     * @return mixed
     */
    public function review($id, $status, $review_note)
    {
        // TODO: Implement review() method.
        return StoreCompanyInfo::where(['id' => $id])
            ->update(['status' => $status, 'review_note' => $review_note, 'review_time' => time()]);
    }

    /**
     * 获取店铺id
     * @param $id
     * @return mixed
     */
    public function getStoreId($id)
    {
        // TODO: Implement getStoreId() method.
        return StoreCompanyInfo::where(['id' => $id])->value('store_id');
    }

    /**
     * 查看企业资质审核状态
     * @param $store_id
     * @return mixed
     */
    public function reviewStatus($store_id)
    {
        // TODO: Implement reviewStatus() method.
        return StoreCompanyInfo::where(['store_id' => $store_id])->value('status');
    }


    /**
     * 记录日志
     * @param $store_id
     * @param $status
     * @return mixed
     */
    public function writeLog($store_id, $status)
    {
        // TODO: Implement writeLog() method.
        return StoreStatusLog::create([
            'status' => $status,
            'store_id' => $store_id
        ]);
    }
}