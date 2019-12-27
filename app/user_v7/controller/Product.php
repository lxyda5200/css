<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/5
 * Time: 13:47
 */

namespace app\user_v7\controller;

use app\common\controller\Base;
use app\user_v7\controller\Interact;
use app\user_v7\common\Logic;
use app\user_v7\common\User as UserFunc;
use app\user_v7\common\UserLogic;
use app\user_v7\model\DrawLottery;
use app\user_v7\model\DynamicModel;
use app\user_v7\model\Giftpack;
use app\user_v7\model\GiftpackOrder;
use app\user_v7\model\MemberOrder;
use app\user_v7\model\Product as Pmodel;
use app\user_v7\model\ProductComment;
use app\user_v7\validate\ProductValidate;
use think\Cache;
use think\Config;
use think\Db;
use app\user_v7\common\User;
use think\Exception;
use think\Log;
use think\response\Json;
use app\user_v7\validate\CouponValidate;

class Product extends Base
{

    /**
     * 获取店铺分类
     */
    public function getStoreCategory(){

        try{
            $data = Db::name('store_category')->field('id,category_name')->where('is_show',1)->where('client_type',2)->where('is_member_store',2)->order('paixu','asc')->select();
            $web_path = Config::get('web_path');
            foreach ($data as $k=>$v){
                $category_img = Db::name('store_category_img')->field('id,img_url,type,link,product_id,store_id')->where('category_id',$v['id'])->select();
                foreach ($category_img as $k1=>$v1){
                    if ($v1['type'] == 3) {
                        $category_img[$k1]['link'] = "{$web_path}/user/index/store_banner_p/id/{$v1['id']}.html";
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
     * 获取新店铺分类
     */
    public function getnewStoreCategory(){

        try{
            $data = Db::name('store_category')->field('id,category_name,is_member_store')->where('is_show',1)->where('client_type',2)->order('paixu','asc')->select();
            $web_path = Config::get('web_path');
            foreach ($data as $k=>$v){
                $category_img = Db::name('store_category_img')->field('id,img_url,type,link,product_id,store_id')->where('category_id',$v['id'])->select();
                foreach ($category_img as $k1=>$v1){
                    if ($v1['type'] == 3) {
                        $category_img[$k1]['link'] = "{$web_path}/user/index/store_banner_p/id/{$v1['id']}.html";
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
     * 获取推荐banner
     */
    public function recommendList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $data['banner'] = Db::name('recommend_banner')->field('id,title,description,image,activity_id,type')->order('sort','asc')->select();
                foreach ($data['banner'] as &$v){
                    if($v['id']==1){
                        $v['is_new']=1;
                        if (isset($user_id) && $user_id>0) {
                        $order = Db::name('product_order')->where('user_id',$user_id)->where('pay_time','GT',0)->find();
                        if($order){
                            $v['is_new']=0;
                        }
                    }
                }
            }
            $images = Db::name('store_category_img')
                ->alias('ab')
                ->join('store s','ab.type=4 AND ab.store_id>0 AND ab.store_id = s.id','LEFT')
                ->where('ab.category_id',18)
                ->field(['ab.id','ab.category_id','ab.img_url','ab.content','ab.link','ab.product_id','ab.store_id','ab.chaoda_id','ab.lottery_id','ab.type',
                    'IF(ab.type=4 AND ab.store_id>0 AND ab.store_id=s.id AND s.type=2,0,ab.type) type'
                ])
                ->select();
            foreach($images as $k => $vv){
                if($vv['type'] == 11){  ##删除下架活动
                    if(!DrawLottery::checkDrawLottery($vv['lottery_id'])){
                        unset($images[$k]);

                    }

                }
            }
            $arr=[];
      foreach ($images as  &$v2){
          $arr[]=$v2;
      }
            $data['images'] = $arr;
            return \json(self::callback(1,'查询成功!',$data));
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

            $wh = $field = "";

            if ($lat || $lng) {
//                $range = 10000000;	//距离  km
                $range = 5000000;	//距离  km

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

            $total = Db::name('store')->where('category_id',$category_id)->where($where)->where($wh)->count('id');
            $list = Db::name('store')
                ->field('id,read_number,cover,store_name,description,dianzan,brand_name,address,collect_number'.$field)
                ->where('category_id',$category_id)
                ->where($where)
                ->where($wh)
                ->page($page,$size)
                ->order($order)
                ->select();
            foreach ($list as $k=>$v){
                if(isset($userInfo)){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
//                $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                ##关注
                $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                ##收藏
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                $img = Db::view('store_img','id,img_url,product_id,product_specs,chaoda_id')
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
                    }else{
                        $img[$k2]['tag_product_info'] = [];
                    }

                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();

                    $img[$k2]['price'] = $product_specs['price'];
                    $img[$k2]['specs_id'] = $product_specs['id'];
                    $img[$k2]['product_name'] = $product_specs['product_name'];

                    if(isset($userInfo)){
                        $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();
                    }else{
                        $img[$k2]['is_collection'] = 0;
                    }

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
     * 店铺列表
     */
    public function storeListWithSort(){

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

            if(!$lat || !$lng){  ##设置默认经纬度为公司
                $lat = "30.549100";
                $lng = "104.067450";
            }

            $order_const = config('config_common.order_constant');

            $time = time();

            $field = ",(deal_num * deal_num * {$order_const['order_deal_weight']} + follow_num * follow_num * {$order_const['store_follow_weight']} + score_meddle) / (POWER((({$time} - lately_deal_time)/60/60 + 2),1.2) + POWER(ROUND(
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
),2 ) * {$order_const['distance_weight']}) AS score";


            $where['sh_status'] = ['eq',1]; //审核状态 1 为通过
            $where['type'] = ['eq',1];//区分普通店铺和会员店铺 1,为普通店铺2,为会员店铺
            $where['sh_type'] = ['eq',2];
            $where['store_status'] = ['eq',1]; //是否禁用 1为启用
            $where['id'] = ['NOT IN', [76]];
            if ($city){
                $where['city'] = ['like',"$city%"];
            }
            $total = Db::name('store')->where('category_id',$category_id)->where($where)->count('id');
            $list = Db::name('store')
                ->field(['id','read_number','cover','store_name','description','dianzan','brand_name','address','collect_number'.$field])
                ->where('category_id',$category_id)
                ->where($where)
                ->page($page,$size)
                ->order("score","desc")
                ->select();

            ##活动活动中的店铺ids
            $ac_store_ids = Logic::getOnlineAcStoreIds();

            foreach ($list as $k=>$v){

                $list[$k]['is_activity'] = in_array($v['id'],$ac_store_ids)?1:0;

                if(isset($userInfo)){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }

//                $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                ##关注
                $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                ##收藏
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                $img = Db::view('store_img','id,img_url,product_id,product_specs,chaoda_id')
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
                    }else{
                        $img[$k2]['tag_product_info'] = [];
                    }

                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();
                    $img[$k2]['price'] = $product_specs['price'];
                    $img[$k2]['specs_id'] = $product_specs['id'];
                    $img[$k2]['product_name'] = $product_specs['product_name'];

                    if(isset($userInfo)){
                        $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();
                    }else{
                        $img[$k2]['is_collection'] = 0;
                    }

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
//                //查询风格
//                $list[$k]['style'] = Db::view('store_style_store','id as store_style_store_id')
//                    ->view('style_store','id,title','store_style_store.style_store_id = style_store.id','left')
//                    ->where('store_style_store.store_id',$v['id'])
//                    ->where('style_store.delete_time is null ')
//                    ->select();
//                //查询人数
//                $list[$k]['read_user'] = Db::view('store_read_record','store_id,user_id')
//                    ->view('user','avatar','store_read_record.user_id = user.user_id','left')
//                    ->where('store_read_record.store_id',$v['id'])
//                    ->where('user.user_status','in','1,3')
//                    ->limit(30)
//                    ->select();
//                //查询活动

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
     * 购推荐店铺列表
     */
    public function TurtleStoreList(){
        try{
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;
           // $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
           // $city = $this->request->has('city') ? $this->request->param('city') : '' ;
            $category=input('post.category/a','');
            /*$version = input('version');
            if (!isset($version)) {
                return \json(self::callback(0,'版本需要更新，【我的】-【设置】-【检查更新】'),400);
            }*/
//            if (!$category_id || !$page || !$size) {
//                return \json(self::callback(0,'参数错误'),400);
//            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');
            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if(!$lat || !$lng){  ##设置默认经纬度为公司
                $lat = "30.549100";
                $lng = "104.067450";
            }
            //是否是查询
                if($category){
                    $category=$category[0];
                    $category= explode("],", $category);
                    $category=str_replace( "&quot;","",str_replace( "[","",str_replace( "]","",str_replace( "{","",str_replace( "}","",str_replace("\"","",$category))))));
                    $new_category=[];
                    foreach ($category as $k=>$v){
                        $arr= explode(":", $v);
                        foreach ($arr as $k1=>$v1){
                            $new_category[]=$v1;
                        }
                    }
                    $arr=[
                        $new_category[0]=>$new_category[1],
                        $new_category[2]=>$new_category[3],
                        $new_category[4]=>$new_category[5]
                    ];
                    foreach ($arr as $k2=>$v2){
                        if($k2==1){
                            $ids1= UserLogic::GetStoreCateIds($v2);
                        }elseif($k2==2){
                            $ids2 = UserLogic::GetStoreStyleIds($v2);
                        }elseif ($k2==3){
                            $ids3 = UserLogic::GetBusinessCircleIds($v2);
                        }
                    }
                    $ids=array_unique(array_merge((array)$ids1,(array)$ids2,(array)$ids3));
                }

            $order_const = config('config_common.order_constant');
            $time = time();
            $field = ",(deal_num * deal_num * {$order_const['order_deal_weight']} + follow_num * follow_num * {$order_const['store_follow_weight']} + score_meddle) / (POWER((({$time} - lately_deal_time)/60/60 + 2),1.2) + POWER(ROUND(
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
),2 ) * {$order_const['distance_weight']}) AS score";
            $where['sh_status'] = ['eq',1]; //审核状态 1 为通过
            $where['type'] = ['eq',1];//区分普通店铺和会员店铺 1,为普通店铺2,为会员店铺
            $where['sh_type'] = ['eq',2];
            $where['store_status'] = ['eq',1]; //是否禁用 1为启用
            $where['id'] = ['NOT IN', [76]];
            if($ids){$where['id'] = ['IN', $ids];}

//            if ($city){
//                $where['city'] = ['like',"$city%"];
//            }
           // $total = Db::name('store')->where('category_id',$category_id)->where($where)->count('id');
            $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"store.lat","store.lng").", 0) as distance";//计算距离
            $total = Db::name('store')
                ->field(['id','read_number','share_number','cover','store_name','description','dianzan','brand_name','address','collect_number'.$field])
//                ->where('category_id',$category_id)
                ->where($where)
                ->count();

            $list = Db::name('store')
                ->field(['id','read_number','share_number','cover','store_name','lng','lat','description','dianzan','brand_name','address',$distance,'collect_number'.$field])
//                ->where('category_id',$category_id)
                ->where($where)
                ->page($page,$size)
                ->order("score","desc")
                ->select();
            ##活动活动中的店铺ids
            $ac_store_ids = Logic::getOnlineAcStoreIds();
            foreach ($list as $k=>$v){
                $list[$k]['is_activity'] = in_array($v['id'],$ac_store_ids)?1:0;
                if(isset($userInfo)){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
                ##关注
                $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                ##收藏
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                $img = Db::view('store_img','id,img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
//                foreach ($img as $k2=>$v2){
//                    //todo 潮搭标签商品信息
//                    if ($v2['chaoda_id'] != 0){
//                        $tag_product_info = Db::name('chaoda_tag')->field('product_id,price')->where('chaoda_id',$v2['chaoda_id'])->select();
//                        foreach ($tag_product_info as $k3=>$v3){
//                            $tag_product_specs = Db::name('product_specs')->where('product_id',$v3['product_id'])->find();
//                            $tag_product_info[$k3]['product_name'] = $tag_product_specs['product_name'];
//                            $tag_product_info[$k3]['cover'] = $tag_product_specs['cover'];
//                        }
//                        $img[$k2]['tag_product_info'] = $tag_product_info;
//                    }else{
//                        $img[$k2]['tag_product_info'] = [];
//                    }
//                    $product_specs = Db::name('product_specs')->field('id,price,product_name')->where('product_id',$v2['product_id'])->where('product_specs','eq',"{$v2['product_specs']}")->find();
//                    $img[$k2]['price'] = $product_specs['price'];
//                    $img[$k2]['specs_id'] = $product_specs['id'];
//                    $img[$k2]['product_name'] = $product_specs['product_name'];
//                    if(isset($userInfo)){
//                        $img[$k2]['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('specs_id',$img[$k2]['specs_id'])->count();
//                    }else{
//                        $img[$k2]['is_collection'] = 0;
//                    }
//                    if (!$img[$k2]['video']){
//                        $img[$k2]['video'] = '';
//                    }
//                    if (!$img[$k2]['product_name']){
//                        $img[$k2]['product_name'] = '';
//                    }
//                    if (!$img[$k2]['specs_id']){
//                        $img[$k2]['specs_id'] = '';
//                    }
//                    if (!$img[$k2]['price']){
//                        $img[$k2]['price'] = '';
//                    }
//                }
               // $list[$k]['img_info'] = $img;
                $list[$k]['images'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
                //查询风格
                $list[$k]['tags'] = UserLogic::GetStoreStyle($v['id']);
                //查询人数
                $list[$k]['user_look_list'] = UserLogic::GetPerson($v['id']);
                //查询买单
                $maidan = Db::name('maidan')->where('store_id',$v['id'])->where('status',1)->count();
                //查询活动
                $activityids = UserLogic::GetActivity($v['id']);
                if($activityids || $maidan){
                    $list[$k]['activity']=UserLogic::GetTagsStore($activityids,$maidan);
                }else{$list[$k]['activity'] =[];}
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
     * 图片详情商品价格
     */
    public function pictureDetailProductinfo(){
        try{
            $type = input('post.type',0,'intval');  //1.动态;2.店铺
            $id = input('post.id',0);//店铺或动态id
            if($type==1){
                //动态
                if(!$id){return \json(self::callback(0,'动态id不能为空'),400);}
                $img=UserLogic::getDynamicImg($id);
                if($img){
                    $data=UserLogic::getDynamicImgPrice($img);
                }else{$data='';}
            }elseif($type==2){
                //店铺
                if(!$id){return \json(self::callback(0,'店铺id不能为空'),400);}
                $img=UserLogic::getStoreImg($id);
               if($img){
                   $data=UserLogic::getStoreImgPrice($img);
            }else{$data='';}
            }else{return \json(self::callback(0,'参数错误'),400);}
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

            $store_id = input('store_id',0,'intval');
            $type = input('type',1,'intval');

            if (!$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (!Db::name('store')->where('id',$store_id)->count()){
                throw new \Exception('店铺不存在');
            }
            
            if($type == 1){
                if (Db::name('store_dianzan_link')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->count()){
                    throw new \Exception('已点赞');
                }

                $res1 = Db::name('store_dianzan_link')->insert(['user_id'=>$userInfo['user_id'],'store_id'=>$store_id,'create_time'=>time()]);

                $res2 = Db::name('store')->where('id',$store_id)->setInc('dianzan');
                      Db::name('store')->where('id',$store_id)->setInc('real_dianzan');
                if (!$res1 || !$res2){
                    throw new \Exception('操作失败');
                }
            }else{
                if (!Db::name('store_dianzan_link')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->count()){
                    throw new \Exception('未点赞');
                }
                ##取消点赞
                $res1 = Db::name('store_dianzan_link')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->delete();
                if($res1 === false)throw new Exception('取消失败');

                $res2 = Db::name('store')->where('id',$store_id)->setDec('dianzan');
                Db::name('store')->where('id',$store_id)->setDec('real_dianzan');
                if($res2 === false)throw new Exception('取消失败');
            }

            #获取最新点赞数
//            $num = Db::name('store_dianzan_link')->where(['store_id'=>$store_id])->count('id');
            $num = Db::name('store')->where(['id'=>$store_id])->value('dianzan');
            return \json(self::callback(1,'',$num,true));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 店铺分享
     */
    public function StoreShare(){
        try{
            $store_id = input("store_id") ? intval(input("store_id")) : 0 ;
            if(!$store_id){return \json(self::callback(0, '参数错误', false,true));}
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $store=Db::name('store')->where(['id'=>$store_id])-> field('id,share_number')-> find();
            if(!$store){return \json(self::callback(0, '没有找到该店铺!', false,true));}
            $result=Db::name('store')->where(['id'=>$store_id])-> setInc('share_number', 1);
            if($result===false){return \json(self::callback(0, '分享统计失败!', false,true));}
            return \json(self::callback(1, '成功!', true));
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
            $keywords = input('keywords','','addslashes,strip_tags,trim');

            $user_id = input('user_id',0,'user_id');
            $token = input('token','','addslashes,strip_tags,trim');

            if (!$keywords) return \json(self::callback(0,'参数错误'),400);

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

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
            $keywords = input('keywords','','addslashes,strip_tags,trim');

            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');

            //搜索类型：1：搜索店铺/品牌(流量超市搜索)；2：搜索商品(普通商品、店铺搜索)
//            $search_type    = input('search_type',1,'intval');

            if (!$keywords) return \json(self::callback(0,'参数错误'),400);

            if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            $data = [
                'store' => [],
                'product' => [],
            ];
//            if (1 != $search_type){
                $page   = input('page',0,'intval');
                $size   = input('size',30,'intval');
                $data['product']   = Pmodel::getProductsByKeyWords($keywords, $page, $size);
//                $data   = Pmodel::getProductsByKeyWords($keywords,$page,$size);
//                return \json(self::callback(1,'',$data));
//            }

//            if ($search_type == 2) {
                $where['store_name|brand_name'] = ['like',"%$keywords%"];
                $list = Db::name('store')->field('id,read_number,cover,store_name,description,dianzan,collect_number,brand_name,address')
                    ->where($where)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->where('type',1)
                    ->order('create_time','desc')
                    ->limit($page)
                    ->select();

//                echo Db::name('store')->getLastSql();

                if ($list) {
                    $result = Db::name('search_store_record')->where('search_keywords',$keywords)->setInc('search_number',1);

                    if (false == $result){
                        Db::name('search_store_record')->insert(['search_keywords'=>$keywords,'search_number'=>1]);
                    }
                }

                foreach ($list as $k=>$v){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
//                    $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                    ##关注
                    $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                    $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                    ##收藏
//                    $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                    $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
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
//            }
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
            $data['search_words'] = Db::name('search_keywords')->where('client_type',2)->where('type',1)->column('search_keywords');
            $data['search_list'] = Db::name('search_store_record')->order('search_number','desc')->limit('0,5')->column('search_keywords');
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
            $brand_name = input('post.brand_name','','addslashes,strip_tags,trim,filterEmptyStr');
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim,filterEmptyStr');
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if(!$lat || !$lng){  ##设置默认经纬度为公司
                $lat = "30.549100";
                $lng = "104.067450";
            }
            if (!$brand_name && !$lng && !$lat) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"store.lat","store.lng").", 0) as distance";//计算距离
            if($brand_name){
                $order['id'] = 'desc';
                $total = Db::name('store')->where('brand_name','eq',$brand_name)->where('type',1)->count('id');
                $list = Db::name('store')
                    ->field(['id','read_number','share_number','cover','store_name','lng','lat','description','dianzan','brand_name','address',$distance,'collect_number'])
                    ->where('brand_name','eq',$brand_name)
                    ->where('type',1)
                    ->where('sh_status',1)
                    ->where('store_status',1)
//                    ->where($wh)
                    ->order($order)
                    ->limit(($page-1)*$size,$size)
                    ->select();
            }else{
                //            if(!$lng || !$lat)return \json(self::callback(0,'坐标缺失'),400);
                if ($lat || $lng) {
//                $range = 10000000;	//距离  km
                    $range = 3;	//距离  km
                    $wh =  distance($lat,$lng,"lat","lng") ."<= $range  ";
                }
                $total = Db::name('store')
                    ->where('type',1)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->where($wh)
                    ->count('id');
                $order['distance'] = 'asc';
                $list = Db::name('store')
                    ->field(['id','read_number','share_number','cover','store_name','lng','lat','description','dianzan','brand_name','address',$distance,'collect_number'])
//                    ->where('brand_name','eq',$brand_name)
                    ->where('type',1)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->where($wh)
                    ->order($order)
                    ->limit(($page-1)*$size,$size)
                    ->select();
            }
//            echo Db::name('store')->getLastSql();
            ##活动活动中的店铺ids
            $ac_store_ids = Logic::getOnlineAcStoreIds();
            foreach ($list as $k=>$v){
                $list[$k]['is_activity'] = in_array($v['id'],$ac_store_ids)?1:0;
                if(isset($userInfo)){
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
//                $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                ##关注
                $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                ##收藏
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                $img = Db::view('store_img','id,img_url,product_id,product_specs,chaoda_id')
                    ->view('product','video','product.id = store_img.product_id','left')
                    ->where('store_img.store_id',$v['id'])
                    ->order('paixu','asc')
                    ->select();
                $list[$k]['images'] = Db::name('store_img')->where('store_id',$v['id'])->column('img_url');
                //查询风格
                $list[$k]['tags'] = UserLogic::GetStoreStyle($v['id']);
                //查询人数
                $list[$k]['user_look_list'] = UserLogic::GetPerson($v['id']);
                //查询买单
                $maidan = Db::name('maidan')->where('store_id',$v['id'])->where('status',1)->count();
                //查询活动
                $activityids = UserLogic::GetActivity($v['id']);
                if($activityids || $maidan){
                    $list[$k]['activity']=UserLogic::GetTagsStore($activityids,$maidan);
                }else{$list[$k]['activity'] =[];}
            }
            $max_page = ceil($total/$size);
            if(empty($list))$list = [];
            return \json(self::callback(1,'',compact('total','max_page','list')));

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
                $read=[
                    'user_id'=>$user_id,
                    'store_id'=>$store_id,
                    'create_time'=>time()
                ];
                //增加浏览记录
                Db::name('store_read_record')->insert($read);
            }
            if (!$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            //加入会员月卡信息
            $member = Db::name('member_card')->field('id as member_card_id,price as member_price')->where('id',1)->find();
            $data = Db::name('store')->field('id,cover,store_name,type,buy_type,telephone,description,is_ziqu,province,city,area,address,lng,lat,read_number,maidan_deduct,store_status')->where('id',$store_id)->find();
            if($member){$data= array_merge($data,$member);}
            if(!$data){throw new Exception('店铺不存在!');}
            if($data['store_status']!=1){throw new Exception('店铺已下架!');}

            ##增加浏览量
            Db::name('store')->where(['id'=>$store_id])->setInc('read_number');
            Db::name('store')->where(['id'=>$store_id])->setInc('real_read_number');
            if ($userInfo){
                $data['is_follow'] = Db::name('store_follow')->where(['user_id'=>$userInfo['user_id'],'store_id'=>$store_id])->count();
            }else{
                $data['is_follow'] = 0 ;
            }
            $data['chaoda_number'] = Db::name('chaoda')->where('is_delete',0)->where('store_id',$data['id'])->count();
            $data['fans_number'] = Db::name('store_follow')->where('store_id',$data['id'])->count();
            if($store_id==1516){
                $data['fans_number'] =147+$data['fans_number'];
            }
//----
            //判断是否有开启品牌故事和时尚动态
            $data['brand_story'] =DynamicModel::GetBrandStore($store_id);
            //查询店铺banner
            $data['store_banner'] =DynamicModel::GetStoreBanner($store_id);
            //查询买单
            $maidan = Db::name('maidan')->where('store_id',$store_id)->where('status',1)->count();
            //查询活动
            $activityids = UserLogic::GetActivity($store_id);
            if($activityids || $maidan){
                $data['activity']=UserLogic::GetTagsStore($activityids,$maidan);
            }else{$data['activity'] =[];}
//-----

            ##买单折扣
            $discount = Db::name('maidan')->where(['store_id'=>$store_id,'status'=>1])->field('putong_user,member_user,status')->find();
            if(!$discount)$discount = ['putong_user'=>'10.00','member_user'=>'10.00'];
            $data['discount'] = $discount;
            $data['discount']['maidan_status'] = (int)$discount['status'] == 1?1:-1;
            ##优惠券
            ###用户优惠券
            $user_coupons = Db::name('coupon')->where(['user_id'=>$user_id])->column('coupon_id');
            ###优惠券
            $coupons = Db::name('coupon_rule')
                ->where([
                    'store_id'=>$store_id,
                    'type'=>['IN',[2,3]],
                    'is_open'=>1,
                    'surplus_number'=>['GT',0],
                    'client_type'=>['IN',[0,2]],
                    'use_type'=>['IN',[0,1,3]],
                    'coupon_type'=>['IN',[3,4,10]]
                ])
                ->where(function($query){
                    $query->where(['start_time'=>['ELT',time()],'end_time'=>['GT',time()]])->whereOr(['days'=>['GT', 0]]);
                })
                ->field('id,coupon_name,satisfy_money,coupon_money,start_time,end_time,coupon_type,type,days,kind')
                ->select();
            foreach($coupons as &$v){
                $v['start_time_date'] = date('Y-m-d',$v['start_time']);
                $v['end_time_date'] = date('Y-m-d',$v['end_time']);
                $v['is_get_coupon'] = in_array($v['id'],$user_coupons)?1:0;
            }
            $data['coupons'] = $coupons;

            ##获取客服
            $data['jig'] = [
                'jig_id' => "",
                'jig_pwd' => "",
                'store_jig_id' => ""
            ];
            if($user_id){
                $interact = new Interact();
                $service = $interact->getStoreService($user_id, $store_id);
                if($service['status']){
                    $data['jig'] = [
                        'jig_id' => $service['data']['jig_id'],
                        'jig_pwd' => $service['data']['jig_pwd'],
                        'store_jig_id' => $service['data']['store_jig_id']
                    ];
                }
            }

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺基本信息
     * @return Json
     */
    public function storeBaseInfo(){
        if(!request()->isPost())return \json(self::callback(0,'非法请求'),400);

        $store_id = input('post.store_id',0,'intval');
        if(!$store_id)return \json(self::callback(0,'参数缺失'),400);

        $store_info = Db::name('store')->where(['id'=>$store_id])->field('business_img,description,cover,store_name')->find();

        $store_info['business_img'] = '';

        return \json(self::callback(1,'',$store_info));
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
//            $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            if ( !$page || !$size || !$store_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $total = Db::name('product')->alias('p')
                ->join('product_specs ps','p.id = ps.product_id','RIGHT')
                ->where('p.store_id',$store_id)
                ->where('p.status',1)
//                ->where('category_id',$category_id)
                ->count();

            $list = Db::view('product','id,category_id,product_type,days,huoli_money,is_buy')
                ->view('product_specs','id as specs_id,product_name,product_specs,price,huaxian_price,price_activity_temp','product_specs.product_id = product.id','right')
                ->where('product.store_id',$store_id)
                ->where('product.status',1)
//                ->where('product.category_id',$category_id)
                ->page($page,$size)
                ->group('product_id')
                ->order('product.create_time','desc')
                ->select();

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            ##获取活动中的商品ids
            $ac_pro = Logic::getOnlineAcProIds();

            $time = time();
            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                $list[$k]['flag'] = 0 ;
//                if ($category_id == 2) {
//                    $list[$k]['flag'] = ($time < $v['start_time']) ? 1 : 2 ;
//                }
//                unset($list[$k]['start_time']);
//                unset($list[$k]['end_time']);
                if(in_array($v['id'],$product_ids))$list[$k]['price'] = $v['price_activity_temp'];

                $list[$k]['rule'] = in_array($v['id'],$ac_pro['product_ids'])?$ac_pro['ac_pro'][$v['id']]:"";
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }
    

//    /**
//     * 商品详情
//     */
//    public function productDetail(){
//        try{
//            $product_id = $this->request->has('product_id') ? $this->request->param('product_id') : 0 ;
//            $product_specs = $this->request->has('product_specs') ? $this->request->param('product_specs') : '' ;
//            $product_specs = htmlspecialchars_decode($product_specs);
//
//            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
//            $token = input('token');
//
//            if ($user_id || $token) {
//                $userInfo = User::checkToken($user_id,$token);
//                if ($userInfo instanceof Json){
//                    return $userInfo;
//                }
//            }
//
//            if (!$product_id) {
//                return \json(self::callback(0,'参数错误'),400);
//            }
//
//            if (!$product_specs){
//                $product_specs = Db::name('product_specs')->where('product_id',$product_id)->value('product_specs');
//            }
//
//            $data = Cache::get('product_'.$product_id.$product_specs);
//            if ($data){
//                return \json(self::callback(1,'',$data));
//            }
//
//
//            if (!Db::name('product')->where('id',$product_id)->count()) {
//                throw new \Exception('id不存在');
//            }
//
//            $data = Db::view('product','id,category_id,sales,store_id,freight,is_group_buy,start_time,end_time,huoli_money,type,see_type,buy_type,share_price,video,is_zdy_price,product_type,days')
//                ->view('product_specs','id as specs_id,product_name,price,group_buy_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
//                ->view('store','store_name,is_ziqu','store.id = product.store_id','left')
//                ->where('product.id',$product_id)
//                ->where('product_specs.product_specs','eq',"{$product_specs}")
//                ->find();
//
//            $specs = json_decode($data['product_specs']);
//            foreach ($specs as $k=>$v){
//                $specs .= $v.',';
//            }
//
//            $data['product_specs'] = $specs;
//
//            if (!$data['product_specs']){
//                throw new \Exception('商品不存在');
//            }
//
//            $data['link'] = "http://www.supersg.cn/user/index/product_content_p/id/{$data['id']}.html";
//            $data['product_img'] = Db::name('product_img')->where('product_id',$product_id)->column('img_url');
//
//            //如果是预售商品返回倒计时间戳
//            $time = time();
//            $data['flag'] = 0 ;
//            if ($data['category_id'] == 2) {
//                $data['flag'] = ($time < $data['start_time']) ? 1 : 2 ;
//                $data['start_time'] = $data['start_time'] * 1000;
//                $data['end_time'] = $data['end_time'] * 1000;
//            }
//
//            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['id'])->select();
//            foreach ($key as $k=>$v) {
//                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
//            }
//
//            $data['specs'] = $key;
//            if ($userInfo){
//                $data['is_collection'] = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('product_id',$product_id)->count();
//            }
//
//            Cache::set('product_'.$product_id.$product_specs,$data,3600);
//
//            return \json(self::callback(1,'',$data));
//
//        }catch (\Exception $e) {
//            return \json(self::callback(0,$e->getMessage()));
//        }
//    }
//    /**
//     **商品详情
//     **/
//
//    public function productDetail(){
//        try{
//            $product_id = $this->request->has('product_id') ? $this->request->param('product_id') : 0 ;
//            $product_specs = $this->request->has('product_specs') ? $this->request->param('product_specs') : '' ;
//            $product_specs = htmlspecialchars_decode($product_specs);
//            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
//
//            if (!$product_id) {
//                return \json(self::callback(0,'参数错误'),400);
//            }
//            if (!$product_specs){
//                $product_specs = Db::name('product_specs')->where('product_id',$product_id)->value('product_specs');
//            }
//            $data = Cache::get('product_'.$product_id.$product_specs);
//            if ($data){
//                return \json(self::callback(1,'',$data));
//            }
//
//            if (!Db::name('product')->where('id',$product_id)->count()) {
//                throw new \Exception('id不存在');
//            }
//            $data = Db::view('product','id,category_id,sales,store_id,freight,is_buy,content,is_group_buy,start_time,end_time,huoli_money,type,see_type,buy_type,share_price,video,is_zdy_price,product_type,days')
//                ->view('product_specs','id as specs_id,product_name,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
//                ->view('store','store_name,cover as store_logo,is_ziqu,type','store.id = product.store_id','left')
//                ->where('product.id',$product_id)
//                ->where('product_specs.product_specs','eq',"{$product_specs}")
//                ->find();
//            $specs = json_decode($data['product_specs']);
//
//            foreach ($specs as $k=>$v){
//                $specs .= $v.',';
//            }
//            $data['product_specs'] = $specs;
//
//            if (!$data['product_specs']){
//                throw new \Exception('商品不存在');
//            }
//            $data['link'] = "http://192.168.124.233/css/public/index.php/wxapi/product/productDetail/product_id/{$data['id']}.html";
//            $data['product_img'] = Db::name('product_img')->where('product_id',$product_id)->column('img_url');
//
//            //如果是预售商品返回倒计时间戳
//            $time = time();
//            $data['flag'] = 0 ;
//            if ($data['category_id'] == 2) {
//                $data['flag'] = ($time < $data['start_time']) ? 1 : 2 ;
//                $data['start_time'] = $data['start_time'] * 1000;
//                $data['end_time'] = $data['end_time'] * 1000;
//            }
//            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['id'])->order('id asc')->select();
//            foreach ($key as $k=>$v) {
//                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
//            }
//            $data['specs'] = $key;
//            if ($user_id){
//                $data['is_collection'] = Db::name('product_collection')->where('user_id',$user_id)->where('product_id',$product_id)->count();
//            }else{
//                $data['is_collection'] = 0;
//            }
//            //获取评论
//            $total = Db::name('product_comment')->where('product_id',$product_id)->count(); //商品评论数量
//            //查询两条评论
//            $list = Db::view('product_comment','id,order_id,product_id,specs_id,content,create_time')
//                ->view('user','nickname,avatar','user.user_id = product_comment.user_id','left')
//                ->where('product_comment.product_id',$product_id)
//                ->order('create_time','desc')
//                ->find();
//            $list['comment_img']=Db::name('product_comment_img')->where('comment_id',$list['id'])->column('img_url');
//            //获取收藏数
//            $store_collection=Db::name('store_collection')->where('store_id',$data['store_id'])->count();
//            $data['store_collection']=$store_collection; //总收藏数
//            $data['comment_total']=$total; //评论总数
//            $data['comment_detail']=$list; //评论详情
//            Cache::set('product_'.$product_id.$product_specs,$data,3600);
//            return \json(self::callback(1,'返回商品详情信息成功！',$data));
//        }catch (\Exception $e) {
//            return \json(self::callback(0,$e->getMessage()));
//        }
//    }
    /**
     **商品详情
     **/

    public function productDetail(){
        try{
            $type = intval(input('type', 1));
            $dynamic_id = intval(input('dynamic_id', 0));
            $product_id = $this->request->has('product_id') ? $this->request->param('product_id') : 0 ;
            $product_specs = $this->request->has('product_specs') ? $this->request->param('product_specs') : '' ;
            $product_specs = htmlspecialchars_decode($product_specs);
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            // $token = input('token');

            // if ($user_id || $token) {
            //     $userInfo = User::checkToken($user_id,$token);
            //     if ($userInfo instanceof Json){
            //         return $userInfo;
            //     }
            // }

            if (!$product_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            if (!$product_specs){
                $product_specs = Db::name('product_specs')->where('product_id',$product_id)->value('product_specs');
            }else{
                if(!strstr($product_specs,'{'))
                    $product_specs = "{". $product_specs ."}";
            }

            ##增加浏览量
            Db::name('product')->where(['id'=>$product_id])->setInc('read_number',1);

            $data='';
//            $data = Cache::get('product_'.$product_id.$product_specs);
            if ($data){
                if ($user_id){
                    $data['is_collection'] = Db::name('product_collection')->where('user_id',$user_id)->where('product_id',$product_id)->count();
                }else{
                    $data['is_collection'] = 0;
                }
                $data['stock'] = Db::name('product_specs')->where(['id'=>$data['specs_id']])->value('stock');
                $store_collection=Db::name('store_collection')->where('store_id',$data['store_id'])->count();
                $data['store_collection']=$store_collection; //总收藏数
                return \json(self::callback(1,'',$data));
            }

            if (!Db::name('product')->where('id',$product_id)->count()) {
                throw new \Exception('id不存在');
            }
            $data = Db::view('product','id,category_id,product_name,sales,store_id,freight,is_buy,content,is_group_buy,start_time,end_time,huoli_money,type,see_type,buy_type,share_price,video,is_zdy_price,product_type,days,fake_collect_num as product_collection_number')
                ->view('product_specs','id as specs_id,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price,price_activity_temp','product_specs.product_id = product.id','left')
                ->view('store','store_name,cover as store_logo,is_ziqu,type,address,lng,lat,type as store_type','store.id = product.store_id','left')
                ->where('product.id',$product_id)
                ->where('product_specs.product_specs','eq',"{$product_specs}")
                ->find();
            # 类型
            switch ($type) {
                case 3:
                    if($dynamic_id) {
                        $data['price'] = Db::table('dynamic_product_specs')
                            ->where(['specs_id' => $data['specs_id'], 'dynamic_id' => $dynamic_id])
                            ->value('price');
                    }
                    break;
            }

            ##判断商品的是否参与活动
            $activity_info = Logic::productActivityInfo($product_id);
            if($activity_info['status'] == 2 && ($activity_info['activity_type'] == 2 || $activity_info['activity_type'] == 4)){  //活动中且要变价
                $price = $data['price'];
                $data['price'] = $data['price_activity_temp'];
                $data['huaxian_price'] = $price;
            }

            $activity_status = $activity_info['status'];
            $activity = [];
            if($activity_info['status'] != 3){
                $activity[0]['type'] =  Logic::getRecomAcInfoByAcId($activity_info['activity_id'], $activity_info['activity_pro_type']);
                $activity[0]['title'] = $activity_info['type'];
                $activity[0]['desc'] = $activity_info['rule'];
                $activity[0]['start_time'] = $activity_info['start_time'];
                $activity[0]['end_time'] = (int)$activity_info['end_time'];
                $activity[0]['cur_time'] = $activity_info['cur_time'];
                $activity[0]['is_miao_sha'] = $activity_info['is_miao_sha'];
                $activity[0]['activity_id'] = $activity_info['activity_id'];
                $activity[0]['is_count_down'] = ($activity_info['end_time'] - $activity_info['cur_time'] <= config('config_common.activity_count_down') * 60 * 60)? 1 : 0;
            }

//            $data['activity_info'] = $activity_info;

            $specs = json_decode($data['product_specs']);

            foreach ($specs as $k=>$v){
                $specs .= $v.',';
            }
            $data['product_specs'] = $specs;

            if (!$data['product_specs']){
                throw new \Exception('商品不存在');
            }
//            $data['link'] = "http://192.168.124.233/css/public/index.php/wxapi/product/productDetail/product_id/{$data['id']}.html";
//            $data['link'] = request()->domain() . "/index.php/wxapi/product/productDetail/product_id/{$data['id']}.html";
//            $data['link'] = request()->domain() . "/index.php/user/index/product_content_p/id/{$data['id']}.html";
            $data['link'] = "http://121.196.214.146/css/public/index.php/user_v7/index/product_content_p/id/{$data['id']}.html";
            $data['product_img'] = Db::name('product_img')->where('product_id',$product_id)->column('img_url');

            //如果是预售商品返回倒计时间戳
            $time = time();
            $data['flag'] = 0 ;
            if ($data['category_id'] == 2) {
                $data['flag'] = ($time < $data['start_time']) ? 1 : 2 ;
                $data['start_time'] = $data['start_time'] * 1000;
                $data['end_time'] = $data['end_time'] * 1000;
            }

            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['id'])->order('id asc')->select();
            foreach ($key as $k=>$v) {
                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
            }

            $data['specs'] = $key;
            if ($user_id){
                $data['is_collection'] = Db::name('product_collection')->where('user_id',$user_id)->where('product_id',$product_id)->count();
            }else{
                $data['is_collection'] = 0;
            }
            //获取评论
            $total = Db::name('product_comment')->where('product_id',$product_id)->count(); //商品评论数量
            //查询1条评论
            $list = Db::view('product_comment','id,order_id,product_id,specs_id,content,create_time')
                ->view('user','nickname,avatar','user.user_id = product_comment.user_id','left')
                ->where('product_comment.product_id',$product_id)
                ->order('product_comment.create_time','desc')
                ->limit(1)
                ->find();

            $list['comment_img']=Db::name('product_comment_img')->where('comment_id',$list['id'])->column('img_url');

            //获取收藏数
//            $store_collection=Db::name('store_collection')->where('store_id',$data['store_id'])->count();
            $store_collection=Db::name('store')->where('id',$data['store_id'])->value('collect_number');
            $data['store_collection']=$store_collection; //总收藏数
            $data['comment_total']=$total; //评论总数
            $data['comment_detail']=$list; //评论详情

            ##活动判断
            $activity_config = config('config_common.activity');
            if(is_array($activity_config)){
                foreach($activity_config as $k => $v){
                    if(in_array($product_id,$v['product_ids'])){
                        $activity_status = 2;
                        $type = ($k == "sheng_xin_gou")?-1:-2;
                        $activity[0]['type'] = $type;
                        $activity[0]['title'] = $v['title'];
                        $activity[0]['desc'][] = $v['desc'];
                        $activity[0]['start_time'] = 0;
                        $activity[0]['end_time'] = 0;
                        $activity[0]['cur_time'] = time();
                        $activity[0]['is_miao_sha'] = 0;
                        $activity[0]['activity_id'] = 0;
                        $activity[0]['is_count_down'] = 0;
                        break;
                    }
                }
            }

            $data['activity'] = $activity;
            $data['activity_status'] = $activity_status;

            ##获取客服
            $data['jig'] = [
                'jig_id' => "",
                'jig_pwd' => "",
                'store_jig_id' => ""
            ];

            if($user_id){
                $interact = new Interact();
                $service = $interact->getStoreService($user_id, $data['store_id']);
                if($service['status']){
                    $data['jig'] = [
                        'jig_id' => $service['data']['jig_id'],
                        'jig_pwd' => $service['data']['jig_pwd'],
                        'store_jig_id' => $service['data']['store_jig_id']
                    ];
                }
            }

           // Cache::set('product_'.$product_id.$product_specs,$data,3600);
            return \json(self::callback(1,'返回商品详情信息成功！',$data));
        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取商品规格信息
     * @return Json
     */
    public function productSpecs(){
        try{
            #验证
            if(!request()->isPost())return \json(self::callback(0,'非法请求'));
            $product_id = input('post.product_id',0,'intval');
            $product_specs = input('post.product_specs',0,'strip_tags,trim');
            if($product_id <= 0)return \json(self::callback(0,'参数错误'));
         //   Log::info($product_specs);
            #逻辑
            $where = [
                'product.id' => $product_id,
            ];
            if($product_specs)$where['product_specs.product_specs'] = "{{$product_specs}}";
            $data = Db::view('product','id')
                ->view('product_specs','id as specs_id,product_name,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price,price_activity_temp','product_specs.product_id = product.id','left')
                ->where($where)
                ->find();

            ##判断商品的是否参与活动
            $activity_info = Logic::productActivityInfo($product_id);
            if($activity_info['status'] == 2 && ($activity_info['activity_type'] == 2 || $activity_info['activity_type'] == 4)){  //活动中且要变价
                $price = $data['price'];
                $data['price'] = $data['price_activity_temp'];
                $data['huaxian_price'] = $price;
            }

            $specs = json_decode($data['product_specs']);

            foreach ($specs as $k=>$v){
                $specs .= $v.',';
            }
            $data['product_specs'] = $specs;

            if (!$data['product_specs']){
                throw new Exception('商品不存在');
            }

            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$product_id)->order('id asc')->select();
            foreach ($key as $k=>$v) {
                $key[$k]['value'] = Db::name('product_attribute_value')->field('id,attribute_value')->where('attribute_id',$v['id'])->select();
            }

            $data['specs'] = $key;

            return \json(self::callback(1,'',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 通过产品id获取店铺基本信息
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreInfoByProId(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));
        $product_id = input('post.product_id',0,'intval');
        if(!$product_id)return \json(self::callback(0,'参数错误'));

        #逻辑
        $info = Db::view('product','product_name')
            ->view('store','id as store_id,store_name,cover','product.id = store.id')
            ->find();

        if(!$info)return \json(self::callback(0,'店铺不存在'));

        $info['start'] = [3,6];

        return \json(self::callback(1,'',$info));
    }

    /**
     * 产品评论列表
     */
    public function productCommentList(){
        try {
            $product_id = $this->request->has('product_id') ? intval($this->request->param('product_id')) : 0 ;
            $page = $this->request->has('page') ? intval($this->request->param('page')) : 1 ;
            $size = $this->request->has('size') ? intval($this->request->param('size')) : 10 ;
            $user_id = input('post.user_id',0,'intval');


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
                $praise_number = Db('product_comment_dianzan')->where(['comment_id'=>$v['id']])->count('id');
                $list[$k]['praise_number'] = $praise_number;
                $list[$k]['is_praise'] = $praise_number?Db('product_comment_dianzan')->where(['comment_id'=>$v['id'],'user_id'=>$user_id])->count('id'):0;
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
                ->field('id,cover,store_name,description,brand_name,dianzan,collect_number,start_time,end_time'.$field)
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
     * 流量超市分类产品列表
     * @param ProductValidate $ProductValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function memberCateProductList(ProductValidate $ProductValidate){
        try{
            if(!request()->isPost())throw new Exception('非法请求');
            #验证参数
            $res = $ProductValidate->scene('memberCateProductList')->check(input());
            if(!$res)throw new Exception($ProductValidate->getError());

            #逻辑
            $page = input('post.page',1,'intval');
            $size = input('post.size',50,'intval');
            $category_id = input('post.category_id',0,'intval');
            $lng = input('post.lng','','floatval');
            $lat = input('post.lat','','floatval');
            $city = input('post.city','','addslashes,strip_tags,trim');
            $user_id = input('post.user_id',0,'intval');
            $token = input('token','','addslashes,strip_tags,trim');

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if (!$category_id) throw new Exception('参数错误');

            if ($lng == '0.0')$lng = '';
            if ($lat == '0.0')$lat = '';

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
            }

            $where['sh_status'] = ['eq',1];
            $where['type'] = ['eq',2];
            $where['sh_type'] = ['eq',2];
            $where['store_status'] = ['eq',1];
            $where['end_time'] = ['>',time()];
            /*if ($city){
                $where['city'] = ['like',"$city%"];
            }*/

            $store_id = Db::name('store')
                ->where('category_id',$category_id)
                ->where($where)
                ->where($wh)
                ->column('id');
//            echo Db::name('store')->getLastSql();

            if(empty($store_id))throw new Exception('没有更多数据');

            $where = ['store_id'=>['IN',$store_id],'status'=>1,'sh_status'=>1,'type'=>2];

            $total = Db::view('product','id')->where($where)->count('id');
            if(!$total)throw new Exception('没有更多数据');

            $config_sort = config('config_common.order_constant');
            $time = time();
            $field = ",(p.sales * {$config_sort['product_sale_weight']} + p.collect_num * {$config_sort['product_collect_weight']} + p.read_number * {$config_sort['product_read_weight']} + p.score_meddle) / (POWER(({$time} - p.shangjia_time)/60/60 + 2 , 2)) AS score";

//            echo $field;die;

            #查询店铺下的产品
//            $list = Db::view('product',"id,huoli_money,see_type,buy_type,share_price,is_zdy_price,product_type,days,is_buy,type{$field}")
//                ->view('product_specs','id as specs_id,product_name,cover,product_specs,price,share_img,huaxian_price,price_activity_temp','product_specs.product_id = product.id','left')
//                ->where($where)
//                ->page($page,$size)
//                ->group('product.id')
//                ->order('score','desc')
//                ->select();
            $where = ['p.store_id'=>['IN',$store_id],'p.status'=>1,'p.sh_status'=>1,'p.type'=>2];
            $list = Db::name('product')->alias('p')
                ->join('product_specs ps','ps.product_id = p.id','LEFT')
                ->where($where)
                ->field('p.id,p.huoli_money,p.see_type,p.buy_type,p.share_price,p.is_zdy_price,p.product_type,p.days,p.is_buy,p.type,ps.id as specs_id,ps.product_name,ps.cover,ps.product_specs,ps.price,ps.share_img,ps.huaxian_price,ps.price_activity_temp' . $field)
                ->group('p.id')
                ->limit(($page-1)*$size,$size)
                ->order('score','desc')
                ->select();

            ##获取需要改变价格的活动商品IDS
            $activity_ids = Logic::getActivityPros();

            ##获取在活动中的商品信息
            $ac_pro = Logic::getOnlineAcProIds();

            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
                if(in_array($v['id'], $activity_ids))$list[$k]['price'] = $v['price_activity_temp'];
                ##活动规则
                $list[$k]['rule'] = in_array($v['id'],$ac_pro['product_ids'])?$ac_pro['ac_pro'][$v['id']]:"";
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch(\Exception $e){
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
            $type = input('type',1,'intval');

            if (!$product_id || !$specs_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            if($type == 1){
                $specs_info = Db::name('product_specs')->where('id',$specs_id)->where('product_id',$product_id)->find();

                if (!$specs_info) {
                    throw new \Exception('商品不存在');
                }

                if (Db::name('product_collection')->where('user_id',$userInfo['user_id'])->where('product_id',$product_id)->count()) {
                    throw new \Exception('已收藏该商品');
                }

                $res = Db::name('product_collection')->insert([
                    'user_id' => $userInfo['user_id'],
                    'product_id' => $product_id,
                    'specs_id' => $specs_id,
                    'create_time' => time()
                ]);
                if($res === false)throw new Exception('操作失败');
                    ##更新收藏数
                    $count = Db::name('product_collection')->where(['product_id'=>$product_id])->count('id');
                    Db::name('product')->where(['id'=>$product_id])->setField('collect_num',$count);
                    //更新收藏数
                    Db::name('product')->where(['id'=>$product_id])->setInc('fake_collect_num',1);
            }else if($type == -1){
//                Log::info(print_r(request()->param()));
                $res = Db::name('product_collection')->where('product_id',$product_id)->where('user_id',$userInfo['user_id'])->delete();
                if($res === false)throw new Exception('操作失败');
                ##更新收藏数
                $count = Db::name('product_collection')->where(['product_id'=>$product_id])->count('id');

                Db::name('product')->where(['id'=>$product_id])->setField('collect_num',$count);
                //更新收藏数
                Db::name('product')->where(['id'=>$product_id])->setDec('fake_collect_num',1);

            }
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

            $total = UserLogic::userCollectProNum($userInfo['user_id']);

            $list = Db::view('product_collection','specs_id')
                ->view('product_specs','product_id,product_name,product_specs,price','product_collection.specs_id = product_specs.id','left')
                ->view('product',false,'product_collection.product_id = product.id')
                ->view('store',false,'product.store_id = store.id')
                ->where('product_collection.user_id',$userInfo['user_id'])
                ->where('product_specs.id',['GT',0])
                ->where(['product.status'=>1,'product.sh_status'=>1,'store.store_status'=>1])
                ->order('product_collection.create_time','desc')
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v) {
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['product_id'])->value('img_url');
                $list[$k]['type'] = Db::name('product')->where('id',$v['product_id'])->value('type');
                $list[$k]['collect_number'] = Db('product_collection')->where(['specs_id'=>$v['specs_id']])->count('id');
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
            $data = Db::name('member_store_banner')->field('id,img_url,type,link,product_id,store_id')->order('sort','asc')->select();
            $web_path = Config::get('web_path');
            foreach ($data as $k1=>$v1){
                if ($v1['type'] == 3) {
                    $data[$k1]['link'] = "{$web_path}/user/index/member_store_banner_p/id/{$v1['id']}.html";
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
     * 获取会员卡列表
     */
    public function getmemberList(){

        $data = Db::name('member_price')->where('id',1)->find();

        return \json(self::callback(1,'',$data));
    }

    /**
     * 会员卡列表2019.7.28
     */
    public function membercardList(){
        try{
            $data = Db::name('member_card')
                ->field('id,card_name,price')
                ->where('status',1)
                ->order('create_time desc')
                ->select();
            foreach ($data as $k=>$v ){
                if($v['id']==1){
                    //月卡
                    $data[$k]['everyday_price']=round($v['price']/30,2);
                }else{
                    //年卡
                    $data[$k]['everyday_price']=round($v['price']/365,2);
                }
            }
            return json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
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
            $pay_money = input('pay_money');
            $card_id = input('id') ? intval(input('id')) : 0 ;
//            $pay_money = 98;
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
                'member_card_id' => $card_id,
                'status' => 1
            ]);
            return \json(self::callback(1,'',['order_id'=>$order_id,'order_no'=>$order_no]));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 购买会员
     */
    public function buyMemberOrder(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $card_id = input('id') ? intval(input('id')) : 0 ;
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信
            if(!$card_id || !$pay_type){
                return \json(self::callback(0,'参数错误'),400);
            }
            $time=time();//定义当前时间
            $card_id=intval($card_id);
            //判断是否有该id的会员卡
            $card_info=Db::name('member_card')->where('id',$card_id)->find();
            if($card_info){
                    //购买
                    $pay_money=$card_info['price'];
                    $order_no = build_order_no('M');
                    $order_id = Db::name('member_order')->insertGetId([
                        'order_no' => $order_no,
                        'user_id' => $userInfo['user_id'],
                        'pay_money' => $pay_money,
                        'create_time' => time(),
                        'member_card_id' => $card_id,
                        'status' => 1
                    ]);
                    $data['order_id'] = $order_id;
                    $data['order_no'] = $order_no;
                    //插入订单成功 调起支付
                    if($order_id){
                        switch($pay_type){
                            case 1:
                                $notify_url = SERVICE_FX."/user_v7/ali_pay/member_alipay_notify";
                                $aliPay = new AliPay();
                                $data['alipay_order_info'] = $aliPay->appPay($order_no,$pay_money,$notify_url);
                                break;
                            case 2:
                                $notify_url = SERVICE_FX."/user_v7/wx_pay/member_wxpay_notify";
                                $wxPay = new WxPay();
                                $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$pay_money,$notify_url);
                                break;
                            default:
                                throw new \Exception('支付方式错误');
                                break;
                        }
                        return \json(self::callback(1,'购买成功',$data));
                    }else{
                        return \json(self::callback(0,'订单生成失败'),400);
                    }

            }else{
                return \json(self::callback(0,'没找到这条会员卡记录'),400);
            }
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
                    $notify_url = SERVICE_FX."/user_v7/ali_pay/member_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->pay_money,$notify_url);
                    break;
                case 2:
                    $notify_url = SERVICE_FX."/user_v7/wx_pay/member_wxpay_notify";
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

            $list = Db::view('product','id,category_id,product_name,product_type,days,is_buy')
                ->view('product_specs','id as specs_id,product_specs,price,price_activity_temp','product_specs.product_id = product.id','left')
                ->where('product.status',1)
                ->where('product.is_recommend',1)
                ->page($page,$size)
                ->group('product_id')
                ->order('product.create_time','desc')
                ->select();

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                if(in_array($v['id'],$product_ids))$list[$k]['price'] = $v['price_activity_temp'];
            }

            shuffle($list);

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 推荐商品
     */
    public function recommendProductWithSort(){
        try {

            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            $total = Db::name('product')
                ->where('status',1)
                ->where('is_recommend',1)
                ->count();

            $config_sort = config('config_common.order_constant');
            $time = time();
            $field = ",(p.sales * {$config_sort['product_sale_weight']} + p.collect_num * {$config_sort['product_collect_weight']} + p.read_number * {$config_sort['product_read_weight']} + p.score_meddle) / (POWER(({$time} - p.shangjia_time)/60/60 + 2 , 2)) AS score";

            $list = Db::name('product')->alias('p')
                ->join('product_specs ps','ps.product_id = p.id','LEFT')
                ->where(['p.status'=>1,'p.is_recommend'=>1])
                ->field('p.id,p.category_id,p.product_name,p.product_type,p.days,p.is_buy,p.huoli_money,ps.id as specs_id,ps.product_specs,ps.price,ps.price_activity_temp'.$field)
                ->page($page,$size)
                ->group('p.id')
                ->order('score','desc')
                ->select();

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            ##获取在活动中的商品ids
            $ac_pro = Logic::getOnlineAcProIds();

            foreach ($list as $k=>$v){
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                if(in_array($v['id'],$product_ids))$list[$k]['price'] = $v['price_activity_temp'];
                $list[$k]['rule'] = in_array($v['id'],$ac_pro['product_ids'])?$ac_pro['ac_pro'][$v['id']]:"";
            }

//            shuffle($list);

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 附近店铺 - 按品牌查询
     */
    public function nearbyStoreForIos(){
        try {

            $brand_name = input('post.brand_name','','addslashes,strip_tags,trim,filterEmptyStr');
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim,filterEmptyStr');
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');

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

            if (!$brand_name && !$lng && !$lat) {
                return \json(self::callback(0,'参数错误'),400);
            }

//            if(!$lng || !$lat)return \json(self::callback(0,'坐标缺失'),400);

            if ($lat || $lng) {
//                $range = 10000000;	//距离  km
                $range = 3;	//距离  km

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


            }

            if($brand_name){
                $order['id'] = 'desc';
                $total = Db::name('store')->where('brand_name','eq',$brand_name)->where('type',1)->count('id');
                $list = Db::name('store')
                    ->field('id,read_number,cover,store_name,dianzan,collect_number,brand_name,description,address,lng,lat')
                    ->where('brand_name','eq',$brand_name)
                    ->where('type',1)
                    ->where('store_status',1)
                    ->where('sh_status',1)
//                    ->where($wh)
                    ->order($order)
                    ->limit(($page-1)*$size,$size)
                    ->select();
            }else{
                $total = Db::name('store')->where('type',1)->where($wh)->count('id');
                $order['distance'] = 'asc';
                $list = Db::name('store')
                    ->field('id,read_number,cover,store_name,brand_name,dianzan,collect_number,description,lng,lat,address'.$field)
//                    ->where('brand_name','eq',$brand_name)
                    ->where('type',1)
                    ->where('store_status',1)
                    ->where('sh_status',1)
                    ->where($wh)
                    ->order($order)
                    ->limit(($page-1)*$size,$size)
                    ->select();
            }

//            echo Db::name('store')->getLastSql();

            foreach ($list as $k=>$v){

                $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
                ##店铺总点赞人数
//                $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                ##收藏
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');

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
            $max_page = ceil($total/$size);
            if(empty($list))$list = [];
            return \json(self::callback(1,'',compact('total','max_page','list')));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺与商品的混合搜索
     */
    function searchStoreAndProductForIos() {
        try {
            $keywords = input('keywords','','addslashes,strip_tags,trim');

            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');

            //搜索类型：1：搜索店铺/品牌(流量超市搜索)；2：搜索商品(普通商品、店铺搜索)
//            $search_type    = input('search_type',1,'intval');

            if (!$keywords) return \json(self::callback(0,'参数错误'),400);

            if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));

            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            $data = [
                'store' => [],
                'product' => [],
            ];
//            if (1 != $search_type){
            $page   = input('page',0,'intval');
            $size   = input('size',10,'intval');
            $data['product']   = Pmodel::getProductsByKeyWordsForIos($keywords, $page, $size);
//                $data   = Pmodel::getProductsByKeyWords($keywords,$page,$size);
//                return \json(self::callback(1,'',$data));
//            }

//            if ($search_type == 2) {
            $where['store_name|brand_name'] = ['like',"%$keywords%"];
            $list = Db::name('store')->field('id,read_number,cover,dianzan,collect_number,store_name,description,dianzan,brand_name,address')
                ->where($where)
                ->where('sh_status',1)
                ->where('store_status',1)
                ->where('type',1)
                ->order('create_time','desc')
                ->limit($page,$size)
                ->select();

//                echo Db::name('store')->getLastSql();

            if ($list) {
                $result = Db::name('search_store_record')->where('search_keywords',$keywords)->setInc('search_number',1);

                if (false == $result){
                    Db::name('search_store_record')->insert(['search_keywords'=>$keywords,'search_number'=>1]);
                }
            }

            foreach ($list as $k=>$v){
                $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
//                $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                ##关注
                $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                ##收藏
//                $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
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
//            }
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺与商品的混合搜索
     */
    function searchStoreAndProduct_v3() {
        try {
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');
            $type = input('post.type',1,'intval');
            $lng = $this->request->has('lng') ? $this->request->param('lng') : '' ;
            $lat = $this->request->has('lat') ? $this->request->param('lat') : '' ;
            if(!$type)return \json(self::callback(0,'参数缺失'));
            //搜索类型：1：搜索店铺/品牌(流量超市搜索)；2：搜索商品(普通商品、店铺搜索)
//            $search_type    = input('search_type',1,'intval');
            if (!$keywords) return \json(self::callback(0,'参数错误'),400);
            if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $page   = input('page',0,'intval');
            $page = max(0,$page);
            $size   = input('size',10,'intval');
            $size = max(1,$size);
            if($type == 1){
                $data = Pmodel::getProductsByKeyWordsForIos0817($keywords, $page, $size);
            }else{
                $where['store_name|brand_name'] = ['like',"%$keywords%"];
                $total = (int)(Db::name('store')->where($where)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->where('type',1)
                    ->count());
                $max_page = ceil($total/$size);
                if(!$lat || !$lng){  ##设置默认经纬度为公司
                    $lat = "30.549100";
                    $lng = "104.067450";
                }

                $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"store.lat","store.lng").", 0) as distance";//计算距离
                $list = Db::name('store')->field(['id','read_number','cover','store_name','lng','lat','description','dianzan','collect_number','brand_name','address',$distance,'type as store_type'])
                    ->where($where)
                    ->where('sh_status',1)
                    ->where('store_status',1)
                    ->where('type',1)
                    ->order('create_time','desc')
                    ->limit($page*$size,$size)
                    ->select();
                if ($list) {
                    $result = Db::name('search_store_record')->where('search_keywords',$keywords)->setInc('search_number',1);
                    if (false == $result){
                        Db::name('search_store_record')->insert(['search_keywords'=>$keywords,'search_number'=>1]);
                    }
                }
                ##活动活动中的店铺ids
                $ac_store_ids = Logic::getOnlineAcStoreIds();
                foreach ($list as $k=>$v){
                    $list[$k]['is_activity'] = in_array($v['id'],$ac_store_ids)?1:0;
                    $list[$k]['is_dianzan'] = Db::name('store_dianzan_link')->where('store_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
//                    $list[$k]['dianzan'] = Db::name('store_dianzan_link')->where(['store_id'=>$v['id']])->count('id');
//                $list[$k]['comment_number'] = Db::name('store_comment')->where('store_id',$v['id'])->count();
                    ##关注
                    $list[$k]['follow_number'] = Db::name('store_follow')->where(['store_id'=>$v['id']])->count('id');
                    $list[$k]['is_follow'] = Db::name('store_follow')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
                    ##收藏
//                    $list[$k]['collect_number'] = Db::name('store_collection')->where(['store_id'=>$v['id']])->count('id');
                    $list[$k]['is_collect'] = Db::name('store_collection')->where(['store_id'=>$v['id'],'user_id'=>$user_id])->count('id');
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
                    //查询风格
                    $list[$k]['tags'] = UserLogic::GetStoreStyle($v['id']);
                    //查询人数
                    $list[$k]['read_user'] = UserLogic::GetPerson($v['id']);
                    //查询买单
                    $maidan = Db::name('maidan')->where('store_id',$v['id'])->where('status',1)->count();
                    //查询活动
                    $activityids = UserLogic::GetActivity($v['id']);
                    if($activityids || $maidan){
                        $list[$k]['activity']=UserLogic::GetTagsStore($activityids,$maidan);
                    }else{$list[$k]['activity'] =[];}
                }
                $data = compact('max_page','total','list');
            }
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }




    /**
     * 活动页面接口----活动2
     * @return Json
     */
    public function activityStore(){
        try{
            return \json(self::callback(1,'',101));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 活动页面接口----活动1
     * @return Json
     */
    public function activityProducts(){
        try{
            $coupon_id = 75;

            $is_get = 0;

            if(input('?user_id') || input('?token')){
                ##token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }

                ##判断是否领用卡券
                $is_get = UserLogic::checkUserCoupon($userInfo['user_id'],$coupon_id);
            }

            ##商品
            $ids = "4227,4228,4247,4230,4232,4233,4236,4246,4237,4238,4240,4242,4229";
            $data = [

                '4227' => [
                    'product_name' => '可口冷吃杏鲍菇',
                    'specs' => '150g',
                    'price' => '15'
                    ],
                '4228' => [
                    'product_name' => '冷吃鸡尖',
                    'specs' => '150g',
                    'price' => '25'
                    ],
                '4247' => [
                    'product_name' => '冷吃鸡爪(抓钱手)',
                    'specs' => '150g',
                    'price' => '27'
                    ],
                '4230' => [
                    'product_name' => '冷吃五香豆腐干',
                    'specs' => '150g',
                    'price' => '15'
                    ],
                '4232' => [
                    'product_name' => '冷吃鸭郡把子',
                    'specs' => '150g',
                    'price' => '28'
                ],
                '4233' => [
                    'product_name' => '冷吃鸭郡肝',
                    'specs' => '150g',
                    'price' => '28'
                ],
                '4236' => [
                    'product_name' => '冷吃鱿鱼须',
                    'specs' => '150g',
                    'price' => '33'
                ],
                '4246' => [
                    'product_name' => '冷吃掌中宝',
                    'specs' => '150g',
                    'price' => '32'
                ],
                '4237' => [
                    'product_name' => '麻辣冷吃牛肉',
                    'specs' => '150g',
                    'price' => '35'
                ],
                '4238' => [
                    'product_name' => '麻辣兔头',
                    'specs' => '两只装',
                    'price' => '28'
                ],
                '4240' => [
                    'product_name' => '香辣兔腿',
                    'specs' => '两只装',
                    'price' => '28'
                ],
                '4242' => [
                    'product_name' => '销魂冷吃鸭掌',
                    'specs' => '150g',
                    'price' => '28'
                ],
                '4229' => [
                    'product_name' => '原味冷吃兔',
                    'specs' => '150g',
                    'price' => '25'
                ],

            ];
            $products = logic::getActivityProductInfo($ids);
            foreach($products as &$v){
                $v['price'] = $data[$v['product_id']]['price'];
                $v['specs'] = $data[$v['product_id']]['specs'];
                $v['product_name'] = $data[$v['product_id']]['product_name'];
            }
            ##优惠券

            $coupon = Logic::getActivityCoupon($coupon_id);

            return \json(self::callback(1,'',compact('products','coupon','is_get')));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 卡券对应商品列表
     * @param CouponValidate $CouponValidate
     * @return Json
     */
    public function couponProducts(CouponValidate $CouponValidate){
        try{
            #验证
            $res = $CouponValidate->scene('coupon_products')->check(input());
            if(!$res)throw new Exception($CouponValidate->getError());

            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $page = max(1,$page);
            $size = max(10,$size);
            $coupon_id = input('post.coupon_id',0,'intval');

            $type = input('type',0,'intval');
            if(!$type){
                ##判断用户是否拥有优惠券
                $coupon_id = UserLogic::checkCoupon($coupon_id, $userInfo['user_id']);
            }

            $coupon_info = UserLogic::userCouponInfo2($coupon_id);
            if(!$coupon_info)throw new Exception('优惠券信息不存在');

            $product_ids = $coupon_info['product_ids'];
            $product_ids = explode(',',trimFunc($product_ids));
            $total = Logic::countCouponProducts($product_ids);
            $max_page = ceil($total/$size);
            $product_lists = Logic::getCouponProducts($product_ids,$page,$size);
            foreach($product_lists as &$v){
                $v['satisfy_money'] = $coupon_info['satisfy_money'];
                $v['coupon_money'] = $coupon_info['coupon_money'];
            }

            #返回
            return \json(self::callback(1,'',compact('max_page','total','product_lists')));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取活动2商品列表
     * @return Json
     */
    public function activityStoreProducts(){
        try{
            ##商品
            $ids = "8190,8195,8192,9042,8311,8312,8193,9043,9044,9602";
//            $ids = "3,4,5,2,1,9043,9044,9602";

            $list = Logic::getActivityStoreProducts($ids);

            return \json(self::callback(1,'',$list));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取活动信息
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getActivityInfo(){

        ##type => 1.新人专区；2.领券中心； 3.大牌集合地； 4.摩登好店

        try{
            $type = input('post.type',0,'intval');
            $activity_id = input('post.activity_id',0,'intval');
            if(!$type || !$activity_id)throw new Exception('参数缺失');

            ##token 验证
            $user_id = input('post.user_id',0,'intval');
            $token = input('post.token','','addslashes,strip_tags,trim');
            if($user_id || $token){
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            ##获取title
            $title = Logic::getAcShowTitle($activity_id);

            switch($type){
                case 1:  //新人专区
                    $data = Logic::getAcNewPersonInfo($activity_id, $user_id);
                    if($data['status'] == 0)throw new Exception($data['msg']);
                    break;
                case 2:
                    $data = [];
                    break;
                case 3:  //大牌集合馆
                    $data = Logic::getAcGreatBrandInfo($activity_id);
                    if($data['status'] == 0)throw new Exception($data['msg']);
                    break;
                case 4:  //摩登好店
                    $data = Logic::getAcModernInfo($activity_id);
                    if($data['status'] == 0)throw new Exception($data['msg']);
                    break;
                default:
                    $data = [];
                    break;
            }

            $data = $data['data'];

            $data['title'] = $title;

            return \json(self::callback(1,'',$data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取新人专区活动商品
     * @return Json
     */
    public function getAcNewPersonPro(){
        try{

            $activity_id = input('post.activity_id',0,'intval');
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $page = max(1,$page);
            if(!$activity_id)throw new Exception('参数缺失');

            ##获取活动信息
            $activity_info = Logic::getActivityInfo($activity_id);
            if(!$activity_info)throw new Exception('活动信息不存在');

            $total = Logic::countActivityProduct($activity_id);

            $max_page = ceil($total/$size);

            $list = Logic::getActivityProductList($activity_id, $page, $size);

            if($activity_info['activity_type'] == 2 || $activity_info['activity_type'] == 4){
                foreach($list as &$v){
                    $price = $v['price'];
                    $v['price'] = $v['price_activity_temp'];
                    $v['huaxian_price'] = $price;
                }
            }

            return \json(self::callback(1,'',compact('total','max_page','list')));

        }catch(Exception $e){

            return \json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 获取摩登好店活动商品
     * @return Json
     */
    public function getAcModernPro(){
        try{

            $activity_id = input('post.activity_id',0,'intval');
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $page = max(1,$page);
            if(!$activity_id)throw new Exception('参数缺失');

            ##获取活动信息
            $activity_info = Logic::getActivityInfo($activity_id);
            if(!$activity_info)throw new Exception('活动信息不存在');
            if($activity_info['activity_pro_type'] != 4)throw new Exception('活动商品格式错误');

            $activity_status = Logic::autoAcStartTime($activity_info)['status'];

            $total = Logic::countActivityStore($activity_id);

            $max_page = ceil($total/$size);

            $list = Logic::getActivityStoreTypeList($activity_id, $page, $size);

            if($activity_info['activity_type'] == 2 || $activity_info['activity_type'] == 4){  ##需要改变原价的活动
                foreach($list as &$v){
                    foreach($v['product'] as &$vv){
                        $price = $vv['price'];
                        $vv['price'] = $vv['price_activity_temp'];
                        $vv['huaxian_price'] = $price;
                    }
                }
            }

            foreach($list as &$v){
                foreach($v['product'] as &$vv){
                    $vv['activity_status'] = $activity_status;
                    if($vv['huaxian_price'] == 0)$vv['huaxian_price'] = $vv['price'];
                }
            }

            return \json(self::callback(1,'',compact('total','max_page','list')));

        }catch(Exception $e){

            return \json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 获取大牌集合地商品列表
     * @return Json
     */
    public function getAcGreatBrandPro(){

        try{
            $type_id = input('post.type_id',0,'intval');
            $activity_id = input('post.activity_id',0,'intval');

            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $page = max(1,$page);

            if(!$type_id || !$activity_id)throw new Exception('参数缺失');

            $user_id = input('post.user_id',0,'intval');
            $token = input('post.token','','addslashes,strip_tags,trim');
            if($user_id || $token){
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            ##获取活动信息
            $activity_info = Logic::getActivityInfo($activity_id);
            if(!$activity_info)throw new Exception('活动信息不存在');
            if($activity_info['activity_pro_type'] != 5)throw new Exception('活动商品格式错误');

            ##获取商品列表
            $total = Logic::countActivityTypePro($type_id);

            $max_page = ceil($total/$size);

            $list = Logic::getAcDiyTypeProList($type_id, $page, $size);
            if($activity_info['activity_type'] == 2 || $activity_info['activity_type'] == 4){
                foreach($list as &$v){
                    $price = $v['price'];
                    $v['price'] = $v['price_activity_temp'];
                    $v['huaxian_price'] = $price;
                }
            }

            foreach($list as &$v){
                if($v['huaxian_price'] == 0)$v['huaxian_price'] = $v['price'];
                $v['is_clock'] = $user_id?UserLogic::checkUserAcClock($user_id,$activity_id,$v['product_id']) : 0;
            }

            return \json(self::callback(1,'',compact('total','max_page','list')));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 添加 & 取消活动上线提醒
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function activityClock(){
        try{
            $type = input('post.type',0,'intval');
            $activity_id = input('post.activity_id',0,'intval');
            $product_id = input('post.product_id',0,'intval');
            if(!$type || !$activity_id || !$product_id)throw new Exception('参数缺失');

            ##token 验证
            $user_id = input('post.user_id',0,'intval');
            $token = input('post.token','','addslashes,strip_tags,trim');
            $userInfo = UserFunc::checkToken($user_id, $token);
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            ##逻辑
            if($type == 1){  ##增加提醒
                $res = UserLogic::addActivityClock($user_id, $activity_id, $product_id);
            }else{  ##取消提醒
                $res = UserLogic::cancelActivityClock($user_id, $activity_id, $product_id);
            }

            if($res === false)throw new Exception('操作失败');

            return \json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    public function getTestContent(){
        $content['content'] = "<h1><a href='https://www.baidu.com/'>wzh傻***************不**************傻</a></h1>";
        return json(self::callback(1,'',$content));
    }

    public function storeBannerDetail(){
        try{
            $banner_id = input('post.id',0,'intval');
            if(!$banner_id || $banner_id < 0)throw new Exception('参数错误');
            ##获取banner详情
            $content = Db::name('store_banner')->where(['id'=>$banner_id])->value('content');
            $content = htmlspecialchars_decode(stripslashes(htmlspecialchars_decode(stripslashes($content))));
            $content = str_replace('&nbsp;',' ',$content);
            return json(self::callback(1,'',compact('content')));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    public function test(){

        $data = Logic::productActivityInfo(5599);

        print_r($data);

    }

}