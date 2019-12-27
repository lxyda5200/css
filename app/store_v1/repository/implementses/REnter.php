<?php


namespace app\store_v1\repository\implementses;


use app\store_v1\model\Brand;
use app\store_v1\model\BrandCate;
use app\store_v1\model\BrandCompany;
use app\store_v1\model\BrandLink;
use app\store_v1\model\BrandReview;
use app\store_v1\model\BrandStore;
use app\store_v1\model\GoodsCategory;
use app\store_v1\model\IndustryCategory;
use app\store_v1\model\Store;
use app\store_v1\model\StoreCategory;
use app\store_v1\model\StoreCateStore;
use app\store_v1\model\StoreCompanyInfo;
use app\store_v1\model\StoreIndustry;
use app\store_v1\model\StoreStatusLog;
use app\store_v1\model\TrademarkCert;
use think\Db;
use think\Exception;

class REnter
{
    /**
     * 获取品牌分类列表
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCateList() {
        return GoodsCategory::where(['status' => 1, 'level' => ['lt', 3]])->field('id, pid, cate_name')
            ->order('sort')
            ->select();
    }


    /**
     * 添加品牌
     * @param $cate_id
     * @param $brand_name
     * @param $brand_logo
     * @return int|string
     */
    public function addBrand($cate_id, $brand_name, $brand_logo) {
        $brand = new Brand();
        return $brand->insertGetId([
            'brand_name' => $brand_name,
            'cate_id' => $cate_id,
            'logo' => $brand_logo,
            'type' => 2,
            'status' => 0,
            'create_time' => time()
        ]);
    }


    /**
     * 获取品牌列表
     * @param $cate_id
     * @param $brand_name
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandList($cate_id, $brand_name) {
        $where = ['type' => 1, 'status' => 1, 'is_open' => 1, 'delete_time' => null];
        if($cate_id) {
            $cate_id_arr = GoodsCategory::where(['pid' => $cate_id])->column('id');
            $cate_id_arr[] = $cate_id;
            $where['cate_id'] = ['in', $cate_id_arr];
        }

        if(!empty($brand_name))
            $where['brand_name'] = ['like', "%{$brand_name}%"];

        return Brand::where($where)->field('id, brand_name, logo')->order('sort', 'desc')->select();
    }


    /**
     * 获取行业分类
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getIndustryCate() {
        return IndustryCategory::where(['status' => 1])->field('id, pid, cate_name, level')
            ->order('sort')->select();
    }


    /**
     * 添加品牌店铺关联关系
     * @param $data
     * @return int|string
     */
    public function addBrandStoreRelation($data) {
        $brand_store = new BrandStore();
        return $brand_store->insert([
            'store_id' => intval($data['store_id']),
            'brand_id' => intval($data['brand_id']),
            'type' => intval($data['brand_type'])
        ]);
    }


    /**
     * 添加店铺主营分类关系
     * @param $data
     * @return int|string
     */
    public function addStoreCateRelation($data) {
        $store_industry = new StoreIndustry();
        return $store_industry->insertAll($data);
    }


    /**
     * 添加企业经营资质信息
     * @param $data
     * @return int|string
     */
    public function addCompanyInfo($data) {
        $store_company_info = new StoreCompanyInfo();
        return $store_company_info->insert([
            'store_id' => intval($data['store_id']),
            'main_body_type' => intval($data['main_body_type']),
            'paper_type' => intval($data['paper_type']),
            'license_img' => implode(',', $data['license_img']),
            'license_name' => trimStr($data['license_name']),
            'license_no' => trimStr($data['license_no']),
            'build_time' => intval($data['build_time']),
            'open_start_time' => intval($data['open_start_time']),
            'open_end_time' => intval($data['open_end_time']),
            'card_type' => intval($data['card_type']),
            'card_img' => implode(',', $data['card_img']),
            'create_time' => time()
        ]);
    }


    /**
     * 添加品牌审核信息
     * @param $store_id
     * @param $brand_id
     * @return int|string
     */
    public function addBrandReview($store_id, $brand_id) {
        $brand_review = new BrandReview();
        $data = compact('store_id', 'brand_id');
        $data['create_time'] = time();
        return $brand_review->insert($data);
    }



    /**
     * 添加品牌信息
     * @param $is_brand
     * @param $data
     * @return bool|string
     * @throws \Exception
     */
    public function addBrandInfo($is_brand, $data) {


//        if($is_brand == -1) {
//            $insert_data['brand_img'] = implode(',', $data['brand_img']);
//        }

        Db::startTrans();
        try{
            # 添加品牌信息
//            $trademark_cert = new TrademarkCert();
//            foreach ($data['certs'] as $k => $v) {
//                $data['certs'][$k]['store_id'] = $store_id;
//                $data['certs'][$k]['create_time'] = time();
//            }
//            $res1 = $trademark_cert->saveAll($data['certs']);
//            if(!$res1)
//                throw new Exception(false);
            $brand_company = [
                'brand_id' => intval($data['brand_id']),
                'company_id' => $data['store_id'],
                'brand_time_start' => $data['brand_time_start'],
                'brand_time_end' => $data['brand_time_end'],
                'certs' => implode(',', $data['certs']),
                'type' => intval($data['brand_type']),
                'is_brand' => $is_brand,
                'brand_url' => implode(',', array_column($data['brand_link'], 'name'))
            ];
            if($is_brand == 2) {
                $brand_company['brand_img'] = implode(',', $data['brand_img']);
            }else {
                $brand_company['brand_img'] = '';
            }
            $res1 = BrandCompany::create($brand_company);
            if(!$res1)
                throw new Exception(false);



            # 添加品牌链路信息
//            if(isset($data['brand_link']) && !empty($data['brand_link'])) {
//                foreach ($data['brand_link'] as $k => $v)
//                    $data['brand_link'][$k]['store_id'] = $store_id;
//                $brand_link = new BrandLink();
//                $res2 = $brand_link->insertAll($data['brand_link']);
//                if(!$res2)
//                    throw new Exception(false);
//            }

            Db::commit();
            return true;
        }catch (Exception $exception) {
            Db::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 查看品牌信息审核状态
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function viewBrandStatus($store_id) {
        return BrandReview::where(['store_id' => $store_id])->field('status, review_note, review_time')->find();
    }


    /**
     * 查看企业资质审核状态
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function viewCompanyStatus($store_id) {
        return StoreCompanyInfo::where(['store_id' => $store_id])
            ->field('status, review_note, review_time')->find();
    }


    /**
     * 写入店铺状态记录
     * @param $store_id
     * @param $status
     * @return StoreStatusLog
     */
    public function writeStoreStatusLog($store_id, $status) {
        return StoreStatusLog::create([
            'store_id' => $store_id,
            'status' => $status
        ]);
    }


    /**
     * 获取品牌店铺关联关系
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandStoreRelation($store_id) {
        $brand_store = new BrandStore();
        return $brand_store->alias('bs')
            ->join(['brand' => 'b'], 'b.id=bs.brand_id')
            ->where(['bs.store_id' => $store_id])
            ->field('b.id, b.brand_name, b.cate_id, b.logo')
            ->find();
    }


    /**
     * 获取店铺行业分类信息
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreCateRelation($store_id) {
        $store_cate_store = new StoreIndustry();
        return $store_cate_store->alias('scs')
            ->join(['industry_category' => 'sc'], 'scs.industry_id=sc.id')
            ->where(['scs.store_id' => $store_id])
            ->field('sc.cate_name, sc.id')
            ->select();
    }


    /**
     * 获取品牌信息
     * @param $store_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandInfo($store_id) {
        $store = new Store();
        return $store->alias('s')
            ->join(['brand_company' => 'bc'], 's.id=bc.company_id')
            ->where(['s.id' => $store_id])
            ->field('bc.id as brand_company_id, bc.brand_id, s.type, bc.type as brand_type, s.partner_mode, bc.brand_url, bc.is_brand, bc.brand_time_start, bc.brand_time_end, s.has_brand, bc.brand_img, bc.certs')
            ->find();
    }


    /**
     * 获取商标证书
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
//    public function getCerts($store_id) {
//        $trademark_cert = new TrademarkCert();
//        return $trademark_cert->where(['store_id' => $store_id, 'status' => 1])
//            ->field('trademark_cert')
//            ->select();
//    }


    /**
     * 获取品牌授权链路
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
//    public function getLink($store_id) {
//        $brand_link = new BrandLink();
//        return $brand_link->where(['store_id' => $store_id])->field('id, name, sort')
//            ->order('sort', 'asc')->select();
//    }


    /**
     * 获取企业信息
     * @param $store_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCompanyInfo($store_id) {
        $store_company_info = new StoreCompanyInfo();
        return $store_company_info->where(['store_id' => $store_id])
            ->field('status, review_note, create_time, review_time', true)
            ->find();
    }


    /**
     * 更新店铺品牌关系
     * @param $store_id
     * @param $data
     * @return BrandStore
     */
    public function updateBrandInfo($store_id, $data) {
        return BrandStore::where(['store_id' => $store_id])
            ->update($data);
    }


    /**
     * 更新店铺行业分类关系
     * @param $store_id
     * @param $data
     * @return StoreCateStore|bool
     */
    public function updateStoreCate($store_id, $data) {
        $store_industry = new StoreIndustry();
        Db::startTrans();
        try {
            $res = $store_industry->where(['store_id' => $store_id])->delete();
            if($res === false)
                throw new Exception(false);

            $res1 = $store_industry->insertAll($data);
            if(!$res1)
                throw new Exception(false);
            Db::commit();
            return true;
        }catch (Exception $exception) {
            Db::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 更新品牌信息
     * @param $data
     * @return bool|string
     * @throws \Exception
     */
    public function updateBrand($data) {
        $brand_company = [
            'brand_id' => intval($data['brand_id']),
            'company_id' => intval($data['store_id']),
            'brand_time_start' => strtotime($data['brand_time_start']),
            'brand_time_end' => ($data['brand_time_end'] == 0) ? 0 : strtotime($data['brand_time_end']),
            'certs' => implode(',', $data['certs']),
            'type' => intval($data['brand_type']),
            'is_brand' => $data['is_brand'],
            'brand_url' => implode(',', array_column($data['brand_link'], 'name'))
        ];
        if ($data['is_brand'] == 2) {
            $brand_company['brand_img'] = implode(',', $data['brand_img']);
        } else {
            $brand_company['brand_img'] = '';
        }
        $brand_company_model = new BrandCompany();
        $res1 = $brand_company_model->where(['id' => $data['brand_company_id']])
            ->update($brand_company);
        if ($res1 === false)
            return false;
        return true;
    }


    /**
     * 更新企业信息
     * @param $store_id
     * @param $data
     * @return StoreCompanyInfo
     */
    public function updateCompany($store_id, $data) {
        $store_company_info = new StoreCompanyInfo();
        $status = $store_company_info->where(['store_id' => $store_id])->value('status');
        if($status != 1) {
            $update_data = [
                'main_body_type' => intval($data['main_body_type']),
                'paper_type' => intval($data['paper_type']),
                'license_img' => implode(',', $data['license_img']),
                'license_name' => trimStr($data['license_name']),
                'license_no' => trimStr($data['license_no']),
                'build_time' => strtotime($data['build_time']),
                'open_start_time' => strtotime($data['open_start_time']),
                'open_end_time' => strtotime($data['open_end_time']),
                'card_type' => intval($data['card_type']),
                'card_img' => implode(',', $data['card_img']),
                'status' => 0,
                'review_note' => '',
                'create_time' => time(),
                'review_time' => 0
            ];
        }else {
            $update_data = [
                'main_body_type' => intval($data['main_body_type']),
                'paper_type' => intval($data['paper_type']),
                'license_img' => implode(',', $data['license_img']),
                'license_name' => trimStr($data['license_name']),
                'license_no' => trimStr($data['license_no']),
                'build_time' => strtotime($data['build_time']),
                'open_start_time' => strtotime($data['open_start_time']),
                'open_end_time' => strtotime($data['open_end_time']),
                'card_type' => intval($data['card_type']),
                'card_img' => implode(',', $data['card_img'])
            ];
        }
        return $store_company_info->update($update_data, ['store_id' => intval($store_id)]);
    }
}