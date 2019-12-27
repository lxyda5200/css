<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/5
 * Time: 13:47
 */

namespace app\user\controller;

use app\common\controller\Base;
use app\user\model\Giftpack;
use app\user\model\GiftpackOrder;
use app\user\model\MemberOrder;
use app\user\model\Product as Pmodel;
use think\Cache;
use think\Db;
use app\user\common\User;
use think\response\Json;
use think\Config;
class Product extends Base
{

    /**
     * 获取店铺分类
     */
    public function getStoreCategory(){
        $web_url=Config::get('web_path');

        try{
            $data = Db::name('store_category')->field('id,category_name')->where('is_show',1)->where('client_type',2)->order('paixu','asc')->select();

            foreach ($data as $k=>$v){
                $category_img = Db::name('store_category_img')->field('id,img_url,type,link,product_id,store_id')->where('category_id',$v['id'])->select();
                foreach ($category_img as $k1=>$v1){
                    if ($v1['type'] == 3) {
                        $category_img[$k1]['link'] = "{$web_url}/user/index/store_banner_p/id/{$v1['id']}.html";
                    }
                    if ($v1['type'] == 1) {
                        $category_img[$k1]['product_specs'] = Db::name('product_specs')->where('product_id',$v1['product_id'])->value('product_specs');
                    }
                }

                $data[$k]['category_img'] = $category_img;
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺列表
     */
    public function storeList(){

        try{

            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;
            $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
            $city = $this->request->has('city') ? $this->request->param('city') : '' ;

            /*$version = input('version');
            if (!isset($version)) {
                return \json(self::callback(0,'版本需要更新，【我的】-【设置】-【检查更新】'),400);
            }*/

            if (!$category_id || !$page || !$size) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if ($lng == '0.0') {
                $lng = '';
            }
            if ($lat == '0.0') {
                $lat = '';
            }
           $order['is_zhiding'] = 'desc';  //新增普通店铺置顶排序
            if ($lat || $lng) {
                $range = 10000000;	//距离  km

                $wh = "6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        )  <= $range  ";

                $field = ",ROUND(
        6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS distance";

                $order['distance'] = 'asc';
            }
            $order['id'] = 'desc';

            $where['sh_status'] = ['eq',1]; //审核状态 1 为通过
            $where['type'] = ['eq',1];//区分普通店铺和会员店铺 1,为普通店铺2,为会员店铺
            $where['sh_type'] = ['eq',2];
            $where['store_status'] = ['eq',1]; //是否禁用 1为启用
            if ($city){
                $where['city'] = ['like',"$city%"];
            }

            $total = Db::name('store')->where('category_id',$category_id)->where($where)->count();
            $list = Db::name('store')
                ->field('id,cover,store_name,description,dianzan,brand_name'.$field)
                ->where('category_id',$category_id)
                ->where($where)
                ->where($wh)
                ->page($page,$size)
                ->order($order)
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                $img = Db::view('store_img','img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
                foreach ($img as $k2=>$v2){

                    //todo 潮搭标签商品信息
                    if ($v2['chaoda_id'] != 0){
                        $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
                        foreach ($tag_product_info as $k3=>$v3){
                            $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
                            $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
                            $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
                        }

                        $img[$k2]['tag_product_info'] = $tag_product_info;
                    }

                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();

                    $img[$k2]['price'] = $product_specs['price'];
                    $img[$k2]['specs_id'] = $product_specs['id'];
                    $img[$k2]['product_name'] = $product_specs['product_name'];

                    $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();

                    if (!$img[$k2]['video']){
                        $img[$k2]['video'] = '';
                    }

                    if (!$img[$k2]['product_name']){
                        $img[$k2]['product_name'] = '';
                    }

                    if (!$img[$k2]['specs_id']){
                        $img[$k2]['specs_id'] = '';
                    }

                    if (!$img[$k2]['price']){
                        $img[$k2]['price'] = '';
                    }

                }

                $list[$k]['img_info'] = $img;
                $list[$k]['img'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺点赞
     */
    public function storeDianzan(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;

            if (!$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!Db::name('store')->where('id',$store_id)->count()){
                throw new \Exception('店铺不存在');
            }

            if (Db::name('store_dianzan_link')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->count()){
                throw new \Exception('已点赞');
            }

            $res1 = Db::name('store_dianzan_link')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$store_id,'create_time'=>time()]);

            $res2 = Db::name('store')->where('id',$store_id)->setInc('dianzan');

            if (!$res1 || !$res2){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺评论
     */
    public function storeComment(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $content = input('content');

            if (!$store_id || !$content){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!Db::name('store')->where('id',$store_id)->count()){
                throw new \Exception('店铺不存在');
            }

            $res = Db::name('store_comment')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$store_id,'content'=>$content,'create_time'=>time()]);

            if (!$res){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺评论点赞
     */
    public function storeCommentDianzan(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $comment_id = input('comment_id') ? intval(input('comment_id')) : 0 ;

            if (!$comment_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!Db::name('store_comment')->where('id',$comment_id)->count()){
                throw new \Exception('评论不存在');
            }

            if (Db::name('store_comment_dianzan_link')->where('comment_id',$comment_id)->where('user_id',$userInfo['user_id'])->count()){
                throw new \Exception('已点赞');
            }

            $res1 = Db::name('store_comment')->where('id',$comment_id)->setInc('dianzan');

            $res2 = Db::name('store_comment_dianzan_link')->insert(['comment_id'=>$comment_id,'user_id'=>$userInfo['user_id'],'create_time'=>time()]);

            if (!$res1 || !$res2){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺评论列表
     */
    public function storeCommentList(){
        try{
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 0 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$store_id){ return \json(self::callback(0,'参数错误'),400);};

            if (!Db::name('store')->where('id',$store_id)->count()){
                throw new \Exception('店铺不存在');
            }

            $total = Db::name('store_comment')->where('store_id',$store_id)->count();

            $list = Db::view('store_comment','id,content,dianzan,create_time')
                ->view('user','nickname,avatar','user.user_id = store_comment.user_id','left')
                ->where('store_id',$store_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                if ($userInfo){
                    $list[$k]['is_dianzan'] = Db::name('store_comment_dianzan_link')->where('comment_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                }
                $list[$k]['create_time'] = date('Y-m-d H:i:s');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 搜索店铺
     */
    public function searchStore(){
        try {
            $keywords = input('keywords');

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$keywords) return \json(self::callback(0,'参数错误'),400);

            $where['store_name|brand_name'] = ['like',"%$keywords%"];


            $list = Db::name('store')->field('id,cover,store_name,brand_name,description,type')
                ->where($where)
                ->where('sh_status',1)
                ->where('store_status',1)
                ->order('create_time','desc')
                ->select();

            if ($list) {
                $result = Db::name('search_store_record')->where('search_keywords',$keywords)->setInc('search_number',1);

                if (false == $result){
                    Db::name('search_store_record')->insert(['search_keywords'=>$keywords,'search_number'=>1]);
                }
            }

            foreach ($list as $k=>$v){

                $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                $img = Db::view('store_img','img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
                foreach ($img as $k2=>$v2){
                    //todo 潮搭标签商品信息
                    if ($v2['chaoda_id'] != 0){
                        $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
                        foreach ($tag_product_info as $k3=>$v3){
                            $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
                            $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
                            $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
                        }

                        $img[$k2]['tag_product_info'] = $tag_product_info;
                    }
                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();

                    $img[$k2]['price'] = $product_specs['price'];
                    $img[$k2]['specs_id'] = $product_specs['id'];
                    $img[$k2]['product_name'] = $product_specs['product_name'];

                    $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();

                    if (!$img[$k2]['video']){
                        $img[$k2]['video'] = '';
                    }

                    if (!$img[$k2]['product_name']){
                        $img[$k2]['product_name'] = '';
                    }

                    if (!$img[$k2]['specs_id']){
                        $img[$k2]['specs_id'] = '';
                    }

                    if (!$img[$k2]['price']){
                        $img[$k2]['price'] = '';
                    }

                }

                $list[$k]['img_info'] = $img;
                $list[$k]['img'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
            }

            return \json(self::callback(1,'',$list));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺与商品的混合搜索
     */
    function searchStoreAndProduct() {
        try {
            $keywords = input('keywords');

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            //搜索类型：1：搜索店铺/品牌；2：搜索商品
            $search_type    = input('search_type');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$keywords) return \json(self::callback(0,'参数错误'),400);

            $data = [
                'store' => [],
                'product' => [],
            ];
            if (1 != $search_type){
                $page   = input('page') ? intval(input('page')) : 0;
                $size   = input('size') ? intval(input('size')) : 10;
                $data['product']   = (Object)Pmodel::getProductsByKeyWords($keywords, $page, $size);
//                $data   = Pmodel::getProductsByKeyWords($keywords,$page,$size);
//                return \json(self::callback(1,'',$data));
            }

            if (2 != $search_type) {
                $where['store_name|brand_name'] = ['like',"%$keywords%"];
                $list = Db::name('store')->field('id,cover,store_name,brand_name,description,type')
                    ->where($where)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->order('create_time','desc')
                    ->select();

                if ($list) {
                    $result = Db::name('search_store_record')->where('search_keywords',$keywords)->setInc('search_number',1);

                    if (false == $result){
                        Db::name('search_store_record')->insert(['search_keywords'=>$keywords,'search_number'=>1]);
                    }
                }

                foreach ($list as $k=>$v){

                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                    $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                    $img = Db::view('store_img','img_url,product_id,product_specs,chaoda_id')
                        ->view('product','video','product.id = store_img.product_id','left')
                        ->where('store_img.store_id',$v['id'])
                        ->order('paixu','asc')
                        ->select();
                    foreach ($img as $k2=>$v2){
                        //todo 潮搭标签商品信息
                        if ($v2['chaoda_id'] != 0){
                            $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
                            foreach ($tag_product_info as $k3=>$v3){
                                $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
                                $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
                                $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
                            }

                            $img[$k2]['tag_product_info'] = $tag_product_info;
                        }
                        $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();

                        $img[$k2]['price'] = $product_specs['price'];
                        $img[$k2]['specs_id'] = $product_specs['id'];
                        $img[$k2]['product_name'] = $product_specs['product_name'];

                        $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();

                        if (!$img[$k2]['video']){
                            $img[$k2]['video'] = '';
                        }

                        if (!$img[$k2]['product_name']){
                            $img[$k2]['product_name'] = '';
                        }

                        if (!$img[$k2]['specs_id']){
                            $img[$k2]['specs_id'] = '';
                        }

                        if (!$img[$k2]['price']){
                            $img[$k2]['price'] = '';
                        }

                    }

                    $list[$k]['img_info'] = $img;
                    $list[$k]['img'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
                }

                $data['store'] = $list;
            }
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 热门搜索
     */
    public function hotSearch(){
        try{

            $data = Db::name('search_store_record')->order('search_number','desc')->limit('0,5')->column('search_keywords');

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 附近店铺 - 按品牌查询
     */
    public function nearbyStore(){
        try {

            $brand_name = $this->request->has('brand_name') ? $this->request->param('brand_name') : '' ;
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$brand_name) {
                return \json(self::callback(0,'参数错误'),400);
            }

            if ($lng == '0.0') {
                $lng = '';
            }
            if ($lat == '0.0') {
                $lat = '';
            }

            if ($lat || $lng) {
                $range = 10000000;	//距离  km

                $wh = "6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        )  <= $range  ";

                $field = ",ROUND(
        6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS distance";

                $order['distance'] = 'asc';
            }

            $order['id'] = 'desc';
            $list = Db::name('store')
                ->field('id,cover,store_name,brand_name,description'.$field)
                ->where('brand_name','eq',$brand_name)
                ->where('type',1)
                ->where($wh)
                ->order($order)
                ->select();

            foreach ($list as $k=>$v){

                $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                $img = Db::view('store_img','img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
                foreach ($img as $k2=>$v2){
                    //todo 潮搭标签商品信息
                    if ($v2['chaoda_id'] != 0){
                        $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
                        foreach ($tag_product_info as $k3=>$v3){
                            $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
                            $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
                            $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
                        }

                        $img[$k2]['tag_product_info'] = $tag_product_info;
                    }
                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();

                    $img[$k2]['price'] = $product_specs['price'];
                    $img[$k2]['specs_id'] = $product_specs['id'];
                    $img[$k2]['product_name'] = $product_specs['product_name'];

                    $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();

                    if (!$img[$k2]['video']){
                        $img[$k2]['video'] = '';
                    }

                    if (!$img[$k2]['product_name']){
                        $img[$k2]['product_name'] = '';
                    }

                    if (!$img[$k2]['specs_id']){
                        $img[$k2]['specs_id'] = '';
                    }

                    if (!$img[$k2]['price']){
                        $img[$k2]['price'] = '';
                    }

                }

                $list[$k]['img_info'] = $img;
                $list[$k]['img'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
            }

            return \json(self::callback(1,'',$list));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺详情
     */
    public function storeDetail(){

        try {
            $store_id = $this->request->has('store_id') ? $this->request->param('store_id') : 0 ;


            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $data = Db::name('store')->field('id,cover,store_name,type,buy_type,telephone,description,is_ziqu,province,city,area,address,lng,lat')->where('id',$store_id)->find();

            if (!$data){
                return \json(0,'店铺不存在');
            }

            if ($userInfo){
                if (Db::name('store_follow')->where('user_id',$userInfo['user_id'])->count()){
                    $data['is_follow'] = 1;
                }else{
                    $data['is_follow'] = 0;
                }


            }else{
                $data['is_follow'] = 0 ;
            }

            $data['chaoda_number'] = Db::name('chaoda')->where('is_delete',0)->where('store_id',$data['id'])->count();
            $data['fans_number'] = Db::name('store_follow')->where('store_id',$data['id'])->count();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺拼团列表
     */
    public function storePtList(){

        try {
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            if (!$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $time = time();

            $total = Db::name('user_pt')->where('store_id',$store_id)->where('ypt_size < pt_size')->where('pt_status',1)->where('end_time','>=',$time)->count();


            $list = Db::view('user_pt','id,end_time,pt_size,ypt_size')
                ->view('product_specs','cover,product_id,product_name,product_specs,group_buy_price','user_pt.specs_id = product_specs.id','left')
                ->where('user_pt.store_id',$store_id)
                ->where('user_pt.end_time','>=',$time)
                ->where('user_pt.ypt_size < user_pt.pt_size')
                ->where('user_pt.pt_status',1)
                ->page($page,$size)
                ->order('end_time','asc')
                ->select();


            foreach ($list as $k=>$v) {
                $list[$k]['end_time'] = ($v['end_time'] - $time) * 1000;
                $list[$k]['cha_num'] = $v['pt_size'] - $v['ypt_size'];
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取产品分类
     */
    public function  getProductCategory(){
        try {

            $data = Db::name('product_category')
                ->where('is_show',1)
                ->field('id,category_name')
                ->order('paixu','asc')
                ->select();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品列表
     */
    public function productList(){
        try {
            $store_id = $this->request->has('store_id') ? $this->request->param('store_id') : 0 ;
            $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            if (!$category_id || !$page || !$size || !$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $total = Db::name('product')
                ->where('store_id',$store_id)
                ->where('status',1)
                ->where('category_id',$category_id)
                ->count();

            $list = Db::view('product','id,category_id,start_time,end_time,product_name,product_type,days')
                ->view('product_specs','product_specs,price','product_specs.product_id = product.id','left')
                ->where('product.store_id',$store_id)
                ->where('product.status',1)
                ->where('product.category_id',$category_id)
                ->page($page,$size)
                ->group('product_id')
                ->order('product.create_time','desc')
                ->select();


            $time = time();
            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                $list[$k]['flag'] = 0 ;
                if ($category_id == 2) {
                    $list[$k]['flag'] = ($time < $v['start_time']) ? 1 : 2 ;
                }
                unset($list[$k]['start_time']);
                unset($list[$k]['end_time']);
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }
    

    /**
     * 商品详情
     */
    public function productDetail(){
        try{

            $product_id = $this->request->has('product_id') ? $this->request->param('product_id') : 0 ;
            $product_specs = $this->request->has('product_specs') ? $this->request->param('product_specs') : '' ;
            $product_specs = htmlspecialchars_decode($product_specs);

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$product_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!$product_specs){
                $product_specs = Db::name('product_specs')->where('product_id',$product_id)->value('product_specs');
            }

            $data = Cache::get('product_'.$product_id.$product_specs);
            if ($data){
                return \json(self::callback(1,'',$data));
            }


            if (!Db::name('product')->where('id',$product_id)->count()) {
                throw new \Exception('id不存在');
            }

            $data = Db::view('product','id,category_id,sales,store_id,freight,is_group_buy,start_time,end_time,huoli_money,type,see_type,buy_type,share_price,video,is_zdy_price,product_type,days')
                ->view('product_specs','id as specs_id,product_name,price,group_buy_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
                ->view('store','store_name,is_ziqu','store.id = product.store_id','left')
                ->where('product.id',$product_id)
                ->where('product_specs.product_specs','eq',"{$product_specs}")
                ->find();

            $specs = json_decode($data['product_specs']);
            foreach ($specs as $k=>$v){
                $specs .= $v.',';
            }

            $data['product_specs'] = $specs;

            if (!$data['product_specs']){
                throw new \Exception('商品不存在');
            }
            $web_url=Config::get('web_path');
            $data['link'] = "{$web_url}/user/index/product_content_p/id/{$data['id']}.html";
            $data['product_img'] = Db::name('product_img')->where('product_id',$product_id)->column('img_url');

            //如果是预售商品返回倒计时间戳
            $time = time();
            $data['flag'] = 0 ;
            if ($data['category_id'] == 2) {
                $data['flag'] = ($time < $data['start_time']) ? 1 : 2 ;
                $data['start_time'] = $data['start_time'] * 1000;
                $data['end_time'] = $data['end_time'] * 1000;
            }

            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['id'])->select();
            foreach ($key as $k=>$v) {
                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
            }

            $data['specs'] = $key;
            if ($userInfo){
                $data['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('product_id',$product_id)->count();
            }

            Cache::set('product_'.$product_id.$product_specs,$data,3600);

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 产品评论列表
     */
    public function productCommentList(){
        try {
            $product_id = $this->request->has('product_id') ? intval($this->request->param('product_id')) : 0 ;
            $page = $this->request->has('page') ? intval($this->request->param('page')) : 1 ;
            $size = $this->request->has('size') ? intval($this->request->param('size')) : 10 ;


            if (!$product_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $total = Db::name('product_comment')->where('product_id',$product_id)->count(); //商品评论数量

            $list = Db::view('product_comment','id,order_id,product_id,specs_id,content,create_time')
                ->view('user','nickname,avatar','user.user_id = product_comment.user_id','left')
                ->where('product_comment.product_id',$product_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();

            foreach ($list as $k=>$v) {
                $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                $list[$k]['comment_img'] = Db::name('product_comment_img')->where('comment_id',$v['id'])->column('img_url');
                $product_specs = Db::name('product_order_detail')->where('order_id',$v['order_id'])->where('product_id',$v['product_id'])->where('specs_id',$v['specs_id'])->value('product_specs');
                $product_specs = json_decode($product_specs,true);
                $specs = '';
                foreach ($product_specs as $k1 => $v1) {
                    $specs = $k1.';'.$v1;
                }
                $list[$k]['product_specs'] = $specs;
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取会员店铺分类
     */
    public function getMemberCategory(){

        try{
            $data = Db::name('member_store_category')->field('id,category_name')->where('is_show',1)->order('paixu','desc')->select();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 会员店铺列表
     */
    public function memberStoreList(){

        try{
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 50 ;
            $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
            $city = $this->request->has('city') ? $this->request->param('city') : '' ;

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!$category_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            if ($lng == '0.0') {
                $lng = '';
            }
            if ($lat == '0.0') {
                $lat = '';
            }

            $order['is_zhiding'] = 'desc';

            if ($lat || $lng) {
                $range = 10000000;	//距离  km

                $wh = "6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        )  <= $range  ";

                $field = ",ROUND(
        6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            {$lat} * PI() / 180 - `lat` * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS({$lat} * PI() / 180) * COS(`lat` * PI() / 180) * POW(
                    SIN(
                        (
                            {$lng} * PI() / 180 - `lng` * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS distance";

                $order['distance'] = 'asc';
            }

            $order['id'] = 'desc';
            $where['sh_status'] = ['eq',1];
            $where['type'] = ['eq',2];
            $where['sh_type'] = ['eq',2];
            $where['store_status'] = ['eq',1];
            $where['end_time'] = ['>',time()];
            /*if ($city){
                $where['city'] = ['like',"$city%"];
            }*/

            $total = Db::name('store')->where('category_id',$category_id)
                ->where($where)
                ->count();

            $list = Db::name('store')
                ->field('id,cover,store_name,description,brand_name,dianzan,start_time,end_time'.$field)
                ->where('category_id',$category_id)
                ->where($where)
                ->where($wh)
                ->page($page,$size)
                ->order($order)
                ->select();

            foreach ($list as $k=>$v){

                if ($userInfo){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('user_id',$userInfo['user_id'])->where('store_id',$v['id'])->count();
                }

                $list[$k]['start_time'] = $v['start_time'] * 1000;
                $list[$k]['end_time'] = $v['end_time'] * 1000;
                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();

                $img = Db::view('store_img','img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
                foreach ($img as $k2=>$v2){

                    //todo 潮搭标签商品信息
                    if ($v2['chaoda_id'] != 0){
                        $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
                        foreach ($tag_product_info as $k3=>$v3){
                            $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
                            $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
                            $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
                        }

                        $img[$k2]['tag_product_info'] = $tag_product_info;
                    }

                    $img[$k2]['price'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->value('price');

                    $img[$k2]['specs_id'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->value('id');

                    $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();

                    if (!$img[$k2]['video']){
                        $img[$k2]['video'] = '';
                    }

                    if (!$img[$k2]['product_name']){
                        $img[$k2]['product_name'] = '';
                    }


                    if (!$img[$k2]['specs_id']){
                        $img[$k2]['specs_id'] = '';
                    }

                    if (!$img[$k2]['price']){
                        $img[$k2]['price'] = '';
                    }

                }

                $list[$k]['img_info'] = $img;
                $list[$k]['img'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 会员商品列表
     */
    public function memberProductList(){
        try {
            $store_id = $this->request->has('store_id') ? $this->request->param('store_id') : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            if (!$page || !$size || !$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $total = Db::name('product')
                ->where('store_id',$store_id)
                ->where('status',1)
                ->count();

            $list = Db::view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days')
                ->view('product_specs','product_specs,price,share_img,huaxian_price','product_specs.product_id = product.id','left')
                ->where('product.store_id',$store_id)
                ->where('product.status',1)
                ->page($page,$size)
                ->group('product_id')
                ->order('')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 收藏商品
     */
    public function collection (){

        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $product_id = input('product_id') ? intval(input('product_id')) : 0 ;
            $specs_id = input('specs_id') ? intval(input('specs_id')) : 0 ;
            if (!$product_id || !$specs_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $specs_info = Db::name('product_specs')->where('id',$specs_id)->where('product_id',$product_id)->find();

            if (!$specs_info) {
                throw new \Exception('商品不存在');
            }

            if (Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('product_id',$product_id)->count()) {
                throw new \Exception('已收藏该商品');
            }

            Db::name('product_collection')->insert([
                'user_id' => $userInfo['user_id'],
                'product_id' => $product_id,
                'specs_id' => $specs_id,
                'create_time' => time()
            ]);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 取消收藏
     */
    public function cancelCollection(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $product_id = input('product_id') ? intval(input('product_id')) : 0 ;

            if (!$product_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('product_collection')->where('product_id',$product_id)->where('user_id',$userInfo['user_id'])->delete();

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 收藏列表
     */
    public function collectionList(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->count();

            $list = Db::view('product_collection')
                ->view('product_specs','product_name,product_specs,price','product_collection.specs_id = product_specs.id','left')
                ->where('product_collection.user_id',$userInfo['user_id'])
                ->order('product_collection.create_time','desc')
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v) {
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['product_id'])->value('img_url');
                $list[$k]['type'] = Db::name('product')->where('id',$v['product_id'])->value('type');

            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 分享商品
     */
    public function share(){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $param = json_decode($postJson,true);

            /*$param = [
                'share_info' => [
                    [
                        'product_id' => 9,
                        'price' => 899
                    ],
                    [
                        'product_id' => 10,
                        'price' => 1098
                    ]
                ]
            ];*/

            #return \json($param);

            $share_info = $param['share_info'];
            $store_id = Db::name('product')->where('id',$share_info[0]['product_id'])->value('store_id');
            $param['create_time'] = time();
            $param['store_id'] = $store_id;

            $share_id = Db::name('share')->strict(false)->insertGetId($param);

            foreach ($share_info as $k=>$v){
                $share_info[$k]['share_id'] = $share_id;
            }

            Db::name('share_product')->strict(false)->insertAll($share_info);

            return \json(self::callback(1,'',['share_id'=>$share_id]));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 分享列表
     */
    public function shareList(){

        try{
            $share_id = input('share_id') ? intval(input('share_id')) : 0 ;

            if (!$share_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $share = Db::name('share')
                ->join('store','store.id = share.store_id','left')
                ->field('store.store_name,store.cover')
                ->where('share.id',$share_id)
                ->find();

            if (!$share){
                return \json(self::callback(0,'分享不存在'));
            }

            $product = Db::view('share_product','id,product_id,price as plus_price')
                ->view('product','sales','product.id = share_product.product_id','left')
                ->view('product_specs','product_name,product_specs,price','product_specs.product_id = product.id','left')
                ->where('share_product.share_id',$share_id)
                ->group('product.id')
                ->select();

            foreach ($product as $k=>$v){
                $product[$k]['price'] = $v['price'] + $v['plus_price'];
                unset($product[$k]['plus_price']);
                unset($product[$k]['sales']);
                $product[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['product_id'])->value('img_url');
            }

            $share['product'] = $product;

            return \json(self::callback(1,'',$share));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 分享详情
     */
    public function shareDetail(){
        try{
            $id = input('id') ? intval(input('id')) : 0 ;
            $product_specs = input('product_specs');

            $product_specs = htmlspecialchars_decode($product_specs);

            $data = Db::view('share_product','product_id,price as plus_price')
                ->view('product','product_name,sales,freight,content','product.id = share_product.product_id','left')
                ->view('product_specs','cover,product_name,product_specs,price','product_specs.product_id = product.id','left')
                ->where('share_product.id',$id)
                ->where('product_specs.product_specs','eq',"{$product_specs}")
                ->find();

            if (!$data){
                return \json(self::callback(0,'商品不存在'));
            }

            $data['price'] = $data['price'] + $data['plus_price'];

            unset($data['plus_price']);

            $specs = json_decode($data['product_specs']);
            foreach ($specs as $k=>$v){
                $specs .= $v.',';
            }

            $data['product_specs'] = trim($specs,',');

            //商品轮播图
            $data['product_img'] = Db::name('product_img')->where('product_id',$data['product_id'])->column('img_url');

            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['product_id'])->select();
            foreach ($key as $k=>$v) {
                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
            }

            //商品规格属性
            $data['specs'] = $key;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 会员店铺banner
     */
    public function memberStoreBanner(){
        try {
            $web_url=Config::get('web_path');
            $data = Db::name('member_store_banner')->field('id,img_url,type,link,product_id,store_id')->select();
            foreach ($data as $k1=>$v1){
                if ($v1['type'] == 3) {
                    $data[$k1]['link'] = "{$web_url}/user/index/member_store_banner_p/id/{$v1['id']}.html";
                }
                if ($v1['type'] == 1) {
                    $data[$k1]['product_specs'] = Db::name('product_specs')->where('product_id',$v1['product_id'])->value('product_specs');
                }
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 品牌列表
     */
    public function brandList(){
        try{
            $category_id = input('category_id') ? intval(input('category_id')) : 0 ;

            if ($category_id){
                $where['category_id'] = ['eq',$category_id];
                $where['type'] = ['eq',1];
            }

            $brand_name = input('brand_name');

            if ($brand_name) {
                $where['brand_name'] = ['like',"%$brand_name%"];
            }

            $data = Db::name('store')->field('brand_name,cover')->where($where)->where('sh_status',1)->where('store_status',1)->where('category_id',$category_id)->group('brand_name')->select();

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 礼包列表
     */
    public function giftpackList(){

        $data = Db::name('giftpack')->where('is_del',0)->where('status',1)->select();

        return \json(self::callback(1,'',$data));
    }

    /**
     * 礼包详情
     */
    public function giftpackDetail(){
        try{

            $giftpack_id = input('giftpack_id') ? intval(input('giftpack_id')) : 0 ;

            if (!$giftpack_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $data = Db::name('giftpack')->where('id',$giftpack_id)->field('id,cover,name,price')->find();

            $list = Db::name('giftpack_card')->field('id,cover,coupon_name,satisfy_money,coupon_money,type,days,brand_name')->where('giftpack_id',$giftpack_id)->select();

            foreach ($list as $k=>$v){
                $list[$k]['logo'] = Db::view('giftpack_card_store')->view('store','avatar','store.id = giftpack_card_store.store_id','left')->where('card_id',$v['id'])->value('cover');
            }

            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 会员价格
     */
    public function memberPrice(){
        $data = Db::name('member_price')->where('id',1)->find();

        return \json(self::callback(1,'',$data));
    }


    /**
     * 提交订单 购买会员
     */
    public function submitMemberOrder(){
        try{

            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #$pay_money = input('pay_money');
            $pay_money = 98;
            $share_user_id = input('share_user_id') ? intval(input('share_user_id')) : 0 ;
            if (!empty($share_user_id)){
                if (!Db::name('user')->where('user_id',$share_user_id)->count()){
                    throw new \Exception('分享用户不存在');
                }
            }


            $order_no = build_order_no('M');
            $order_id = Db::name('member_order')->insertGetId([
                'order_no' => $order_no,
                'user_id' => $userInfo['user_id'],
                'share_user_id' => $share_user_id,
                'pay_money' => $pay_money,
                'create_time' => time(),
                'status' => 1
            ]);

            return \json(self::callback(1,'',['order_id'=>$order_id,'order_no'=>$order_no]));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取会员支付信息
     */
    public function memberPayInfo(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $data['order_no'] = $order_no = input('order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

            $orderModel = new MemberOrder();
            $order = $orderModel->where('order_no',$order_no)->where('user_id',$userInfo['user_id'])->find();
            $data['order_id'] = $order->order_id;

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->status != 1){
                throw new \Exception('订单不支持该操作');
            }

            switch($pay_type){
                case 1:
                    $notify_url = SERVICE_FX."/user/ali_pay/member_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->pay_money,$notify_url);
                    break;
                case 2:
                    $notify_url = SERVICE_FX."/user/wx_pay/member_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$order->pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 提交订单 购买大礼包
     */
    public function submitGiftpackOrder(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $pay_money = input('pay_money');
            $giftpack_id = input('giftpack_id');


            $giftpack_info = Db::name('giftpack')->where('id',$giftpack_id)->find();

            if (!$giftpack_info){
                throw new \Exception('礼包不存在');
            }

            if ($giftpack_info['price'] != $pay_money){
                throw new \Exception('礼包价格错误');
            }

            $share_user_id = input('share_user_id') ? intval(input('share_user_id')) : 0 ;
            if (!empty($share_user_id)){
                if (!Db::name('user')->where('user_id',$share_user_id)->count()){
                    throw new \Exception('分享用户不存在');
                }
            }

            //判断是否已购买过大礼包
            //修改判断同一礼包id不能重复购买 增加一个礼包id判断 增加代码为 where('giftpack_id',$giftpack_info['id'])->
            if (Db::name('giftpack_order')->where('user_id',$userInfo['user_id'])->where('giftpack_id',$giftpack_info['id'])->where('status',2)->count()){
                throw new \Exception('不可重复购买');
            }

            $order_no = build_order_no('G');
            $order_id = Db::name('giftpack_order')->insertGetId([
                'order_no' => $order_no,
                'user_id' => $userInfo['user_id'],
                'share_user_id' => $share_user_id,
                'pay_money' => $pay_money,
                'giftpack_id' => $giftpack_id,
                'create_time' => time(),
                'status' => 1
            ]);

            return \json(self::callback(1,'',['order_id'=>$order_id,'order_no'=>$order_no]));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取礼包支付信息
     */
    public function giftpackPayInfo(){
        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $data['order_no'] = $order_no = input('order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

            $orderModel = new GiftpackOrder();
            $order = $orderModel->where('order_no',$order_no)->where('user_id',$userInfo['user_id'])->find();
            $data['order_id'] = $order->order_id;

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->status != 1){
                throw new \Exception('订单不支持该操作');
            }

            switch($pay_type){
                case 1:
                    $notify_url = SERVICE_FX."/user/ali_pay/giftpack_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->pay_money,$notify_url);
                    break;
                case 2:
                    $notify_url = SERVICE_FX."/user/wx_pay/giftpack_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$order->pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 内购配置
     */
    public function applePayInfo(){
        $data = Db::name('apple_pay_config')->where('id',1)->select();
        return \json(self::callback(1,'',$data));
    }

    /**
     * 推荐商品
     */
    public function recommendProduct(){
        try {

            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            $total = Db::name('product')
                ->where('status',1)
                ->where('is_recommend',1)
                ->count();

            $list = Db::view('product','id,category_id,product_name,product_type,days')
                ->view('product_specs','product_specs,price','product_specs.product_id = product.id','left')
                ->where('product.status',1)
                ->where('product.is_recommend',1)
                ->page($page,$size)
                ->group('product_id')
                ->order('product.create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }
}