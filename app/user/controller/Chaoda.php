<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/24
 * Time: 10:48
 */

namespace app\user\controller;


use app\common\controller\Base;
use think\Db;
use think\response\Json;

class Chaoda extends Base
{

    /*
     * 潮搭列表
     * */
    public function chaodaList(){
        try{
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $style_id = input('style_id/a');
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;

            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token');
            if ($user_id || $token) {
                $userInfo = \app\user\common\User::checkToken($user_id,$token);
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if (!empty($style_id) || count($style_id) > 0 ){
                $where['chaoda.style_id'] = ['in',$style_id];
            }

            if ($store_id){
                $where['chaoda.store_id'] = ['eq',$store_id];
            }

            $total = Db::view('chaoda')
                ->view('store','store_name','store.id = chaoda.store_id','left')
                ->where('chaoda.is_delete',0)
                ->where('chaoda.store_id','neq',0) //屏蔽小程序普通用户发布
                ->where($where)
                ->count();

            $list = Db::view('chaoda','id,store_id,cover as chaoda_cover,share_number,description,style_id')
                ->view('store','store_name,cover,province,city,area,address,is_ziqu,type','store.id = chaoda.store_id','left')
                ->where('chaoda.is_delete',0)
                ->where('chaoda.store_id','neq',0) //屏蔽小程序普通用户发布
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
                $product_info = Db::view('chaoda_tag')
                    ->view('product','freight','product.id = chaoda_tag.product_id','left')
                    ->where('chaoda_id',$v['id'])
                    ->select();

                foreach ($product_info as $k2=>$v2){
                    $product = Db::name('product_specs')->field('id,product_specs,product_name,price,cover')->where('product_id',$v2['product_id'])->find();
                    $product_info[$k2]['specs_id'] = $product['id'];
                    $product_info[$k2]['product_name'] = $product['product_name'];
                    $product_info[$k2]['old_price'] = $product['price'];
                    $product_info[$k2]['cover'] = $product['cover'];
                    $product_info[$k2]['product_specs'] = $product['product_specs'];
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
                $pt_info = Db::name('chaoda_pt_info')
                    ->field('id,end_time')
                    ->where('chaoda_id',$v['id'])
                    ->where('end_time','>',$time)
                    ->where('pt_status',1)
                    ->find();

                //拼团商品信息
                $pt_product = Db::view('chaoda_pt_product_info','product_id,price,status')
                    ->view('product','freight','product.id = chaoda_pt_product_info.product_id','left')
                    ->where('chaoda_pt_product_info.pt_id',$pt_info['id'])
                    ->select();

                if (!$pt_product){
                    $list[$k]['pt_info'] = new \stdClass();
                }else{

                    foreach ($pt_product as $k3=>$v3){

                        $product = Db::name('product_specs')->field('id,product_specs,product_name,price,cover')->where('product_id',$v3['product_id'])->find();
                        $pt_product[$k3]['specs_id'] = $product['id'];
                        $pt_product[$k3]['product_name'] = $product['product_name'];
                        $pt_product[$k3]['old_price'] = $product['price'];
                        $pt_product[$k3]['cover'] = $product['cover'];
                        $pt_product[$k3]['product_specs'] = $product['product_specs'];

                    }
                    $pt_info['pt_product'] = $pt_product;

                    $list[$k]['pt_info'] = $pt_info;
                }

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
                $userInfo = \app\user\common\User::checkToken($user_id,$token);
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
        $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

        $res = Db::name('chaoda')->where('id',$chaoda_id)->setInc('share_number',1);

        if (!$res){
            return \json(self::callback(0,'操作失败'));
        }
        return \json(self::callback(1,''));
    }

    /*
     * 收藏的潮搭列表
     * */
    public function collectionChaodaList(){
        try{

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $userInfo = \app\user\common\User::checkToken();
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

    /*
     * 潮搭评论
     * */
    public function chaodaCommentList(){
        try{
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::name('chaoda_comment')->where('chaoda_id',$chaoda_id)->count();

            $list = Db::view('chaoda_comment','content,create_time')
                ->view('user','nickname,avatar','user.user_id = chaoda_comment.user_id','left')
                ->where('chaoda_comment.chaoda_id',$chaoda_id)
                ->page($page,$size)
                ->order('chaoda_comment.create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['create_time'] = date('Y-m-d H:i',$v['create_time']);
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
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

    /*
     * 评论潮搭
     * */
    public function comment(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            $content = input('content') ? trim(input('content')) : '' ;

            if (!$chaoda_id || !$content){
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('chaoda_comment')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'content' => $content,
                'create_time' => time()
            ]);

            return \json(self::callback(1,''));

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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
     * 潮搭收藏
     * */
    public function collection(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
    public function cancelFollow(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $store_id = input('store_id');

            if (!$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('store_follow')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->delete();

            return \json(self::callback(1,''));

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