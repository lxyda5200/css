<?php

namespace app\wxapi\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\wxapi\common\Weixin;
use app\wxapi\model\HouseEntrust;
use app\wxapi\model\TagFollowModel;
use app\wxapi\model\TopicFollowModel;
use app\wxapi\validate\UserAddress;
use app\wxapi\validate\User as UserValidate;
use sourceUpload\UploadVideo;
use templateMsg\CreateTemplate;
use think\Config;
use think\Db;
use think\Exception;
use app\wxapi\common\Logic;
use app\wxapi\common\UserLogic;
use think\File;
use app\wxapi\common\Images;
use think\Image;
use think\Log;
use think\Request;
use think\response\Json;
use think\Session;
use app\wxapi\common\User as UserFunc;

class User extends Base
{
    /**
     * 普通用户发布潮搭
     */
    public function fabu(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }
            $category_id=intval($param['category_id']);
            $title=$param['fabu_info']['title'];
            $description=$param['fabu_info']['description'];
            $location=$param['fabu_info']['location'];
            $images=$param['fabu_info']['images'];
            if(!$category_id){
                return json(self::callback(0,'请选择分类'),400);
            }
            $cc=  Db::name('store_category')->field('id')->where('id',$category_id)->where('is_show',1)->where('client_type',1)->find();
            if(!$cc){
                return json(self::callback(0,'该分类不存在或已删除'),400);
            }

            $first_images=$images['0']; //第一个图片或视频
            if(!$first_images){
                return json(self::callback(0,'没有上传图片或视频'),400);
            }
            $type=$images['0']['type'];
            //获取封面图
            if($type=='image'){
                //第一个为图片
                $info['cover'] = $images['0']['src'];//封面路径
                $path = trim($images['0']['src'],'/');
                if(file_exists($path)){  //生成缩略图
                    $path = createThumb($path,"uploads/product/thumb/",'chaoda');
                }
                $info['type'] = 'image';
                $info['cover_thumb'] = $path;
            }else if($type=='video'){
                //第一个为视频
                ##检查视频信息是否已经保存
                $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($images[0]['video_id']);
                if($chaoda_img_info && $chaoda_img_info['cover']){  //阿里云返回有封面
                    $info['cover'] = $chaoda_img_info['cover'];
                    $info['cover_thumb'] = $chaoda_img_info['cover'];
                }else{
                    $cover = $images['0']['cover'];//封面路径
                    //如果不存在cover则用默认的cover
                    if(!$cover){
                        $info['cover'] = '/default/video_default.png';
                        $info['cover_thumb'] = '/default/video_default.png';
                    }else{
                        $info['cover'] = $images['0']['cover'];
                        $info['cover_thumb'] = $images['0']['cover'];
                    }
                }
                $info['type']='video';
            }else{
                //报错
                return json(self::callback(0,'未知错误',1));
            }

            //潮搭公共信息
            $info['fb_user_id']=$userInfo['user_id'];
            $info['is_pt_user']=1;
            $info['is_delete']=1;//默认不显示
            $info['status']=1;//默认待审核状态
            $info['create_time']=time();
            $info['title']=$title;
            $info['category_id']=$category_id;//分类
            $info['description']=$description;
            $info['address']=$location['address'];
            $info['latitude']=$location['latitude'];
            $info['longitude']=$location['longitude'];
            $info['name']=$location['name'];
            $info['style_id']='0';
            //插入潮搭信息
            $chaoda_id = Db::name('chaoda')->insertGetId($info);
            if ($chaoda_id===false){
                return json(self::callback(0,'发布潮搭失败'));
            }
            foreach ($images as $k=>$v){
                $type=$v['type'];
                if($type=='image'){
                    //处理图片发布
                    $chaoda_img['img_url']=$v['src'];
                    $chaoda_img['chaoda_id']=$chaoda_id;
                    $chaoda_img['type']='image';
                    $img_id = Db::name('chaoda_img')->insertGetId($chaoda_img);
                    if ($img_id===false){
                        return json(self::callback(0,'发布图片失败'));
                    }
                    //循环插入图片的多个tags
                    $tags=$v['tags'];
                    foreach ($tags as $k2=>$v2){
                        $chaoda_tag = [
                            'chaoda_id' => $chaoda_id,
                            'tag_name'=>$v2['tag_name'],
                            'x_postion'=>$v2['x_postion'],
                            'y_postion'=>$v2['y_postion'],
                            'img_id'=>$img_id,
                            'direction' => $v2['direction']
                        ];
                        $insert_chaoda_tag =Db::table('chaoda_tag')->insert($chaoda_tag);
                        if ($insert_chaoda_tag===false){
                            return json(self::callback(0,'图片标签写入失败'));
                        }
                    }

                }else if($type=='video'){
                    $media_id = $v['video_id'];
                    ##检查视频信息是否已经保存
                    $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($media_id);
                    if($chaoda_img_info){ //已经保存
                        $res = Logic::updateChadaImgChaoId($chaoda_img_info['id'],$chaoda_id);
                        if($res === false)throw new Exception('视频保存失败');
                        ##判断是否只上传了视频
                        if(count($images) == 1 && $chaoda_img_info['cover']){  //单一视频,更新封面图
                            $res = Logic::updateChaodaCover($chaoda_id, $chaoda_img_info['cover']);
                            if($res === false)throw new Exception('封面更新失败');
                        }
                    }else{
                        //处理视频发布
                        $video_url['img_url']=$v['src'];//视频地址
                        $video_url['chaoda_id']=$chaoda_id;
                        $video_url['type']='video';
                        $video_url['cover']=$v['cover'];
                        $video_url['media_id'] = $v['video_id'];
                        $video_url['cover_status'] = 1;
                        $video_url['video_type'] = 2;
                        $insert_video= Db::name('chaoda_img')->insertGetId($video_url);
                        if ($insert_video===false){
                            return json(self::callback(0,'视频发布失败'));
                        }
                    }

                }else{
                    //报错
                    return json(self::callback(0,'未知错误',2));
                }
            }

            return json(self::callback(1,'发布成功',$chaoda_id));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
  /**
     * 普通用户发布潮搭
     */
    public function fabu2(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $postJson = trim(file_get_contents('php://input'));
            $param = json_decode($postJson,true);
//            $param = $this->request->post();
//            if (!$param) {
//                return json(self::callback(0,'参数错误'),400);
//            }
            $images=$param['fabu_info']['images'];//图片或视频

            $first_images=$images['0']; //第一个图片或视频
            $chaoda_type=$param['fabu_info']['type'];//判断上传的类型
            if(!$first_images || !$chaoda_type){
                return json(self::callback(0,'没有上传图片或视频'),400);
            }
            //接收数据
            $description=$param['fabu_info']['description'];//发布内容
            $location=$param['fabu_info']['location'];//位置
            $topic_id=$param['fabu_info']['topic_id'];//话题id
            $tag_ids=$param['fabu_info']['tag_ids'];//标签字符串
            $product_ids=$param['fabu_info']['product_ids'];//商品字符串
            $recommend_store_id=$param['fabu_info']['recommend_store_id'];//store_id
            if($chaoda_type!='image' && $chaoda_type!='images' && $chaoda_type!='video'){
                return json(self::callback(0,'上传类型错误'),400);
            }
            //标签转成数组
            if(isset($tag_ids)){
                $tag_id=explode(",",$tag_ids);
                $tag_ids='';
                foreach ($tag_id as $k=>$v){
                    $tag_ids.="[".$v."],";
                }
                $tag_ids=  rtrim($tag_ids,",");
            }
            //商品转成数组
            if(isset($product_ids)){
                $product_id=explode(",",$product_ids);
                $product_ids='';
                foreach ($product_id as $k=>$v){
                    $product_ids.="[".$v."],";
                }
                $product_ids= rtrim($product_ids,",");
            }
//        if(!$category_id){
//            return json(self::callback(0,'请选择分类'),400);
//        }
//        $cc=  Db::name('store_category')->field('id')->where('id',$category_id)->where('is_show',1)->where('client_type',1)->find();
//        if(!$cc){
//            return json(self::callback(0,'该分类不存在或已删除'),400);
//        }


            $type=$images['0']['type'];
            //获取封面图
            if($type=='image'){
                //第一个为图片
                $info['cover'] = $images['0']['src'];//封面路径
                $path = trim($images['0']['src'],'/');
                if(file_exists($path)){  //生成缩略图
                    $path = createThumb($path,"uploads/product/thumb/",'chaoda');
                }
               // $info['type'] = 'image';
                $info['cover_thumb'] = $path;
            }else if($type=='video'){
                //第一个为视频
                ##检查视频信息是否已经保存
                $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($images[0]['video_id']);
                if($chaoda_img_info && $chaoda_img_info['cover']){  //阿里云返回有封面
                    $info['cover'] = $chaoda_img_info['cover'];
                    $info['cover_thumb'] = $chaoda_img_info['cover'];
                }else{
                    $cover = $images['0']['cover'];//封面路径
                    //如果不存在cover则用默认的cover
                    if(!$cover){
                        $info['cover'] = '/default/video_default.png';
                        $info['cover_thumb'] = '/default/video_default.png';
                    }else{
                        $info['cover'] = $images['0']['cover'];
                        $info['cover_thumb'] = $images['0']['cover'];
                    }
                }
               // $info['type']='video';
            }else{
                //报错
                return json(self::callback(0,'未知错误',1));
            }
            //潮搭公共信息
            $info['type'] = $chaoda_type;
            $info['fb_user_id']=$userInfo['user_id'];
            $info['is_pt_user']=1;
            $info['is_delete']=0;//默认显示
            $info['status']=2;//默认审核通过状态
            $info['create_time']=time();
            $info['description']=$description;
            $info['title']=$description;
            $info['topic_id']=$topic_id;
            $info['recommend_store_id']=$recommend_store_id;
            $info['tag_ids']=$tag_ids;
            $info['product_ids']=$product_ids;
            $info['address']=$location['address'];
            $info['latitude']=$location['latitude'];
            $info['longitude']=$location['longitude'];
            $info['name']=$location['name'];
            $info['style_id']='0';
            //插入潮搭信息
            $chaoda_id = Db::name('chaoda')->insertGetId($info);
            if ($chaoda_id===false){return json(self::callback(0,'发布潮搭失败'));}
            //判断是否有话题背景图
            if(isset($topic_id) && $topic_id>0){
                $bg_cover=  Db::name('topic')->field('id,bg_cover,list_bg_cover')->where('id',$topic_id)->find();
                if(!$bg_cover['bg_cover']){
                    if(strpos($info['cover'],'http')!== false){
                        $p= $info['cover'];
                    }else{
                        $url=$info['cover'];
                        $web_path = Config::get('web_path');
                        $p= $web_path . $url;
                    }
                    $rst = Images::gaussian_blur($p,null,null);
                    $url2=strstr($rst,"/uploads/gaosi/");
                    $bg=  Db::name('topic')->where('id',$topic_id)->setField('bg_cover',$url2);
                    if ($bg===false){return json(self::callback(0,'话题图片更新失败!'));}
                }
            }
            //存贮未处理过的话题背景图
                if(!$bg_cover['list_bg_cover']){
                    $lbg=  Db::name('topic')->where('id',$topic_id)->setField('list_bg_cover',$info['cover']);
                    if ($lbg===false){return json(self::callback(0,'话题图片2更新失败!'));}
                }
            foreach ($images as $k=>$v){
                $type=$v['type'];
                if($type=='image' || $type=='images'){
                   //处理图片发布
                    $chaoda_img['img_url']=$v['src'];
                    $chaoda_img['chaoda_id']=$chaoda_id;
                    $chaoda_img['type']=$type;
                    $img_id = Db::name('chaoda_img')->insertGetId($chaoda_img);
                    if ($img_id===false){return json(self::callback(0,'发布图片失败'));}
                    //循环插入图片的多个tags
//                    $tags=$v['tags'];
//                    foreach ($tags as $k2=>$v2){
//                        $chaoda_tag = [
//                            'chaoda_id' => $chaoda_id,
//                            'tag_name'=>$v2['tag_name'],
//                            'x_postion'=>$v2['x_postion'],
//                            'y_postion'=>$v2['y_postion'],
//                            'img_id'=>$img_id,
//                            'direction' => $v2['direction']
//                        ];
//                        $insert_chaoda_tag =Db::table('chaoda_tag')->insert($chaoda_tag);
//                        if ($insert_chaoda_tag===false){
//                            return json(self::callback(0,'图片标签写入失败'));
//                        }
//                    }
                }else if($type=='video'){
                    $media_id = $v['video_id'];
                    ##检查视频信息是否已经保存
                    $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($media_id);
                    if($chaoda_img_info){ //已经保存
                        $res = Logic::updateChadaImgChaoId($chaoda_img_info['id'],$chaoda_id);
                        if($res === false)throw new Exception('视频保存失败');
                        ##判断是否只上传了视频
                        if(count($images) == 1 && $chaoda_img_info['cover']){  //单一视频,更新封面图
                            $res = Logic::updateChaodaCover($chaoda_id, $chaoda_img_info['cover']);
                            if($res === false)throw new Exception('封面更新失败');
                        }
                    }else{
                        //处理视频发布
                        $video_url['img_url']=$v['src'];//视频地址
                        $video_url['chaoda_id']=$chaoda_id;
                        $video_url['type']='video';
                        $video_url['cover']=$v['cover'];
                        $video_url['media_id'] = $v['video_id'];
                        $video_url['cover_status'] = 1;
                        $video_url['video_type'] = 2;
                        $insert_video= Db::name('chaoda_img')->insertGetId($video_url);
                        if ($insert_video===false){
                            return json(self::callback(0,'视频发布失败'));
                        }
                    }
            }else{
                //报错
                    return json(self::callback(0,'未知错误',2));
            }
        }
            //增加标签使用次数
            if(isset($tag_ids)){
                $tag_id=explode(",",$tag_ids);
                foreach ($tag_id as $k=>$v){
                    $rst= Db::name('tag')->where('id',$v)->setInc('use_number');
                }
            }
            //增加话题使用次数
            $rst= Db::name('topic')->where('id',$topic_id)->setInc('use_number');

            //插入chaoda_and_dynamic表
//            $chaoda_and_dynamic = ['chaoda_id' => $chaoda_id, 'create_time' =>time()];
//            $chaoda_and_dynamic_rst= Db::name('chaoda_and_dynamic')->insert($chaoda_and_dynamic);
//            if ($chaoda_and_dynamic_rst===false){throw new \Exception('操作失败:004,关系表插入失败');}
            return json(self::callback(1,'发布成功',$chaoda_id));
        }catch (\Exception $e){
        return json(self::callback(0,$e->getMessage()));
        }
        }
            /*
     * 个人删除潮搭
     * */
    public function deleteChaoda(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $chaoda_id = $param['chaoda_id'];
          $rst=  Db::name('chaoda')->where('id',$chaoda_id)->where('fb_user_id',$userInfo['user_id'])->setField('is_delete',-1);
            if($rst===false){
                return \json(self::callback(0,'删除失败',false));
            }else{
                return \json(self::callback(1,'删除成功',true));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 我的关注列表
     * */
    public function myFollowList(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $type = input('type') ? intval(input('type')) : 0;  // 0-用户 1-话题 2-标签
            if($type == 0){
                //关注用户店铺列表
                $total = Db::name('store_follow')
                    ->where('user_id',$userInfo['user_id'])
                    ->count();
                $list = Db::view('store_follow','store_id,fb_user_id,type')
                    ->view('store','store_name,cover',' store_follow.store_id=store.id','left')
                    ->view('user','nickname ,user_status,avatar',' store_follow.fb_user_id=user.user_id','left')
                    ->where('store_follow.user_id',$userInfo['user_id'])
                    ->page($page,$size)
                    ->select();
                foreach ($list as $k=>$v){
                    if($v['type']==1){
                        unset($list[$k]['type']);
                        unset($list[$k]['nickname']);
                        unset($list[$k]['fb_user_id']);
                        unset($list[$k]['user_status']);
                        unset($list[$k]['avatar']);
                        $list[$k]['fans_number'] = Db::name('store_follow')->where('store_id',$v['store_id'])->count();
                        $list[$k]['chaoda_number'] = Db::name('chaoda')->where('is_delete',0)->where('store_id',$v['store_id'])->count();
                    }elseif($v['type']==2){
                        unset($list[$k]['type']);
                        unset($list[$k]['cover']);
                        unset($list[$k]['store_id']);
                        unset($list[$k]['store_name']);
                        $list[$k]['fans_number'] = Db::name('store_follow')->where('fb_user_id',$v['fb_user_id'])->count();
                        $list[$k]['chaoda_number'] = Db::name('chaoda')->where('is_delete',0)->where('fb_user_id',$v['fb_user_id'])->count();
                    }
                }
            }elseif ($type == 1){
                // 查询关注话题数据
                $list = TopicFollowModel::getUserFollowNoSort($userInfo['user_id'], $page, $size);
                return \json(self::callback(1,'查询成功',$list));
            }elseif ($type == 2){
                // 查询关注标签数据
                $list = TagFollowModel::getUserFollowNoSort($userInfo['user_id'], $page, $size);
                return \json(self::callback(1,'查询成功',$list));
            }else{
                return \json(self::callback(0,'参数错误',false));
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            if(empty($list)){
                $list=[];
            }
            $data['list'] = $list;
            return \json(self::callback(1,'查询成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 个人主页
     */
    public function userOrStoreInfo(){
        try{
            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            if(!$fb_user_id && !$store_id){
                return json(self::callback(0,'参数错误'),400);
            }
            if(isset($fb_user_id) && !$store_id){
             //用户
                $userinfo = Db::name('user')->field('user_id as fb_user_id,nickname,avatar,description')->where('user_id',$fb_user_id)->find();
                //是否关注
                $userinfo['is_follow']=0;
                if(isset($user_id) && $user_id>0){
                    $num = Db::name('store_follow')->where('user_id',$user_id)->where('fb_user_id',$fb_user_id)->count();
                    if($num){
                        $userinfo['is_follow']=$num;
                    }
                }
                //关注数
                $follows = Db::name('store_follow')->where('user_id',$fb_user_id)->count();
                $topic=Db::name('topic_follow')->where('user_id',$fb_user_id)->count();
                $tags=Db::name('tag_follow')->where('user_id',$fb_user_id)->count();

                // TODO 增加关注的话题及标签数量
                $userinfo['topic_number'] = $topic;
                $userinfo['tags_number'] = $tags;
                // TODO
                if(!$follows){$follows=0;}
                $userinfo['follows']=$follows+$topic+$tags;
                //粉丝数
                $fans = Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();
                if(!$fans){$fans=0;}
                $userinfo['fans']=$fans;
                //赞数
                $dianzan_numbers=Db::name('chaoda')->where('is_delete',0)->where('fb_user_id',$fb_user_id)->sum('dianzan_number');
                if(!$dianzan_numbers){$dianzan_numbers=0;}
                $userinfo['dianzan_number']=$dianzan_numbers;
                $where1['chaoda.fb_user_id'] = ['eq',$fb_user_id];
            }elseif (isset($store_id) && !$fb_user_id){
             //店铺
                $userinfo = Db::name('store')->field('id as store_id,cover,store_name,description')->where('id',$store_id)->find();
                //是否关注
                $userinfo['is_follow']=0;
                if(isset($user_id) && $user_id>0){
                    $num = Db::name('store_follow')->where('user_id',$user_id)->where('store_id',$store_id)->count();
                    if($num){
                        $userinfo['is_follow']=$num;
                    }
                }
                //收藏数
//                $product=Db::name('product_collection')->where('store_id',$store_id)->count();
                $chaoda=Db::name('chaoda_collection')->where('store_id',$store_id)->count();
                $follows=$chaoda;
                if(!$follows){$follows=0;}
                $userinfo['follows']=$follows;
                //粉丝数
                $fans = Db::name('store_follow')->where('store_id',$store_id)->count();
                if(!$fans){$fans=0;}
                $userinfo['fans']=$fans;
                //点赞数
                $dianzan_numbers=Db::name('chaoda')->where('is_delete',0)->where('store_id',$store_id)->sum('dianzan_number');
                if(!$dianzan_numbers){$dianzan_numbers=0;}
                $userinfo['dianzan_number']=$dianzan_numbers;
                $where1['chaoda.store_id'] = ['eq',$store_id];
            }else{
                return \json(self::callback(0,'参数错误2',false,true));
            }

            $list = Db::view('chaoda','id,store_id,cover,description,title,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where1)
                ->group('chaoda.id')
                ->select();
            $video_num=0;
            $images_num=0;
            foreach ($list as $k=>$v){
                //判断是否是多图
                if($v['type']=='video'){
                    $video_num+=1;
                }else{
                    $num2[$k]= Db::name('chaoda_img')->where('chaoda_id',$v['id'])->where('type','video')->count();
                    if($num2[$k]>0){
                        $video_num+=1;
                    }else{
                        $images_num+=1;
                    }
                }
            }
            $userinfo['total_numbers'] =$video_num+$images_num;
            $userinfo['video_numbers'] =$video_num;
            $userinfo['picture_numbers'] =$images_num;
            return \json(self::callback(1,'返回成功',$userinfo));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 个人主页动态列表
     */
    public function userOrStoreInfoDynamicList(){
        try{
            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            $type = input('type') ? intval(input('type')) : 0 ;//0全部 1视频 2图片
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if(!$fb_user_id && !$store_id){
                return json(self::callback(0,'参数错误'),400);
            }
            if(isset($fb_user_id) && !$store_id){
                //用户
                $where1['chaoda.fb_user_id'] = ['eq',$fb_user_id];
            }elseif (isset($store_id) && !$fb_user_id){
                //店铺
                $where1['chaoda.store_id'] = ['eq',$store_id];
            }else{
                return \json(self::callback(0,'参数错误2',false,true));
            }
            if($type==1 ){
                $where['chaoda.type'] = ['eq','video'];
            }elseif($type==2){
                $where="chaoda.type = 'image'  OR chaoda.type = 'images' OR chaoda.type is null";
            }

            $total = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title as topic_title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->where($where1)
                ->group('chaoda.id')
                ->count();
            $list = Db::view('chaoda','id,store_id,cover,description,title,fb_user_id,address,type,cover_thumb,dianzan_number')
                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->view('topic','title as topic_title','chaoda.topic_id = topic.id','left')
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status !=-1")
                ->where($where)
                ->where($where1)
                ->group('chaoda.id')
                ->page($page,$size)
                ->order('chaoda.id','desc')
                ->select();
            //判断是否点赞
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
                if(isset($user_id) && $user_id>0){
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 关注的话题或标签列表
     */
    public function followTopicOrTagList(){
        try{
            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            $type = input('type') ? intval(input('type')) : 0 ;// 1:标签 2:话题
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if((!$fb_user_id && !$store_id ) || !$type){
                return json(self::callback(0,'参数错误'),400);
            }
            if(isset($fb_user_id) && !$store_id){
                //用户
                if($type==1 ){
                    //标签
                    $table='tag_follow';
                    $table2='tag';
                    $filed='tag_id';
                }elseif($type==2){
                    //话题
                    $table='topic_follow';
                    $table2='topic';
                    $filed='topic_id';
                }else{
                    return \json(self::callback(0,'参数错误2'),400);
                }
                $total = Db::view($table,'id,'.$filed)
                    ->view($table2,'title,description',$table.'.'.$filed .'='. $table2.'.id','left')
                    ->where($table2.'.status',1)
                    ->where($table.'.user_id',$fb_user_id)
                    ->count();
                $list = Db::view($table,'id,'.$filed)
                    ->view($table2,'title,description',$table.'.'.$filed .'='. $table2.'.id','left')
                    ->where($table2.'.status',1)
                    ->where($table.'.user_id',$fb_user_id)
                    ->page($page,$size)
                    ->order('id','desc')
                    ->select();
            }elseif (isset($store_id) && !$fb_user_id){
                //店铺
                $total=0;
                $list=[];
            }else{
                return \json(self::callback(0,'参数错误2',false,true));
            }

            foreach ($list as $k=>$v){
                if(isset($user_id) && $user_id>0){
                    $list[$k]['is_follow'] = Db::name($table)->where('user_id',$user_id)->where('id',$v['id'])->count();
                }else{
                    $list[$k]['is_follow'] = 0;
                }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 个人或店铺粉丝列表
     */
    public function FansList(){
        try{
            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
            $user_type=input('user_type') ? intval(input('user_type')) : 0;  // 1-自己 2-别人
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 0 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            $token = (input('token') && input('token') != '') ? input('token') : 0 ;
            if(!$user_type){return json(self::callback(0,'参数错误'),400);}
            // token 验证  查询自己关注列表  或  查看别人关注列表并传递user_id+token时进行验证
            if ($user_type == 1 ){
                $userInfo = \app\wxapi\common\User::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            // 查询别人时 必传其一
            if($user_type == 2){
                if (!$fb_user_id && !$store_id){
                    return json(self::callback(0,'参数错误'),400);
                }
            }
            // 查询别人时 判断是查询店铺还是个人
            if(isset($fb_user_id) && !$store_id && $user_type == 2){
                //用户
                $where['store_follow.fb_user_id'] = ['eq',$fb_user_id];
                $filed='fb_user_id';
                $filed2 = 'user_id';
                $op=$fb_user_id;
            }elseif (isset($store_id) && !$fb_user_id && $user_type == 2){
                //店铺
                $where['store_follow.store_id'] = ['eq',$store_id];
                $filed='store_id';
                $filed2='store_id';
                $op=$store_id;
            }elseif(!$store_id && !$fb_user_id && $user_type == 2){
                return \json(self::callback(0,'参数错误2',false,true));
            }elseif($user_type == 1){
                // 查询自己时 只需传递 店铺ID即可 fb_user_id不做判断，直接用user_id即可
//                if ($store_id) $where['store_follow.store_id'] = ['eq', $store_id];
//                if (!$store_id) $where['store_follow.fb_user_id'] = ['eq', $user_id];
                $where['store_follow.fb_user_id'] = ['eq',$user_id];
            }
            $total =  Db::view('store_follow','store_id,fb_user_id,user_id as fans_user_id,type')
                ->view('store','store_name,cover',' store_follow.store_id=store.id','left')
                ->view('user','nickname ,user_status,avatar',' store_follow.user_id=user.user_id','left')
                ->where($where)
                ->count();
            $list =  Db::view('store_follow','store_id,fb_user_id,user_id as fans_user_id,type')
                ->view('store','store_name,cover,description as store_description',' store_follow.store_id=store.id','left')
                ->view('user','nickname ,avatar,description',' store_follow.user_id=user.user_id','left')
                ->where($where)
                ->page($page,$size)
                ->select();

            foreach ($list as $k=>&$v){
                if($v['type']==1){
                    //店铺
                    unset($v['store_description']);
                }
//                if($user_type==1 || $user_type == 2){
//                    $filed='fb_user_id';
//                    $op=$v['user_id'];
//                }
                if($v['avatar']==''){
                    $v['avatar']='/default/user_logo.png';
                }
                //是否关注

                    if(isset($user_id) && $user_id>0){
                        $list[$k]['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('fb_user_id',$v['fans_user_id'])->count();
                    }else{
                        $list[$k]['is_follow'] = 0;
                    }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 个人和自己的关注列表
     */
    public function FollowList(){
        try{

            $user_id = (input('user_id') && input('user_id') > 0)? intval(input('user_id')) : 0 ;
            // $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            $user_type = input('user_type') ? intval(input('user_type')) : 0 ;//1自己 2别人
            $page = input('page') ? intval(input('page')) : 1  ;
            $size = input('size') ? intval(input('size')) : 15 ;
            $token = (input('token') && input('token') != '') ? input('token') : 0 ;
            $type = input('type') ? intval(input('type')) : 0;  // 0-用户 1-话题 2-标签
            if(!$user_type ){return json(self::callback(0,'参数错误'),400);}
            // token 验证  查询自己关注列表  或  查看别人关注列表并传递user_id+token时进行验证
            if ($user_type == 1 ){
                $userInfo = \app\wxapi\common\User::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }

            if($user_type == 2){
                if (!$fb_user_id){
                    return json(self::callback(0,'参数错误'),400);
                }else{
                    $otherUid = $fb_user_id;
                }
            }else{
                // 查看自己的关注列表时  检测登录用户是否关注  此处默认为0 表示都以关注
                $otherUid = 0;
            }

            if ($type == 0){
                //用户
                if ($user_type == 1){
                    $where['store_follow.user_id'] = ['eq',$user_id];
                }elseif($user_type == 2){
                    $where['store_follow.user_id'] = ['eq',$fb_user_id];
                }
                $total =  Db::view('store_follow','store_id,fb_user_id,user_id,type')
                    ->view('store','store_name,cover,description as store_description',' store_follow.store_id=store.id','left')
                    ->view('user','nickname ,user_status,avatar,description',' store_follow.fb_user_id=user.user_id','left')
                    ->where($where)
                    ->count();
                $list =  Db::view('store_follow','store_id,fb_user_id,user_id,type')
                    ->view('store','store_name,cover',' store_follow.store_id=store.id','left')
                    ->view('user','nickname ,avatar',' store_follow.fb_user_id=user.user_id','left')
                    ->where($where)
                    ->page($page,$size)
                    ->select();

                foreach ($list as $k=>$v){
                    if($v['type']==1){

                        $list[$k]['fb_user_id']=null;

                        unset($list[$k]['description']);
                        // 原来代码  逻辑错误
                        /*$filed='fb_user_id';
                        $op=$v['fb_user_id'];*/
                        // 新改代码
                        $filed='store_id';
                        $op=$v['store_id'];
                    }elseif($v['type']==2){



                        $list[$k]['description'] = $v['store_description'];
                        unset($list[$k]['description']);
                        // 原来代码 逻辑错误
                        /*$filed='store_id';
                        $op=$v['store_id'];*/
                        // 新改代码
                        $filed='fb_user_id';
                        $op=$v['fb_user_id'];
                    }
                    //是否关注
                    if(isset($user_id) && $user_id>0){

                        $is_follow = Db::name('store_follow')->where('user_id',$user_id)->where($filed,$op)->count();
                        $list[$k]['is_follow'] = $is_follow > 0 ? 1 : 0;
                    }else{
                        $list[$k]['is_follow'] = 0;
                    }
                }
            }elseif ($type == 1){
                // 查询关注话题数据
                $list = TopicFollowModel::getUserFollowNoSort($user_id, $page, $size, $otherUid);
                return \json(self::callback(1,'查询成功',$list));
            }elseif ($type == 2){
                // 查询关注标签数据
                $list = TagFollowModel::getUserFollowNoSort($user_id, $page, $size,$otherUid);
                return \json(self::callback(1,'查询成功',$list));
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 收藏店铺动态的用户列表
     */
    public function collectionUserList(){
        try{
            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;s
//            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $store_id = input('store_id') ? intval(input('store_id')) : 0 ;
//            $fb_user_id = input('fb_user_id') ? intval(input('fb_user_id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if(!$store_id ){
                return json(self::callback(0,'参数错误'),400);
            }
            //店铺
            $where['chaoda_collection.store_id'] = ['eq',$store_id];
            $total =  Db::view('chaoda_collection','store_id,user_id')
                ->view('user','nickname,avatar',' chaoda_collection.user_id=user.user_id','left')
                ->where($where)
                ->group('chaoda_collection.user_id')
                ->count();
            $list =   Db::view('chaoda_collection','store_id,user_id')
                ->view('user','nickname,avatar',' chaoda_collection.user_id=user.user_id','left')
                ->where($where)
                ->group('chaoda_collection.user_id')
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v){
                //是否关注
                if(isset($user_id) && $user_id>0){
                    $list[$k]['is_follow'] = Db::name('store_follow')->where('user_id',$user_id)->where('fb_user_id',$v['user_id'])->count();
                }else{
                    $list[$k]['is_follow'] = 0;
                }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'返回成功！',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 兑换优惠券(兑换码+分享码)
     */
    public function exchangeCoupon()
    {
        try {
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json) {
                return $userInfo;
            }
            #接收参数
            $coupon_code = $this->request->post('coupon_code');
            if (!$coupon_code) {
                return json(self::callback(0, '请输入兑换码'), 400);
            }
            #逻辑
            $user_id = input('post.user_id',0,'intval');
            ##优惠券信息
            $coupon_info = Logic::couponCodeInfo($coupon_code);
            if (!$coupon_info) throw new Exception('无效兑换码');
            if ($coupon_info['status'] == -1) throw new Exception('兑换码已冻结');
            if ($coupon_info['is_open'] != 1) throw new Exception('优惠券已下架');
            if ($coupon_info['end_time'] < time()) throw new Exception('优惠券已过期');
            if ($coupon_info['surplus_number'] <= 0) throw new Exception('来晚啦!优惠券已经被领光啦!');
            if ($coupon_info['type'] == 1 && $coupon_info['status'] == 2) throw new Exception('兑换码已失效');
            ##获取总领取数
            $number =Db::name('coupon_exchange_record')->where('coupon_rule_id',$coupon_info['css_coupon_id'])->where('user_id',$userInfo['user_id'])->count();
            if($number>=$coupon_info['zengsong_number']){
                throw new Exception('优惠券领用已达上限');
            }
//            $get_count = UserLogic::countExchangeRecord($coupon_info['css_coupon_id'], $user_id);
//            if ($get_count >= $coupon_info['zengsong_number']) throw new Exception('优惠券领用已达上限');
            if ($coupon_info['type'] == 2) {  //验证推广券
                ##获取推广人状态
                $extend_info = Logic::getExtendInfo($coupon_info['extend_id']);
                if (!$extend_info) throw new Exception('兑换码异常[2001]');
                if ($extend_info['status'] != 1) throw new Exception('兑换码已下架');
                //查询用户是否兑换

            }
            ##下发优惠券
            Db::startTrans();
            ###下发优惠券
            $data_coupon = [
                'user_id' => $user_id,
                'coupon_id' => $coupon_info['css_coupon_id'],
                'coupon_name' => $coupon_info['coupon_name'],
                'store_id' => (int)$coupon_info['store_id'],
                'satisfy_money' => $coupon_info['satisfy_money'],
                'coupon_money' => $coupon_info['coupon_money'],
                'expiration_time' => $coupon_info['end_time'],
                'create_time' => time(),
                'css_coupon_table_num' => 1,
                'coupon_type' => $coupon_info['coupon_type']
            ];
            $coupon_id = UserLogic::userGetCouponRtnId($data_coupon);
            if ($coupon_id === false) throw new Exception('优惠券兑换失败[2002]');
            ###修改优惠券信息
            $res = Logic::updateCouponNum($coupon_info['css_coupon_id']);
            if ($res === false) throw new Exception('优惠券兑换失败[2003]');
            ###增加兑换记录
            $data_coupon_record = [
                'code_id' => $coupon_info['id'],
                'extend_id' => $coupon_info['extend_id'],
                'user_id' => $user_id,
                'coupon_id' => $coupon_id,
                'coupon_rule_id' => $coupon_info['css_coupon_id'],
            ];
            $res = UserLogic::addExchangeCouponLog($data_coupon_record);
            if ($res === false) throw new Exception('优惠券兑换失败[2004]');
            ###修改兑换码状态(合作券兑换码)
            if ($coupon_info['type'] == 1) {
                $data_coupon_code = [
                    'exchange_time' => time(),
                    'status' => 2
                ];
                $res = Logic::cancelCouponCode($coupon_info['id'], $data_coupon_code);
                if ($res === false) throw new Exception('优惠券兑换失败[2005]');
            }

            #返回
            Db::commit();
            return \json(self::callback(1, '优惠券兑换成功',true));
        } catch (Exception $e) {
            Db::rollback();
            return \json(self::callback(0, $e->getMessage()));
        }
    }
    /**
     * 用户优惠券卡券列表
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function couponList(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));
        ##验证token
        $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        $type = input('post.type',0,'intval');//优惠券类型，0.所有优惠券；1.平台优惠券；2.商家优惠券;3商品优惠券
        $user_id = input('post.user_id',0,'intval');
        $page = input('post.page',0,'intval');
        $size = input('post.size',10,'intval');
        $page = $page<0?0:$page;
        $size = $size<=0?10:$size;
        $list = UserLogic::userCouponLists($user_id,$type,$page,$size);
        return \json(self::callback(1,'',compact('total','max_page','list')));
    }
    /**
     * 获取订单可用优惠券列表
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function orderCouponList_old(){
        try{
            ##token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #逻辑
            $user_id = input('post.user_id',0,'intval');
            $coupon_type = input('post.type',1,'intval');  //1.平台券;2.店铺券 3,商品券
//            $coupon_id = $coupon_type == 2?input('post.coupon_id',0,'intval'):input('post.coupon_id','','addslashes,strip_tags,trim');
            $store_id = input('post.store_id',0,'intval');
            $product_id = $this->request->post('product_id/a');//商品id数组
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $money = input('post.money');
            $coupon_id = $this->request->post('coupon_id/a');
            if($coupon_type == 2 && !$store_id)throw new Exception('参数错误');
            if($coupon_type == 3 && !$store_id && !$product_id)throw new Exception('参数错误');
            $data = UserLogic::userOrderCouponLists0812($coupon_type,$money, $coupon_id,$user_id, $store_id,$product_id,$page, $size);
            return \json(self::callback(1,'',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 获取订单可用优惠券列表
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function orderCouponList(UserValidate $UserValidate){
        try{
            #验证
            $res = $UserValidate->scene('order_coupon_list')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $user_id = input('post.user_id',0,'intval');
            $coupon_type = input('post.type',0,'intval');  //1.平台券;2.店铺券;3.商品券
//            $coupon_id = $coupon_type == 2?input('post.coupon_id',0,'intval'):input('post.coupon_id','','addslashes,strip_tags,trim'); //已选中卡券id
//            if($coupon_type == 1 && $coupon_id)$coupon_id = explode(',',$coupon_id);
            if(!$coupon_type){
                throw new Exception('参数错误');
            }
            $store_id = input('post.store_id',0,'intval');
            $page = input('post.page',0,'intval');
            $size = input('post.size',10,'intval');
            $money = input('post.money',0);
            $product_id = $this->request->post('product_id/a');//商品id数组
            $coupon_id = $this->request->post('coupon_id/a');
            if($coupon_type == 3 && !$product_id)throw new Exception('参数错误');
            if($coupon_type == 2 && !$store_id)throw new Exception('参数错误');

            $data = UserLogic::userOrderCouponLists0906($money,$coupon_type, $coupon_id,$user_id, $store_id, $product_id, $page, $size);

            return \json(self::callback(1,'返回成功!',$data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 收货地址列表
     */
    public function addressList(){
        try {
        $param = $this->request->post();
        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }
        //token 验证
        $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }
        $data = Db::name('user_address')->where('user_id',$userInfo['user_id'])->order('is_default desc')->order('create_time desc')->select();
        if ($data){
            return json(self::callback(1,'查询成功',$data));
        }
        $data=[];
        return json(self::callback(1,'没有可用的地址了',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 二维码分项数据统计
     */
    public function shareData(){
        try {
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }
            //当天
            $today = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $today_end = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
            //本周

            $curr = date("Y-m-d");
            $w=date('w');//获取当前周的第几天 周日是 0 周一到周六是1-6  
            $beginLastweek=strtotime("$curr -".($w ? $w-1 : $w-6).' days');//获取本周开始日期，如果$w是0是周日:-6天;其它:-1天    
            $week=date('Y-m-d 00:00:00',$beginLastweek);
            $week_end=date('Y-m-d 23:59:59',strtotime("$week +6 days"));
            $where2["FROM_UNIXTIME(authorize_time,'%Y-%m-%d')"]=['between',[$week,$week_end] ];
            $todayShare = Db::name('user')->where('invitation_user_id',$userInfo['user_id'])->where('authorize_time','between',[$today,$today_end])->count();
            $weekShare = Db::name('user')->where('invitation_user_id',$userInfo['user_id'])->where($where2)->count();
            $totalShare = Db::name('user')->where('invitation_user_id',$userInfo['user_id'])->count();
            if(!$todayShare){
                $todayShare=0;
            }
            if(!$weekShare){
                $weekShare=0;
            }
            if(!$totalShare){
                $totalShare=0;
            }
           $data=[
               'todayShare'=>$todayShare,
               'weekShare'=>$weekShare,
               'totalShare'=>$totalShare
            ];
            return json(self::callback(1,'返回成功',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 添加收货地址
     */
    public function addAddress(){
        try {
        $param = $this->request->post();
        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }
        //token 验证
        $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }
        $validate = new UserAddress();
        if (!$validate->check($param,[])) {
            return json(self::callback(0,$validate->getError()),400);
        }
        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];
        $userAddressModel = new \app\wxapi\model\UserAddress();
        if ($param['is_default'] == 1){
            $userAddressModel->where('user_id',$userInfo['user_id'])->setField('is_default',0);
        }
        $result = $userAddressModel->allowField(true)->save($param);
        if (!$result){
            return json(self::callback(0,'操作失败'));
        }
        return json(self::callback(1,'添加成功',true));
        }catch (\Exception $e){
        return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改收货地址
     */
    public function modifyAddress(){
        try {
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $result = $this->validate($param, ['address_id'  => 'require|number']);
        if(true !== $result){
            // 验证失败 输出错误信息
            return json(self::callback(0,$result),400);
        }

        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];

        $userAddressModel = new \app\wxapi\model\UserAddress();


        if ($param['is_default'] == 1){
            $userAddressModel->where('user_id',$userInfo['user_id'])->setField('is_default',0);
        }

        $result = $userAddressModel->allowField(true)->save($param,['id'=>$param['address_id']]);

        if (!$result){
            return json(self::callback(0,'操作失败'));
        }

        return json(self::callback(1,'修改成功',true));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 设置默认收货地址
     */
    public function defaultAddress(){
        try {
            $param = $this->request->post();
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }
            //获取地址id
            $id=intval($param['id']);
            if (!$id) {
                return json(self::callback(0,'参数错误',false));
            }
            $rst = Db::name('user_address')->where('id',$id)->where('user_id',$userInfo['user_id'])->find();
        if($rst){
            Db::startTrans();
            $rst2= Db::table('user_address')->where('id',$id)->where('user_id',$userInfo['user_id'])->setField('is_default', 1);
            if($rst2 === false)throw new Exception('设置默认地址失败');
            $rst1= Db::table('user_address')->where('user_id',$userInfo['user_id'])->where('id','neq',$id)->setField('is_default', 0);
            if($rst1 === false)throw new Exception('取消默认地址失败');
            Db::commit();
            return json(self::callback(1,'设置成功',true));
        }else{
            return json(self::callback(0,'没有找到这个地址',false));
        }
        }catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除地址
     */
    public function deleteAddress(){
        try {
        //token 验证
        $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }
        $address_id = input('address_id') ? intval(input('address_id')) : 0 ;
        if (!$address_id){
            return \json(self::callback(0,'参数错误'));
        }
        $userAddressModel = new \app\wxapi\model\UserAddress();
        $result = $userAddressModel->where('id',$address_id)->delete();
        if (!$result){
            return \json(self::callback(0,'操作失败'));
        }

        return \json(self::callback(1,'删除成功',true));
    }catch (\Exception $e){
return json(self::callback(0,$e->getMessage()));
}
    }
    /**
     * 意见反馈
     */
    public function feedback(){
        try{
            $param = $this->request->post();
            if (!$param || !$param['content']) {
                return json(self::callback(0,'提交的内容不能为空'),400);
            }
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $param['content']=trim($param['content']);//删除空格
            $data = [
                'content' => $param['content'],
                'user_id' => $userInfo['user_id'],
                'img_url' => '',//暂时没有设置反馈带图片 暂时默认为空 以后有需求再改
                'is_read' =>0 ,
                'create_time' => time()
            ];
            $feedback_id = Db::name('feedback')->insertGetId($data);
            $files = $this->request->file('img');
            if ($files){
                foreach ($files as $file){
                    $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'feedback_img');
                    if($info){
                        $img_url= $avatar = DS.'uploads'.DS.$this->request->module().DS.'feedback_img'.DS.$info->getSaveName().',,,';
                        $param['img_url'] = trim(str_replace(DS,"/",$img_url),',,,');

                    }else{
                        return json(self::callback(0,$file->getError()));
                    }
                }
            }
            if ($feedback_id===false){
                return json(self::callback(0,'操作失败'));
            }
            return json(self::callback(1,'反馈成功',$feedback_id));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }
     /**
     ** 我的资料
     **/
    public function getMyInfo(){
        try{
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
           $data=Db::table('user')->field('user_id,token,mobile,nickname,avatar')->where('user_id',$userInfo['user_id'])->find();
            return json(self::callback(1,'',$data));
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));

        }
    }



    /**
     ** 我的
     **/
    public function MyInfo(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            //查询用户信息
            $data['user_info']=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,description')->where('user_id',$userInfo['user_id'])->find();
            //查询关注
            $store_follow=Db::name('store_follow')->where('user_id',$userInfo['user_id'])->count();
            $topic=Db::name('topic_follow')->where('user_id',$userInfo['user_id'])->count();
            $tags=Db::name('tag_follow')->where('user_id',$userInfo['user_id'])->count();
            $data['guanzhu_number']=$store_follow+$topic+$tags;
            //我的粉丝
            $data['fans_number']=Db::name('store_follow')->where('fb_user_id',$userInfo['user_id'])->count();
            //1.潮搭收藏
            $chaoda = Db:: view('chaoda_collection','chaoda_id,fb_user_id,store_id,user_id')
                ->view('chaoda','id,is_delete','chaoda_collection.chaoda_id = chaoda.id','left')
                ->view('store','store_status','chaoda_collection.store_id = store.id','left')
                ->view('user','user_id,user_status','chaoda_collection.fb_user_id = user.user_id','left')
                ->where('chaoda_collection.user_id',$userInfo['user_id'])
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status =1 OR user.user_status =3")
                ->count();
//            $num=0;
//            foreach ($chaoda as $k=>$v){
//            if($v['store_id']>0 ){
//            if($v['store_status']==1 ){
//                $num+=1;
//            }
//            }elseif($v['fb_user_id']>0 ){
//            if($v['user_status']==1 || $v['user_status']==3){
//                $num+=1;
//            }
//            }
//            }
            //2.商品收藏
            $shangpin = Db::view('product_collection')
                ->view('product_specs','product_specs','product_collection.specs_id = product_specs.id','left')
                ->view('product','product_name','product.id = product_collection.product_id','right')
                ->view('store','store_status','product.store_id = store.id','left')
                ->where('product_collection.user_id',$userInfo['user_id'])
                ->where('product.status','eq',1)
                ->where('product_specs.id','gt',0)
                ->where('store.store_status',1)
                ->count();
            //收藏总数
            $data['shoucang_number']=$chaoda+$shangpin;
            //查询发布
            $data['fabu_number']=Db::name('chaoda')->where('fb_user_id',$userInfo['user_id'])->where('is_delete', 'neq',-1 )->count();
            //查询未读消息
            $data['notice']=Db::name('user_msg_link')->where('user_id',$userInfo['user_id'])->where('is_read',0)->count();
            return json(self::callback(1,'',$data));
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
      ** 修改用户信息
     **/
//    public function modifyUserinfo(){
//        try{
//            $param = $this->request->post();
//
//            if (!$param) {
//                return json(self::callback(0,'参数错误'),400);
//            }
//            //token 验证
//            $userInfo = \app\wxapi\common\User::checkToken();
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }
//            $nickname=trim($param['nickname']);//获取昵称并去除特殊字符串
//            $rst=Db::table('user')->where('user_id',$userInfo['user_id'])->setField('nickname', $nickname);
//            if($rst){
//                 return json(self::callback(1,'修改昵称成功!',$nickname));
//            }
//                return json(self::callback(0,'修改昵称失败!'));
//        }catch (\Exception $e) {
//            return json(self::callback(0,$e->getMessage()));
//
//        }
//    }
    /**
     ** 修改头像上传处理
     **/
    public function modifyavatar(){
        try {

            if($this->request->file('avatar')){
                $file = $this->request->file('avatar');
            }else{
                return json(self::callback(0,'没有上传文件'));
            }
            if ($file) {
                //修改头像
                $info = $file->validate(['ext'=>'jpg,jpeg,png'])->move(ROOT_PATH.'public'.DS.'uploads'.DS.'user'.DS.'avatar');
                if ($info) {

                    ##审核图片内容
                    $path = DS . 'uploads' . DS . 'user' . DS . 'avatar' . DS . $info->getSaveName();
                    if($info->getSize() > 1024 * 1024){
                        $thumb = createThumb($path,"uploads/temp/", 'check');
                        $check = imgSecCheck(realpath(trim($thumb,'/')));
                        unlink(trim($thumb,'/'));
                    }else{
                        $check = imgSecCheck($info->getRealPath());
                    }

                    if(!$check){  ##审核不通过
                        return \json(self::callback(0,"fail"));
                    }

                    $res['src'] = DS.'uploads'.DS.'user'.DS.'avatar'.DS.$info->getSaveName();
                    // $data['avatar'] = str_replace(DS,"/",$avatar);
                    return json(self::callback(1,'success',$res));
                }else{
                    return json(self::callback(0,'fail'));
                }
            }
        } catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 我的发布列表
     */
    public function myFabu(){
        try {
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;
        $total= Db::table('chaoda')->where('fb_user_id',$userInfo['user_id'])->where('is_delete', 'neq',-1 )->count();
        $list= Db::table('chaoda')->field('id,cover,title,description,status,is_delete,reason,type,cover_thumb')->where('fb_user_id',$userInfo['user_id'])->where('is_delete', 'neq',-1 )->page($page,$size)->order('id desc')->select();
        foreach ($list as $k => $v) {
            if($v['cover_thumb']){
                $list[$k]['cover'] = $v['cover_thumb'];
            }
            $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
            $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->where('user_id',$userInfo['user_id'])->count();
            if($list[$k]['is_dianzan']==0){
                $list[$k]['is_dianzan']=0;
            }

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
        }
        $data['total'] = $total;
        $data['max_page'] = ceil($total/$size);
        $data['list'] = $list;
        return \json(self::callback(1,'',$data));
        } catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 潮搭发布上传文件 返回url
     * @return [type] [description]
     */
    public function uploadFile()
    {
        try {
            $module = 'user';//user文件夹
            $use = 'fabu';//user发布的
            if($this->request->file('file')){
                $file = $this->request->file('file');
            }else{
                return json(self::callback(0,'没有上传文件'));
            }
            $info = $file->validate(['size'=>20*1024*1024,'ext'=>'jpg,png,gif,mp4,zip,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
            if($info) {
                ##审核图片内容
                $path = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
                if($info->getSize() > 1024 * 1024){
                    $thumb = createThumb($path,"uploads/temp/", 'check');
                    $check = imgSecCheck(realpath(trim($thumb,'/')));
                    unlink(trim($thumb,'/'));
                }else{
                    $check = imgSecCheck($info->getRealPath());
                }

                if(!$check){  ##审核不通过
                    return \json(self::callback(0,'图片内容审核不通过'));
                }
                $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
                return json(self::callback(1,'success',$res));
            } else {
                // 上传失败获取错误信息
                return \json(self::callback(0,'failed：'.$file->getError()));
            }
        } catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 上传视频
     * @return Json
     */
    public function uploadVideo(){
        try{
            ##验证
            $user_id = input('user_id','0','intval');
            if(!$user_id)throw new Exception('参数缺失');
            if(!request()->has('video','file'))throw new Exception('上传文件缺失');

            $file = request()->file('video');
            if($file){
                $path = $file->getRealPath();
                $size = $file->getSize();
                $ext = explode('/',$file->getInfo('type'))[1];
                ##判断文件格式
                $right_ext = config('config_uploads.video_type');
                if(!in_array(strtolower($ext),$right_ext))throw new Exception('文件格式不支持');
                ##判断文件大小
                if($size > 50 * 1024 *1024)throw new Exception('文件过大');
                ##保存临时本地文件
                $file_name = $user_id . time() . rand(10000,99999) . '.' . $ext;
                $data = file_get_contents($path);
                $path2 = "uploads/video_temp/{$file_name}";
                file_put_contents($path2,$data);
                if(file_exists($path2)){
                    $res = UploadVideo::uploadLocalVideo($path2,$file_name);
                    if(!$res)throw new Exception('上传失败');
                    $data = [
                        'video_url' => $res['path'],
                        'cover_img' => $res['path'] . "?x-oss-process=video/snapshot,t_4000,m_fast",
                        'video_id'  => $res['media_id']
                    ];
                    @unlink($path2);  //删除临时文件
                    return json(self::callback(1,'',$data));
                }
                throw new Exception('临时本地文件生成失败');
            }
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

//------------------------------------------------------------------

    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $mobile = input('mobile');   //获取验证码的手机号
        $mobile_type = input('mobile_type');  //是否已注册 1是 0否  需要验证

        if (!$mobile || !isset($mobile_type)) {
            return json(self::callBack(0,'参数错误'),400);
        }

        $count = Db::name('user')->where('mobile','eq',$mobile)->count();

        if ($mobile_type == 1){
            if(!$count){
                return json(self::callback(0, "该手机号未注册"));
            }
        }elseif ($mobile_type == 0){
            if($count){
                return json(self::callback(0, "该手机号已注册"));
            }
        }else{
            return json(self::callBack(0,'参数错误'),400);
        }


        $res = IhuyiSMS::getCode($mobile);

        if ($res !== true) {
            return json(self::callBack(0,$res));
        }

        return json(self::callBack(1,'',['mobile_code'=>Session::get('mobile_code')]));
    }


    /**
     * 注册
     */
    public function register(){
        try {
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $param['password'] = password_hash($param['password'], PASSWORD_DEFAULT);
            $param['nickname'] = '新用户'.hide_phone($param['mobile']);

            Db::startTrans();
            $UserModel = new \app\user\model\User();
            $result = $UserModel->allowField(true)->save($param);

            $user_id = $UserModel->user_id;

            if (!$result) {
                Db::rollback();
                throw new \Exception('注册失败');
            }

            $tokenInfo = \app\user\common\User::setToken();

            if (!$tokenInfo) {
                Db::rollback();
                throw new \Exception('注册失败,请稍后重试');
            }

            //是否填写邀请码
            if ($param['user_code']){
                $invitation_user_id = decode($param['user_code']);
                $UserModel->invitation_user_id = $invitation_user_id;
            }

            $UserModel->token = $tokenInfo['token'];
            $UserModel->token_expire_time = $tokenInfo['token_expire_time'];
            $UserModel->login_time = time();

            $UserModel->allowField(true)->save();


            /*//赠送优惠券 满300-50 七天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 300, 'coupon_money' => 50, 'status' => 1, 'expiration_time' => time() + 24*3600*7, 'create_time' => time()]);

            //赠送优惠券 满100-25 五天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 100, 'coupon_money' => 25, 'status' => 1, 'expiration_time' => time() + 24*3600*5, 'create_time' => time()]);

            //赠送优惠券 满50-15 三天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 50, 'coupon_money' => 15, 'status' => 1, 'expiration_time' => time() + 24*3600*3, 'create_time' => time()]);

            //赠送优惠券 满20-10 两天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 20, 'coupon_money' => 10, 'status' => 1, 'expiration_time' => time() + 24*3600*2, 'create_time' => time()]);*/

            /*//赠送优惠券
            $coupon_rule = Db::name('coupon_rule')->where('id',1)->find();

            if ($coupon_rule['is_open'] == 1){
                for ($i=0;$i<$coupon_rule['zengsong_number'];$i++) {
                    Db::name('coupon')->insert([
                        'user_id' => $user_id,
                        'coupon_name' => $coupon_rule['coupon_name'],
                        'satisfy_money' => $coupon_rule['satisfy_money'],
                        'coupon_money' => $coupon_rule['coupon_money'],
                        'status' => 1,
                        'expiration_time' => time() + 24*3600*$coupon_rule['days'],
                        'create_time' => time()
                    ]);
                }
            }*/

            Session::clear();
            Db::commit();

            return json(self::callback(1,'',['user_id'=>$user_id,'token'=>$tokenInfo['token']]));


        } catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }


    /**
     * 微信授权登录
     */
    public  function wxlogin(){
        try{

            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }


            $UserModel = new \app\user\model\User();
            $userInfo = $UserModel->where('mobile',$param['mobile'])->find();

            if (!$userInfo) {



                throw new \Exception('账号不存在');
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }

            if ($userInfo['user_status'] != 1) {
                throw new \Exception('账号已被禁用');
            }

            $token = \app\user\common\User::setToken();

            $result = $UserModel->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'login_time'=>time()
                ],['user_id'=>$userInfo['user_id']]);

            if($result === false){
                return \json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,'',['user_id'=>$userInfo['user_id'],'token'=>$UserModel->getData('token')]));

        }catch (\Exception $e) {

            return json(self::callback(0,$e->getMessage()));

        }
    }


    /**
     * 忘记密码
     */
    public function forgetPassword(){
        try {

            $param = $this->request->param();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'forgetPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['password'], PASSWORD_DEFAULT);

            $result = Db::name('user')->where('mobile','eq',$param['mobile'])->setField('password',$password);

            if (!$result) {
                return \json(self::callback(0,'操作失败'));
            }

            Session::clear();

            return json(self::callback(1,''));


        } catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
   


    /**
     * 修改密码
     */
    public function modifyPassword(){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'modifyPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }

            $UserModel = new \app\user\model\User();
            $result = $UserModel->allowField(true)->save(['password'=>password_hash($param['new_password'],PASSWORD_DEFAULT)],['user_id'=>$userInfo['user_id']]);

            if ($result === false){
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 修改用户信息
     */
    public function modifyUserInfo(){
        try{
            $param = $this->request->post();
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $avatar = input('avatar','','addslashes,strip_tags,trim');
            $description = input('description','','addslashes,strip_tags,trim');//个性签名
            $nickname = input('nickname','','addslashes,strip_tags,trim');//昵称
            if (!$nickname && !$avatar && !$description){
                return json(self::callback(0,'参数错误'),400);
            }
       //处理更新
            $result = Db::table('user')->where('user_id', $userInfo['user_id'])->update([
                'description' => $description,
                'nickname' => $nickname,
                'avatar' => $avatar
            ]);

            if ($result === false) {
                return json(self::callback(0,'修改失败'));
            }
            $data=Db::table('user')->field('user_id,mobile,nickname,avatar,description')->where('user_id',$userInfo['user_id'])->find();
            return \json(self::callback(1, '修改成功', $data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 收藏列表
     */
    public function collectionList(){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;
        $type = input('type') ? intval(input('type')) : 0 ;   //1长租 2短租

        if (!$type){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }


        switch ($type){
            case 1:

                $id = Db::name('house_collection')->where('user_id',$userInfo['user_id'])->column('house_id');

                if (!$id){
                    break;
                }

                $where['id'] = ['in',$id];
                $where['is_delete'] = ['eq',0];

                $HouseModel = new \app\user\model\House();

                $total = $HouseModel->where($where)->count();

                $list = \app\user\model\House::getHouseList($page,$size,$where);

                if ($list){
                    $list = $list->toArray();
                    foreach ($list as $k=>$v){
                        $lines_name = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');
                        $list[$k]['lines_name'] = !empty($lines_name) ? $lines_name : '';
                        $list[$k]['tag_info'] = Db::name('house_tag')->whereIn('id',$id)->where('type',1)->field('id,tag_name')->select();
                        unset($list[$k]['tag_id']);
                        unset($list[$k]['lines_id']);
                    }

                }
                break;
            case 2:

                $id = Db::name('short_collection')->where('user_id',$userInfo['user_id'])->column('short_id');

                if (!$id){
                    break;
                }

                $where['id'] = ['in',$id];

                $model = new \app\user\model\HouseShort();

                $total = $model->where($where)->count();

                $list = \app\user\model\HouseShort::getHouseShortList($page,$size,$where);

                if ($list){
                    $list = $list->toArray();
                    foreach ($list as $k=>$v){
                        $list[$k]['tag_info'] = $this->getTagInfo($v['tag_id']);

                        $list[$k]['traffic_tag_info'] = $this->getTrafficTagInfo($v['traffic_tag_id']);
                        $list[$k]['city_name'] = Db::name('city')->where('id',$v['city_id'])->value('city_name');
                        $score = Db::name('short_comment')->where('short_id',$v['id'])->avg('hygiene_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('service_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('position_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('renovation_score');

                        $list[$k]['avg_score'] = round($score/4,1);
                        $list[$k]['total_comment'] = Db::name('short_comment')->where('short_id',$v['id'])->count();
                        unset($list[$k]['tag_id']);
                        unset($list[$k]['traffic_tag_id']);
                        unset($list[$k]['city_id']);
                    }

                }
                break;
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }


    /*
     * 获取标签信息
     * */
    protected function getTagInfo($id){
        $data = Db::name('house_tag')->whereIn('id',$id)->where('type',2)->field('id,tag_name')->select();
        return $data;
    }

    /*
     * 获取交通位置信息
     * */
    protected function getTrafficTagInfo($id){
        $data = Db::name('short_traffic_tag')->whereIn('id',$id)->field('id,name')->select();
        return $data;
    }



    /**
     * 委托列表
     */
    public function entrustList(){
        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $data = HouseEntrust::all(['type'=>1,'param_id'=>$userInfo['user_id']]);

        return \json(self::callback(1,'',$data));
    }


    /**
     * 优惠券列表
     */
    public function userCouponList(){

        try {
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $status = input('status') ? intval(input('status')) : 1 ;  //状态 1未使用 -1已过期

            switch ($status) {
                case 1:
                    $where['status'] = ['eq',1];
                    $where['expiration_time'] = ['gt',time()];
                    break;
                case -1:
                    $where['expiration_time'] = ['lt',time()];
                    break;
                default:
                    throw new \Exception('参数错误');
                    break;
            }

            $data = Db::name('coupon')->where($where)->where('user_id',$userInfo['user_id'])->select();

            foreach ($data as $k=>$v) {
                $data[$k]['create_time'] = date('Y-m-d H:i',$v['create_time']);
                $data[$k]['expiration_time'] = date('Y-m-d H:i',$v['expiration_time']);
                unset($data[$k]['use_time']);
                unset($data[$k]['status']);
            }

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 消息列表
     */
    public function msgList(){
        try{
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::view('user_msg_link')
                ->view('user_msg','title,content,type,create_time','user_msg.id = user_msg_link.msg_id','left')
                ->where('user_msg_link.user_id',$userInfo['user_id'])
                ->count();

            $list = Db::view('user_msg_link','id')
                ->view('user_msg','title,content,type,create_time','user_msg.id = user_msg_link.msg_id','left')
                ->where('user_msg_link.user_id',$userInfo['user_id'])
                ->order('user_msg.create_time','desc')
                ->page($page,$size)
                ->select();


            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 代购记录
     */
    public function dgRecord(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');
            switch ($status){
                case 1:
                    //已完成获利
                    $where['product_order.order_status'] = ['in','5,6'];
                    break;
                case 2:
                    //待完成获利
                    $where['product_order.order_status'] = ['in','3,4'];
                    break;
                default:
                    return \json(self::callback(0,'参数错误'),400);
                    break;
            }

            $total = Db::view('product_order_detail','specs_id,product_name,product_specs,huoli_money')
                ->view('product_order','order_no','product_order.id = product_order_detail.order_id','left')
                ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id')
                ->where('product_order_detail.type',2)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->where($where)->count();

            $list = Db::view('product_order_detail','specs_id,product_name,product_specs,huoli_money')
                ->view('product_order','order_no','product_order.id = product_order_detail.order_id','left')
                ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id')
                ->where('product_order_detail.type',2)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->where($where)
                ->order('product_order.create_time','desc')
                ->page($page,$size)
                ->select();

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 提现
     */
    public function tixian(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $money = input('money');
            $alipay_account = input('alipay_account') ? trim(input('alipay_account')) : '';  //支付宝账号 手机号或者邮箱

            if (!$money || !$alipay_account ) {
                return json(self::callback(0,'参数错误'), 400);
            }

            if (!$this->ALIAccountVerify($alipay_account)) {
                throw new \Exception('支付宝账号无效');
            }

            if ($userInfo['money'] < $money) {
                throw new \Exception('余额不足');
            }

            Db::startTrans();

            $date = date('Y-m-d H:i:s');
            $id = Db::name('user_tixian_record')->insertGetId([
                'order_no'=> $order_no = build_order_no('T'),
                'money'=>$money,
                'alipay_account'=>$alipay_account,
                'user_id'=>$userInfo['user_id'],
                'create_at'=>$date
            ]);

            $aliPay = new AliPay();
            $data = $aliPay->transfer($order_no,$alipay_account,$money);

            $code = $data['code'];
            Db::name('user_tixian_record')->where('id',$id)->update(['code'=>$code,'order_id'=>$data['order_id']]);

            //提现成功
            if(!empty($code) && $code == 10000){

                Db::name('user')->where('user_id',$userInfo['user_id'])->setDec('money',$money);
                Db::name('user_money_detail')->insert([
                    'user_id' => $userInfo['user_id'],
                    'order_id' => $id,
                    'note' => '提现',
                    'money' => -$money,
                    'balance' => $userInfo['money'] - $money,
                    'create_time' => time()
                ]);

            }else{
                Db::commit();
                throw new \Exception('提现失败：'.$data['sub_msg']);
            }

            Db::commit();

            return \json(self::callback(1,''));
        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 支付密码验证
     */
    public function payPwdVerify(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $pay_password = input('pay_password');  //支付密码

            if(!$pay_password){
                return json(self::callback( 0, "参数错误"), 400);
            }
            if (!password_verify($pay_password, $userInfo['pay_password'])) {
                // Pass
                return \json(self::callback(0,'密码错误'));
            }
            return json(self::callback( 1, ""));
        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 设置支付密码
     */
    public function setPayPassword(){
        try {

            $param = $this->request->post();

            if (!$param || !$param['mobile'] || !$param['code'] || !$param['pay_password']) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['pay_password'], PASSWORD_DEFAULT);

            $result = Db::name('user')->where('mobile','eq',$param['mobile'])->setField('pay_password',$password);

            if (!$result) {
                return json(self::callback(0,'操作失败'));
            }

            Session::clear();

            return json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 收支记录
     */
    public function userMoneyRecord2(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $month = input('month');

            $status = input('status');

            switch ($status){
                case 1:
                    $list = Db::name('user_money_detail')->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->join('product_order_detail p','p.id = d.order_detail_id','left')
                        ->field('d.note,d.money,d.create_time,o.order_no,p.product_name')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(d.`create_time`),'%Y%m') = $month")
                        #->where('d.note','eq','代购收入')
                        ->where('d.user_id',$userInfo['user_id'])
                        ->order('d.create_time','desc')
                        ->select();

                    break;
                case 2:
                    $list = Db::name('product_order_detail')
                        ->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->field('d.note,d.huoli_money as money,o.create_time,o.order_no,d.product_name')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(o.`create_time`),'%Y%m') = $month")
                        ->where('o.user_id',$userInfo['user_id'])
                        ->where('d.huoli_money','neq',0)
                        ->where('o.order_status','in','3,4')
                        ->select();

                    break;
                case 3:
                    $list = Db::name('user_money_detail')->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->join('product_order_detail p','p.id = d.order_detail_id','left')
                        ->field('d.note,d.money,d.create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(d.`create_time`),'%Y%m') = $month")
                        ->where('d.note','eq','提现')
                        ->where('d.user_id',$userInfo['user_id'])
                        ->order('d.create_time','desc')
                        ->select();
                    break;
                default:
                    return \json(self::callback(0,'参数错误'),400);
                    break;
            }

            #$data = Db::query($sql);

            $total_money = 0;
            foreach ($list as $k=>$v){
                $total_money += $v['money'];
                $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            }

            $data['total_money'] = $total_money;
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 收支记录
     */
    public function userMoneyRecord(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $month = input('month');

            $status = input('status'); //0全部 1收入 2支出

            switch ($status){
                case 1:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->where('money','>',0)
                        ->order('create_time','desc')
                        ->select();

                    break;
                case 2:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->where('money','<',0)
                        ->order('create_time','desc')
                        ->select();

                    break;
                default:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->order('create_time','desc')
                        ->select();

                    break;
            }


            $total_money = 0;
            foreach ($list as $k=>$v){
                $total_money += $v['money'];
                $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            }

            $data['total_money'] = $total_money;
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 增加活跃度
     */
    public function addActive(){
        try{

            $date = date('Y-m-d');

            if (Db::name('active_count')->where('active_date','eq',$date)->count()){
                Db::name('active_count')->where('active_date','eq',$date)->setInc('active_number');
            }else{
                Db::name('active_count')->insert(['active_number'=>1,'active_date'=>$date]);
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 我的卡券列表
     */
    public function cardList(){
        try{

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $status = input('status') ? intval(input('status')) : 0 ;  //状态 1未使用 2已使用 -1已过期
            $lng = input('lng');
            $lat = input('lat');

            switch ($status) {
                case 1:
                    $where['status'] = ['eq',1];
                    $where['end_time'] = ['gt',time()];
                    break;
                case 2:
                    $where['status'] = ['eq',2];
                    break;
                case -1:
                    $where['end_time'] = ['lt',time()];
                    break;
                default:
                    throw new \Exception('参数错误');
                    break;
            }

            $data = Db::name('user_card')->where('user_id',$userInfo['user_id'])->where($where)->select();

            foreach ($data as $k=>$v) {
                $data[$k]['start_time'] = date('Y-m-d H:i',$v['start_time']);
                $data[$k]['end_time'] = date('Y-m-d H:i',$v['end_time']);

                $store_list = Db::view('user_card_store','store_id')
                    ->view('store','cover,store_name,province,city,area,address,lng,lat','store.id = user_card_store.store_id','left')
                    ->where('user_card_store.card_id',$v['id'])
                    ->select();

                foreach ($store_list as $k2=>$v2){
                    $store_list[$k2]['address'] = $v2['province'].$v2['city'].$v2['area'];
                    if (!empty($lat) && !empty($lng)) {
                        $store_list[$k2]['distance'] = round(getDistance($lat,$lng,$v2['lat'],$v2['lng']),1);
                    }else{
                        $store_list[$k2]['distance'] = '' ;
                    }

                    unset($store_list[$k2]['province']);
                    unset($store_list[$k2]['city']);
                    unset($store_list[$k2]['area']);
                    #unset($store_list[$k2]['lng']);
                    #unset($store_list[$k2]['lat']);
                }

                $distance = array_column($store_list,'distance');
                array_multisort($distance,SORT_ASC,$store_list);
                $data[$k]['store_list'] = $store_list;
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 保存用户的form_id
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function saveFormid(UserValidate $UserValidate){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $res = $UserValidate->scene('keep_form_id')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            #逻辑
            $form_id = input('form_id','','addslashes,strip_tags,trim');
            $user_id = $userInfo['user_id'];

            ##记录form_id
            $res = UserLogic::keepUserFormId($user_id,$form_id);
            if($res === false)throw new Exception('form_id记录失败');

            #返回
            return \json(self::callback(1,'form_id记录成功',true));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 发送模板消息
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function sendTemplateMsg(UserValidate $UserValidate){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $res = $UserValidate->scene('send_template')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            #逻辑
            $user_id = $userInfo['user_id'];
            $type = input('type',1,'intval');

            ##获取openid
            $open_id = UserLogic::getUserOpenId($user_id);
            if(!$open_id)throw new Exception('用户不存在');

            ##获取access_token
            $access_token = Weixin::getAccessToken();
            if(!$access_token)throw new Exception('获取access_token失败');

            ##获取用户的form_id
            $form_id = UserLogic::getUserFormId($user_id);
            if(!$form_id)throw new Exception('没有可用form_id');

            ##获取模板id
            $templateInfo = UserLogic::getTemplateInfo($type);

            ##更新模板信息的状态
            Db::startTrans();
            $res = UserLogic::useFormId($form_id['id']);
            if($res === false)throw new Exception('模板信息更新失败');

            ##发送消息
            $res = CreateTemplate::sendTemplateMsg($open_id, $templateInfo, $form_id, $access_token);
            $result = json_decode($res, true);
            if($result && isset($result['errcode'])){
                $errCode = $result['errcode'];
                if($errCode > 0)throw new Exception("模板消息发送失败,错误码{$errCode}");
            }else{
                throw new Exception('模板消息发送失败');
            }

            #返回
            return \json(self::callback(1,'模板消息发送成功',true));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    public function video_callback(){
        try{

            $param = file_get_contents("php://input");
            addErrLog($param);
            if(!$param)return false;
            $param = json_decode($param,true);
            if(!$param)return false;
            if(!isset($param['EventType']))return false;
            $eventType = $param['EventType'];

            Db::startTrans();
            switch($eventType){
                case 'FileUploadComplete':  //上传成功回调
                    $media_id = $param['VideoId'];
                    $check = Logic::checkChaodaExists($media_id);
                    if($check)return false;
                    $img_url = $param['FileUrl'];
                    $type = 'video';
                    $video_type = 2;
                    $data = compact('media_id','img_url','type','video_type');
                    $res = Logic::addChaodaImg($data);
                    if(!$res)throw new Exception('操作失败');
                    break;
                case 'SnapshotComplete':  //截图成功回调
                    $media_id = $param['VideoId'];
                    $cover = explode('?',$param['CoverUrl'])[0];
                    if(!strpos($cover,'https'))$cover = str_replace('http','https',$cover);
                    $cover_status = 2;
                    $data = compact('cover','cover_status');
                    $res = Logic::updateChaodaImg($media_id,$data);
                    if($res === false)throw new Exception('操作失败');
                    UploadVideo::submitAIMediaAuditJob($media_id);
                    break;
                case 'AIMediaAuditComplete':  //视频审核回调
                    $media_id = $param['MediaId'];
                    $suggestion = json_decode($param['Data'],true)['Suggestion'];
                    if(strtolower($suggestion) != 'pass'){
                        $res = Logic::shieldChaodaVideo($media_id);
                        if($res === false)throw new Exception("操作失败");
                    }
                    break;
            }
            Db::commit();
            return 'SUCCESS';
        }catch(Exception $e){
            Db::rollback();
            Log::error($e->getMessage());
            return \json(self::callback(0,''));
        }
    }

    /**
     * 验证文字内容
     * @return Json
     */
    public function msgSecCheck(){
        try{
            $content = input('post.content','','addslashes,strip_tags,trim');
            if(!$content) throw new Exception('参数缺失');

            $check = msgSecCheckLocal($content);
            if(!empty($check)){
                $bad_words = '';
                foreach($check as $v){
                    $bad_words .= implode(',',$v).",";
                }
                $bad_words = trim($bad_words,',');
                throw new Exception("内容审核不通过,违规词[{$bad_words}]");
            }

            return \json(self::callback(1,'验证通过'));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    public function test(){
        $content = input('post.content');
        $res = msgSecCheck($content);
        var_dump($res);
    }

}