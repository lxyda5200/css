<?php


namespace app\admin\controller;


use app\admin\model\BrandCate;
use app\admin\model\BrandDynamic;
use app\admin\model\BrandDynamicAds;
use app\admin\model\BrandDynamicArticle;
use app\admin\model\BrandProduct;
use app\admin\model\BrandReviewModel;
use app\admin\model\BrandStory;
use app\admin\model\BrandStoryAds;
use app\admin\repository\implementses\RBrandReview;
use app\admin\repository\implementses\RStoreCompanyInfo;
use app\admin\tool\Tool;
use app\common\controller\AliSMS;
use think\Db;
use think\Exception;
use app\admin\validate\Brand;
use app\admin\model\Brand as BrandModel;
use app\admin\model\Product;

class BrandApi extends ApiBase
{

    /**
     * 获取品牌分类列表
     * @param BrandCate $brandCate
     * @return \think\response\Json
     */
    public function brandCateList(BrandCate $brandCate){
        try{
            $list = $brandCate->getList();
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增品牌主信息
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function addBrand(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $res = $brand->scene('add_brand')->check(input());
            if($res === false)throw new Exception($brand->getError());
            #逻辑
            $brandModel->add();
            #返回
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 筛选商品
     * @param Brand $brand
     * @param Product $product
     * @return \think\response\Json
     */
    public function productList(Brand $brand, Product $product){
        try{
            #验证
            $res = $brand->scene('product_list')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $data = $product->getProList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增品牌故事
     * @param Brand $brand
     * @param BrandStory $brandStory
     * @param BrandStoryAds $brandStoryAds
     * @param BrandProduct $brandProduct
     * @return \think\response\Json
     */
//    public function editBrandStory(Brand $brand, BrandStory $brandStory, BrandStoryAds $brandStoryAds, BrandProduct $brandProduct){
//
//        $postJson = trim(file_get_contents('php://input'));
//        $post = json_decode($postJson,true);
//        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));
//
//        #验证
//        $res = $brand->scene('add_brand_story')->check($post);
//        if(!$res)return json(self::callback(0,$brand->getError()));
//
//        #逻辑
//        Db::startTrans();
//        try{
//            ##添加品牌故事主信息
//            $brand_story_id = $brandStory->edit($post);
//            ##添加广告(banner)
//            $brandStoryAds->add($brand_story_id, $post);
//            ##添加经典款商品
//            if(isset($post['products']) && is_array($post['products'])){
//                $brandProduct->add($post);
//            }
//            Db::commit();
//            #返回
//            return json(self::callback(1,'品牌故事添加成功'));
//        }catch(Exception $e){
//            Db::rollback();
//            return json(self::callback(0,$e->getMessage()));
//        }
//    }

    /**
     * 获取品牌故事信息
     * @param Brand $brand
     * @param BrandStory $brandStory
     * @return \think\response\Json
     */
    public function brandStoryInfo(Brand $brand, BrandStory $brandStory){
        try{
            #验证
            $res = $brand->scene('brand_story_info')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $info = $brandStory->getInfo();
            #返回
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 更新品牌故事
     * @param Brand $brand
     * @param BrandStory $brandStory
     * @param BrandStoryAds $brandStoryAds
     * @param BrandProduct $brandProduct
     * @return \think\response\Json
     */
    public function editBrandStory(Brand $brand, BrandStory $brandStory, BrandStoryAds $brandStoryAds, BrandProduct $brandProduct){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $res = $brand->scene('edit_brand_story')->check($post);
        if(!$res)return json(self::callback(0,$brand->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##添加品牌故事主信息
            $brand_story_id = $brandStory->edit($post);
            ##添加广告(banner)
            $brandStoryAds->del($brand_story_id);
            $brandStoryAds->add($brand_story_id, $post);
            ##添加经典款商品
            $brandProduct->del($post);
            if(isset($post['products']) && is_array($post['products'])){
                $brandProduct->add($post);
            }
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 获取品牌主信息
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function brandInfo(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $res = $brand->scene('brand_info')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $info = $brandModel->getInfo();
            #返回
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改品牌信息
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function editBrand(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $id = input('post.id',0,'intval');
            $rule = [
                'brand_name' => "require|min:1|max:16|unique:brand,brand_name,{$id}",
            ];
            $res = $brand->scene('edit_brand')->rule($rule)->check(input());
            if($res === false)throw new Exception($brand->getError());
            #逻辑
            $brandModel->edit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增时尚动态
     * @param Brand $brand
     * @param BrandDynamic $brandDynamic
     * @param BrandDynamicAds $brandDynamicAds
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function addBrandDynamic(Brand $brand, BrandDynamic $brandDynamic, BrandDynamicAds $brandDynamicAds, BrandDynamicArticle $brandDynamicArticle){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $res = $brand->scene('add_brand_dynamic')->check($post);
        if(!$res)return json(self::callback(0,$brand->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##添加动态
            $brand_dynamic_id = $brandDynamic->add($post);
            ##增加广告位(banner)
            $brandDynamicAds->add($brand_dynamic_id, $post);
            ##增加资讯集
            if(isset($post['articles']) && is_array($post['articles'])){
                $brandDynamicArticle->add($brand_dynamic_id, $post);
            }
            Db::commit();
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 新增品牌动态广告
     * @param Brand $brand
     * @param BrandDynamicAds $brandDynamicAds
     * @return \think\response\Json
     */
    public function addBrandDynamicAds(Brand $brand, BrandDynamicAds $brandDynamicAds){
        try{
            #验证
            $res = $brand->scene('add_brand_dynamic_ads')->check(input());
            if(!$res)return json(self::callback(0,$brand->getError()));
            #逻辑
            $brandDynamicAds->addOne();
            #返回
            return json(self::callback(1,'新增成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取品牌动态广告列表
     * @param Brand $brand
     * @param BrandDynamicAds $brandDynamicAds
     * @return \think\response\Json
     */
    public function brandDynamicAdsList(Brand $brand, BrandDynamicAds $brandDynamicAds){
        try{
            #验证
            $res = $brand->scene('brand_dynamic_ads_list')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $data = $brandDynamicAds->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑时尚动态广告
     * @param Brand $brand
     * @param BrandDynamicAds $brandDynamicAds
     * @return \think\response\Json
     */
    public function editBrandDynamicAds(Brand $brand, BrandDynamicAds $brandDynamicAds){
        try{
            #验证
            $res = $brand->scene('edit_brand_dynamic_ads')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicAds->edit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除时尚动态广告
     * @param Brand $brand
     * @param BrandDynamicAds $brandDynamicAds
     * @return \think\response\Json
     */
    public function delBrandDynamicAds(Brand $brand, BrandDynamicAds $brandDynamicAds){
        try{
            #验证
            $res = $brand->scene('del_brand_dynamic_ads')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicAds->del();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 排序时尚动态广告
     * @param Brand $brand
     * @param BrandDynamicAds $brandDynamicAds
     * @return \think\response\Json
     */
    public function sortBrandDynamicAds(Brand $brand, BrandDynamicAds $brandDynamicAds){
        try{
            #验证
            $res = $brand->scene('sort_brand_dynamic_ads')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicAds->sort();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增时尚动态资讯
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function addBrandDynamicArticle(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $type = $post['type'];
        $res = $brand->scene("add_brand_dynamic_article_{$type}")->check($post);
        if(!$res)return json(self::callback(0,$brand->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##增加资讯集
            $brandDynamicArticle->addOne($post);
            Db::commit();
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 品牌动态资讯列表
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function brandDynamicArticleList(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        try{
            #验证
            $res = $brand->scene('brand_dynamic_article_list')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $data = $brandDynamicArticle->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取品牌动态资讯信息
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function brandDynamicArticleInfo(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        try{
            #验证
            $res = $brand->scene('brand_dynamic_article_info')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $data = $brandDynamicArticle->getInfo();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 更新时尚动态资讯
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function editBrandDynamicArticle(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $type = $post['type'];
        $res = $brand->scene("edit_brand_dynamic_article_{$type}")->check($post);
        if(!$res)return json(self::callback(0,$brand->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##增加资讯集
            $brandDynamicArticle->edit($post);
            Db::commit();
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 排序时尚动态资讯
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function sortBrandDynamicArticle(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        try{
            #验证
            $res = $brand->scene('sort_brand_dynamic_article')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicArticle->sort();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 置顶时尚动态资讯
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function topBrandDynamicArticle(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        try{
            #验证
            $res = $brand->scene('top_brand_dynamic_article')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicArticle->toTop();
            #逻辑
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除时尚动态
     * @param Brand $brand
     * @return \think\response\Json
     */
    public function delBrandDynamicArticle(Brand $brand){
        try{
            #验证
            $res = $brand->scene('del_brand_dynamic_article')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $id = input('post.id',0,'intval');
            BrandDynamicArticle::destroy($id);
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 便捷品牌动态资讯状态
     * @param Brand $brand
     * @param BrandDynamicArticle $brandDynamicArticle
     * @return \think\response\Json
     */
    public function editBrandDynamicArticleStatus(Brand $brand, BrandDynamicArticle $brandDynamicArticle){
        try{
            #验证
            $res = $brand->scene('edit_brand_dynamic_article_status')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandDynamicArticle->editStatus();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取品牌列表
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function brandList(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $res = $brand->scene('brand_list')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $data = $brandModel->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除品牌
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function delBrand(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $res = $brand->scene('del_brand')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            Db::startTrans();
            $brandModel->del();
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑品牌开放状态
     * @param Brand $brand
     * @param BrandModel $brandModel
     * @return \think\response\Json
     */
    public function editBrandIsOpen(Brand $brand, BrandModel $brandModel){
        try{
            #验证
            $res = $brand->scene('edit_brand_is_open')->check(input());
            if(!$res)throw new Exception($brand->getError());
            #逻辑
            $brandModel->editIsOpen();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 品牌审核列表
     * @param RBrandReview $brandReview
     * @return \think\response\Json
     */
    public function reviewBrandList(RBrandReview $brandReview) {
        $params = input('post.');
        $where = ['br.status' => 0];
        if(isset($params['key']) && !empty($params['key']))
            $where['b.brand_name'] = ['like', "%{$params['key']}%"];
        if(isset($params['is_brand']) && !empty($params['is_brand']))
            $where['s.is_brand'] = $params['is_brand'];
        if(isset($params['time']) && !empty($params['time'])) {
            $time = explode('-', $params['time']);
            $where['br.create_time'] = ['egt', strtotime($time[0])];
            $where['br.create_time'] = ['elt', strtotime($time[1])];
        }

        $list = $brandReview->brandReviewList($where)->toArray();

        if(!$list['data'])
            return json(self::callback(1, '暂无数据', [
                'total' => 0,
                'per_page' => 10,
                'current_page' => 1,
                'last_page' => 1,
                'data' => []
            ]));

        return json(self::callback(1, 'success', $list));
    }


    /**
     * 品牌审核详情
     * @param RBrandReview $brandReview
     * @return \think\response\Json
     */
    public function brandReviewDetail(RBrandReview $brandReview) {
        $id = input('post.id', 0, 'intval');
        if(!$id)
            return json(self::callback(0, '参数缺失'));

        $detail = $brandReview->brandReviewDetail($id);
        $detail['brand_time_start'] = date('Y-m-d', $detail['brand_time_start']);
        $detail['brand_time_end'] = $detail['brand_time_end']==0?"长期":date('Y-m-d', $detail['brand_time_end']);
        return json(self::callback(1, 'success', $detail));
    }


    /**
     * 审核品牌信息
     * @param RBrandReview $brandReview
     * @return \think\response\Json
     */
    public function brandReview(RBrandReview $brandReview, RStoreCompanyInfo $companyInfo) {
        $params = input('post.');
        # 数据验证
        $validate = new Brand();
        if(!$validate->scene('review')->check($params))
            return json(self::callback(0, $validate->getError()));

        Db::startTrans();
        try{
            # 店铺id
            $store_id = $brandReview->getStoreId(intval($params['id']));
            if($params['status'] == 1) {
                # 查看企业资质审核状态
                $company_status = $companyInfo->reviewStatus($store_id);
                if($company_status == 1) {
                    $res = Db::table('user_and_store')->insert([
                        'store_id' => $store_id,
                        'create_time' => time()
                    ]);
                    if(!$res)
                        throw new Exception('操作失败');

                    # 修改品牌显示状态
                    $brand_id = BrandReviewModel::where(['id' => intval($params['id'])])->value('brand_id');
                    $res3 = \app\admin\model\Brand::update(['status' => 1], ['id' => $brand_id]);
                    if($res3 === false)
                        throw new Exception('品牌显示状态修改失败');

                    # 写入日志
                    $res1 = $brandReview->writeLog($store_id, 2);
                    if(!$res1)
                        throw new Exception('日志记录失败');

                    # 写入合作期限
                    $res2 = \app\admin\model\Store::update([
                        'start_time' => time(),
                        'end_time' => strtotime('+1 year', time())
                    ], ['id' => $store_id]);
                    if($res2 === false)
                        throw new Exception('合作期限写入失败');

                    # 修改店铺状态
                    $res3 = \app\admin\model\Store::where(['id' => $store_id])->update([
                        'sh_status' => 1,
                        'status' => 1,
                        'store_status' => 1
                    ]);
                    if($res3 === false)
                        throw new Exception('store状态修改失败');


                    # 发送邮件
                    $data = Db::table('business')
                        ->where(['store_id' => $store_id, 'main_id' => $store_id, 'group_id' => 0])
                        ->field('email, mobile')->find();
                    if($data['email'])
                        Tool::ton_email($data['email'], '您好！您提交的入驻资质已经审核通过');

                    # 发送短息
                    AliSMS::sendEntryStatus($data['mobile'], 'send_success');
                }
            }else {

                # 修改店铺状态
                $res3 = \app\admin\model\Store::where(['id' => $store_id])->update([
                    'sh_status' => -1,
                    'status' => 2,
                    'store_status' => 0,
                    'reason' => trimStr(isset($params['review_note'])?$params['review_note']:'')
                ]);
                if($res3 === false)
                    throw new Exception('store状态修改失败');

                # 写入日志
                $res1 = $brandReview->writeLog($store_id, 6);
                if(!$res1)
                    throw new Exception('日志记录失败!');

                # 发送邮件
                $data = Db::table('business')
                    ->where(['store_id' => $store_id, 'main_id' => $store_id, 'group_id' => 0])
                    ->field('email, mobile')->find();
                if($data['email'])
                    Tool::ton_email($data['email'], '您好！您提交的入驻资质审核未通过');

                # 发送短息
                AliSMS::sendEntryStatus($data['mobile'], 'send_fail');
            }

            $res = $brandReview->brandReview(intval($params['id']), intval($params['status']), trimStr(isset($params['review_note'])?$params['review_note']:''));
            if($res === false)
                throw new Exception('操作失败啦');

            Db::commit();
            return json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            Db::rollback();
            return json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 品牌分类列表
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function brandCate() {
        $params = input('post.');
        $size = isset($params['size'])?$params['size']:10;
        $where = [];
        if(isset($params['brand_name']) && !empty($params['brand_name']))
            $where['b.brand_name'] = ['like', "%{$params['brand_name']}%"];
        if(isset($params['cate_pid']) && !empty($params['cate_pid']))
            $where['b.cate_pid'] = $params['cate_pid'];
        if (isset($params['cate_id']) && !empty($params['cate_id']))
            $where['b.cate_id'] = $params['cate_id'];
        $brand_model = new \app\admin\model\Brand();
        $data = $brand_model->alias('b')
            ->join(['goods_category' => 'gc'], 'gc.id=b.cate_id')
            ->join(['goods_category' => 'gc1'], 'gc1.id=b.cate_pid')
            ->where($where)
            ->field('b.id, b.brand_name, b.logo, gc.cate_name, gc1.cate_name as cate_pname')
            ->paginate($size);
        return json(self::callback(1, 'success', $data));
    }


    /**
     * 添加品牌分类信息
     * @return \think\response\Json
     */
    public function addBrandCate() {
        $params = input('post.');
        $brand_model = new \app\admin\model\Brand();
        $insert_data = [
            'cate_pid' => intval($params['cate_pid']),
            'cate_id' => intval($params['cate_id']),
            'brand_name' => trimStr($params['brand_name']),
            'logo' => trimStr($params['logo'])
        ];
        $res = $brand_model->insert($insert_data);
        if(!$res)
            return json(self::callback(0, '添加失败'));

        return json(self::callback(1, '添加成功'));
    }


    /**
     * 获取品牌分类详情
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function brandCateDetail() {
        $params = input('post.');
        $brand_model = new \app\admin\model\Brand();
        $detail = $brand_model->alias('b')
            ->join(['goods_category' => 'gc'], 'gc.id=b.cate_id')
            ->join(['goods_category' => 'gc1'], 'gc1.id=b.cate_pid')
            ->where(['b.id' => $params['id']])
            ->field('b.id, b.brand_name, b.logo, gc.cate_name, gc1.cate_name as cate_pname, gc.id as cate_id, gc1.id as cate_pid')
            ->find();
        return json(self::callback(1, 'success', $detail));
    }


    /**
     * 更新品牌分类信息
     * @return \think\response\Json
     */
    public function updateBrandCate() {
        $params = input('post.');

        $brand_model = new \app\admin\model\Brand();
        $id = intval($params['id']);
        unset($params['id']);
        $res = $brand_model->update($params, ['id' => $id]);
        if($res === false)
            return json(self::callback(0, '更新失败'));

        return json(self::callback(1, '更新成功'));
    }

}