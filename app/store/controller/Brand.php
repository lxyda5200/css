<?php


namespace app\store\controller;


use app\common\controller\Base;
use app\store\common\Store;
use app\store\model\BrandCate;
use app\store\model\BrandDynamic;
use app\store\model\BrandDynamicAds;
use app\store\model\BrandProduct;
use app\store\model\BrandStore;
use app\store\model\BrandStory;
use app\store\model\BrandStoryAds;
use app\store\validate\BrandDynamicArticle;
use think\Db;
use think\Exception;
use think\Request;
use think\response\Json;

class Brand extends Base
{
    /**
     * 获取知名品牌列表
     * @param \app\store\model\Brand $brand
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function getFamousBrandList(\app\store\model\Brand $brand) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $data = $brand->getFamousBrandList();
            if(!$data['data'])
                throw new Exception('没有更多数据了');

            return json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 获取知名品牌信息
     * @param BrandStore $brandStore
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getFamousBrand(BrandStore $brandStore) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Brand();
            if(!$validate->scene('getFamousBrand')->check($params))
                throw new Exception($validate->getError());

            $data = $brandStore->getFamousBrand($params['store_id']);
            if(!$data)
                throw new Exception('暂无信息');

            return \json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 添加/更新知名品牌
     * @param \app\store\model\Brand $brand
     * @param BrandStore $brandStore
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function saveFamousBrand(\app\store\model\Brand $brand, BrandStore $brandStore) {
        Db::startTrans();
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\BrandStore();
            if(!$validate->scene('add')->check($params))
                return \json(self::callback(1, $validate->getError()));

            # 判断是否存在该门店
            $exits = $brandStore->exitsStore($params['store_id'], 1);
            if(!$exits) {
                # 新增
                $res = $brandStore->addRelation($params, 1);
                if(!$res)
                    throw new Exception('操作失败');

                Db::commit();
                return \json(self::callback(1, '操作成功'));
            }
            # 更新
            $res1 = $brandStore->updateRelation($params, 1);
            if($res1 === false)
                throw new Exception('操作失败');

            Db::commit();
            return \json(self::callback(1, '操作成功'));

        }catch (Exception $exception) {
            Db::rollback();
            return \json(self::callback(0, $exception->getMessage()));
        }

    }


    /**
     * 获取品牌分类信息
     * @param BrandCate $brandCate
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrandCate(BrandCate $brandCate) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $data = $brandCate->getBrandCate();
            if(!$data)
                throw new Exception('暂无数据');

            return \json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(1, $exception->getMessage()));
        }
    }


    /**
     * 获取自有品牌/品牌故事信息
     * @param \app\store\model\Brand $brand
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getSelfBrandStory(\app\store\model\Brand $brand) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 获取品牌id
            $brand_id = BrandStore::getBrandId(intval($params['store_id']), 2);

            # 获取品牌基本信息
            $base_info = $brand->brandBaseInfo($brand_id);

            # 获取品牌故事广告
            $ads = BrandStoryAds::getAds($brand_id);

            # 获取品牌故事信息
            $story_info = BrandStory::getStoryInfo($brand_id);

            # 获取经典款商品
            $product = BrandProduct::getGoods($brand_id);

            $data = [
                'base_info' => $base_info,
                'ads' => $ads,
                'story_info' => $story_info,
                'product' => $product
            ];

            if(!$base_info)
                return \json(self::callback(0, '还未添加'));

            return \json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getPrevious()));
        }

    }


    /**
     * 获取店铺产品数据
     * @param \app\store\model\Product $product
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreProducts(\app\store\model\Product $product) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            $key = isset($params['key'])?$params['key']:'';
            $data = $product->storeProductMin(intval($params['store_id']), trimStr($key));
            if(!$data)
                throw new Exception('暂无数据');

            return \json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }



    /**
     * 添加/修改自有品牌品牌故事
     * @param \app\store\model\Brand $brand
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function saveSelfBrandStory(\app\store\model\Brand $brand, BrandStore $brandStore) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Brand();
            if(!$validate->scene('selfBrandStory')->check($params))
                throw new Exception($validate->getError());

            # 验证品牌名称是否重复
            $res1 = $brand->exitsBrandName($params['brand_name'], intval($params['store_id']));
            if($res1)
                throw new Exception('品牌名称重复');

            # 查看是否存在自有品牌
            $res2 = $brandStore->exitsStore(intval($params['store_id']), 2);
            if(!$res2) {
                # 插入
                $res = $brand->insertSelfBrand($params);
                if(is_string($res))
                    throw new Exception($res);

                return \json(self::callback(1, '操作成功'));
            }

            # 查看品牌id
            $brand_id = Db::table('brand_store')
                ->where(['store_id' => $params['store_id'], 'type' => 2])->value('brand_id');
            $params['brand_id'] = $brand_id;

            # 更新
            $res3 = $brand->updateSelfBrand($params);
            if(!$res3)
                throw new Exception('操作失败2');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 添加时尚动态广告位
     * @param BrandDynamic $brandDynamic
     * @param BrandDynamicAds $ads
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function saveBrandDynamicAds(BrandDynamic $brandDynamic, BrandDynamicAds $ads) {
        Db::startTrans();
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            $params['create_time'] = time();

            # 数据验证
            $validate = new \app\store\validate\BrandDynamicAds();
            if(!$validate->scene('addAds')->check($params))
                throw new Exception($validate->getError());

            # 查询是否存在自有品牌时尚动态
            $res = $brandDynamic->exitsBrandDynamic(intval($params['store_id']));

            # 获取品牌id
            $brand_id = BrandStore::getBrandId(intval($params['store_id']), 2);
            if(!$brand_id)
                return \json(self::callback(0, '请先添加自有品牌'));

            if(!$res) {
                # 新增
                $dynamic_id = $brandDynamic->addRelation($brand_id);
                if(!$dynamic_id)
                    throw new Exception('操作失败');

                $params['brand_dynamic_id'] = $dynamic_id;
                unset($params['token']);
                unset($params['store_id']);
                $params['status'] = 1;
                $res = $ads->addDynamicAds($params);
                if(!$res)
                    throw new Exception('操作失败');

                Db::commit();
                return \json(self::callback(1, '操作成功'));
            }
            # 更新
            $dynamic_id = Db::table('brand_dynamic')->where(['brand_id'=>$brand_id])->value('id');
            $params['brand_dynamic_id'] = $dynamic_id;
            unset($params['token']);
            unset($params['store_id']);
            $params['status'] = 1;
            $res1 = $ads->addDynamicAds($params);
            if(!$res1)
                throw new Exception('操作失败');

            Db::commit();
            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            Db::rollback();
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 编辑时尚动态广告位
     * @param BrandDynamicAds $ads
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editBrandDynamicAds(BrandDynamicAds $ads) {
        try{
            $id = input('param.id', 0);
            if(!$id)
                return \json(self::callback(0, '参数错误'));

            $data = input('post.');

            if(\request()->isPost()) {
                ##token 验证
                $store_info = Store::checkToken();
                if ($store_info instanceof Json){
                    return $store_info;
                }

                # 数据验证
                $validate = new \app\store\validate\BrandDynamicAds();
                if(!$validate->scene('addAds')->check($data))
                    throw new Exception($validate->getError());

                if(isset($data['id']))
                    unset($data['id']);

                # 修改数据
                unset($data['store_id']);
                unset($data['token']);
                $res = $ads->editDynamicAds($id, $data);
                if($res === false)
                    throw new Exception('操作失败');


                return \json(self::callback(1, '操作成功'));
            }else {
                # 返回数据用于渲染回填
                $data = $ads->getDynamicAds($id);
                if(!$data)
                    throw new Exception('参数错误');

                return \json(self::callback(1, 'success', $data));
            }
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 删除时尚动态广告
     * @param BrandDynamicAds $ads
     * @return Json
     */
    public function delBrandDynamicAds(BrandDynamicAds $ads) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $id = input('id', 0);
            if(!$id)
                return \json(self::callback(0, '参数错误'));

            $res = $ads->delDynamicAds($id);
            if($res === false)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }


    /**
     * 获取时尚动态广告列表
     * @param BrandDynamicAds $ads
     * @param BrandDynamic $brandDynamic
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getAdsList(BrandDynamicAds $ads, BrandDynamic $brandDynamic) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $param = input('post.');
            $dynamic_id = $brandDynamic->getDynamicId($param['store_id']);
            if(!$dynamic_id)
                return \json(self::callback(0, '数据丢失'));
            $data = $ads->getDynamicAdsList($dynamic_id);
            if(!$data)
                throw new Exception('暂无数据');

            return \json(self::callback(1, 'success', $data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 添加时尚动态咨询集
     * @param BrandDynamic $brandDynamic
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function addDynamic(BrandDynamic $brandDynamic) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new BrandDynamicArticle();
            if(!$validate->scene('checkType')->check($params))
                throw new Exception($validate->getError());

            $dynamic_id = $brandDynamic->getDynamicId($params['store_id']);
            if(!$dynamic_id)
                return \json(self::callback(0, '请先创建自有品牌'));

            $params['brand_dynamic_id'] = $dynamic_id;
            $params['create_time'] = time();

            switch ($params['type']) {
                case 3:
                    # news
                    # 数据验证
                    $rule = [
                        'title|主题' => "require|unique:brand_dynamic_article"
                    ];
                    if(!$validate->scene('addNews')->rule($rule)->check($params))
                        throw new Exception($validate->getError());

                    $res = \app\store\model\BrandDynamicArticle::addNews($params);
                    if(!$res)
                        throw new Exception('添加失败');

                    break;
                case 2:
                    # 影集
                    # 数据验证
                    $rule = [
                        'title|主题' => 'require|unique:brand_dynamic_article'
                    ];
                    if(!$validate->scene('addVideos')->rule($rule)->check($params))
                        throw new Exception($validate->getError());

                    unset($params['store_id']);
                    unset($params['token']);
                    $params['create_time'] = time();
                    $res = \app\store\model\BrandDynamicArticle::addVideos($params);
                    if(!$res)
                        throw new Exception('添加失败');

                    break;
                case 1:
                    # 视频
                    # 数据验证
                    $rule = [
                        'title|主题' => 'require|unique:brand_dynamic_article'
                    ];
                    if(!$validate->scene('addVideo')->rule($rule)->check($params))
                        throw new Exception($validate->getError());

                    unset($params['store_id']);
                    unset($params['token']);
                    $res = \app\store\model\BrandDynamicArticle::addVideo($params);
                    if(!$res)
                        throw new Exception('添加失败');

                    break;
                default:
                    break;
            }
            return \json(self::callback(1, '添加成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 删除动态
     * @param \app\store\model\BrandDynamicArticle $article
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function delDynamic(\app\store\model\BrandDynamicArticle $article) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new BrandDynamicArticle();
            if(!$validate->scene('del')->check($params))
                throw new Exception($validate->getError());

            $res = $article->delDynamic($params['id']);
            if(!$res)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 更改显示状态
     * @param \app\store\model\BrandDynamicArticle $article
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeView(\app\store\model\BrandDynamicArticle $article) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new BrandDynamicArticle();
            if(!$validate->scene('del')->check($params))
                throw new Exception($validate->getError());

            $params['status'] = $params['status'] == 1?2:1;
            $res = $article->changeView($params['id'], $params['status']);
            if($res === false)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 获取咨询列表
     * @param BrandDynamic $brandDynamic
     * @param \app\store\model\BrandDynamicArticle $article
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getDynamicList(BrandDynamic $brandDynamic, \app\store\model\BrandDynamicArticle $article) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 时尚动态id
            $brand_dynamic_id = $brandDynamic->getDynamicId($params['store_id']);

            if(!$brand_dynamic_id)
                return \json(self::callback(0, '暂无数据'));

            $list = $article->getList($brand_dynamic_id);
            if(!$list)
                throw new Exception('暂无数据');

            return \json(self::callback(1, 'success', $list));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 编辑资讯
     * @param \app\store\model\BrandDynamicArticle $article
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function editDynamic(\app\store\model\BrandDynamicArticle $article, BrandDynamic $brandDynamic) {
        try{
            $id = input('param.id');
            if (\request()->isPost()) {
                ##token 验证
                $store_info = Store::checkToken();
                if ($store_info instanceof Json){
                    return $store_info;
                }

                $data = input('post.');

                $dynamic_id = $brandDynamic->getDynamicId($data['store_id']);
                if(!$dynamic_id)
                    return \json(self::callback(0, '数据丢失'));

                $data['brand_dynamic_id'] = $dynamic_id;
                unset($data['store_id']);
                unset($data['token']);
                if(isset($data['id']))
                    unset($data['id']);

                $validate = new BrandDynamicArticle();

                switch ($data['type']) {
                    case 1:
                        # 视频

                        # 数据验证
                        $rule = [
                            'title|主题' => "require|unique:brand_dynamic_article, title, {$id}"
                        ];
                        if(!$validate->scene('addVideo')->rule($rule)->check($data))
                            throw new Exception($validate->getError());

                        $res = \app\store\model\BrandDynamicArticle::updateVideo($id, $data);
                        if(!$res)
                            throw new Exception('修改失败');

                        break;
                    case 2:
                        # 影集

                        # 数据验证
                        $rule = [
                            'title|主题' => "require|unique:brand_dynamic_article, title, {$id}"
                        ];
                        if(!$validate->scene('addVideos')->rule($rule)->check($data))
                            throw new Exception($validate->getError());

                        $res = \app\store\model\BrandDynamicArticle::updateVideos($id, $data);
                        if(!$res)
                            throw new Exception('修改失败');

                        break;
                    case 3:
                        # news

                        # 数据验证
                        $rule = [
                            'title|主题' => "require|unique:brand_dynamic_article, title, {$id}"
                        ];
                        if(!$validate->scene('addVideos')->rule($rule)->check($data))
                            throw new Exception($validate->getError());

                        $res = \app\store\model\BrandDynamicArticle::updateNews($id, $data);
                        if(!$res)
                            throw new Exception('修改失败');

                        break;
                    default:
                        break;
                }

                return \json(self::callback(1, '修改成功'));
            }else {
                $data = $article->getInfo($id);
                if(!$data)
                    throw new Exception('参数错误');

                return \json(self::callback(1, 'success', $data));
            }
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 更改广告故事广告排序
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeStoryAdSort() {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Brand();
            if(!$validate->scene('sort')->check($params))
                throw new Exception($validate->getError());

            # 排序
            $res = BrandStoryAds::changeSort($params['id'], $params['store_id'], $params['sort']);
            if(!$res)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 更改时尚动态广告位排序
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeDynamicAdSort() {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Brand();
            if(!$validate->scene('sort')->check($params))
                throw new Exception($validate->getError());

            # 排序
            $res = BrandDynamicAds::changeSort($params['id'], $params['store_id'], $params['sort']);
            if(!$res)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 更改咨询集排序
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeDynamicSort() {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Brand();
            if(!$validate->scene('sort')->check($params))
                throw new Exception($validate->getError());

            # 排序
            $res = \app\store\model\BrandDynamicArticle::changeSort($params['id'], $params['store_id'], $params['sort']);
            if(!$res)
                throw new Exception('操作失败');

            return \json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 获取时尚动态展示状态
     * @param BrandStore $store
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function dynamicStatus(BrandStore $store) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            $status = $store->getDynamicStatus(intval($params['store_id']));
            if(!$status)
                throw new Exception('参数错误');

            return \json(self::callback(1, 'success', ['is_show_dynamic' => $status]));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 修改时尚动态展示状态
     * @param BrandStore $store
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeDynamicStatus(BrandStore $store) {
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            $params = input('post.');
            $status = $params['status'] == 1?2:1;
            $res = $store->changeDynamicStatus(intval($params['store_id']), $status);
            if($res === false)
                throw new Exception('修改失败');

            return \json(self::callback(1, '修改成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }
}