<?php


namespace app\admin\controller;


use app\admin\model\CateStore;
use app\admin\model\NewTrend;
use app\admin\model\NewTrendProduct;
use app\admin\model\NewTrendStore;
use app\admin\model\NewTrendStyle;
use app\admin\model\PopularProducts;
use app\admin\model\PopularProductsDetails;
use app\admin\model\ProductStyleProduct;
use app\admin\model\RoommateRecommend;
use app\admin\model\RoommateRecommendDetail;
use app\admin\model\StoreStyleStore;
use app\admin\model\StyleStore;
use app\admin\model\Topic;
use app\store\model\StyleProduct;
use think\Db;
use think\Exception;
use app\admin\validate\Operate as OperateValidate;
use app\admin\model\Product;
use app\admin\model\Store;

class Operate extends ApiBase
{

    /**
     * 人气单品-列表
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function popularProductsList(OperateValidate $operate, PopularProducts $popularProducts){
        try{
            ##验证
            $res = $operate->scene('popular_products_list')->check(input());
            if(!$res)throw new Exception($operate->getError());
            ##逻辑
            $page = input('post.page',1,'intval');
            $data = $popularProducts->field('id,title,visit_num,status,sort')->order('sort','asc')->paginate(15,false,['page'=>$page])->toArray();
            $data['max_page'] = ceil($data['total']/$data['per_page']);
            ##返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 人气单品-添加
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @param PopularProductsDetails $popularProductsDetails
     * @return \think\response\Json
     * @throws \Exception
     */
    public function addPopularProduct(OperateValidate $operate, PopularProducts $popularProducts, PopularProductsDetails $popularProductsDetails){
        $postJson = trim(file_get_contents('php://input'));
        addErrLog($postJson);
        $post = json_decode($postJson,true);

        ##验证
        $res = $operate->scene('add_popular_product')->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        ##逻辑
        Db::startTrans();
        try{
            ##插入主数据
            $title = trimStr($post['title']);
            $bg_img = trimStr($post['bg_img']);
            ###插入
            $res = $popularProducts->add(compact('title','bg_img'));
            if($res === false)throw new Exception('添加失败');
            $pop_pro_id = $popularProducts->getLastInsID();
            ##插入商品数据
            $product_info = $post['product_info'];
            if($product_info && is_array($product_info)){
                $data = [];
                $rule = [
                    'title|商品标题' => "max:30|min:2",
                ];
                foreach($product_info as $v){
                    ##验证
                    $check = $operate->scene('pop_pro')->rule($rule)->check($v);
                    if(!$check)throw new Exception($operate->getError());
                    $data[] = [
                        'title' => trimStr($v['title']),
                        'cover' => trimStr($v['cover']),
                        'desc' => trimStr($v['desc']),
                        'product_id' => intval($v['product_id']),
                        'pop_pro_id' => $pop_pro_id,
                        'sort' => intval($v['sort'])
                    ];
                }
                if($data){
                    $res = $popularProductsDetails->add($data);
                    if($res === false)throw new Exception('添加失败');
                }
            }
            Db::commit();

            ##返回
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 人气单品筛选商品列表
     * @param OperateValidate $operate
     * @param Product $product
     * @return \think\response\Json
     */
    public function productList(OperateValidate $operate, Product $product){
        try{
            ##验证
            $res = $operate->scene('product_list')->check(input());
            if(!$res)throw new Exception($operate->getError());
            ##逻辑
            $page = input('post.page',1,'intval');
            $keywords_store = input('post.keywords_store','','trimStr');
            $keywords_product = input('post.keywords_product','','trimStr');
            $product_id = input('post.product_id',0,'intval');
            $where = [
                'p.status' => 1,
                'p.sh_status' => 1,
                's.sh_status' => 1,
                's.store_status' => 1
            ];
            if($keywords_store)$where['s.store_name|s.mobile'] = ['LIKE', "%{$keywords_store}%"];
            if($keywords_product)$where['p.product_name'] = ['LIKE', "%{$keywords_product}%"];
            if($product_id)$where['p.id'] = ['NEQ', $product_id];

            ##获取列表
            $list = $product->getPopProList($where, $page);

            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取人气单品信息
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function popularProductInfo(OperateValidate $operate, PopularProducts $popularProducts){
        try{
            #验证
            $res = $operate->scene('popular_product_info')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $info = $popularProducts->getInfo();

            return json(self::callback(1,'',$info));
        }catch(Exception $e){

        }
    }

    /**
     * 热门单品-修改
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @param PopularProductsDetails $popularProductsDetails
     * @return \think\response\Json
     */
    public function editPopularProduct(OperateValidate $operate, PopularProducts $popularProducts, PopularProductsDetails $popularProductsDetails){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);

        ##验证
        $id = intval($post['id']);
        $rule = [
            'title' => "require|max:16|min:2|unique:popular_products,title,{$id}",
        ];
        $res = $operate->scene('edit_popular_product')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        ##逻辑
        ###判断数据是否存在
        if(!$popularProducts->check($id))return json(self::callback(0,'数据不存在或已删除'));
        Db::startTrans();
        try{
            ###修改主数据
            $title = trimStr($post['title']);
            $bg_img = trimStr($post['bg_img']);
            ###修改
            $res = $popularProducts->edit($id, compact('title','bg_img'));
            if($res === false)throw new Exception('添加失败');
            ###删除原有推荐商品
            $res = $popularProductsDetails->delByPopId($id);
            if($res === false)throw new Exception('删除失败');
            ###插入商品数据
            $product_info = $post['product_info'];
            if($product_info && is_array($product_info)){
                $data = [];
                $rule = [
                    'title' => "max:30|min:2",
                ];
                foreach($product_info as $v){
                    ##验证
                    $check = $operate->scene('pop_pro')->rule($rule)->check($v);
                    if(!$check)throw new Exception($operate->getError());
                    $data[] = [
                        'title' => trimStr($v['title']),
                        'cover' => trimStr($v['cover']),
                        'desc' => trimStr($v['desc']),
                        'product_id' => intval($v['product_id']),
                        'pop_pro_id' => $id,
                        'sort' => intval($v['sort'])
                    ];
                }
                if($data){
                    $res = $popularProductsDetails->add($data);
                    if($res === false)throw new Exception('添加失败');
                }
            }
            Db::commit();

            ##返回
            return json(self::callback(1,'修改成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 人气单品--删除
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function delPopularProduct(OperateValidate $operate, PopularProducts $popularProducts){
        try{
            ##验证
            $res = $operate->scene('del_pop_pro')->check(input());
            if(!$res)throw new Exception($operate->getError());

            ##逻辑
            $id = input('post.id',0,'intval');
            $res = $popularProducts->del($id);
            if($res === false)throw new Exception('删除失败');

            return json(self::callback(1,'删除成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 人气单品--更新状态
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function editPopularProductStatus(OperateValidate $operate, PopularProducts $popularProducts){
        try{
            ##验证
            $res = $operate->scene('edit_pop_pro_status')->check(input());
            if(!$res)throw new Exception($operate->getError());

            ##逻辑
            $id = input('post.id',0,'intval');
            $status = input('post.status',1,'intval');
            ###修改
            $res = $popularProducts->editStatus($id, $status);
            if($res === false)throw new Exception('修改失败');

            return json(self::callback(1,'修改成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改人气单品排序
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function sortPopularProduct(OperateValidate $operate, PopularProducts $popularProducts){
        try{

            ##验证
            $res = $operate->scene('edit_sort')->check(input());
            if(!$res)throw new Exception($operate->getError());
            ##逻辑
            Db::startTrans();
            $popularProducts->sort();
            Db::commit();
            return json(self::callback(1,'操作成功'));

        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 置顶
     * @param OperateValidate $operate
     * @param PopularProducts $popularProducts
     * @return \think\response\Json
     */
    public function topPopularProduct(OperateValidate $operate, PopularProducts $popularProducts){
        try{
            #验证
            $res = $operate->scene('top_popular_product')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            Db::startTrans();
            $popularProducts->toTop();
            Db::commit();
            return json(self::callback(1,'置顶成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 宿友推荐列表
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @return \think\response\Json
     */
    public function roommateRecomList(OperateValidate $operate, RoommateRecommend $roommateRecommend){
        try{
            #验证
            $res = $operate->scene('roommate_recom_list')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $data = $roommateRecommend->getList();

            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 宿友推荐筛选店铺列表
     * @param OperateValidate $operate
     * @param Store $store
     * @return \think\response\Json
     */
    public function storeList(OperateValidate $operate, Store $store){
        try{
            #验证
            $res = $operate->scene('store_list')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $data = $store->roommateStoreList();

            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺分类和店铺风格列表
     * @param CateStore $cateStore
     * @param StyleStore $styleStore
     * @return \think\response\Json
     */
    public function cateStoreAndStyleStore(CateStore $cateStore, StyleStore $styleStore){
        try{
            $cate_list = $cateStore->getList();
            $style_list = $styleStore->getList();
            $data = compact('cate_list','style_list');
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 添加宿友推荐
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @param RoommateRecommendDetail $roommateRecommendDetail
     * @return \think\response\Json
     */
    public function addRoommateRecom(OperateValidate $operate, RoommateRecommend $roommateRecommend, RoommateRecommendDetail $roommateRecommendDetail){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post)return json(self::callback(0,'参数缺失'));

        ##验证
        $rule = [
            'title|主题' => "require|max:16|min:2|unique:roommate_recommend,title"
        ];
        $res = $operate->scene('add_roommate_recom')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        #逻辑
        try{
            Db::startTrans();
            ##新增主信息
            $roommate_recom_id = $roommateRecommend->add($post);
            ##添加店铺
            $roommateRecommendDetail->add($post['store_list'], $roommate_recom_id);
            Db::commit();
            ##返回
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改宿友推荐
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @param RoommateRecommendDetail $roommateRecommendDetail
     * @return \think\response\Json
     */
    public function editRoommateRecom(OperateValidate $operate, RoommateRecommend $roommateRecommend, RoommateRecommendDetail $roommateRecommendDetail){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post)return json(self::callback(0,'参数缺失'));
        $id = intval($post['id']);
        ##验证
        $rule = [
            'title|主题' => "require|max:16|min:2|unique:roommate_recommend,title,{$id}"
        ];
        $res = $operate->scene('edit_roommate_recom')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        #逻辑
        try{
            Db::startTrans();
            ##更新主信息
            $roommateRecommend->edit($post);
            ##删除原有的店铺
            $roommateRecommendDetail->del($id);
            ##添加店铺
            $roommateRecommendDetail->add($post['store_list'], $id);
            Db::commit();
            ##返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 宿友推荐详情
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @return \think\response\Json
     */
    public function roommateRecomInfo(OperateValidate $operate, RoommateRecommend $roommateRecommend){
        try{
            #验证
            $res = $operate->scene('roommate_recom_info')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $info = $roommateRecommend->getInfo();
            #返回
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 宿友推荐修改状态
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @return \think\response\Json
     */
    public function editRoommateRecomStatus(OperateValidate $operate, RoommateRecommend $roommateRecommend){
        try{
            ##验证
            $rule = [
                'status' => 'require|number'
            ];
            $res = $operate->scene('edit_roommate_status')->rule($rule)->check(input());
            if(!$res)throw new Exception($operate->getError());
            ##逻辑
            $roommateRecommend->editStatus();
            ##返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除宿友推荐
     * @param OperateValidate $operate
     * @return \think\response\Json
     */
    public function delRoommateRecom(OperateValidate $operate){
        try{
            #验证
            $res = $operate->scene('del_roommate_recom')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $id = input('post.id',0,'intval');
            $res = RoommateRecommend::destroy($id);
            if($res === false)throw new Exception('操作失败');
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改宿友推荐排序
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @return \think\response\Json
     */
    public function sortRoommateRecom(OperateValidate $operate, RoommateRecommend $roommateRecommend){
        try{
            #验证
            $res = $operate->scene('sort_roommate_recom')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            Db::startTrans();
            $roommateRecommend->sort();
            Db::commit();
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 置顶宿友推荐
     * @param OperateValidate $operate
     * @param RoommateRecommend $roommateRecommend
     * @return \think\response\Json
     */
    public function topRoommateRecom(OperateValidate $operate, RoommateRecommend $roommateRecommend){
        try{
            #验证
            $res = $operate->scene('top_roommate_recom')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $roommateRecommend->toTop();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮-门店主营风格&商品风格&话题&可展示标签
     * @param StoreStyleStore $storeStyleStore
     * @param ProductStyleProduct $productStyleProduct
     * @param Topic $topic
     * @return \think\response\Json
     */
    public function newTrendStyleAndTopic(StoreStyleStore $storeStyleStore, ProductStyleProduct $productStyleProduct, Topic $topic){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);

            #逻辑
            ##话题
            $topic_list = $topic->getList();
            ##店铺风格
            $style_store_list = isset($post['store_ids'])?$storeStyleStore->getStoreStyleList($post['store_ids']):[];
            ##店铺风格
            $style_product_list = isset($post['product_ids'])?$productStyleProduct->getProductStyleList($post['product_ids']):[];
            ##需要显示的风格
            $show_styles = isset($post['show_styles'])?$post['show_styles']:[];

            $show_style_list = [];

            foreach($style_product_list as $k=> $v){
                $style_product_list[$k]['is_checked'] = false;
                foreach($show_styles as $kk=> $vv){
                    if($vv['style_id'] == $v['style_id'] && $vv['type'] == $v['type']){
                        $v['sort'] = $kk;
                        $show_style_list[] = $v;
                        $style_product_list[$k]['is_checked'] = true;
                    }
                }
            }

            foreach($style_store_list as $k=> $v){
                $style_store_list[$k]['is_checked'] = false;
                foreach($show_styles as $kk=> $vv){
                    if($vv['style_id'] == $v['style_id'] && $vv['type'] == $v['type']){
                        $v['sort'] = $kk;
                        $show_style_list[] = $v;
                        $style_store_list[$k]['is_checked'] = true;
                    }
                }
            }
            ##保持展示的排序和传入的参数的排序一致
            array_multisort(array_column($show_style_list,'sort'),SORT_ASC,$show_style_list);

            ##返回
            return json(self::callback(1,'',compact('topic_list','style_store_list','style_product_list','show_style_list')));

        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增时尚新潮
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @param NewTrendStore $newTrendStore
     * @param NewTrendProduct $newTrendProduct
     * @param NewTrendStyle $newTrendStyle
     * @return \think\response\Json
     */
    public function addNewTrend(OperateValidate $operate, NewTrend $newTrend, NewTrendStore $newTrendStore, NewTrendProduct $newTrendProduct, NewTrendStyle $newTrendStyle){
        #接收参数
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数错误'));

        #验证
        $rule = [
            'title' => 'require|min:2|max:16|unique:new_trend,title',
            'cover' => 'require'
        ];
        $res = $operate->scene('add_new_trend')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        Db::startTrans();
        #逻辑
        try{
            ##添加主信息
            $new_trend_id = $newTrend->add($post);

            ##绑定店铺
            if(isset($post['store_list']) && is_array($post['store_list']) && !empty($post['store_list'])){
                $newTrendStore->add($post['store_list'], $new_trend_id);
            }

            ##绑定商品
            if(isset($post['product_list']) && is_array($post['product_list']) && !empty($post['product_list'])){
                $newTrendProduct->add($post['product_list'], $new_trend_id);
            }

            ##绑定风格
            if(isset($post['style_list']) && is_array($post['style_list']) && !empty($post['style_list'])){
                $newTrendStyle->add($post['style_list'], $new_trend_id);
            }

            Db::commit();
            #返回
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮信息
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @return \think\response\Json
     */
    public function newTrendInfo(OperateValidate $operate, NewTrend $newTrend){
        try{
            #验证
            $res = $operate->scene('new_trend_info')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #数据
            $info = $newTrend->getInfo();

            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 更新时尚新潮
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @param NewTrendStore $newTrendStore
     * @param NewTrendProduct $newTrendProduct
     * @param NewTrendStyle $newTrendStyle
     * @return \think\response\Json
     */
    public function editNewTrend(OperateValidate $operate, NewTrend $newTrend, NewTrendStore $newTrendStore, NewTrendProduct $newTrendProduct, NewTrendStyle $newTrendStyle){
        #接收参数
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数错误'));
        $new_trend_id = intval($post['id']);

        #验证
        $rule = [
            'title' => "require|min:2|max:16|unique:new_trend,title,{$new_trend_id}",
            'cover' => 'require'
        ];
        $res = $operate->scene('edit_new_trend')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$operate->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##更新主信息
            $newTrend->edit($post);

            ##绑定店铺
            $newTrendStore->del($new_trend_id);
            if(isset($post['store_list']) && is_array($post['store_list'])){
                $newTrendStore->add($post['store_list'], $new_trend_id);
            }

            ##绑定商品
            $newTrendProduct->del($new_trend_id);
            if(isset($post['product_list']) && is_array($post['product_list'])){
                $newTrendProduct->add($post['product_list'], $new_trend_id);
            }

            ##绑定风格
            $newTrendStyle->del($new_trend_id);
            if(isset($post['style_list']) && is_array($post['style_list'])){
                $newTrendStyle->add($post['style_list'], $new_trend_id);
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
     * 时尚新潮列表
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @return \think\response\Json
     */
    public function newTrendList(OperateValidate $operate, NewTrend $newTrend){
        try{
            #验证
            $res = $operate->scene('new_trend_list')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $data = $newTrend->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮修改状态
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @return \think\response\Json
     */
    public function editNewTrendStatus(OperateValidate $operate, NewTrend $newTrend){
        try{
            #验证
            $res = $operate->scene('edit_new_trend_status')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $newTrend->editStatus();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮修改排序
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @return \think\response\Json
     */
    public function sortNewTrend(OperateValidate $operate, NewTrend $newTrend){
        try{
            #验证
            $res = $operate->scene('sort_new_trend')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            Db::startTrans();
            $newTrend->sort();
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮 置顶
     * @param OperateValidate $operate
     * @param NewTrend $newTrend
     * @return \think\response\Json
     */
    public function topNewTrend(OperateValidate $operate, NewTrend $newTrend){
        try{
            #验证
            $res = $operate->scene('top_new_trend')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $newTrend->toTop();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 时尚新潮 删除
     * @param OperateValidate $operate
     * @return \think\response\Json
     */
    public function delNewTrend(OperateValidate $operate){
        try{
            #验证
            $res = $operate->scene('del_new_trend')->check(input());
            if(!$res)throw new Exception($operate->getError());
            #逻辑
            $id = input('post.id',0,'intval');
            $res = NewTrend::destroy($id);
            if($res === false)throw new Exception('操作失败');
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}