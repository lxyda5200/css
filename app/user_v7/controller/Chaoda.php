<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/24
 * Time: 10:48
 */

namespace app\user_v7\controller;


use app\common\controller\Base;
use app\user_v7\common\Logic;
use app\user_v7\common\UserLogic;
use think\Db;
use think\response\Json;
use app\user_v7\common\User as UserFunc;

class Chaoda extends Base
{

    /*
     * 潮搭列表
     * */
//    public function chaodaList(){
//        try{
//            $page = input('page') ? intval(input('page')) : 1 ;
//            $size = input('size') ? intval(input('size')) : 10 ;
//            $style_id = input('style_id/a');
//            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
//
//            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
//            $token = input('token');
//            if ($user_id || $token) {
//                $userInfo = \app\user\common\User::checkToken($user_id,$token);
//                if ($userInfo instanceof Json){
//                    return $userInfo;
//                }
//            }
//
//            if (!empty($style_id) || count($style_id) > 0 ){
//                $where['chaoda.style_id'] = ['in',$style_id];
//            }
//
//            if ($store_id){
//                $where['chaoda.store_id'] = ['eq',$store_id];
//            }
//
//            $total = Db::view('chaoda')
//                ->view('store','store_name','store.id = chaoda.store_id','left')
//                ->where('chaoda.is_delete',0)
//                ->where('chaoda.store_id','neq',0) //屏蔽小程序普通用户发布
//                ->where($where)
//                ->count();
//
//            $list = Db::view('chaoda','id,store_id,cover as chaoda_cover,share_number,description,style_id')
//                ->view('store','store_name,cover,province,city,area,address,is_ziqu,type','store.id = chaoda.store_id','left')
//                ->where('chaoda.is_delete',0)
//                ->where('chaoda.store_id','neq',0) //屏蔽小程序普通用户发布
//                ->where($where)
//                ->page($page,$size)
//                ->order('chaoda.create_time','desc')
//                ->select();
//
//            foreach ($list as $k=>$v){
//
//                $chaoda_img = Db::name('chaoda_img')->where('chaoda_id',$v['id'])->column('img_url');
//
//                array_unshift($chaoda_img,$v['chaoda_cover']);
//
//                unset($list[$k]['chaoda_cover']);
//
//                $list[$k]['chaoda_img'] = $chaoda_img;
//
//                //潮搭商品信息
//                $product_info = Db::view('chaoda_tag')
//                    ->view('product','freight','product.id = chaoda_tag.product_id','left')
//                    ->where('chaoda_id',$v['id'])
//                    ->select();
//
//                foreach ($product_info as $k2=>$v2){
//                    $product = Db::name('product_specs')->field('id,product_specs,product_name,price,cover')->where('product_id',$v2['product_id'])->find();
//                    $product_info[$k2]['specs_id'] = $product['id'];
//                    $product_info[$k2]['product_name'] = $product['product_name'];
//                    $product_info[$k2]['old_price'] = $product['price'];
//                    $product_info[$k2]['cover'] = $product['cover'];
//                    $product_info[$k2]['product_specs'] = $product['product_specs'];
//                }
//                $list[$k]['product_info'] = $product_info;
//
//                //潮搭评论数
//                $list[$k]['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$v['id'])->count();
//
//                //店铺粉丝数
//                $list[$k]['fans_number'] = Db::name('store_follow')->where('store_id',$v['store_id'])->count();
//
//                //潮搭点赞数
//                $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
//
//                if ($userInfo) {
//                    $list[$k]['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('store_id',$v['store_id'])->count();
//                    $list[$k]['is_collection'] = Db::name('chaoda_collection')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
//                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
//                }else{
//                    $list[$k]['is_follow'] = 0;
//                    $list[$k]['is_collection'] = 0;
//                    $list[$k]['is_dianzan'] = 0;
//                }
//
//                $time = time();
//                //潮搭拼团信息
//                $pt_info = Db::name('chaoda_pt_info')
//                    ->field('id,end_time')
//                    ->where('chaoda_id',$v['id'])
//                    ->where('end_time','>',$time)
//                    ->where('pt_status',1)
//                    ->find();
//
//                //拼团商品信息
//                $pt_product = Db::view('chaoda_pt_product_info','product_id,price,status')
//                    ->view('product','freight','product.id = chaoda_pt_product_info.product_id','left')
//                    ->where('chaoda_pt_product_info.pt_id',$pt_info['id'])
//                    ->select();
//
//                if (!$pt_product){
//                    $list[$k]['pt_info'] = new \stdClass();
//                }else{
//
//                    foreach ($pt_product as $k3=>$v3){
//
//                        $product = Db::name('product_specs')->field('id,product_specs,product_name,price,cover')->where('product_id',$v3['product_id'])->find();
//                        $pt_product[$k3]['specs_id'] = $product['id'];
//                        $pt_product[$k3]['product_name'] = $product['product_name'];
//                        $pt_product[$k3]['old_price'] = $product['price'];
//                        $pt_product[$k3]['cover'] = $product['cover'];
//                        $pt_product[$k3]['product_specs'] = $product['product_specs'];
//
//                    }
//                    $pt_info['pt_product'] = $pt_product;
//
//                    $list[$k]['pt_info'] = $pt_info;
//                }
//
//                //潮搭评论信息
//                $list[$k]['comment_info'] = Db::view('chaoda_comment','content')
//                    ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
//                    ->where('chaoda_comment.chaoda_id',$v['id'])
//                    ->limit(0,2)
//                    ->order('chaoda_comment.create_time','desc')
//                    ->select();
//            }
//
//            $data['total'] = $total;
//            $data['max_page'] = ceil($total/$size);
//            $data['list'] = $list;
//
//            return \json(self::callback(1,'',$data));
//        }catch (\Exception $e){
//            return json(self::callback(0,$e->getMessage()));
//        }
//    }
    /**
     * 潮搭列表
     */
    public function chaodaList(){
        try{
            //获取分类的潮搭类型
            $id = input('id') ? intval(input('id')) : 0 ;
            //获取用户
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 20 ;

            if(isset($store_id)&&!empty($store_id)){
                $total=Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                    ->view('store','cover as store_logo,store_name,address,lng,lat','chaoda.store_id = store.id','left')
                    ->where('store_id',$store_id)
                    ->where('is_delete',0)
                    ->where('store.sh_status',1)
                    ->where('store.store_status',1)->count();
                //店铺潮搭
                $list = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                    ->view('store','cover as store_logo,store_name,address,lng,lat','chaoda.store_id = store.id','left')
                    ->where('store_id',$store_id)
                    ->where('is_delete',0)
                    ->where('store.sh_status',1)
                    ->where('store.store_status',1)
                    ->page($page,$size)
                    ->order('chaoda.is_ceshi','asc')
                    ->order('chaoda.id','desc')
                    ->select();
            }else{
                if(!$id){
                    //首页潮搭
                    $banner = Db::name('store_category')
                        ->field('id,category_name')
                        ->where('is_show', 1)
                        ->where('client_type', 1)
                        ->order('paixu asc')
                        ->select();
                    if($banner){
                        //默认取第一个导航值
                        $id=$banner[0]['id'];
                        if(!$id){
                            return \json(self::callback(0,'没有导航！'));
                        }
                    }
                }
                $id="%".$id."%";
                $ids="'".$id."'";
                //查询数据
                $total=Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                    ->view('store','cover as store_logo,store_name,address,lng,lat','chaoda.store_id = store.id','left')
                    ->where('is_delete','eq',0)
                    ->where('store_id','gt',0)
                    ->where('store.sh_status',1)
                    ->where('store.store_status',1)
                    ->where('category_id','like',$id)->count();
                $list = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                    ->view('store','cover as store_logo,store_name,address,lng,lat','chaoda.store_id = store.id','left')
                    ->where('is_delete','eq',0)
                    ->where('store_id','gt',0)
                    ->where('store.sh_status',1)
                    ->where('store.store_status',1)
                    ->where('category_id','like',$id)
                    ->page($page,$size)
                    ->order('chaoda.is_ceshi','asc')
                    ->order('chaoda.id','desc')
                    ->select();
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
     * 潮搭详情
     */
    public function chaodaDetail(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            if(!$chaoda_id){
                return \json(self::callback(0,'参数错误！'));
            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $chaoda=Db::name('chaoda')->where('id',$chaoda_id)->find();
            if(!$chaoda){return \json(self::callback(0,'没有该潮搭！'));}
            if($chaoda['is_delete']==1){return \json(self::callback(0,'该潮搭已删除！'));}

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            if($chaoda['is_pt_user']!=1){
                //-------------------------------------------------------------------------------------------------------------
                //商家潮搭
                $list = Db::view('chaoda','id as chaoda_id,store_id,cover as chaoda_cover,share_number,description,title,is_group,freight')
                    ->view('store','store_name,cover,address,is_ziqu,lng,lat,type as store_type','store.id = chaoda.store_id','left')
                    ->where('chaoda.id',$chaoda_id)
                    ->find();
                if(!$list){
                    return \json(self::callback(0,'没有找到该条信息！'));
                }
                //查询所有潮搭图片
                $chaoda_img= Db::name('chaoda_img')->field('id,img_url,chaoda_id')->where('chaoda_id',$chaoda_id)->select();
                //查询所有图片的tag
                foreach ($chaoda_img as $k=>$v){
                    $chaoda_img[$k]['tags']= Db::name('chaoda_tag')->field('tag_name,x_postion,y_postion,direction')->where('chaoda_id',$v['chaoda_id'])->select();
                };
                $list['images']=$chaoda_img;

                //潮搭商品tag信息
                $product_info = Db::view('chaoda_tag')
                    ->view('product','freight,is_buy,status','product.id = chaoda_tag.product_id','left')
                    ->where('chaoda_id',$chaoda_id)
                    ->where('product.status',1)
                    ->select();
                $total_group_buy_price=0;//计算总团购价格
                $total_price=0;//计算总划线价格
//                $total_money_1= 0;

                foreach ($product_info as $k2=>$v2){
                    $product = Db::name('product_specs')->field('id,cover,product_specs,product_name,price,group_buy_price,huaxian_price,stock,share_img,platform_price,price_activity_temp')->where('product_id',$v2['product_id'])->find();
                    $product_info[$k2]['specs_id'] = $product['id'];
                    $product_info[$k2]['is_buy'] = $v2['is_buy'];
                    $product_info[$k2]['cover'] = $product['cover'];
                    $product_info[$k2]['product_specs'] = $product['product_specs'];
                    $product_info[$k2]['product_name'] = $product['product_name'];
                    $product_info[$k2]['show_product_specs'] = $product['product_specs'];

                    if($list['is_group'] == 1){
                        $product_info[$k2]['price'] = $v2['price'];
                    }else{
                        if(in_array($v2['product_id'],$product_ids)){
                            $product_info[$k2]['price'] = $product['price_activity_temp'];
                            $product_info[$k2]['huaxian_price'] = $product['price'];
                        }else{
                            $product_info[$k2]['price'] = $product['price'];
                        }
                    }

                    $product_info[$k2]['group_buy_price'] = $v2['price'];
                    $product_info[$k2]['stock'] = $product['stock'];
                    $product_info[$k2]['huaxian_price'] = $product['price'];
                    $product_info[$k2]['share_img'] = $product['share_img'];
                    $product_info[$k2]['platform_price'] = $product['platform_price'];

                    if($list['is_group']==1){
                        $total_group_buy_price+=$v2['price'];
                        $product_info[$k2]['freight'] = $list['freight'];
                    }else{
                        $total_group_buy_price+=$product['price'];
                    }
//                    $total_group_buy_price+=$v2['price'];//计算总团购价格
//                    $total_group_buy_price+=$product['group_buy_price'];//计算总团购价格
//                    $total_price+=$product['price'];//计算总划线价格
                    $total_price+=$product['price'];//计算总划线价格
//                    $total_money_1 += $product['price'];
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
                $list['type']=1;
                //用于区分是商家还是普通用户 商家返回1

                //-------------------------------------------------------------------------------------------------------------

            }else{
                //此为普通用户数据
                $list = Db::view('chaoda','id as chaoda_id,cover as chaoda_cover,share_number,description,title,address,fb_user_id,type,is_group')
                    ->view('user','nickname,avatar','user.user_id = chaoda.fb_user_id','left')
                    ->where('chaoda.id',$chaoda_id)
                    ->find();
                if(!$list){
                    return \json(self::callback(0,'没有找到该条信息！'));
                }
                //查询所有潮搭图片数量
                $chaoda_img= Db::name('chaoda_img')->field('id,img_url,type,cover')->where('chaoda_id',$chaoda_id)->select();
                //查询所有图片的tag
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

                //普通用户则返回2
                $list['type']=2;

            }
            //推荐不超过6个潮搭列表
            $tuijian = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                ->view('store','cover as store_logo,store_name,address,lng,lat','chaoda.store_id = store.id','left')
                ->where('chaoda.is_delete','eq',0)
                ->where('chaoda.store_id','neq',0)
                ->where('chaoda.id','neq',$chaoda_id)
                ->order('chaoda.id','desc')
                ->limit(0,6)
                ->select();
            foreach ($tuijian as $k3=>$v3){
                //潮搭点赞数
                $tuijian[$k3]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v3['id'])->count();
                if($user_id){
                    //判断是否点赞
                    $tuijian[$k3]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v3['id'])->count();
                }else{
                    $tuijian[$k3]['is_dianzan'] = 0;
                }
            }
            $list['tuijian']=$tuijian;
            $data=$list;
            return json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }
    /*
* 潮搭点赞和取消点赞
* */
    public function chaoda_Dianzan(){
        try{
            //token 验证
            $userInfo = \app\user_v7\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            $status = input('status');
            if(!$chaoda_id || !$status){
                return \json(self::callback(0,'参数错误'),400);
            }
            if($status=='true'){
                $guan= Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
                if ($guan==0){
                    //写入点赞
                    Db::name('chaoda_dianzan')->insert([
                        'chaoda_id' => $chaoda_id,
                        'user_id' => $userInfo['user_id'],
                        'create_time' => time()
                    ]);
                    //统计最新点赞
                    $xie= Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($xie==0){
                        //写入失败
                        $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                        if($data==0){
                            $data=-1;

                        }else{

                        }
                        return \json(self::callback(0,'点赞失败',$data));
                    }else{

                        //写入成功
                        $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                        if($data==0){
                            $data=-1;
                        }else{
                        }
                        return \json(self::callback(1,'点赞成功',$data));
                    }

                }else{

                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;
                    }else{
                    }

                    return \json(self::callback(0,'已点赞',$data));
                }

            }else if($status=='false'){

                $de=Db::name('chaoda_dianzan')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
                if($de==0){
                    //删除失败
                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;
                    }else{

                    }
                    return \json(self::callback(0,'取消点赞失败',$data));

                }else{
                    //删除成功

                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;

                    }else{

                    }
                    return \json(self::callback(1,'取消点赞成功',$data));
                }

            }else{
                return \json(self::callback(0,'操作错误'));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * banner列表
     */
    public function Banner(){
        try {
            $data = Db::name('store_category')
                ->field('id,category_name')
                ->where('is_show', 1)
                ->where('client_type', 1)
                ->order('paixu asc')
                ->select();
            return json(self::callback(1, '', $data));
        } catch (\Exception $e) {
            Db::rollback();
            return json(self::callback(0, $e->getMessage()));
        }
    }
    /*
    * 潮搭分享
    * */
    public function chaodaShare(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id) {
                return \json(self::callback(0,'参数错误'),400);
            }


            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');
            if ($user_id || $token) {
                $userInfo = UserFunc::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $where['chaoda.id'] = ['eq',$chaoda_id];

            $list = Db::view('chaoda','id,store_id,cover as chaoda_cover,share_number,description,style_id')
                ->view('store','store_name,cover,province,city,area,address,is_ziqu,type','store.id = chaoda.store_id','left')
                ->where('chaoda.is_delete',0)
                ->where($where)
                ->order('chaoda.create_time','desc')
                ->find();

            $chaoda_img = Db::name('chaoda_img')->where('chaoda_id',$list['id'])->column('img_url');

            array_unshift($chaoda_img,$list['chaoda_cover']);

            unset($list['chaoda_cover']);

            $list['chaoda_img'] = $chaoda_img;

            //潮搭商品信息
            $product_info = Db::view('chaoda_tag')
                ->view('product','freight','product.id = chaoda_tag.product_id','left')
                ->where('chaoda_id',$list['id'])
                ->select();

            foreach ($product_info as $k2=>$v2){
                $product = Db::name('product_specs')->field('id,product_specs,product_name,price,cover')->where('product_id',$v2['product_id'])->find();
                $product_info[$k2]['specs_id'] = $product['id'];

                $product_info[$k2]['product_name'] = $product['product_name'];
                $product_info[$k2]['old_price'] = $product['price'];
                $product_info[$k2]['cover'] = $product['cover'];

                $product_info[$k2]['product_specs'] = $product['product_specs'];
            }
            $list['product_info'] = $product_info;

            //潮搭评论数
            $list['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$list['id'])->count();

            //店铺粉丝数
            $list['fans_number'] = Db::name('store_follow')->where('store_id',$list['store_id'])->count();

            //潮搭点赞数
            $list['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$list['id'])->count();


            if ($userInfo) {
                $list['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('store_id',$list['store_id'])->count();
                $list['is_collection'] = Db::name('chaoda_collection')->where('user_id',$user_id)->where('chaoda_id',$list['id'])->count();
                $list['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$list['id'])->count();
            }else{
                $list['is_follow'] = 0;
                $list['is_collection'] = 0;
                $list['is_dianzan'] = 0;
            }
            $time = time();

            //潮搭拼团信息
            $pt_info = Db::name('chaoda_pt_info')
                ->field('id,end_time')
                ->where('chaoda_id',$list['id'])
                ->where('end_time','>',$time)
                ->where('pt_status',1)->find();

            $pt_product = Db::name('chaoda_pt_product_info')
                ->field('product_id,price,status')
                ->where('pt_id',$pt_info['id'])
                ->select();

            if (!$pt_product){
                $list['pt_info'] = new \stdClass();
            }else{

                foreach ($pt_product as $k2=>$v2){
                    $pt_product[$k2]['product_name'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('product_name');
                    $pt_product[$k2]['cover'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('cover');
                    $pt_product[$k2]['old_price'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('price');
                }
                $pt_info['pt_product'] = $pt_product;

                $list['pt_info'] = $pt_info;
            }



            //潮搭评论信息
            $list['comment_info'] = Db::view('chaoda_comment','content')
                ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                ->where('chaoda_comment.chaoda_id',$list['id'])
                ->limit(0,2)
                ->order('chaoda_comment.create_time','desc')
                ->select();

            return \json(self::callback(1,'',$list));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /*
     * 分享统计次数
     * */
    public function shareCount(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            $result= Db::name('chaoda')->field('id,share_number')->where('id',$chaoda_id)->find();
            if($result){
                $res = Db::name('chaoda')->where('id',$chaoda_id)->setInc('share_number',1);
                if ($res===false){
                    return \json(self::callback(0,'操作失败'));
                }
                return \json(self::callback(1,'分享统计成功',true));
            }else{
                return \json(self::callback(0,'操作失败，没有该潮搭'));
            }

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 收藏的潮搭列表
     * */
    public function collectionChaodaList(){
        try{

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $user_id = $userInfo['user_id'];

            $chaoda_id = Db::name('chaoda_collection')->where('user_id',$userInfo['user_id'])->column('chaoda_id');

            if ($chaoda_id){
                $where['chaoda.id'] = ['in',$chaoda_id];
            }else{
                $data['total'] = 0;
                $data['max_page'] = 0;
                $data['list'] = [];

                return \json(self::callback(1,'',$data));
            }

            $total = Db::view('chaoda')
                ->view('store','store_name,cover,province,city,area,address','store.id = chaoda.store_id','left')
                ->where('chaoda.is_delete',0)
                ->where($where)
                ->count();

            $list = Db::view('chaoda','id,store_id,cover as chaoda_cover,share_number,description,style_id')
                ->view('store','store_name,cover,province,city,area,address','store.id = chaoda.store_id','left')
                ->where('chaoda.is_delete',0)
                ->where($where)
                ->page($page,$size)
                ->order('chaoda.create_time','desc')
                ->select();

            foreach ($list as $k=>$v){

                $chaoda_img = Db::name('chaoda_img')->where('chaoda_id',$v['id'])->column('img_url');

                array_unshift($chaoda_img,$v['chaoda_cover']);

                unset($list[$k]['chaoda_cover']);

                $list[$k]['chaoda_img'] = $chaoda_img;

                //潮搭商品信息
                $product_info = Db::name('chaoda_tag')
                    ->where('chaoda_id',$v['id'])
                    ->select();

                foreach ($product_info as $k2=>$v2){
                    $product_info[$k2]['product_name'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('product_name');
                    $product_info[$k2]['old_price'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('price');
                    $product_info[$k2]['cover'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('cover');
                }
                $list[$k]['product_info'] = $product_info;

                //潮搭评论数
                $list[$k]['comment_number'] = Db::name('chaoda_comment')->where('chaoda_id',$v['id'])->count();

                //店铺粉丝数
                $list[$k]['fans_number'] = Db::name('store_follow')->where('store_id',$v['store_id'])->count();

                //潮搭点赞数
                $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();

                if ($userInfo) {
                    $list[$k]['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('store_id',$v['store_id'])->count();
                    $list[$k]['is_collection'] = Db::name('chaoda_collection')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_follow'] = 0;
                    $list[$k]['is_collection'] = 0;
                    $list[$k]['is_dianzan'] = 0;
                }

                $time = time();
                //潮搭拼团信息
                $pt_info = Db::name('chaoda_pt_info')->field('id,end_time')->where('chaoda_id',$v['id'])->where('end_time','>',$time)->where('pt_status',1)->find();
                $pt_product = Db::name('chaoda_pt_product_info')
                    ->field('product_id,price,status')
                    ->where('pt_id',$pt_info['id'])
                    ->select();

                foreach ($pt_product as $k2=>$v2){
                    $pt_product[$k2]['product_name'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('product_name');
                    $pt_product[$k2]['cover'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('cover');
                    $pt_product[$k2]['old_price'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('price');
                }
                $pt_info['pt_product'] = $pt_product;
                $list[$k]['pt_info'] = $pt_info;
                //潮搭评论信息
                $list[$k]['comment_info'] = Db::view('chaoda_comment','content')
                    ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                    ->where('chaoda_comment.chaoda_id',$v['id'])
                    ->limit(0,2)
                    ->order('chaoda_comment.create_time','desc')
                    ->select();
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /*
      * 潮搭收藏和取消收藏
      * */
    public function chaoda_collection(){
        try{
            //token 验证
            $userInfo = \app\user_v7\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            $status = input('status');
            if(!$chaoda_id || !$status){
                return \json(self::callback(0,'参数错误'),400);
            }
            if($status=='true'){
                $guan= Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();

                if ($guan==0){
                    //写入收藏
                    Db::name('chaoda_collection')->insert([
                        'chaoda_id' => $chaoda_id,
                        'user_id' => $userInfo['user_id'],
                        'create_time' => time()
                    ]);
                    //统计最新收藏
                    $xie= Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($xie==0){

                        //写入失败
                        $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                        if($data==0){
                            $data=-1;

                        }else{

                        }
                        return \json(self::callback(0,'收藏失败',$data));
                    }else{

                        //写入成功
                        $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                        if($data==0){
                            $data=-1;
                        }else{
                        }
                        return \json(self::callback(1,'收藏成功',$data));
                    }

                }else{

                    $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;
                    }else{
                    }

                    return \json(self::callback(1,'已收藏',$data));
                }

            }else if($status=='false'){

                $de=Db::name('chaoda_collection')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
                if($de==0){
                    //删除失败
                    $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;
                    }else{

                    }
                    return \json(self::callback(0,'取消收藏失败',$data));

                }else{
                    //删除成功

                    $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
                    if($data==0){
                        $data=-1;

                    }else{

                    }
                    return \json(self::callback(1,'取消收藏成功',$data));
                }

            }else{
                return \json(self::callback(0,'操作错误'));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 潮搭拼团列表
     * */
    public function chaodaPtList(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::name('chaoda_pt_info')->where('chaoda_id',$chaoda_id)->where('pt_status',1)->count();

            $time = time();
            $list = Db::name('chaoda_pt_info')->field('id,chaoda_id,end_time')->where('chaoda_id',$chaoda_id)->where('end_time','>',$time)->where('pt_status',1)->page($page,$size)->select();

            foreach ($list as $k=>$v){
                $pt_product = Db::name('chaoda_pt_product_info')
                    ->field('product_id,price,status')
                    ->where('pt_id',$v['id'])
                    ->select();

                foreach ($pt_product as $k2=>$v2){
                    $pt_product[$k2]['freight'] = Db::name('product')->where('id',$v2['product_id'])->value('freight');
                    $pt_product[$k2]['specs_id'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('id');
                    $pt_product[$k2]['product_specs'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('product_specs');
                    $pt_product[$k2]['product_name'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('product_name');
                    $pt_product[$k2]['cover'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('cover');
                    $pt_product[$k2]['old_price'] = Db::name('product_specs')->where('product_id',$v2['product_id'])->value('price');
                }

                $list[$k]['pt_product'] = $pt_product;
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

//    /*
//     * 潮搭评论
//     * */
//    public function chaodaCommentList(){
//        try{
//            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
//            if (!$chaoda_id){
//                return \json(self::callback(0,'参数错误'),400);
//            }
//
//            $page = input('page') ? intval(input('page')) : 1 ;
//            $size = input('size') ? intval(input('size')) : 10 ;
//
//            $total = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();
//
//            $list = Db::view('chaoda_comment','content,create_time')
//                ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
//                ->where('chaoda_comment.chaoda_id',$chaoda_id)
//                ->page($page,$size)
//                ->order('chaoda_comment.create_time','desc')
//                ->select();
//
//            foreach ($list as $k=>$v){
//                $list[$k]['create_time'] = date('Y-m-d H:i',$v['create_time']);
//            }
//
//            $data['total'] = $total;
//            $data['max_page'] = ceil($total/$size);
//            $data['list'] = $list;
//
//            return \json(self::callback(1,'',$data));
//
//        }catch (\Exception $e){
//            return \json(self::callback(0,$e->getMessage()));
//        }
//    }
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
                    }else{}
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
    /*
     * 风格
     * */
    public function styleList(){
        try{
            $data = Db::name('product_style')->field('id,style_name')->where('status',1)->where('is_delete',0)->select();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

//    /*
//     * 评论潮搭
//     * */
//    public function comment(){
//        try{
//            //token 验证
//            $userInfo = \app\user\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
//
//            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
//            $content = input('content') ? trim(input('content')) : '' ;
//
//            if (!$chaoda_id || !$content){
//                return \json(self::callback(0,'参数错误'),400);
//            }
//
//            Db::name('chaoda_comment')->insert([
//                'chaoda_id' => $chaoda_id,
//                'user_id' => $userInfo['user_id'],
//                'content' => $content,
//                'create_time' => time()
//            ]);
//
//            return \json(self::callback(1,''));
//
//        }catch (\Exception $e){
//            return \json(self::callback(0,$e->getMessage()));
//        }
//
//    }
    /*
     * 评论潮搭
     * */
    public function comment(){
        try{
            //token 验证
            $userInfo = \app\user_v7\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $chaoda_id=$param['chaoda_id'];
            $content=$param['content'];

            if(!$chaoda_id || !$content){
                return \json(self::callback(0,'参数错误'),400);
            }
            //查询是否有这个chaoda_id并且没有删除
            $find = Db::name('chaoda')->where('id',$chaoda_id)->where('is_delete',0)->count();
            if($find==0){
                return \json(self::callback(0,'没有找到这个潮搭'));
            }else{

                $rst= Db::name('chaoda_comment')->insert([
                    'chaoda_id' => $chaoda_id,
                    'user_id' => $userInfo['user_id'],
                    'content' => $content,
                    'create_time' => time()
                ]);
                if($rst===false){
                    return \json(self::callback(0,'评论失败'));
                }
//统计最新
                $data = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();

                return \json(self::callback(1,'评论成功',$data));
            }

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
     * 潮搭点赞
     * */
    public function dianzan(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count()){
                return \json(self::callback(0,'已点赞'));
            }

            Db::name('chaoda_dianzan')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'create_time' => time()
            ]);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 潮搭取消点赞
     * */
    public function cancelDianzan(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('chaoda_dianzan')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /*
          * 收藏的潮搭列表
          * */
    public function chaodaCollectionList(){
        try{
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $userInfo = \app\user_v7\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $user_id = $userInfo['user_id'];
            $total =  Db::name('chaoda_collection')->alias('cc')
                ->join('chaoda c','c.id = cc.chaoda_id','RIGHT')
                ->join('store s','s.id = c.store_id')
                ->where(['cc.user_id'=>$user_id,'c.is_delete'=>0,'s.store_status'=>1])
                ->count('cc.id');
            //统计
            if($total==0){
                $data['total'] = 0;
                $data['max_page'] = 0;
                $data['list'] = [];
                return \json(self::callback(1,'还没有收藏任何商品哦',$data));
            }else{
                //查询到有收藏
                $list = Db:: view('chaoda_collection','chaoda_id,user_id')
                    ->view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user','chaoda.id = chaoda_collection.chaoda_id','left')
                    ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                    ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                    ->where('chaoda_collection.user_id',$user_id)
                    ->where('chaoda.is_delete',0)
                    ->where('store.store_status',1)
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
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 潮搭收藏
     * */
    public function collection(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count()){
                return \json(self::callback(0,'已收藏'));
            }

            Db::name('chaoda_collection')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'create_time' => time()
            ]);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 潮搭取消收藏
     * */
    public function cancelCollection(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('chaoda_collection')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 关注店铺
     * */
    public function followStore(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $store_id = input('store_id');

            if (!$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if (Db::name('store_follow')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count()){
                return \json(self::callback(0,'该店铺已关注过'));
            }

            Db::name('store_follow')->insert([
                'user_id' => $userInfo['user_id'],
                'store_id' => $store_id,
                'create_time' => time()
            ]);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 取消关注店铺
     * */
    public function storeFollow(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $store_id = input('store_id',0,'intval');
            $type = input('type',1,'intval');

            if (!$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            if($type == -1){//取消关注
                $res = Db::name('store_follow')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->delete();
            }else{//关注
                ##检查是否关注
                $user_id = $userInfo['user_id'];
                $check = UserLogic::checkFollowStore($user_id,$store_id);
                if($check)return \json(self::callback(0,'您已关注该商铺'));

                $store_status = Db::name('store')->where('id',$store_id)->value('store_status');
                if($store_status!=1){return \json(self::callback(0,'该店铺已下架，不能关注!'));}
                ##添加关注
                $data = compact('store_id','user_id');
                $data['create_time'] = time();
                $res = UserLogic::userFollowStore($data);
            }

            if($res === false)return \json(self::callback(0,'关注失败'));

            ##获取当前关注数
            $num = Db::name('store_follow')->where(['store_id'=>$store_id])->count('id');

            ##更新店铺关注数
            $res = Db::name('store')->where(['id'=>$store_id])->setField('follow_num',$num);

            return \json(self::callback(1,'',$num,true));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /*
     * 潮搭拼团分享
     * */
    public function chaodaPtShare(){
        try{
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $order_info = Db::view('product_order','pt_id,user_id')
                ->view('user','nickname,avatar','user.user_id = product_order.user_id','left')
                ->where('product_order.id',$order_id)
                ->find();

            $order_info['chaoda_id'] = Db::name('chaoda_pt_info')->where('id',$order_info['pt_id'])->value('chaoda_id');
            if (!$order_info){
                return \json(self::callback(0,'该拼团订单不存在'));
            }

            $pt_id = $order_info['pt_id'];

            $pt_product = Db::name('chaoda_pt_product_info')->field('product_id,price,status')->where('pt_id',$pt_id)->select();

            foreach ($pt_product as $k=>$v){
                $pt_product[$k]['product_name'] = Db::name('product_specs')->where('product_id',$v['product_id'])->value('product_name');
                $pt_product[$k]['cover'] = Db::name('product_specs')->where('product_id',$v['product_id'])->value('cover');
                $pt_product[$k]['old_price'] = Db::name('product_specs')->where('product_id',$v['product_id'])->value('price');
            }

            $order_info['pt_product'] = $pt_product;

            return \json(self::callback(1,'',$order_info));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

}