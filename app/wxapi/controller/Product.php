<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/12/5
 * Time: 13:47
 */

namespace app\wxapi\controller;
use app\common\controller\Base;
use app\wxapi\model\ChaoDaModel;
use app\wxapi\model\CommentModel;
use app\wxapi\model\CommentSupportModel;
use app\wxapi\model\GiftpackOrder;
use app\wxapi\model\MemberOrder;
use app\wxapi\model\Product as Pmodel;
use app\wxapi\model\TopicReadModel;
use think\Config;
use think\Db;
use app\wxapi\common\User;
use think\response\Json;
use think\Validate;
class Product extends Base
{
    /**
     * 潮搭详情
     */
    public function chaodaDetail(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            if(!$chaoda_id){
                return \json(self::callback(0,'参数错误！'));
            }
            $user_id = $this->request->get('user_id'); //用户id
            $chaoda=Db::name('chaoda')->where('id',$chaoda_id)->find();
            if(!$chaoda){
                return \json(self::callback(0,'没有该潮搭！'));
            }
            if($chaoda['is_delete']!=0){
                return \json(self::callback(0,'该潮搭已下架或已删除！'));
            }
            if($chaoda['is_pt_user']!=1){

                //-------------------------------------------------------------------------------------------------------------
                //商家潮搭
                $list = Db::view('chaoda','id as chaoda_id,store_id,cover as chaoda_cover,share_number,description,title,is_group')
                    ->view('store','store_name,cover,address,is_ziqu,lng as longitude,lat as latitude','store.id = chaoda.store_id','left')
                    ->where('chaoda.id',$chaoda_id)
                    ->where('store.store_status',1)
                    ->find();

                if(!$list){
                    return \json(self::callback(0,'没有找到该条信息！'));
                }
                $list['location']=[
                    'address'=>$list['address'],
                    'latitude'=>$list['latitude'],
                    'longitude'=>$list['longitude'],
                    'name'=>$list['store_name']
                ];
                unset ($list['latitude'], $list['longitude']);
                //查询所有潮搭图片
                $chaoda_img= Db::name('chaoda_img')->field('id,img_url')->where('can_use',1)->where('chaoda_id',$chaoda_id)->select();
                $list['images']=$chaoda_img;
                //潮搭商品tag信息
                $product_info = Db::view('chaoda_tag')
                    ->view('product','freight,is_buy,status','product.id = chaoda_tag.product_id','left')
                    ->where('chaoda_id',$chaoda_id)
                    ->where('product.status',1)
                    ->select();
                $total_group_buy_price=0;//计算总团购价格
                $total_price=0;//计算总划线价格
                foreach ($product_info as $k2=>$v2){
                    $product = Db::name('product_specs')->field('id,cover,product_specs,product_name,price,group_buy_price,huaxian_price,stock,share_img,platform_price')->where('product_id',$v2['product_id'])->find();
                    $product_info[$k2]['specs_id'] = $product['id'];
                    $product_info[$k2]['is_buy'] = $v2['is_buy'];
                    $product_info[$k2]['cover'] = $product['cover'];
                    $product_info[$k2]['product_specs'] = $product['product_specs'];
                    $product_info[$k2]['product_name'] = $product['product_name'];
                    $product_info[$k2]['price'] = $list['is_group']==1?$v2['price']:$product['price'];
                    $product_info[$k2]['group_buy_price'] = $product['group_buy_price'];
                    $product_info[$k2]['stock'] = $product['stock'];
                    $product_info[$k2]['huaxian_price'] = $product['price'];
                    $product_info[$k2]['share_img'] = $product['share_img'];
                    $product_info[$k2]['platform_price'] = $product['platform_price'];
                    if($list['is_group']==1){
                        $total_group_buy_price+=$v2['price'];
                    }else{
                        $total_group_buy_price+=$product['price'];
                    }
                   // $total_group_buy_price+=$v2['price'];//计算总团购价格
                    $total_price+=$product['price'];//计算总划线价格
                }
                $list['total_huaxian_price']=$total_price;//总划线金额
                $list['total_money']=$total_group_buy_price;//总团购金额
                $list['product_info'] = $product_info;
                //潮搭评论数
                $list['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();

                //店铺粉丝数
                $list['fans_number'] = Db::name('store_follow')->where('store_id',$list['store_id'])->count();

                //潮搭点赞数
                $list['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                //收藏数
                $list['collection_number'] = Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                if ($user_id) {
                    $list['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('store_id',$list['store_id'])->count();
                    $list['is_collection'] = Db::name('chaoda_collection')->where('user_id',$user_id)->where('chaoda_id',$chaoda_id)->count();
                    $list['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$chaoda_id)->count();
                }else{
                    $list['is_follow'] = 0;
                    $list['is_collection'] = 0;
                    $list['is_dianzan'] = 0;
                }
                //潮搭评论信息
                $list['comment_info'] = Db::view('chaoda_comment','content')
                    ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                    ->where('chaoda_comment.chaoda_id',$chaoda_id)
                    ->limit(0,2)
                    ->order('chaoda_comment.create_time','desc')
                    ->select();

                //推荐不超过6个潮搭列表
                    $tuijian = Db::view('chaoda','id,cover,share_number,description,store_id,title,address,type,cover_thumb')
                        ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                    ->where('is_delete','eq',0)
                    ->where('store_id','eq',$list['store_id'])
                    ->where('chaoda.id','neq',$chaoda_id)
                    ->order('chaoda.id','desc')
                    ->limit(6)
                    ->select();
                foreach ($tuijian as $k=>$v){
                    if($v['cover_thumb']){
                        $tuijian[$k]['cover'] = $v['cover_thumb'];
                    }
                    $tuijian[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
                    if ($user_id) {

                        $tuijian[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                    }else{

                        $tuijian[$k]['is_dianzan'] = 0;
                    }
                }
                $list['tuijian']=$tuijian;
                $list['type']=1;
                //用于区分是商家还是普通用户 商家返回1
                $data['list'] = $list;
                return \json(self::callback(1,'获取商家潮搭信息成功！',$data));
           //-------------------------------------------------------------------------------------------------------------

            }else{
                //此为普通用户数据
                $list = Db::view('chaoda','id as chaoda_id,cover as chaoda_cover,share_number,description,title,address,fb_user_id,type,is_group,latitude,longitude,name')
                    ->view('user','nickname,avatar','user.user_id = chaoda.fb_user_id','left')
                    ->where('chaoda.id',$chaoda_id)
                    ->find();
                if(!$list){
                    return \json(self::callback(0,'没有找到该条信息！'));
                }
                $list['location']=[
                    'address'=>$list['address'],
                    'latitude'=>$list['latitude'],
                    'longitude'=>$list['longitude'],
                    'name'=>$list['name']
                ];
                unset ($list['latitude'], $list['longitude'], $list['name']);
                //查询所有潮搭图片数量
               $chaoda_img= Db::name('chaoda_img')->field('id,img_url,type,cover')->where('chaoda_id',$chaoda_id)->where('can_use',1)->select();
//                //查询所有图片的tag
                    foreach ($chaoda_img as $k=>$v){
                        $chaoda_img[$k]['tags']= Db::name('chaoda_tag')->field('tag_name,x_postion,y_postion,direction')->where('img_id',$v['id'])->select();
                    };
                $list['images']=$chaoda_img;
                //潮搭评论数
                $list['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();

                //个人粉丝数
                $list['fans_number'] = Db::name('store_follow')->where('fb_user_id',$list['fb_user_id'])->count();

                //潮搭点赞数
                $list['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                //收藏数
                $list['collection_number'] = Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                if ($user_id) {
                    $list['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('fb_user_id',$list['fb_user_id'])->count();
                    $list['is_collection'] = Db::name('chaoda_collection')->where('user_id',$user_id)->where('chaoda_id',$chaoda_id)->count();
                    $list['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$chaoda_id)->count();
                }else{
                    $list['is_follow'] = 0;
                    $list['is_collection'] = 0;
                    $list['is_dianzan'] = 0;
                }
                //潮搭评论信息
                $list['comment_info'] = Db::view('chaoda_comment','content')
                    ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                    ->where('chaoda_comment.chaoda_id',$chaoda_id)
                    ->limit(0,2)
                    ->order('chaoda_comment.create_time','desc')
                    ->select();
                //推荐不超过6个潮搭列表
                $tuijian = Db::view('chaoda','id,cover,share_number,description,title,address,fb_user_id')
                    ->view('user','nickname,avatar','user.user_id = chaoda.fb_user_id','left')
                    ->where('chaoda.is_delete','eq',0)
                    ->where('chaoda.fb_user_id','eq',$list['fb_user_id'])
                    ->where('chaoda.id','neq',$chaoda_id)
                    ->order('chaoda.id','desc')
                    ->limit(6)
                    ->select();
                foreach ($tuijian as $k=>$v){
                    $tuijian[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
                    if ($user_id) {

                        $tuijian[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                    }else{

                        $tuijian[$k]['is_dianzan'] = 0;
                    }
                }
                $list['tuijian']=$tuijian;
                //普通用户则返回2
                $list['type']=2;
                $data['list'] = $list;
                return \json(self::callback(1,'获取普通潮搭信息成功！',$data));
            }
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }

 /**
 **商品详情
 **/

 public function productDetail(){
        try{
            $product_id = $this->request->has('product_id') ? $this->request->param('product_id') : 0 ;
            $product_specs = $this->request->has('product_specs') ? $this->request->param('product_specs') : '' ;
            $product_specs = htmlspecialchars_decode($product_specs);
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            if (!$product_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            if (!$product_specs){
                $product_specs = Db::name('product_specs')->where('product_id',$product_id)->value('product_specs');
            }
//            $data = Cache::get('product_'.$product_id.$product_specs);
            $data='';
            if ($data){
                return \json(self::callback(1,'',$data));
            }

            if (!Db::name('product')->where('id',$product_id)->count()) {
                throw new \Exception('id不存在');
            }
            $data = Db::view('product','id,category_id,sales,store_id,product_name,freight,is_buy,content,is_group_buy,start_time,end_time,huoli_money,type,see_type,buy_type,share_price,video,is_zdy_price,product_type,days')
                ->view('product_specs','id as specs_id,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
                ->view('store','store_name,cover as store_logo,address,is_ziqu,type,lng as longitude,lat as latitude','store.id = product.store_id','left')
                ->where('product.id',$product_id)
                ->where('store.store_status',1)
                ->where('product_specs.product_specs','eq',"{$product_specs}")
                ->find();

            $data['location']=[
                'address'=>$data['address'],
                'latitude'=>$data['latitude'],
                'longitude'=>$data['longitude'],
                'name'=>$data['store_name']
            ];
            unset ($data['latitude'], $data['longitude']);
            $specs = json_decode($data['product_specs']);
            foreach ($specs as $k=>$v){
                $specs .= $v.',';
            }
            $data['product_specs'] = $specs;
            if (!$data['product_specs']){
                throw new \Exception('商品不存在');
            }
            $data['link'] = "http://192.168.124.233/css/public/index.php/wxapi/product/productDetail/product_id/{$data['id']}.html";
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
             //查询两条评论
            $list = Db::view('product_comment','id,order_id,product_id,specs_id,content,create_time')
                ->view('user','nickname,avatar','user.user_id = product_comment.user_id','left')
                ->where('product_comment.product_id',$product_id)
                ->limit(0,1)
                ->order('create_time','desc')
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['comment_img']=Db::name('product_comment_img')->where('comment_id',$v['id'])->column('img_url');
            }
            $data['comment_total']=$total; //评论总数
            $data['comment_detail']=$list; //评论详情
            //Cache::set('product_'.$product_id.$product_specs,$data,3600);

            return \json(self::callback(1,'返回商品详情信息成功！',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 搜索功能（潮搭title，商品名，用户名，店铺名）
     */
public function search() {
        try {
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $user_id = input('user_id',0,'intval') ;
            $type = input('post.type',1,'intval');
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 20 ;
            if(!$type)return \json(self::callback(0,'参数缺失'));
            //搜索类型：1：搜索潮搭（潮搭title，user_name，store_name）；2：搜索商品(普通商品、店铺搜索，product_name)
            if (!$keywords) return \json(self::callback(0,'参数错误'),400);
            if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));

            if($type == 1){
                $where['chaoda.title|store.store_name|user.nickname'] = ['like',"%$keywords%"];
                $total=Db::view('chaoda','id,store_id,cover,title,fb_user_id,address,is_pt_user,type')
                    ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                    ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                    ->where('chaoda.is_delete',0)
                    ->where("store.store_status = 1 OR user.user_status =1 ")
                    ->where($where)->count();
                //潮搭
                $list = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                    ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                    ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                    ->where('chaoda.is_delete',0)
                    ->where("store.store_status = 1 OR user.user_status =1 ")
                    ->where($where)
                    ->page($page,$size)
                    ->order('chaoda.id','desc')
                    ->select();
                if(!$list){
                    $list=[];
                    $data=[
                        'data'=>$list,
                        ];
                    ;return \json(self::callback(1,'没有搜索找到相关信息',$data));
                }
                foreach ($list as $k=>$v){
                    //潮搭点赞数
                    $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
                    if($user_id){
                        //判断是否点赞
                        $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                    }else{
                        $list[$k]['is_dianzan'] = 0;
                    }
                }
            }else if($type==2){
                //商品
                $where['product.product_name'] = ['like',"%$keywords%"];
                $total=Db::view('chaoda_tag','chaoda_id,product_id')
                    ->view('chaoda','store_id,is_delete,type','chaoda_tag.chaoda_id = chaoda.id','left')
                    ->view('store','store_name,store_status','chaoda.store_id = store.id','left')
                    ->view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days','chaoda_tag.product_id = product.id','left')
                    ->view('product_specs','id as specs_id,product_specs,price,share_img,huaxian_price','product.id = product_specs.product_id','left')
                    ->where('chaoda.is_delete',0)
                    ->where('chaoda.store_id','gt',0)
                    ->where('product.status',1)
                    ->where('store.store_status',1)
                    ->where(['product_specs.id'=>['GT',0]])
                    ->where($where)
                    ->group('chaoda_tag.product_id')
                    ->count();
                $list = Db::view('chaoda_tag','chaoda_id,product_id')
                    ->view('chaoda','store_id,is_delete,type','chaoda_tag.chaoda_id = chaoda.id','left')
                    ->view('store','store_name,store_status','chaoda.store_id = store.id','left')
                    ->view('product','id,huoli_money,see_type,buy_type,store_id,product_name,share_price,is_zdy_price,product_type,days','chaoda_tag.product_id = product.id','left')
                    ->view('product_specs','id as specs_id,stock,product_specs,price,share_img,huaxian_price','product.id = product_specs.product_id','left')
                    ->where('chaoda.is_delete',0)
                    ->where('chaoda.store_id','gt',0)
                    ->where('product.status',1)
                    ->where('store.store_status',1)
                    ->where(['product_specs.id'=>['GT',0]])
                    ->where($where)
                    ->page($page,$size)
                    ->group('chaoda_tag.product_id')
                    ->order('')
                    ->select();
                if(!$list){
                    $list=[];
                    $data=[
                        'data'=>$list,
                    ];
                    ;return \json(self::callback(1,'没有搜索找到相关信息',$data));
                }
                foreach ($list as $k=>$v){
                    $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                    $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
                }
            }else{
                return \json(self::callback(0,'搜索类型不正确'));
            }
            //增加搜索记录
            $search_keywords = Db::name('search_store_record')->where('search_keywords',$keywords)->where('client_type',1)->find();
            if($search_keywords){
                //+1
               Db::name('search_store_record')->where('id',$search_keywords['id'])->setInc('search_number',1);
            }else{
                //新增
                $newsearch = [
                    'search_keywords' => $keywords,
                    'search_number' => 1,
                    'client_type' => 1
                ];
                Db::table('search_store_record')->insert($newsearch);
            }
            $list = arraySequence($list,'dianzan_number');
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$list;
            return json(self::callback(1,'成功',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 店铺详情/个人详情
     */
    public function store_or_user_Detail(){
        try{
            //获取分类的潮搭类型
            $store_id = $this->request->post('store_id');
            $fb_user_id = $this->request->post('fb_user_id');
            //获取用户
            $user_id = $this->request->post('user_id');
            if(!$store_id && !$fb_user_id){
                return \json(self::callback(0,'参数错误！'));
            }
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 20 ;
            if(isset($store_id) && !$fb_user_id){
                //店铺
                $store=Db::name('store')->field('store_name,cover')->where('id',$store_id)->find();
                if(!$store){
                    return \json(self::callback(0,'没有该店铺！'));
                }
                $data['type']=1;
                $data['avatar']=$store['cover'];
                $data['name']=$store['store_name'];
                $where['chaoda.store_id'] = $store_id;
                $where1['store_id'] = $store_id;
                $product=Db::name('product_collection')->where('store_id',$store_id)->count();
                $chaoda=Db::name('chaoda_collection')->where('store_id',$store_id)->count();
                $data['collection_number']=$product+$chaoda;
                $data['dianzan_number']=Db::name('chaoda_dianzan')->where('store_id',$store_id)->count();
            }else if(isset($fb_user_id) && !$store_id){
                //个人
                $user=Db::name('user')->field('nickname,avatar')->where('user_id',$fb_user_id)->find();
                if(!$user){
                    return \json(self::callback(0,'没有该用户！'));
                }
                $data['type']=2;
                if(empty($user['avatar'])){
                    $user['avatar'] ='/default/user_logo.png';
                }
                $data['avatar']=$user['avatar'];
                $data['name']=$user['nickname'];
                $where['chaoda.fb_user_id'] = $fb_user_id;
                $where1['fb_user_id'] = $fb_user_id;
                $where2['user_id'] = $fb_user_id;
                $data['follow_number']=Db::name('store_follow')->where($where2)->count();
                $chaoda = Db:: view('chaoda_collection','chaoda_id,user_id')
                    ->view('chaoda','id,fb_user_id,is_delete,is_pt_user','chaoda.id = chaoda_collection.chaoda_id','left')
                    ->view('user','user_id,user_status','chaoda.fb_user_id = user.user_id','left')
                    ->where('chaoda_collection.user_id',$fb_user_id)
                    ->where('chaoda.is_delete',0)
                    ->count();

                //2.商品收藏
                $shangpin = Db::view('product_collection')
                    ->view('product_specs','product_specs','product_collection.specs_id = product_specs.id','right')
                    ->view('product','product_name','product.id = product_collection.product_id','left')
                    ->where('product_collection.user_id',$fb_user_id)
                    ->where('product.status','eq',1)
                    ->where('product_specs.id','gt',0)
                    ->count();

                $data['collection_number']=$chaoda+$shangpin;
            }else{
                return \json(self::callback(0,'未知错误！'));
            }

            $total=Db::view('chaoda','id,store_id,cover,title,fb_user_id,address,is_pt_user,type,category_id')
                ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->where('chaoda.is_delete','eq',0)
                ->where($where)
                ->where(("store.store_status = 1  OR user.user_status =1 OR user.user_status =3"))->count();

            $list = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->where('chaoda.is_delete','eq',0)
                ->where($where)
                ->where(("store.store_status = 1  OR user.user_status =1 OR user.user_status =3"))
                ->page($page,$size)
                ->order('chaoda.id','desc')
                ->select();

            foreach ($list as $k=>$v){
                //潮搭点赞数
                $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
                if($user_id){
                    //判断是否点赞
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
            }
            if($user_id){
                //判断是否关注
                $data['is_guanzhu'] = Db::name('store_follow')->where('user_id',$user_id)->where($where1)->count();
            }else{
                $data['is_guanzhu'] = 0;
            }
            $data['fans_number']=Db::name('store_follow')->where($where1)->count();
            $list = arraySequence($list,'dianzan_number');
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            if(empty($list)){
                $list=[];
            }
            $data['data']=$list;
            return json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
 * 店铺详情或个人详情页 潮搭点赞和取消点赞
 * */
    public function dianzan_and_cancel(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $chaoda_id=$param['chaoda_id'];
            $status=$param['status'];
            if(!$chaoda_id || !$status){
                return \json(self::callback(0,'参数错误'),400);
            }
            $chaoda= Db::name('chaoda')->field('id,store_id,fb_user_id')->where('id',$chaoda_id)->where('is_delete',0)->find();
            if(!$chaoda){return \json(self::callback(0,'没有找到该潮搭或已下架'),400);}
            if($status=='true'){
                $guan= Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
                if ($guan==0){
                    //写入点赞
                    if($chaoda['store_id']>0){
                        Db::name('chaoda_dianzan')->insert([
                            'chaoda_id' => $chaoda_id,
                            'user_id' => $userInfo['user_id'],
                            'store_id' => $chaoda['store_id'],
                            'create_time' => time()
                        ]);
                    }elseif($chaoda['fb_user_id']>0){
                        Db::name('chaoda_dianzan')->insert([
                            'chaoda_id' => $chaoda_id,
                            'user_id' => $userInfo['user_id'],
                            'fb_user_id' => $chaoda['fb_user_id'],
                            'create_time' => time()
                        ]);
                    }

                    //统计最新点赞
                    $xie= Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($xie==0){
                        //写入失败
                        $data['chaoda_number']=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                        if($data['chaoda_number']==0){
                            $data['chaoda_number']=0;
                        }
                        if($chaoda['store_id']>0){
                            $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['store_id'])->count();
                        }elseif($chaoda['fb_user_id']>0){
                            $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['fb_user_id'])->count();
                        }else{
                            $data['total_number']=0;
                        }

                        return \json(self::callback(0,'点赞失败',$data,true));
                    }else{
                         Db::name('chaoda')->where('id',$chaoda_id)->setInc('dianzan_number');
                        //写入成功
                        $data['chaoda_number']=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                        if($data['chaoda_number']==0){
                            $data['chaoda_number']=0;
                        }
                        if($chaoda['store_id']>0){
                            $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['store_id'])->count();
                        }elseif($chaoda['fb_user_id']>0){
                            $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['fb_user_id'])->count();
                        }else{
                            $data['total_number']=0;
                        }
                        return \json(self::callback(1,'点赞成功',$data,true));
                    }

                }else{

                    $data['chaoda_number']=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data['chaoda_number']==0){
                        $data['chaoda_number']=0;
                    }
                    if($chaoda['store_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['store_id'])->count();
                    }elseif($chaoda['fb_user_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['fb_user_id'])->count();
                    }else{
                        $data['total_number']=0;
                    }
                    return \json(self::callback(0,'已点赞',$data,true));
                }

            }else if($status=='false'){

                $de=Db::name('chaoda_dianzan')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
                if($de==0){
                    //删除失败
                    $data['chaoda_number']=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data['chaoda_number']==0){
                        $data['chaoda_number']=0;
                    }
                    if($chaoda['store_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['store_id'])->count();
                    }elseif($chaoda['fb_user_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['fb_user_id'])->count();
                    }else{
                        $data['total_number']=0;
                    }
                    return \json(self::callback(0,'已取消',$data,true));

                }else{
                    //删除成功
                    Db::name('chaoda')->where('id',$chaoda_id)->setDec('dianzan_number');
                    $data['chaoda_number']=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data['chaoda_number']==0){
                        $data['chaoda_number']=0;

                    }
                    if($chaoda['store_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['store_id'])->count();
                    }elseif($chaoda['fb_user_id']>0){
                        $data['total_number']=Db::name('chaoda_dianzan')->where('store_id',$chaoda['fb_user_id'])->count();
                    }else{
                        $data['total_number']=0;
                    }
                    return \json(self::callback(1,'取消点赞成功',$data,true));
                }
            }else{
                return \json(self::callback(0,'操作错误'));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     *发布详情
     */
    public function fabuDetail(){
        try {
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            //获取潮搭id
            $chaoda_id = $this->request->post('chaoda_id');
            if (!$chaoda_id) {
                return json(self::callback(0,'参数错误'),400);
            }
            $list = Db::name('chaoda')->field('id as chaoda_id,cover,title,is_delete,status,description,create_time,address,fb_user_id')->where('id',$chaoda_id)->find();
            if(!$list){
                return json(self::callback(0,'没有找到这条潮搭'),400);
            }
            //查询所有潮搭图片数量
            $chaoda_img= Db::name('chaoda_img')->field('id,img_url,type,cover')->where('can_use',1)->where('chaoda_id',$chaoda_id)->select();
//                //查询所有图片的tag
            foreach ($chaoda_img as $k=>$v){
                $chaoda_img[$k]['tags']= Db::name('chaoda_tag')->field('tag_name,x_postion,y_postion,direction')->where('img_id',$v['id'])->select();
            };
            $list['images']=$chaoda_img;
            //潮搭评论数
            $list['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();
            //潮搭点赞数
            $list['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
            //收藏数
            $list['collection_number'] = Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
            if ($list['fb_user_id']) {
                $list['is_comment'] = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->where('user_id',$list['fb_user_id'])->count();
                $list['is_collection'] = Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$list['fb_user_id'])->count();
                $list['is_dianzan'] = Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$list['fb_user_id'])->count();
            }else{
                $list['is_comment'] = 0;
                $list['is_collection'] = 0;
                $list['is_dianzan'] = 0;
            }
            //潮搭评论信息
            $list['comment_info'] = Db::view('chaoda_comment','content')
                ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                ->where('chaoda_comment.chaoda_id',$chaoda_id)
                ->limit(0,2)
                ->order('chaoda_comment.create_time','desc')
                ->select();
            $data['list'] = $list;
            return \json(self::callback(1,'获取普通潮搭信息成功！',$data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
     * 商品分享统计次数
     * */
    public function shareCount(){
        try{
            $product_id = $this->request->post('product_id');
            if(!$product_id){
                return \json(self::callback(0,'参数错误',false));
            }
            $res = Db::name('product')->where('id',$product_id)->setInc('share_number',1);

            if ($res===false){
                return \json(self::callback(0,'操作失败',false));
            }
            $data= Db::name('product')->field('id,share_number')->where('id',$product_id)->find();
            return \json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

   /**
     * 商品评论列表
     */
    public function productCommentList(){
        try {
            $product_id = $this->request->has('product_id') ? intval($this->request->param('product_id')) : 0 ;
            $page = $this->request->has('page') ? intval($this->request->param('page')) : 1 ;
            $size = $this->request->has('size') ? intval($this->request->param('size')) : 10 ;
            $user_id = $this->request->post('user_id');
            $user_id = intval($user_id);

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
//              $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                //统计每条评论点赞数
                $list[$k]['dianzan_number'] = Db::name('product_comment_dianzan')->where('comment_id',$v['id'])->count();
                $list[$k]['comment_img'] = Db::name('product_comment_img')->where('comment_id',$v['id'])->column('img_url');
                $product_specs = Db::name('product_order_detail')->where('order_id',$v['order_id'])->where('product_id',$v['product_id'])->where('specs_id',$v['specs_id'])->value('product_specs');
                $product_specs = json_decode($product_specs,true);
                $specs = '';
                foreach ($product_specs as $k1 => $v1) {
                    $specs = $k1.';'.$v1;
                }
                $list[$k]['product_specs'] = $specs;

            }
            if($user_id){
                foreach ($list as $k=>$v){

                    $rst= Db::name('chaoda_comment_dianzan')->where('user_id',$user_id)->where('comment_id',$v['id'])->count();
                    if($rst==0){
                        $list[$k]['is_dianzan']=0;
                    }else{}
                    $list[$k]['is_dianzan']=$rst;
                }
            }else{
                foreach ($list as $k=>$v){

                    $list[$k]['is_dianzan']=0;

                }
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回评论列表成功！',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 评论列表点赞
     */
    public function commentDianzan(){
        try {
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $comment_id = $this->request->post('id');
            $type = $this->request->post('type');
            $status = $this->request->post('status');
            if (!$comment_id || !$type || !$status) {
                return \json(self::callback(0,'参数错误',false));
            }
            if($status=='true'){
                //点赞
                if($type=='chaoda'){

                    $number = Db::name('chaoda_comment_dianzan')->where('comment_id', $comment_id)->where('user_id', $userInfo['user_id'])->find();
                    if(!$number){
                        //可以点赞
                        $addcomment = [
                            'comment_id' => $comment_id,
                            'user_id' => $userInfo['user_id'],
                            'create_time' => time()
                        ];

                        $rst=Db::name('chaoda_comment_dianzan')->insert($addcomment);
                        if($rst===false){
                            return \json(self::callback(0,'点赞失败',false));
                        }
                        $dianzanumber = Db::name('chaoda_comment_dianzan')->where('comment_id', $comment_id)->count();
                        if($dianzanumber==0){
                            $dianzanumber=0;
                        }
                        //点赞数量
                        $data=$dianzanumber;
                    }else{
                        //返回
                        return \json(self::callback(0,'不能重复点赞',false));
                    }

                }else if($type=='product'){

                    $number = Db::name('product_comment_dianzan')->where('comment_id', $comment_id)->where('user_id', $userInfo['user_id'])->find();
                    if(!$number){
                        //可以点赞
                        $addcomment = [
                            'comment_id' => $comment_id,
                            'user_id' => $userInfo['user_id'],
                            'create_time' => time()
                        ];

                        $rst=Db::name('product_comment_dianzan')->insert($addcomment);
                        if($rst===false){
                            return \json(self::callback(0,'点赞失败',false));
                        }
                        $dianzanumber = Db::name('product_comment_dianzan')->where('comment_id', $comment_id)->count();
                        if($dianzanumber==0){
                            $dianzanumber=0;
                        }
                        //点赞数量
                        $data=$dianzanumber;
                    }else{
                        //返回
                        return \json(self::callback(0,'不能重复点赞',false));
                    }

                }else{
                    return \json(self::callback(0,'未知错误',false));
                }
                return \json(self::callback(1,'点赞成功！',$data));

            }else if($status=='false'){
                //取消点赞
                if($type=='chaoda'){
                    $num=Db::name('chaoda_comment_dianzan')->where('user_id',$userInfo['user_id'])->where('comment_id',$comment_id)->find();
                    if(!$num){
                        $data=Db::name('chaoda_comment_dianzan')->where('comment_id',$comment_id)->count();
                        return \json(self::callback(0,'已取消',$data,true));
                    }
                    $de=Db::name('chaoda_comment_dianzan')->where('user_id',$userInfo['user_id'])->where('comment_id',$comment_id)->delete();
                    if($de===false){
                        //删除失败
                        $data=Db::name('chaoda_comment_dianzan')->where('comment_id',$comment_id)->count();
                        return \json(self::callback(0,'取消失败',$data,true));
                    }else{
                        //删除成功
                        $data=Db::name('chaoda_comment_dianzan')->where('comment_id',$comment_id)->count();
                        return \json(self::callback(0,'取消成功',$data,true));
                    }

                }else if($type=='product'){
                    $num=Db::name('product_comment_dianzan')->where('user_id',$userInfo['user_id'])->where('comment_id',$comment_id)->find();
                    if(!$num){
                        $data=Db::name('product_comment_dianzan')->where('comment_id',$comment_id)->count();
                        return \json(self::callback(0,'已取消',$data,true));
                    }
                    $de=Db::name('product_comment_dianzan')->where('user_id',$userInfo['user_id'])->where('comment_id',$comment_id)->delete();
                    if($de===false){
                        //删除失败
                        $data=Db::name('product_comment_dianzan')->where('comment_id',$comment_id)->count();
                        return \json(self::callback(0,'取消点赞失败',$data,true));

                    }else{
                        //删除成功
                        $data=Db::name('product_comment_dianzan')->where('comment_id',$comment_id)->count();
                    }
                }else{
                    return \json(self::callback(0,'未知错误',false));
                }
                return \json(self::callback(1,'取消点赞成功！',$data,true));

            }else{
                return \json(self::callback(0,'未知错误',false));
            }

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 潮搭评论列表
     */
    public function chaodaCommentList(){
        try {
            $chaoda_id = $this->request->has('chaoda_id') ? intval($this->request->param('chaoda_id')) : 0 ;
            $page = $this->request->has('page') ? intval($this->request->param('page')) : 1 ;
            $size = $this->request->has('size') ? intval($this->request->param('size')) : 10 ;
            $user_id = $this->request->post('user_id');
            $user_id = intval($user_id);
            if (!$chaoda_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $total = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count(); //潮搭评论数量
            $list = Db::view('chaoda_comment','id,chaoda_id,content,create_time')
                ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                ->where('chaoda_comment.chaoda_id',$chaoda_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();
            foreach ($list as $k1=>$v1){
                $list[$k1]['dianzan_number'] = Db::name('chaoda_comment_dianzan')->where('comment_id',$v1['id'])->count();
            }

            if($user_id){
                foreach ($list as $k=>$v){

                    $rst= Db::name('chaoda_comment_dianzan')->where('user_id',$user_id)->where('comment_id',$v['id'])->count();
                    if($rst==0){
                        $list[$k]['is_dianzan']=0;
                    }
                    $list[$k]['is_dianzan']=$rst;
                }
            }else{
                foreach ($list as $k=>$v){

                    $list[$k]['is_dianzan']=0;

            }
            }

            //判断是否点赞

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回潮搭评论列表成功！',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 潮搭评论列表点赞
     */
    public function chaodaCommentdianzan(){
        try {
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $comment_id = $this->request->post('id');
            if (!$comment_id) {
                return \json(self::callback(0,'参数错误',false));
            }

           $rst= Db::table('chaoda_comment')->where('id', $comment_id)->setInc('dianzan');
        if($rst===false){
            return \json(self::callback(0,'点赞失败',false));
        }
            $dianzan = Db::name('chaoda_comment')->where('id', $comment_id)->value('dianzan'); //点赞数量
            $data=intval($dianzan);
            return \json(self::callback(1,'点赞成功！',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 商品收藏列表
     */
    public function collectionList(){
        try{
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::view('product_collection')
                ->view('product_specs','product_name,product_specs,price','product_collection.specs_id = product_specs.id','left')
                ->view('product','product_name','product.id = product_collection.product_id','right')
                ->view('store','id as store_id,store_name,cover,store_status','store.id = product.store_id','left')
                ->where('product_collection.user_id',$userInfo['user_id'])
                ->where('product.status','eq',1)
                ->where('product_specs.id','gt',0)
                ->where('store.store_status',1)
                ->count();

            $list = Db::view('product_collection')
                ->view('product_specs','product_name,product_specs,price','product_collection.specs_id = product_specs.id','left')
                ->view('product','product_name','product.id = product_collection.product_id','right')
                ->view('store','id as store_id,store_name,cover,store_status','store.id = product.store_id','left')
                ->where('product_collection.user_id',$userInfo['user_id'])
                ->where('product.status','eq',1)
                ->where('store.store_status',1)
                ->where('product_specs.id','gt',0)
                ->order('product_collection.create_time','desc')
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v) {
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['product_id'])->value('img_url');

            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'查询成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 标签动态列表
     */
    public function tagdynamicList(){
        try {
            $id = input('id') ? intval(input('id')) : 0 ;//标签id
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ; //用户id
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if (!$id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $tag = Db::name('tag')->field('id,title,description,status,list_bg_cover as bg_cover')->where('id',$id)->find();
            if(!$tag){
                return \json(self::callback(0,'标签不存在'),400);
            }
            if($tag['status']!=1){
                return \json(self::callback(0,'标签已下架或删除'),400);
            }
            unset($tag['status']);
            $tag_id='['.$id.']';
            $where['chaoda.tag_ids'] = ['like',"%$tag_id%"];
            $total = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->group('chaoda.id')
                ->count();
            $list = Db::view('chaoda','id,store_id,cover,description,fb_user_id,title,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title as tag_title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->group('chaoda.id')
                ->page($page,$size)
                ->order('chaoda.id','desc')
                ->select();
            $is_guanzhu=0;
            if(isset($user_id) && $user_id>0){
                    // 老代码 查询错误
                    // $num= Db::name('tag_follow')->where('user_id',$user_id)->count();
                    $num = Db::name('tag_follow')->where(['user_id' => $user_id, 'tag_id' => $id])->count();
                    if($num==1){$is_guanzhu=$num;}
            }
            //缩略图
            $thumb_conf = config('config_common.compress_config');
            $thumb_mark = "_{$thumb_conf['chaoda'][0]}X{$thumb_conf['chaoda'][1]}";
            foreach ($list as $k=>$v){
                //判断是否点赞
                if(isset($user_id) && $user_id>0){
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }

                if(!$v['cover_thumb'] || !strstr($v['cover_thumb'], $thumb_mark)){  //不是视频封面且没有生成缩略图
                    ##生成缩略图
                    $path = createThumb($v['cover'],'uploads/product/thumb/', 'chaoda');
                    if(file_exists(trim($path,'/'))){ //修改cover_thumb字段
                        Db::name('chaoda')->where(['id'=>$v['id']])->setField('cover_thumb',$path);
                        $list[$k]['cover'] = $path;
                    }
                }else{
                    $list[$k]['cover'] = $v['cover_thumb'];
                }
            }
            $tag['is_follow']=$is_guanzhu;
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['tag'] = $tag;
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 话题动态列表
     */
    public function topicdynamicList(){
        try {
            $topic_id = input('id') ? intval(input('id')) : 0 ;//话题id
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ; //用户id
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if (!$topic_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
            $topic = Db::name('topic')->field('id,title,description,status,list_bg_cover as bg_cover')->where('id',$topic_id)->find();
            if(!$topic){
                return \json(self::callback(0,'话题不存在'),400);
            }
            if($topic['status']!=1){
                return \json(self::callback(0,'话题已下架或删除'),400);
            }
            unset($topic['status']);
            $where['chaoda.topic_id'] = ['eq',$topic_id];
            $total = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->group('chaoda.id')
                ->count();
            $list = Db::view('chaoda','id,store_id,cover,description,title,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title as topic_title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->group('chaoda.id')
                ->page($page,$size)
                ->order('chaoda.id','desc')
                ->select();
            $is_guanzhu=0;
            if(isset($user_id) && $user_id>0){
                // 老代码  查询错误
                // $num= Db::name('tag_follow')->where('user_id',$user_id)->count();
                $num = Db::name('topic_follow')->where(['user_id' => $user_id, 'topic_id' => $topic_id])->count();
                if($num==1){$is_guanzhu=$num;}
            }
            //缩略图
            $thumb_conf = config('config_common.compress_config');
            $thumb_mark = "_{$thumb_conf['chaoda'][0]}X{$thumb_conf['chaoda'][1]}";

            foreach ($list as $k=>$v){
                //判断是否是多图
                if($v['type']=='' || $v['type']=='image'){
                    $num[$k]= Db::name('chaoda_img')->where('chaoda_id',$v['id'])->where('type','image')->count();
                    $num2[$k]= Db::name('chaoda_img')->where('chaoda_id',$v['id'])->where('type','video')->count();
                    if($num2[$k]<=0){
                        if($num[$k]>=2){$list[$k]['type'] ='images';}elseif ($num[$k]<=1){
                            $list[$k]['type'] ='image';
                        }
                    }else{
                        $list[$k]['type'] ='video';
                    }
                }

                //判断是否点赞
                if(isset($user_id) && $user_id>0){
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
                //缩略图
                if(!$v['cover_thumb'] || !strstr($v['cover_thumb'], $thumb_mark)){  //不是视频封面且没有生成缩略图
                    ##生成缩略图
                    $path = createThumb($v['cover'],'uploads/product/thumb/', 'chaoda');
                    if(file_exists(trim($path,'/'))){ //修改cover_thumb字段
                        Db::name('chaoda')->where(['id'=>$v['id']])->setField('cover_thumb',$path);
                        $list[$k]['cover'] = $path;
                    }
                }else{
                    $list[$k]['cover'] = $v['cover_thumb'];
                }

            }
            $is_read = TopicReadModel::where(['topic_id' => $topic_id,'user_id' => $user_id]) -> find();
            if (!$is_read){

                TopicReadModel::insert(['topic_id' => $topic_id,'user_id' => $user_id,'create_time'=>time()]);
            }
            $topic['is_follow']=$is_guanzhu;
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['topic'] = $topic;
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
* 关注标签和取消关注
* */
    public function followandcancel(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $id = input('id') ? intval(input('id')) : 0 ;//id
            $type = input('type') ? intval(input('type')) : 0 ;//1:标签 2:话题
            $status = input('status') ;//1：关注 -1：取消关注
            if(!$id || !$type ||!$status){return \json(self::callback(0,'参数错误'),400);}

            //判断状态
            if($type==1){
                //标签
                $table='tag_follow';
                $table2='tag';
                $filed='tag_id';
                $msg='标签';
            }elseif($type==2){
                //话题
                $table='topic_follow';
                $table2='topic';
                $filed='topic_id';
                $msg='话题';
            }else{
                return \json(self::callback(0,'参数错误2'),400);
            }
            $rst= Db::name($table2)->field('id,title,description,status')->where('id',$id)->find();
            if(!$rst){
                return \json(self::callback(0,'查找内容不存在'),400);
            }
            if($rst['status']!=1){
                return \json(self::callback(0,$msg.'已下架或删除'),400);
            }
            if($status==1){
                //关注
                $guan= Db::name($table)->where($filed,$id)->where('user_id',$userInfo['user_id'])->find();
                if($guan){
                    return \json(self::callback(0,'已关注',false,true));
                }else{
                    //写入关注
                    $rst=Db::name($table)->insert([
                        'user_id' => $userInfo['user_id'],
                        $filed => $id,
                        'create_time' => time()
                    ]);
                    if($rst===false){
                        return \json(self::callback(0,'关注失败',false,true));
                    }else{
                        $rst= Db::name($table2)->where('id',$id)->setInc('follow_number');//增加关注数
                        //写入成功
                        return \json(self::callback(1,'关注成功',true));
                    }
                }
             }elseif($status==-1){
                $guan= Db::name($table)->where($filed,$id)->where('user_id',$userInfo['user_id'])->find();
                if($guan){
                    //取消关注
                    $de=Db::name($table)->where('user_id',$userInfo['user_id'])->where($filed,$id)->delete();
                    if($de===false){
                        //删除失败
                        return \json(self::callback(0,'取消关注失败',false,true));
                    }else{
                        $rst= Db::name($table2)->where('id',$id)->setDec('follow_number');//减少关注数
                        //删除成功
                        return \json(self::callback(1,'取消关注成功',true));
                    }
                }else{
                    return \json(self::callback(0,'已取消',false,true));
                }
            }else{
                return \json(self::callback(0,'状态错误'),400);
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
 * 删除动态
 * */
    public function deleteChaoda(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $id = input('id') ? intval(input('id')) : 0 ;//潮搭id
            if(!$id){
                return \json(self::callback(0,'参数错误'),400);
            }
            $rst=  Db::name('chaoda')->where('id',$id)->where('fb_user_id',$userInfo['user_id'])->setField('is_delete',-1);
            if($rst===false){
                return \json(self::callback(0,'删除失败',false,true));
            }else{
                return \json(self::callback(1,'删除成功',true,true));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
 * 创建话题
 * */
    public function createTopic(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $title = input('title','','addslashes,strip_tags,trim');
            $description = input('description','','addslashes,strip_tags,trim');
            if(!$title){
                return \json(self::callback(0,'参数错误'),400);
            }
            $rst=  Db::name('topic')->where('title',$title)->find();
            if($rst){
                return \json(self::callback(0,'该话题已存在!',false,true));
            }else{
                //创建
                $data = ['title' =>$title, 'description' => $description,'user_id'=>$userInfo['user_id'],'create_time'=>time()];
                $key=  Db::name('topic')->insertGetId($data);
            if($key===false){
                return \json(self::callback(0,'创建话题失败',false,true));
            }else{
                $data=  Db::name('topic')->field('id,title,description')->where('id',$key)->find();
                return \json(self::callback(1,'创建话题成功',$data,true));
            }
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 添加和查询库存数量
     */
    public function selectStock(){

        try {

            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            //验证接收的数字
            $number = $this->request->post('number');
            $specs_id = $this->request->post('specs_id');
            if(!$number || !$specs_id){
                return \json(self::callback(0,'参数错误',false));
            }
            if(is_numeric($number)&&is_numeric($specs_id)){
                //接收的是数字
                $number=intval($number);
                if($number==0 || $number<0){
                    return \json(self::callback(0,'不能为0或负数',false));
                }
                if (!Db::name('product_specs')->where('id',$specs_id)->count()){
                    return \json(self::callback(0,'商品不存在'));
                }

                //判断库存
                $rst= Db::table('product_specs')->field('id,stock')->where('id',$specs_id)->find();
                if($rst['stock']<$number ){
                    return \json(self::callback(1,'库存不足',-1));
                }else{
                    return \json(self::callback(1,'',true));
                }

            }else{
                //报错
                return \json(self::callback(0,'参数错误',false));
            }

        } catch (\Exception $e) {

            return \json(self::callback(0,$e->getMessage()));
        }
    }


//---------------------------------------------------------------------------------





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
                throw new \Exception('不能重复点赞');
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
                throw new \Exception('不能重复点赞');
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
    function searchStoreOrProduct() {
        try {
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            //搜索类型：1：搜索商品；2：搜索店铺/品牌
            $search_type = input('search_type',0);
            if (!$search_type) return \json(self::callback(0,'参数错误'),400);
            if (($search_type !=1 && $search_type!=2) ) return \json(self::callback(0,'参数错误'),400);
            if (!empty($keywords)) {
                if(substr_count($keywords,'%'))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_'))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')))return \json(self::callback(0,'关键词不能包含关键词_%'));
            }
            $page = input('page',0,'intval');
            $size = input('size',20,'intval');
            if ($search_type==1){
                //商品
                $where['product.product_name'] = ['like',"%$keywords%"];
                $total = Db::view('product','id,product_name')
                    ->view('product_specs','price,cover','product.id = product_specs.product_id','left')
                    ->view('store','store_name','product.store_id = store.id','left')
                    ->where($where)
                    ->where('product.status',1)
                    ->where('store.store_status',1)
                    ->group('product.id')
                    ->count();

                $list = Db::view('product','id,product_name')
                    ->view('product_specs','id as specs_id,price,cover','product.id = product_specs.product_id','left')
                    ->view('store','store_name','product.store_id = store.id','left')
                    ->where($where)
                    ->where('product.status',1)
                    ->where('store.store_status',1)
                    ->page($page,$size)
                    ->group('product.id')
                    ->select();
            }
            if ($search_type==2){
                //店铺
                $where['store_name'] = ['like',"%$keywords%"];
                $total = Db::name('store')->field('id,cover,store_name,address')
                    ->where('store_status',1)
                    ->where($where)
                    ->count();
                $list = Db::name('store')->field('id,cover,store_name,address')
                    ->where('store_status',1)
                    ->where($where)
                    ->page($page,$size)
                    ->select();
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 获取标签
     */
    public function gettag(){
        try{
            //token 验证
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');
            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }}
            $page   = input('page',0,'intval');
            $size   = input('size',20,'intval');
            $total = Db::name('tag')
                ->field('id,title,description')
                ->where('status',1)->count();
            $list = Db::name('tag')
                ->field('id,title,description,bg_cover')
                ->where('status',1)
                ->page($page,$size)
                ->order('id','desc')
                ->select();
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 获取话题
     */
    public function gettopic(){
        try{
            //token 验证
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');
            if ($user_id || $token) {
                $userInfo = User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }}
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            if (!empty($keywords)) {
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where['title|description'] = ['like',"%{$keywords}%"];
            }
            $page   = input('page',0,'intval');
            $size   = input('size',20,'intval');
            $total = Db::name('topic')
                ->field('id,title,description')
                ->where('status',1)
                ->where($where)->count();
            $list = Db::name('topic')
                ->field('id,title,description,bg_cover')
                ->where('status',1)
                ->where($where)
                ->page($page,$size)
                ->order('id','desc')
                ->select();

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 热门搜索
     */
    public function hotSearch(){
        try{
            $data = Db::name('search_store_record')->where('client_type',1)->order('search_number','desc')->limit('0,12')->column('search_keywords');
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
     * 商品或店铺列表
     */
    public function productOrstoreList(){
        try {
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $id = input('id') ? intval(input('id')) : 0 ;
            $type = input('type') ? intval(input('type')) : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 10 ;

            if (!$id || !$type ) {return \json(self::callback(0,'参数错误'),400);}
            $where = [
                'ucc.user_id' => $userInfo['user_id'],
                'ucc.id'=>$id,
                'cc.type'=>$type,
                'ucc.status' => 1
            ];
            $where['ucc.expiration_time'] = ['gt',time()];
            $coupon = Db::name('coupon')->alias('ucc')
                ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
                ->field('ucc.id,ucc.satisfy_money,ucc.coupon_money,cc.type,cc.store_ids,cc.product_ids,cc.is_solo')
                ->where($where)
                ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
                ->find();
           if(!$coupon){return \json(self::callback(0,'没有找到优惠券或优惠券已过期'),400);}

           if($coupon['type']==2){
               //店铺
               $store_ids=str_replace("[","",$coupon['store_ids']);
               $store_ids=str_replace("]","",$store_ids);
               $store_ids= explode(",",$store_ids);
               $total = Db::name('store')
                   ->field('id,store_name,cover,store_status')
                   ->where('id','in',$store_ids)
                   ->where('store_status',1)
                   ->count();
               $list = Db::name('store')
                   ->field('id,store_name,cover,store_status')
                   ->where('id','in',$store_ids)
                   ->where('store_status',1)
                   ->page($page,$size)
                   ->order('store.id','desc')
                   ->select();

           }elseif($coupon['type']==3){
               //商品
               $product_ids=str_replace("[","",$coupon['product_ids']);
               $product_ids=str_replace("]","",$product_ids);
               $product_ids= explode(",",$product_ids);
               $total = Db::view('product','id,store_id,product_name,status')
                   ->view('product_specs','id as specs_id,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
                   ->where('product.id','in',$product_ids)
                   ->where('product.status',1)
                   ->group('product.id')
                   ->count();
               $list = Db::view('product','id,store_id,product_name,status')
                   ->view('product_specs','id as specs_id,price,group_buy_price,huaxian_price,stock,cover,product_specs,share_img,huaxian_price','product_specs.product_id = product.id','left')
                   ->where('product.id','in',$product_ids)
                   ->where('product.status',1)
                   ->page($page,$size)
                   ->order('product.id','desc')
                   ->group('product.id')
                   ->select();
           }
           foreach ($list as $k=>&$v){
               $v['coupon_info']="全场满".$coupon['satisfy_money']."减".$coupon['coupon_money'];
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
     * 店铺列表
     */
    public function storeList(){
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
     * 首页数据
     */
    public function MainList(){

        try{
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 50 ;
            $category_id = $this->request->has('category_id') ? $this->request->param('category_id') : 0 ;


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
     * 单个商品收藏和取消收藏
     */
    public function collection_and_cancel (){

        try{
            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $product_id=$param['product_id'];
            $specs_id=$param['specs_id'];
            $status=$param['status'];

            if (!$product_id || !$specs_id || !$status) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $specs_info = Db:: view('product_specs','id,product_id')
                ->view('product','status,store_id','product_specs.product_id = product.id','left')
                ->where('product_specs.id',$specs_id)
                ->where('product_specs.product_id',$product_id)
                ->where('product.status',1)
                ->find();

            if (!$specs_info) {
                throw new \Exception('商品不存在,或已下架');
            }

            if($status=='true'){
                //收藏
                $rst=  Db::name('product_collection')->insert([
                    'user_id' => $userInfo['user_id'],
                    'product_id' => $product_id,
                    'store_id' => $specs_info['store_id'],
                    'specs_id' => $specs_id,
                    'create_time' => time()
                ]);
                if($rst===false){
                    return \json(self::callback(0,'收藏失败',false));
                }else{
                    return \json(self::callback(1,'收藏成功',true));
                }

            }else if($status=='false'){
                //取消收藏
                $de=Db::name('product_collection')->where('user_id',$userInfo['user_id'])->delete();
if($de===false){
    return \json(self::callback(0,'取消收藏失败',false));
}else{
    return \json(self::callback(1,'取消收藏成功',true,true));
}
            }else{
                //报错
                return \json(self::callback(0,'操作错误',false));
            }

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
     *
     * 收藏列表
     */
//    public function collectionList(){
//        try{
//            $userInfo = User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
//
//            $page = input('page') ? intval(input('page')) : 1 ;
//            $size = input('size') ? intval(input('size')) : 10 ;
//
//            $total = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->count();
//
//            $list = Db::view('product_collection')
//                ->view('product_specs','product_name,product_specs,price','product_collection.specs_id = product_specs.id','left')
//                ->where('product_collection.user_id',$userInfo['user_id'])
//                ->order('product_collection.create_time','desc')
//                ->page($page,$size)
//                ->select();
//            foreach ($list as $k=>$v) {
//                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['product_id'])->value('img_url');
//                $list[$k]['type'] = Db::name('product')->where('id',$v['product_id'])->value('type');
//
//            }
//
//            $data['total'] = $total;
//            $data['max_page'] = ceil($total/$size);
//            $data['list'] = $list;
//
//            return \json(self::callback(1,'',$data));
//        }catch (\Exception $e){
//            return \json(self::callback(0,$e->getMessage()));
//        }
//    }

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
            $data = Db::name('member_store_banner')->field('id,img_url,type,link,product_id,store_id')->select();
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
            if (Db::name('giftpack_order')->where('user_id',$userInfo['user_id'])->where('status',2)->count()){
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

    /**
     *  TODO zd
     *  获取动态详情数据接口
     * @return Json
     */
    public function posttingDetails(){
        try{
            $params = $this -> request -> only(['chaoda_id', 'user_id','type']);
            if (!isset($params['chaoda_id'])) return json(self::callback(0, "参数错误"), 400);
            $user_id = $params['user_id'] ? $params['user_id'] : 0;
            $info = ChaoDaModel::getDetailsById(intval($params['chaoda_id']), $user_id);

            return \json(self::callback(1, '成功', $info));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *  用户评论或回复评论
     * @return Json
     */
    public function addComment(){
        try{
            $params = $this -> request -> only(['chaoda_id', 'user_id', 'pid', 'content','token']);

            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            // 模型数据验证及添加 返回验证错误信息
            $result = CommentModel::insertData($params);
            if(is_string($result)) return json(self::callback(0, $result), 400);

            if ($result === false) return json(self::callback(0, '评论失败'), 400);

            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *  动态详情页  评论数据分页
     * @return Json
     */
    public function postingDetailsCommentPage(){
        try{
            $params = $this -> request -> only(['chaoda_id', 'user_id', 'page', 'size']);

            $result = CommentModel::detailsCommentPage($params);
            return \json(self::callback(1, '成功', $result));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     *  TODO zd
     *  动态详情评论页  评论数据分页
     * @return Json
     */
    public function postingDetailsCommentList(){
        try{
            $params = $this -> request -> only(['chaoda_id','comment_id','user_id', 'page', 'size']);
            $result = CommentModel::detailsCommentList($params);
            return \json(self::callback(1, '成功', $result));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *   动态详情页面 推荐数据分页
     * @return Json
     */
    public function postingDetailsRecommendPage(){
        try{
            $params = $this -> request -> only(['chaoda_id', 'user_id', 'page', 'size']);
            // 传入数据验证 失败返回错误信息
            $rule = [
                'chaoda_id'  => 'require|number',
                // 'user_id'    => 'require|number',
                'page'       => 'require|number|egt:1',
                'size'       => 'require|number|egt:1',
            ];
            $msg = [
                'chaoda_id.require' => '缺少必要参数',
                'chaoda_id.number'  => '参数格式不正确',
                // 'user_id.require'   => '缺少必要参数',
                // 'user_id.number'    => '参数格式不正确',
                'page.require'      => '缺少必要参数',
                'page.egt'          => '参数范围错误',
                'size.require'      => '缺少必要参数',
                'page.number'       => '参数格式不正确',
                'size.number'       => '参数格式不正确',
                'size.egt'          => '参数范围错误',
            ];
            $validate = new Validate($rule, $msg);
            if (!$validate->check($params)) {
                return json(self::callback(0, $validate->getError()), 400);
            }
            // 查找动态数据 并 获取相关推荐数据
            $data = ChaoDaModel::where('id' , $params['chaoda_id']) -> field(['tag_ids', 'topic_id']) -> find();
            if (!$data) return json(self::callback(0, '数据检索失败'), 400);

            $result = ChaoDaModel::ChaoDaDetailsRecommendDataPage($data['tag_ids'], $params['topic_id'], $params['page'], $params['size']);

            return \json(self::callback(1, '成功', [$result]));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *  用户删除评论
     * @return Json
     */
    public function userDelComment(){
        try{
            $params = $this -> request -> only(['cid', 'user_id','token']);

            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            // 模型数据处理
            $result = CommentModel::userDelComment($params);

            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);

            if ($result === false) return json(self::callback(0, '失败'), 400);

            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *  用户点赞或取消
     *  用户点踩或取消
     * @return Json
     */
    public function userSupportOrHateComment(){
        try{
            $params = $this -> request -> only(['comment_id', 'support', 'user_id', 'type','token']);

            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            // 模型数据处理
            $result = CommentSupportModel::supportComment($params);

            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);

            if ($result === false) return json(self::callback(0, '失败'), 400);

            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
}