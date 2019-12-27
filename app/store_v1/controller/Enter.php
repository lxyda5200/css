<?php


namespace app\store_v1\controller;



use app\store_v1\model\BrandCompany;
use app\store_v1\model\BrandReview;
use app\store_v1\model\Store;
use app\store_v1\model\StoreUser;
use app\store_v1\model\TempEntry;
use app\store_v1\repository\implementses\REnter;
use think\Db;
use think\Exception;

class Enter extends Base
{
    protected $noNeedLogin = ['saveTempData'];


    /**
     * 构建子孙树
     * @param $a
     * @param int $pid
     * @return array
     */
    private function makeTree($a,$pid = 0){
        $tree = array();
        foreach ($a as $v) {
            if ($v['pid'] == $pid) {
                $v['children'] = $this->makeTree($a, $v['id']);
                if ($v['children'] == null) {
                    unset($v['children']);
                }

                $tree[] = $v;
            }
        }
        return $tree;
    }


    /**
     * 获取品牌列表
     * @param REnter $enter
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandList(REnter $enter) {
        $params = input('post.');
        $cate_id = isset($params['cate_id'])?$params['cate_id']:0;
        $brand_name = isset($params['key'])?$params['key']:'';
        $data = $enter->getBrandList(intval($cate_id), trimStr($brand_name));
        if(!$data)
            $this->ajaxReturn(0, '暂无数据');

        $this->ajaxReturn(1, 'success', $data);
    }


    /**
     * 获取品牌分类列表
     * @param REnter $enter
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCateList(REnter $enter) {
        $data = $enter->getCateList();
        if(!$data)
            $this->ajaxReturn(0, '暂无分类');
        $data = $this->makeTree($data);
        $this->ajaxReturn(1, 'success', $data);
    }


    /**
     * 添加品牌
     * @param $params
     * @param REnter $enter
     */
    public function addBrand(REnter $enter) {
        $params = input('post.');
        # 数据验证
        $validate = new \app\store_v1\validate\Brand();
        if(!$validate->scene('addBrand')->check($params))
            $this->ajaxReturn(0,$validate->getError());

        $brand_id = $enter->addBrand(intval($params['cate_id']), trimStr($params['brand_name']), trimStr($params['logo']));
        if(!$brand_id)
            $this->ajaxReturn(0, '添加失败');

        $this->ajaxReturn(1, '添加成功', ['brand_id' => $brand_id, 'logo' => $params['logo']]);
    }


    /**
     * 获取行业分类
     * @param REnter $enter
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getIndustryCate(REnter $enter) {
        $data = $enter->getIndustryCate();
        if(!$data)
            $this->ajaxReturn(0, '暂无数据');

        $data = $this->makeTree($data);
        $this->ajaxReturn(1, 'success', $data);

    }


    /**
     * 添加品牌信息
     * @param REnter $enter
     * @throws \Exception
     */
    public function addBrandInfo($params, REnter $enter) {
//        $params = input('post.');
        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->scene('addBrandInfo')->check($params))
            return $validate->getError();
        $params['brand_time_start'] = strtotime($params['brand_time_start']);
        $params['brand_time_end']==0?($params['brand_time_end']=0):($params['brand_time_end']=strtotime($params['brand_time_end']));

        $res = $enter->addBrandInfo(intval($params['is_brand']), $params);
        if(!$res)
            return false;
        else
            return true;
    }


    /**
     * 添加企业资质信息
     * @param $params
     * @param REnter $enter
     * @return array|bool
     */
    public function addCompanyInfo($params, REnter $enter) {
        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->scene('addCompanyInfo')->check($params))
            return $validate->getError();
        $params['open_start_time'] = strtotime($params['open_start_time']);
        $params['open_end_time']==0?($params['open_end_time']=0):($params['open_end_time']=strtotime($params['open_end_time']));
        $params['build_time'] = strtotime($params['build_time']);
        $res = $enter->addCompanyInfo($params);
        if(!$res)
            return false;

        return true;
    }


    /**
     * /添加品牌店铺关联关系
     * @param $params
     * @param REnter $enter
     * @return array|bool
     */
    public function addBrandStoreRelation($params, REnter $enter) {
        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->scene('addBrandStore')->check($params))
            return $validate->getError();
        $res = $enter->addBrandStoreRelation($params);
        if(!$res)
            return false;

        return true;
    }


    /**
     * 添加店铺行业分类关系
     * @param $params
     * @param REnter $enter
     * @return array|bool
     */
    public function addStoreCateRelation($params, REnter $enter) {
        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->scene('addStoreCate')->check($params))
            return $validate->getError();
        $data = [];
        foreach ($params['cate_store_id'] as $k => $v) {
            $data[$k]['store_id'] = intval($params['store_id']);
            $data[$k]['industry_id'] = $v;
        }
        $res = $enter->addStoreCateRelation($data);
        if(!$res)
            return false;

        return true;
    }


    /**
     * 添加品牌审核信息
     * @param $params
     * @param REnter $enter
     * @return array|bool
     */
    public function addBrandReview($params, REnter $enter) {
        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->scene('addBrandReview')->check($params))
            return $validate->getError();
        $res = $enter->addBrandReview(intval($params['store_id']), intval($params['brand_id']));
        if(!$res)
            return false;

        return true;
    }


    /**
     * 提交资料
     * @throws \Exception
     */
    public function submitInfo(REnter $enter) {
        $params = input('post.');
        Db::startTrans();
        try{
            $insert_data = [
//            'is_brand' => $is_brand,
//            'brand_time_start' => $data['brand_time_start'],
//            'brand_time_end' => $data['brand_time_end'],
                'type' => intval($params['type']),
                'has_brand' => intval($params['has_brand']),
                'partner_mode' => intval(isset($params['partner_mode'])?$params['partner_mode']:0),
                'business_img' => $params['license_img']
            ];

            # 添加店铺基本信息
            $store = new Store();
            $store_id = $store->insertGetId($insert_data);
            if(!$store_id)
                throw new Exception('店铺信息添加失败');
            $params['store_id'] = $store_id;

            if($params['has_brand'] == 1) {
                # 添加品牌信息，获取到store_id用于之后的操作
                $res1 = $this->addBrandInfo($params, new REnter());
                if(is_string($res1))
                    throw new Exception($store_id);
                if(!$res1)
                    throw new Exception('品牌信息提交失败');

                # 添加品牌审核信息
                $res = $this->addBrandReview($params, new REnter());
                if(is_string($res))
                    throw new \Exception($res);
                if(!$res)
                    throw new \Exception('品牌审核信息提交失败');

                # 添加店铺品牌关系
                $res = $this->addBrandStoreRelation($params, new REnter());
                if(is_string($res))
                    throw new Exception($res);
                if(!$res)
                    throw new Exception('店铺品牌关系提交失败');
            }

            # 添加店铺行业分类关系
            $res = $this->addStoreCateRelation($params, new REnter());
            if(is_string($res))
                throw new Exception($res);
            if(!$res)
                throw new Exception('店铺行业分类关系提交失败');

            # 添加企业资质信息
            $res = $this->addCompanyInfo($params, new REnter());
            if(is_string($res))
                throw new Exception($res);
            if(!$res)
                throw new Exception('企业资质提交失败');

            # 添加店铺状态记录
            $res = $enter->writeStoreStatusLog($store_id, 1);
            if(!$res)
                throw new Exception('日志记录失败');
            
            # 绑定账号
            $res = StoreUser::up_StoreUser($this->store_info['user_id'],$store_id);
            if(is_string($res))
                throw new Exception($res);
            if($res === false)
                throw new Exception('提交失败');

            Db::commit();
            $this->ajaxReturn(1, '提交成功');
        }catch (Exception $exception) {
            Db::rollback();
            $this->ajaxReturn(0, $exception->getMessage());
        }
    }


    /**
     * 查看审核状态
     * @param REnter $enter
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function viewStatus(REnter $enter) {
        $store_id = input('post.store_id', 0, 'intval');
        if(!$store_id)
            $this->ajaxReturn(0, '参数缺失');

        $return = [
            'status' => 1,
            'brand' => ['msg' => '品牌审核通过', 'review_note' => '', 'review_time' => ''],
            'company' => ['msg' => '企业资质审核通过', 'review_note' => '', 'review_time' => '']
        ];
        # 查看品牌信息审核状态
        $brand_status = $enter->viewBrandStatus($store_id);
        if(!is_null($brand_status)) {
            if($brand_status['status'] == 2) {
                $return['status'] = 0;
                $return['brand']['msg'] = '品牌审核失败';
                $return['brand']['review_note'] = $brand_status['review_note'];
                $return['brand']['review_time'] = date('Y-m-d H:i:s', $brand_status['review_time']);
            }
            if($brand_status['status'] == 0) {
                $return['brand']['msg'] = '品牌待审核';
                $return['status'] = 0;
            }
        }else {
            $return['brand']['msg'] = '';
        }

        # 查看企业资质审核状态
        $company_status = $enter->viewCompanyStatus($store_id);
        if($company_status['status'] == 2) {
            $return['status'] = 0;
            $return['company']['msg'] = '企业资质审核失败';
            $return['company']['review_note'] = $company_status['review_note'];
            $return['company']['review_time'] = date('Y-m-d H:i:s', $company_status['review_time']);
        }
        if($company_status['status'] == 0) {
            $return['company']['msg'] = '企业资质待审核';
            $return['status'] = 0;
        }

        if($company_status['status'] == 0 && !is_null($brand_status)) {
            if($brand_status['status'] == 0) {
                $return['status'] = 2;
                $return['company']['msg'] = '企业资质待审核';
                $return['brand']['msg'] = '品牌待审核';
            }
        }

        $this->ajaxReturn(1, 'success', $return);
    }


    /**
     * 获取信息
     * @param REnter $enter
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo(REnter $enter) {
        $store_id = input('post.store_id', 0, 'intval');
        if(!$store_id)
            $this->ajaxReturn(0, '参数缺失');

        # 获取关联品牌信息
        $brand_store_relation = $enter->getBrandStoreRelation($store_id);

        # 获取经营分类信息
        $store_cate_relation = $enter->getStoreCateRelation($store_id);

        # 获取品牌信息
        $brand_info = $enter->getBrandInfo($store_id);
        $brand_info['brand_time_start'] = date('Y-m-d', $brand_info['brand_time_start']);
        $brand_info['brand_time_end'] = $brand_info['brand_time_end']==0?0:date('Y-m-d', $brand_info['brand_time_end']);
//        $certs = $enter->getCerts($store_id);
//        $link = $enter->getLink($store_id);
        $brand_info['certs'] = explode(',', $brand_info['certs']);
        $brand_info['brand_url'] = explode(',', $brand_info['brand_url']);
//        $brand_info['link'] = $link;

        # 获取企业资料
        $company_info = $enter->getCompanyInfo($store_id);
        $company_info['build_time'] = date('Y-m-d', $company_info['build_time']);
        $company_info['open_start_time'] = date('Y-m-d', $company_info['open_start_time']);
        $company_info['open_end_time'] = $company_info['open_end_time']==0?0:date('Y-m-d', $company_info['open_end_time']);

        # 获取第一步保存的数据
        $user_id = Db::table('business')->where(['main_id' => $store_id])->order('id', 'desc')->value('id');
        $step_one_data = TempEntry::where(["company_id" => $user_id, 'step' => 1])
            ->value('content');

        $brand_link = $brand_info['brand_url'];
        foreach ($brand_link as $k => $item) {
            $brand_link[$k] = ['name' => $item, 'sort' => $k + 1];
        }


        # 获取品牌审核id
        $brand_review_id = BrandReview::where(['store_id' => $store_id, 'brand_id' => $brand_info['brand_id']])->value('id');

        $return = [
            'has_brand' => $brand_info['has_brand'],
            'partner_mode' => $brand_info['partner_mode'],
            'is_brand' => $brand_info['is_brand'],
            'certs' => $brand_info['certs'],
            'brand_time_start' => $brand_info['brand_time_start'],
            'brand_time_end' => $brand_info['brand_time_end'],
            'brand_img' => explode(',', $brand_info['brand_img']),
            'main_body_type' => $company_info['main_body_type'],
            'paper_type' => $company_info['paper_type'],
            'license_img' => explode(',', $company_info['license_img']),
            'license_name' => $company_info['license_name'],
            'license_no' => $company_info['license_no'],
            'build_time' => $company_info['build_time'],
            'open_start_time' => $company_info['open_start_time'],
            'open_end_time' => $company_info['open_end_time'],
            'card_type' => $company_info['card_type'],
            'card_img' => explode(',', $company_info['card_img']),
            'brand_id' => $brand_info['brand_id'],
            'type' => $brand_info['type'],
            'cate_store_id' => array_column($store_cate_relation, 'id'),
            'brand_link' => $brand_link,
            'brand_type' => $brand_info['brand_type'],
            'step_one_data' => json_decode(html_entity_decode($step_one_data), true),
            'brand_company_id' => $brand_info['brand_company_id'],
            'brand_review_id' => $brand_review_id
        ];

        $this->ajaxReturn(1, 'success', $return);
    }


    /**
     * 更新信息
     * @param REnter $enter
     * @throws \Exception
     */
    public function updateInfo(REnter $enter) {
        $params = input('post.');
        if(isset($params['step_one_data'])) {
            unset($params['step_one_data']);
        }

        # 数据验证
        $validate = new \app\store_v1\validate\Enter();
        if(!$validate->check($params))
            $this->ajaxReturn(0, $validate->getError());

        $store_id = intval($params['store_id']);
        Db::startTrans();
        try {
            if($params['has_brand'] == 1) {
                # 更新品牌店铺关联关系
                $brand_data = [
                    'brand_id' => intval($params['brand_id']),
                    'type' => intval($params['brand_type'])
                ];
                $res = $enter->updateBrandInfo($store_id, $brand_data);
                if($res === false)
                    throw new Exception('品牌店铺关联关系更新失败');
            }

            # 更新品牌审核信息
            $status = BrandReview::where(['id' => $params['brand_review_id']])
                ->value('status');
            if($status != 1) {
                $res4 = BrandReview::where(['id' => $params['brand_review_id']])->update([
                    'brand_id' => $params['brand_id'],
                    'status' => 0,
                    'review_note' => '',
                    'create_time' => time(),
                    'review_time' => 0
                ]);
                if($res4 === false)
                    throw new Exception('品牌审核更新失败');
            }

            # 更新行业类别店铺关联关系
            $data = [];
            foreach ($params['cate_store_id'] as $k => $v) {
                $data['store_id'] = intval($params['store_id']);
                $data['industry_id'] = $v;
            }
            $res1 = $enter->updateStoreCate($store_id, $data);
            if($res1 === false)
                throw new Exception('更新行业类别店铺关系失败');

            if($params['has_brand'] == 1) {
                # 更新品牌信息
                # 更新店铺基本信息
                $insert_data = [
                    'type' => intval($params['type']),
                    'has_brand' => intval($params['has_brand']),
                    'partner_mode' => intval(isset($params['partner_mode'])?$params['partner_mode']:0),
                    'business_img' => $params['license_img']
                ];

                $store = new Store();
                $res = $store->update($insert_data, ['id' => $store_id]);
                if($res === false)
                    throw new Exception('更新店铺基本信息失败');

                $res2 = $enter->updateBrand($params);
                if(!$res2)
                    throw new \Exception('更新品牌信息失败');
            }

            # 更新企业资质信息
            $res3 = $enter->updateCompany($store_id, $params);
            if($res3 === false)
                throw new Exception('企业资质信息更新失败');

            Db::commit();
            $this->ajaxReturn(1, '提交成功');
        }catch (Exception $exception) {
            Db::rollback();
            $this->ajaxReturn(0, $exception->getMessage());
        }
    }


    /**
     * 保存临时数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function saveTempData() {
        $params = input('post.');

        $model = new TempEntry();
        $is_exists = $model->where(['company_id' => $params['company_id'], 'step' => $params['step']])
            ->find();
        if($is_exists) {
            $res = $model->where(['company_id' => $params['company_id'], 'step' => $params['step']])->update($params);
        }else {
            $res = $model->isUpdate(false)->save($params);
        }

        if($res === false)
            $this->ajaxReturn(0, 'false');

        $this->ajaxReturn(1, 'success');
    }


    /**
     * 获取临时数据
     */
    public function getTempData() {
        $params = input('post.');
        $model = new TempEntry();
        $data = $model->where(["company_id" => $params['company_id'], 'step' => $params['step']])
            ->value('content');
        if(!$data)
            $this->ajaxReturn(1, '未填写数据');

        $this->ajaxReturn(1, 'success', json_decode(html_entity_decode($data)));
    }

}