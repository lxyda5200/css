<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/24
 * Time: 10:48
 */

namespace app\wxapi_test\controller;
use app\common\controller\Base;
use app\wxapi_test\common\Logic;
use app\wxapi_test\model\ChaoDaModel;
use app\wxapi_test\model\TagFollowModel;
use app\wxapi_test\model\TagModel;
use app\wxapi_test\model\TopicFollowModel;
use app\wxapi_test\model\TopicModel;
use sourceUpload\UploadVideo;
use think\Config;
use think\Db;
use think\Exception;
use think\response\Json;
use app\wxapi_test\common\User;

class Chaoda extends Base
{

    /**
     * 小程序首页
     */
    public function chaodaList(){
        try{
            //获取参数
            $id = input('id') ? intval(input('id')) : 0 ;
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 20 ;
            if(!$id){
                //查询导航
                $category = Db::name('store_category')
                    ->field('id,category_name')
                    ->where('is_show', 1)
                    ->where('client_type', 1)
                    ->order('paixu asc')
                    ->select();
                if($category){
                    //默认取第一个导航值
                    $id=$category[0]['id'];
                    if(!$id){
                        return \json(self::callback(0,'参数错误！'));
                    }
                }else{
                    return \json(self::callback(0,'没有导航！'));
                }
                //查询banner

            }
            $category_img = Db::name('store_category_img')->field('id,img_url,type,link,product_id,store_id,chaoda_id')->where('category_id','in',[0,$id])->select();
            $web_path = Config::get('web_path');
            foreach ($category_img as $k1=>$v1){
                if ($v1['type'] == 3) {
                    $category_img[$k1]['link'] = "{$web_path}/wxapi_test/index/store_banner_p/id/{$v1['id']}.html";
                }
                if ($v1['type'] == 1) {
                    $category_img[$k1]['product_specs'] = Db::name('product_specs')->where('product_id',$v1['product_id'])->value('product_specs');
                }
            }
            $id="'".$id."%'";
            //统计
            $total=Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group')
                ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->where("chaoda.is_delete = 0 AND chaoda.category_id like $id ")
                ->where("store.store_status = 1  OR user.user_status =1 OR user.user_status =3")
                ->count();
            //查询数据
            $list = Db::view('chaoda','id,store_id,cover,description,title,category_id,fb_user_id,address,is_pt_user,is_delete,type,is_group,cover_thumb')
                ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                ->where("chaoda.is_delete = 0 AND chaoda.category_id like $id ")
                ->where("store.store_status = 1  OR user.user_status =1 OR user.user_status =3")
                ->page($page,$size)
                ->order('chaoda.id','desc')
                ->select();

            $thumb_conf = config('config_common.compress_config');
            $thumb_mark = "_{$thumb_conf['chaoda'][0]}X{$thumb_conf['chaoda'][1]}";
            foreach ($list as $k=>$v){
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
                //潮搭点赞数
                $list[$k]['dianzan_number'] = Db::name('chaoda_dianzan')->where('chaoda_id',$v['id'])->count();
                if($user_id){
                    //判断是否点赞
                    $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                }else{
                    $list[$k]['is_dianzan'] = 0;
                }
            }
            $list = arraySequence($list,'dianzan_number');
            $data['category']=$category;
            $data['banner']=$category_img;
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
     * 小程序首页
     */
    public function index(){
        try{
            //获取参数
            $category_id = input('category_id') ? intval(input('category_id')) : 0 ;//1:关注 2:推荐
            $search_type = input('search_type') ? intval(input('search_type')) : 0 ;//0:综合 1:用户 2:图片 3:视频
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            if(!empty($keywords)){
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
            }
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $userLoginStatus = Db::table('user') -> where(['user_id' => $user_id]) -> field(['login_out']) -> find();
            $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
            $size = $this->request->has('size') ? $this->request->param('size') : 20 ;
            if ($user_id && $user_id > 0){
                if ($userLoginStatus && $userLoginStatus['login_out'] == -1 && $category_id == 1){
                    $total = 0;
                    $list = [];
                }else{
                    if(!$category_id){return \json(self::callback(0,'参数错误!'));}
                    if($category_id!=1 && $category_id!=2){return \json(self::callback(0,'参数错误!'));}
                    if($search_type==1){
                        if(!empty($keywords)){$where['user.nickname|store.store_name'] = ['like',"%$keywords%"];}
                        if($category_id==1){
                            //关注--用户
                            $total = Db::view('store_follow','id,store_id,fb_user_id')
                                ->view('store','cover as store_logo,store_name','store_follow.store_id = store.id','left')
                                ->view('user','avatar,nickname','store_follow.fb_user_id = user.user_id','left')
                                ->where($where)
                                ->where('store_follow.user_id',$user_id)
                                ->count();
                            $list = Db::view('store_follow','id,store_id,fb_user_id')
                                ->view('store','cover as store_logo,store_name,description as store_description','store_follow.store_id = store.id','left')
                                ->view('user','avatar,nickname,description','store_follow.fb_user_id = user.user_id','left')
                                ->where($where)
                                ->where('store_follow.user_id',$user_id)
                                ->page($page,$size)
                                ->select();
                        }elseif($category_id==2){
                            //推荐--用户
                            $total = Db::view('user_and_store','id,store_id,user_id as fb_user_id')
                                ->view('store','cover as store_logo,store_name','user_and_store.store_id = store.id','left')
                                ->view('user','avatar,nickname','user_and_store.user_id = user.user_id','left')
                                ->where($where)
                                ->where("store.store_status = 1  OR user.user_status !=-1")
                                ->count();
                            $list = Db::view('user_and_store','id,store_id,user_id as fb_user_id')
                                ->view('store','cover as store_logo,store_name,description as store_description','user_and_store.store_id = store.id','left')
                                ->view('user','avatar,nickname,description','user_and_store.user_id = user.user_id','left')
                                ->where($where)
                                ->where("store.store_status = 1  OR user.user_status !=-1")
                                ->page($page,$size)
                                ->select();
                        }
                    }else{
                        if($search_type==2 ){
                            $where3="(chaoda.type = 'image'  OR chaoda.type = 'images')";
                        }elseif ($search_type==3){
                            $where3['chaoda.type'] = ['eq','video'];
                        }
                        if($category_id==1){
                            //关注
                            if(isset($user_id) && $user_id>0){
                                $finalStatus = 1; // 判断用户是否有关注数据  1-有 进入数据库查询 0-没有 返回空数据
                                $stores = Db::name('store_follow')->where('user_id',$user_id)->where('store_id','gt',0)->column('store_id');
                                $fb_user_ids = Db::name('store_follow')->where('user_id',$user_id)->where('fb_user_id','gt',0)->column('fb_user_id');
                                if($stores && $fb_user_ids){
                                    $stores=implode(",",$stores);
                                    $fb_user_ids=implode(",",$fb_user_ids);
                                    $where2="(chaoda.store_id in ($stores)  OR chaoda.fb_user_id in ($fb_user_ids))";
                                }elseif(!$stores && $fb_user_ids){
                                    $fb_user_ids=implode(",",$fb_user_ids);
                                    $where2['chaoda.fb_user_id'] = ['in',$fb_user_ids];
                                }elseif($stores && !$fb_user_ids){
                                    $stores=implode(",",$stores);
                                    $where2['chaoda.store_id'] = ['in',$stores];
                                }
                                // TODO START zd 条件为 关注下的 综合、图片、视频
                                if (in_array($search_type, [0,2,3])){
                                    // 查询关注的话题
                                    $temp = TopicFollowModel::where(['user_id' => $user_id]) -> field('topic_id') -> select();
                                    $topicIds = implode(',', array_column($temp,'topic_id'));
                                    // 查询关注的标签
                                    $TagData = TagFollowModel::where(['user_id' => $user_id])-> field(['tag_id']) -> select();
                                    $followIds = array_column($TagData, 'tag_id');
                                    $temp = ''; // 组装正则查询条件
                                    foreach ($followIds as $k => $v){
                                        $temp .= "[{$v}]*,*";
                                    }
                                    $exp = 'REGEXP \''."(".rtrim($temp, ",*").")+".'\'';
                                    $where4 = "(chaoda.topic_id in ($topicIds) OR chaoda.tag_ids $exp)";
                                    if (empty($followIds) && $topicIds != ''){
                                        $where4 = "(chaoda.topic_id in ($topicIds)";
                                    }elseif ($topicIds == '' && !empty($followIds)){
                                        $where4 = "(chaoda.tag_ids $exp)";
                                    }elseif(empty($followIds) && $topicIds == ''){
                                        $where4 = [];
                                    }
                                    // 组装查询where语句
                                }
                                if (empty($temp) && empty($TagData) && empty($stores) && empty($fb_user_ids)) $finalStatus = 0;
                                if ($finalStatus == 1){

                                    // TODO END zd 添加查询条件
                                    if(!empty($keywords)){$where['user.nickname|chaoda.description|chaoda.title|topic.title|store.store_name'] = ['like',"%$keywords%"];}
                                    $total = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number')
                                        ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                                        ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                                        ->view('topic','title','chaoda.topic_id = topic.id','left')
                                        ->where('chaoda.is_delete',0)
                                        ->where('chaoda.status',2)
                                        ->where("store.store_status = 1  OR user.user_status !=-1")
                                        ->where($where2)
                                        ->where($where3)
                                        ->where($where)
                                        ->whereOr($where4)
                                        ->group('chaoda.id')
                                        ->count();

                                    // TODO zd 添加查询条件及添加得分计算字段
                                    $list = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number,title,create_time,collect_number,comment_number')
                                        ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                                        ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                                        ->view('topic','title as topic_title','chaoda.topic_id = topic.id','left')
                                        ->where('chaoda.is_delete',0)
                                        ->where('chaoda.status',2)
                                        ->where("store.store_status = 1  OR user.user_status !=-1")
                                        ->where($where2)
                                        ->where($where3)
                                        ->where($where)
                                        ->whereOr($where4)
                                        ->group('chaoda.id')
                                        ->page($page,$size)
                                        ->order('chaoda.id','desc')
                                        ->select();
                                }else{
                                    $total=0;
                                    $list=[];
                                }

                            }else{
                                $total=0;
                                $list=[];
                            }
                        }elseif($category_id==2){
                            // TODO  zd  查找未查看话题数据 一条
                            $notReadtopic = TopicModel::getTopicOneDataBySort($page,$user_id);

                            //推荐
                            if(!empty($keywords)){$where['user.nickname|chaoda.description|chaoda.title|topic.title|store.store_name'] = ['like',"%$keywords%"];}
                            $total = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,cover_thumb,dianzan_number')
                                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                                ->view('topic','title','chaoda.topic_id = topic.id','left')
                                ->where('chaoda.is_delete',0)
                                ->where('chaoda.status',2)
                                ->where("store.store_status = 1  OR user.user_status !=-1")
                                ->where($where3)
                                ->where($where)
                                ->group('chaoda.id')
                                ->count();
                            $list = Db::view('chaoda','id,store_id,cover,description,fb_user_id,address,type,title,cover_thumb,dianzan_number')
                                ->view('store','cover as store_logo,store_name','chaoda.store_id = store.id','left')
                                ->view('user','avatar,nickname','chaoda.fb_user_id = user.user_id','left')
                                ->view('topic','title as topic_title','chaoda.topic_id = topic.id','left')
                                ->where('chaoda.is_delete',0)
                                ->where('chaoda.status',2)
                                ->where("store.store_status = 1  OR user.user_status !=-1")
                                ->where($where3)
                                ->where($where)
                                ->group('chaoda.id')
                                ->page($page,$size)
                                ->order('chaoda.id','desc')
                                ->select();
                        }
                        //判断是否点赞
                        $sort_result = [];
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

                            // TODO START zd  计算得分开始 及 删除不必要字段
                            $hours = (time() - $v['create_time'])/3600;
                            $score = $v['dianzan_number']*0.5+$v['comment_num']*2+(($v['collect_num']*5)/sqrt($hours+2));
                            // $list[$k]['score'] = $score;
                            unset($v['comment_num']);
                            unset($v['collect_num']);
                            // 计算得分
                            $sort_result[] = $score;

                            // TODO END zd   计算得分结束
                            if(isset($user_id) && $user_id>0){
                                //判断是否点赞
                                $list[$k]['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v['id'])->count();
                            }else{
                                $list[$k]['is_dianzan'] = 0;
                            }
                        }
                        // TODO zd  按照计算得分排序
                        array_multisort($sort_result, SORT_DESC, $list);
                    }

                    $thumb_conf = config('config_common.compress_config');
                    $thumb_mark = "_{$thumb_conf['chaoda'][0]}X{$thumb_conf['chaoda'][1]}";
                    foreach($list as $k => $v){
//                        if(!$v['cover_thumb']){  //不是视频封面且没有生成缩略图
//                            ##生成缩略图
//                            $path = createThumb($v['cover'],'uploads/product/thumb/','chaoda');
//                            if(file_exists(trim($path,'/'))){ //修改cover_thumb字段
//                                Db::name('chaoda')->where(['id'=>$v['id']])->setField('cover_thumb',$path);
//                                $list[$k]['cover'] = $path;
//                            }
//                        }else{
//                            $list[$k]['cover'] = $v['cover_thumb'];
//                        }
                        if(!$v['cover_thumb'] || !strstr($v['cover_thumb'], $thumb_mark)){  //不是视频封面且没有生成缩略图
                            ##生成缩略图
                            $path = createThumb($v['cover'],'uploads/product/thumb/','chaoda');
                            if(file_exists(trim($path,'/'))){ //修改cover_thumb字段
                                Db::name('chaoda')->where(['id'=>$v['id']])->setField('cover_thumb',$path);
                                $list[$k]['cover'] = $path;
                            }
                        }else{
                            $list[$k]['cover'] = $v['cover_thumb'];
                        }
                    }
                }
            }else{
                $total = 0;
                $list = [];
            }
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$list;
            // 当传递的页数大于数据总页数 则将数据置空
            if ($page>$data['max_page']){
                $data['not_read_topic'] = [];
            }else{
                // 查询到数据
                if (!is_null($notReadtopic)){
                    $data['not_read_topic'] = $notReadtopic;
                // 未查询到数据 则置空
                }else{
                    $data['not_read_topic'] = [];
                }
            }

            return json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
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
                ->where('chaoda.status',2)
                ->where($where)
                ->order('chaoda.create_time','desc')
                ->find();

            $chaoda_img = Db::name('chaoda_img')->where('chaoda_id',$list['id'])->where('can_use',1)->column('img_url');

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

                if (!$res){
                    return \json(self::callback(0,'操作失败'));
                }
                $data= Db::name('chaoda')->field('id,share_number')->where('id',$chaoda_id)->find();
            }else{
                return \json(self::callback(0,'操作失败，没有该潮搭'));
            }
        return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /*
       * 小程序收藏的潮搭列表
       * */
    public function chaodaCollectionList(){
        try{
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $user_id = $userInfo['user_id'];
            $total = Db:: view('chaoda_collection','chaoda_id,store_id,user_id,fb_user_id')
                ->view('chaoda','id,cover,description,title,category_id,address','chaoda_collection.chaoda_id = chaoda.id','left')
                ->view('store','cover as store_logo,store_name,address,store_status','chaoda_collection.store_id = store.id','left')
                ->view('user','avatar,nickname,user_status','chaoda_collection.fb_user_id = user.user_id','left')
                ->where('chaoda_collection.user_id',$user_id)
                ->where('chaoda.is_delete',0)
                ->where("store.store_status = 1  OR user.user_status =1 OR user.user_status =3")
                ->count();
            //统计
            if($total==0){
                $data['total'] = 0;
                $data['max_page'] = 0;
                $data['list'] = [];
                return \json(self::callback(1,'还没有收藏任何商品哦',$data));
            }else{
                //查询到有收藏
                $list = Db:: view('chaoda_collection','chaoda_id,user_id')
                    ->view('chaoda','id,store_id,cover,description,title,type,category_id,fb_user_id,address,is_pt_user','chaoda.id = chaoda_collection.chaoda_id','left')
                    ->view('store','cover as store_logo,store_name,address','chaoda.store_id = store.id','left')
                    ->view('user','avatar,nickname,user_status','chaoda.fb_user_id = user.user_id','left')
                    ->where('chaoda_collection.user_id',$user_id)
                    ->where('chaoda.is_delete',0)
                    ->where("store.store_status = 1  OR user.user_status =1 OR user.user_status =3")
                    ->page($page,$size)
                    ->order('chaoda.id','desc')
                    ->select();


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
     * 收藏的潮搭列表
     * */
    public function collectionChaodaList(){
        try{

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $userInfo = \app\wxapi_test\common\User::checkToken();
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

                $chaoda_img = Db::name('chaoda_img')->where('chaoda_id',$v['id'])->where('can_use',1)->column('img_url');

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
            $userInfo = \app\wxapi_test\common\User::checkToken();
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
                Db::name('chaoda')->where('id',$chaoda_id)->setInc('comment_number');
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
            $userInfo = \app\wxapi_test\common\User::checkToken();
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
            $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
            return \json(self::callback(1,'点赞成功',$data));

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
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;

            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            Db::name('chaoda_dianzan')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
            $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
            return \json(self::callback(1,'取消点赞成功',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
 * 潮搭点赞和取消点赞
 * */
    public function chaoda_Dianzan(){
        try{
            //token 验证
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $chaoda_id=$param['chaoda_id'];
            $status=$param['status'];
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
                        return \json(self::callback(0,'点赞失败',$data,true));
                    }else{
                        Db::name('chaoda')->where('id',$chaoda_id)->setInc('dianzan_number',1);
                        //写入成功
                        $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                        return \json(self::callback(1,'点赞成功',$data,true));
                    }
                }else{
                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    return \json(self::callback(0,'已点赞',$data,true));
                }
            }else if($status=='false'){
                $de=Db::name('chaoda_dianzan')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
                if($de==0){
                    //删除失败
                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    return \json(self::callback(0,'已取消',$data,true));
                }else{
                    //删除成功
                    Db::name('chaoda')->where('id',$chaoda_id)->setDec('dianzan_number',1);
                    $data=Db::name('chaoda_dianzan')->where('chaoda_id',$chaoda_id)->count();
                    return \json(self::callback(1,'取消点赞成功',$data,true));
                }
            }else{
                return \json(self::callback(0,'操作错误'));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }



    /*
     * 潮搭收藏和取消收藏
     * */
    public function chaoda_collection(){
        try{
            //token 验证
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
        $chaoda_id=$param['chaoda_id'];
        $status=$param['status'];

        if(!$chaoda_id || !$status){
            return \json(self::callback(0,'参数错误'),400);
        }
        if($status=='true'){

            $chaoda= Db::name('chaoda')->field('id,store_id,fb_user_id')->where('id',$chaoda_id)->where('is_delete',0)->find();
        if(!$chaoda){
            return \json(self::callback(0,'没有找到该潮搭或已下架'),400);
        }
   $guan= Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();

    if ($guan==0){
        //写入收藏
        if($chaoda['store_id']>0){
            Db::name('chaoda_collection')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'store_id' => $chaoda['store_id'],
                'create_time' => time()
            ]);
        }elseif($chaoda['fb_user_id']>0){

            Db::name('chaoda_collection')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'fb_user_id' => $chaoda['fb_user_id'],
                'create_time' => time()
            ]);
        }else{
            Db::name('chaoda_collection')->insert([
                'chaoda_id' => $chaoda_id,
                'user_id' => $userInfo['user_id'],
                'create_time' => time()
            ]);
        }
        //统计最新收藏
       $xie= Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->where('user_id',$userInfo['user_id'])->count();
        if ($xie==0){
            //写入失败
            $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
            return \json(self::callback(0,'收藏失败',$data,true));
        }else{
           Db::name('chaoda')->where('id',$chaoda_id)->setInc('collect_number');
            //写入成功
            $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
            return \json(self::callback(1,'收藏成功',$data,true));
        }
    }else{
        $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
        return \json(self::callback(1,'已收藏',$data,true));
    }

}else if($status=='false'){

    $de=Db::name('chaoda_collection')->where('user_id',$userInfo['user_id'])->where('chaoda_id',$chaoda_id)->delete();
    if($de==0){
        //删除失败
        $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
        return \json(self::callback(0,'取消收藏失败',$data,true));

    }else{
        Db::name('chaoda')->where('id',$chaoda_id)->setDec('collect_number');
        //删除成功
            $data=Db::name('chaoda_collection')->where('chaoda_id',$chaoda_id)->count();
            return \json(self::callback(1,'取消收藏成功',$data,true));
        }

}else{
    return \json(self::callback(0,'操作错误'));
}
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
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $chaoda_id = input('chaoda_id') ? intval(input('chaoda_id')) : 0 ;
            if (!$chaoda_id){
                return \json(self::callback(0,'参数错误'),400);
            }


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
* 关注用户/店铺和取消用户/店铺关注
* */
    public function guanzhu_and_cancel(){
        try{
            //token 验证
            $userInfo = \app\wxapi_test\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $fb_user_id=$param['fb_user_id'];
            $store_id=$param['store_id'];
            $status=$param['status'];
            if(!$fb_user_id && !$store_id){
                return \json(self::callback(0,'参数错误'),400);
            }
            if(!$status){
                return \json(self::callback(0,'状态不能为空'),400);
            }
            if($fb_user_id==''&&$store_id!='') {
                //关注店铺
                if($status=='true'){
                    $guan= Db::name('store_follow')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($guan==0){
                        //写入关注
                        Db::name('store_follow')->insert([
                            'user_id' => $userInfo['user_id'],
                            'store_id' => $store_id,
                            'type' => 1,
                            'create_time' => time()
                        ]);
                        //统计最新关注数
                        $xie= Db::name('store_follow')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                        if ($xie==0){
                            //写入失败
                            $data=Db::name('store_follow')->where('store_id',$store_id)->count();
                            return \json(self::callback(0,'关注店铺失败',$data,true));
                        }else{
                            //写入成功
                            $data=Db::name('store_follow')->where('store_id',$store_id)->count();
                            return \json(self::callback(1,'关注店铺成功',$data,true));
                        }
                    }else{
                        $data=Db::name('store_follow')->where('store_id',$store_id)->count();
                        return \json(self::callback(0,'已关注店铺',$data,true));
                    }
                }else if($status=='false'){
                    $de=Db::name('store_follow')->where('user_id',$userInfo['user_id'])->where('store_id',$store_id)->delete();
                    if($de==0){
                        //删除失败
                        $data=Db::name('store_follow')->where('store_id',$store_id)->count();
                        return \json(self::callback(0,'取消店铺关注失败',$data,true));
                    }else{
                        //删除成功
                        $data=Db::name('store_follow')->where('store_id',$store_id)->count();
                        return \json(self::callback(1,'取消店铺关注成功',$data,true));
                    }
                }else{
                    return \json(self::callback(0,'操作错误'));
                }
            }else if(!$fb_user_id==''&& $store_id=='') {
                //关注个人
                if($status=='true'){
                    if($fb_user_id==$userInfo['user_id']){
                        return \json(self::callback(0,'不能关注自己哦',false));
                    }
                    $guan= Db::name('store_follow')->where('fb_user_id',$fb_user_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($guan==0){
                        //写入关注
                        Db::name('store_follow')->insert([
                            'user_id' => $userInfo['user_id'],
                            'fb_user_id' => $fb_user_id,
                            'type' => 2,
                            'create_time' => time()
                        ]);
                        //统计最新关注数
                        $xie= Db::name('store_follow')->where('fb_user_id',$fb_user_id)->where('user_id',$userInfo['user_id'])->count();
                        if ($xie==0){
                            //写入失败
                            $data=Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();
                            return \json(self::callback(0,'关注该用户失败',$data,true));
                        }else{
                            //写入成功
                            $data=Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();
                            return \json(self::callback(1,'关注该用户成功',$data,true));
                        }
                    }else{
                        $data=Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();

                        return \json(self::callback(0,'已关注该用户',$data,true));
                    }
                }else if($status=='false'){
                    $de=Db::name('store_follow')->where('user_id',$userInfo['user_id'])->where('fb_user_id',$fb_user_id)->delete();
                    if($de==0){
                        //删除失败
                        $data=Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();
                        return \json(self::callback(0,'取消该用户关注失败',$data,true));
                    }else{
                        //删除成功
                        $data=Db::name('store_follow')->where('fb_user_id',$fb_user_id)->count();
                        return \json(self::callback(1,'取消该用户关注成功',$data,true));
                    }
                }else{
                    return \json(self::callback(0,'操作错误'));
                }
            }else{
                return \json(self::callback(0,'未知错误'));
            }
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

    /**
     * 删除视频
     * @return Json
     */
    public function deleteMezzanines(){
        try{
            $video_id = input('video_id','','addslashes,strip_tags,trim');
            if(!$video_id)throw new Exception('参数缺失');
            ##判断视频是否与潮搭绑定
            $check = Logic::getChaodaImgInfoByMediaId($video_id);
            if($check)throw new Exception('该视频不能删除');
            UploadVideo::deleteMezzanines($video_id);
            return \json(self::callback(1,'已提交删除请求',true));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     *  动态点赞 取消点赞
     * @return Json
     */
    public function userSupportPost(){
        try{
            $params = $this -> request -> only(['chaoda_id', 'user_id', 'kind','token']);

            //token 验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            // 模型数据处理
            $result = ChaoDaModel::userSupportPost($params);

            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);

            if ($result === false) return json(self::callback(0, '失败'), 400);

            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  推荐页面获取标签数据并排序取前20条
     * @return Json
     */
    public function getRecommendListTagData(){
        try{
            $list = TagModel::where(['status' => 1]) -> field(['id','title','create_time','use_number','follow_number']) -> select();

            foreach ($list as $k => $v){
                // 动态分数得分
                $hours = (time() - $v['create_time'])/3600;
                $score = $v['use_number']*1+$v['follow_number']*2/sqrt($hours+2);
                // 话题得分
                $sort_result[] = $score;
                unset($v['create_time']);
                unset($v['use_number']);
                unset($v['follow_number']);
            }
            array_multisort($sort_result, SORT_DESC, $list);

            return \json(self::callback(1, '成功', [array_slice($list, 0, 19)]));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
}
