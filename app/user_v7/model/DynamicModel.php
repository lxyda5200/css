<?php


namespace app\user_v7\model;
use think\Config;
use think\Model;
use think\db\Query;
use think\Validate;
use think\Db;
use app\common\controller\Base;

class DynamicModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic';
    protected $dateFormat=false;
    /**统计关注店铺id
     *store_follow
     */
    static public function GetStoreFollowIds($user_id)
    {
        return Db::name('store_follow')
            ->where('store_id','GT',0)
            ->where('type','eq',1)
            ->where('user_id','eq',$user_id)
            ->group('store_id')
            ->column('store_id');
    }
    /**获取分类
     *title
     */
    static public function GetShopCategory()
    {
        return Db::name('shop_category')
            ->field('id,title')
            ->where('status',1)
            ->order('sort desc')
            ->select();
    }
    /**
     *增加访问记录
     */
    static public function setIncRecord($id)
    {
      $rst= Db::name('dynamic') ->where('id',$id)->setInc('visit_number',1);
      if($rst){
          return true;
      }else{
          return false;
      }
    }
    /**
     *获取日人气单id
     */
    static public function getPopularProductsId($id)
    {
        $rst= Db::name('popular_products') ->where('status', 1)->where('delete_time is null' )->where('id',$id)->find();
        if($rst){
            return $rst;
        }else{
            return false;
        }
    }
    /**
     *获取人气单品排序
     */
    static public function getPopularProductsSort($id,$derect)
    {
        if($derect==3){
            //默认
            $rst=Db::table('popular_products')->where('status', 1)->where('delete_time is null' )->min('sort');
        }else{
            $sort= Db::name('popular_products') ->where('id',$id)->value('sort');
            if ($derect==1){
                $where['sort'] = ['<', $sort];
                $order = 'sort DESC';
            }elseif ($derect==2){
                $where['sort'] = ['>', $sort];
                $order = 'sort ASC';
            }
            $rst= Db::name('popular_products') ->where('status', 1)->where('delete_time is null' )->where($where)->order($order)->value('sort');
        }
        if($rst){
            return $rst;
        }else{
            return false;
        }
    }

    /**
     *写入访问记录
     */
    static public function addIncRecord($id,$user_id)
    {
        $data=[
        'user_id'=>$user_id,
        'dynamic_id'=>$id,
        'create_time'=>time()
        ];
    $rst=Db::name('dynamic_visit_record') ->insert($data);
    if($rst){
    return true;
    }else{
        return false;
    }
}
    /**
     *写入访问记录次数
     */
    static public function addIncRecordTimes($id,$user_id)
    {
        $record=Db::name('dynamic_user_record') ->where('user_id',$user_id)->where('dynamic_id',$id)->find();
        if($record){
            $rst= Db::name('dynamic_user_record') ->where('user_id',$user_id)->where('dynamic_id',$id)->setInc('visit_number',1);
        }else{
    $data=[
        'user_id'=>$user_id,
        'dynamic_id'=>$id,
        'visit_number'=>1,
        'create_time'=>time()
    ];
    $rst=Db::name('dynamic_user_record') ->insert($data);

}
        if($rst){
            return true;
        }else{
            return false;
        }
    }



    /**获取店铺主营分类
     *title
     */
    static public function GetStoreCategory()
    {
        return Db::name('cate_store')
            ->field('id,title')
            ->where('delete_time is null')
            ->select();
    }
    /**获取店铺主营风格
     *title
     */
    static public function GetStoreStyle()
    {
        return Db::name('style_store')
            ->field('id,title')
            ->where('delete_time is null')
            ->select();
    }
    /**获取商圈
     *title
     */
    static public function GetBusinessCircle()
    {
        return Db::name('business_circle')
            ->field('id,circle_name as title')
            ->where('status',1)
            ->order('sort desc')
            ->select();
    }
    /**统计关注店铺数
     *title
     */
    static public function GetStoreFollow($user_id)
    {
        return Db::name('store_follow')->alias('sf')
            ->join('store s','sf.store_id = s.id','LEFT')
            ->where('sf.store_id','GT',0)
            ->where('sf.type','eq',1)
            ->where('s.store_status','eq',1)
            ->where('sf.user_id','eq',$user_id)
            ->count();
    }

    /**获取店铺banner广告位
     *title
     */
    static public function GetStoreBanner($store_id)
    {
        $list = Db::name('store_banner')
            ->field('id,title,store_id,cover,type,content,link,product_id,chaoda_id')
            ->where('store_id',$store_id)
            ->where('status',1)
            ->select();
        $url = config('config_common.h5_url')['store_banner'];
        foreach($list as &$v){
            if($v['type'] == 3){
                $v['link'] = sprintf($url, $v['id']) . "&" . rand(100000,999999);
            }
        }
        return $list;
    }

    /**获取阅读头像
     *title
     */
    static public function GetUserLookList($id)
    {
        $list =  Db::name('dynamic_user_record')->alias('dur')
            ->join('user u','dur.user_id = u.user_id','LEFT')
            ->where('dur.dynamic_id','EQ',$id)
            ->field('u.user_id,u.avatar')
            ->group('dur.user_id')
            ->limit(10)
            ->order('dur.create_time desc')
            ->select();
        foreach($list as $k=> $v){
            if(!$v['avatar'] || strstr($v['avatar'],'default'))unset($list[$k]);
        }
        $total = count($list);
        if($total<10){
            $list2 = getDefaultHeadPic(10 - $total);
            $list = array_merge($list, $list2);
        }
        $web_path = Config::get('web_path');
        foreach($list as &$v){
            if(!strstr($v['avatar'],'http')){
                $v['avatar'] = $web_path . $v['avatar'];
            }
        }
        $list = array_values($list);
        return $list;
    }


    /**获取生活剪影阅读头像
     *title
     */
    static public function GetLifeUserLookList($id)
    {
        $list =  Db::name('dynamic_group_look')->alias('dur')
            ->join('user u','dur.user_id = u.user_id','LEFT')
            ->where('dur.dynamic_group_id','EQ',$id)
            ->field('u.user_id,u.avatar')
            ->group('dur.user_id')
            ->limit(10)
            ->order('dur.create_time desc')
            ->select();
        foreach($list as $k=> $v){
            if(!$v['avatar'] || strstr($v['avatar'],'default'))unset($list[$k]);
        }
        $total = count($list);
        if($total<10){
            $list2 = getDefaultHeadPic(10 - $total);
            $list = array_merge($list, $list2);
        }
        $web_path = Config::get('web_path');
        foreach($list as &$v){
            if(!strstr($v['avatar'],'http')){
                $v['avatar'] = $web_path . $v['avatar'];
            }
        }
        $list = array_values($list);
        return $list;
    }

    /**获取动态图片数组
     *title
     */
    static public function GetDynamicImagesList($id)
    {
         return Db::name('dynamic_img')
        ->where('dynamic_id','EQ',$id)
        ->where('can_use','EQ',1)
        ->field('img_url,type,cover')
        ->order('id asc')
        ->select();
    }

    /**获取动态图片数量
     *s
     */
    static public function GetDynamicImagesNums($id)
    {
        return Db::name('dynamic_img')
            ->where('dynamic_id','EQ',$id)
            ->where('can_use','EQ',1)
            ->count();
    }

    /**获取动态视频列表
     *title
     */
    static public function GetDynamicVideoList($id)
    {
        return Db::name('dynamic_img')
            ->where('dynamic_id','EQ',$id)
            ->where('can_use','EQ',1)
            ->where('type','EQ',2)
            ->field('img_url,type,cover')
            ->limit(1)
            ->order('id asc')
            ->select();
    }
    /**是否有该评论
     *title
     */
    static public function GetDynamicCommentId($id)
    {
        return Db::name('dynamic_comment')
            ->field('id,dynamic_id')
            ->where('id',$id)
            ->find();
    }

    /**获取动态列表
     *title
     */
    static public function GetDynamicList($user_id,$where,$lat,$lng,$limit)
    {

        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where($where)
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','d.scene_id','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',$distance,
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->limit($limit)
            ->select();
        foreach($list as &$v){
            if($v['type == 2'] && strstr($v['cover'],"http") === false){
                $v['cover'] = "http://" . $_SERVER['HTTP_HOST'] . $v['cover'];
            }
        }
        return $list;
    }


    /**
     *获取宿友推荐
     */
    static public function GetRoommateRecommend($where)
    {
        return Db::name('roommate_recommend')
            ->where('status','EQ',1)
            ->where('delete_time is null')
            ->where($where)
            ->value('id');
    }

    /**获取品牌故事和时尚动态
     *title
     */
    static public function GetBrandStore($store_id)
    {
        //品牌故事
        $is_brand_story=[];
        $brand_story= Db::name('brand_store')->alias('bs')
            ->join('brand_story b','bs.brand_id = b.brand_id','LEFT')
            ->where('bs.store_id','EQ',$store_id)
            ->where('bs.is_selected','EQ',1)
            ->where('bs.is_show_story','EQ',1)
            ->field('bs.is_show_story as is_show,b.id')->find();
        if($brand_story){
            $config = config('config_common.h5_url');
            $brand_story['title']='品牌故事';
            $brand_story['brand_type']=1;
            $brand_story['type']=3;
            $brand_story['url']=sprintf($config['brand_story'], (int)$brand_story['id']);
            $is_brand_story[]=$brand_story;
        }
        $brand_dynamic=Db::name('brand_store')->alias('bs')
            ->join('brand_dynamic b','bs.brand_id = b.brand_id','LEFT')
            ->where('bs.store_id','EQ',$store_id)
            ->where('bs.is_selected','EQ',1)
            ->where('bs.is_show_dynamic','EQ',1)
            ->field('bs.is_show_dynamic as is_show,b.id')->find();
        if($brand_dynamic){
            $brand_dynamic['title']='时尚动态';
            $brand_dynamic['brand_type']=2;
            $is_brand_story[]=$brand_dynamic;
        }
        if($is_brand_story ){
            return $is_brand_story;
        }else{
            return [];
        }
    }

    /**获取关注店铺动态
     *title
     */
    static public function GetStoreFollowDynamic($user_id,$page,$size,$lng,$lat)
    {
        $store_ids = self::GetStoreFollowIds($user_id);
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'left')  //  关联话题表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表

            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.store_id','in',$store_ids)
            -> field([
                'd.id','d.store_id','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.topic_id','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                't.title as topic_title',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])->group('d.id')->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'left')  //  关联话题表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->join('store_follow sf', 'd.store_id = sf.store_id and sf.user_id = '.$user_id, 'left')  //  关联店铺收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.store_id','in',$store_ids)
            -> field([
                'd.id','d.store_id','d.cover','d.title','d.scene_id','(d.visit_number + d.look_number) as look_number','d.type','d.topic_id','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                $distance,
                't.title as topic_title',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])
            ->group('d.id')
            ->page($page,$size)
            ->order('d.create_time desc')
            ->select();
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] = self::GetUserLookList($v['id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['id']);
            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['id']);
            }
        }
        $data['type']=1;
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }

    /**获取推荐店铺
     *title
     */
    static public function GetRecommendStoreFollow($user_id,$page,$size)
    {
        $total = Db::name('store_follow ')->alias('sf')
            ->join('store s','s.id = sf.store_id','LEFT')
            ->where('sf.store_id','GT',0)
            ->where('sf.type','EQ',1)
            ->where('sf.store_id <> 76 AND sf.store_id <> 217')
            ->group('sf.store_id')
            ->field('sf.store_id,s.store_name,s.cover,s.signature,count(sf.store_id) as follow_number')
            ->count();
        $list = Db::name('store_follow ')->alias('sf')
            ->join('store s','s.id = sf.store_id','LEFT')
            ->join('store_follow uf', 's.id = uf.store_id and uf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            ->where('sf.store_id','GT',0)
            ->where('sf.type','EQ',1)
            ->where('sf.store_id <> 76 AND sf.store_id <> 217')
            ->group('sf.store_id')
            ->field(['sf.store_id','s.store_name','s.cover','s.signature',
                'IF(uf.create_time > 0 ,1 ,0) is_follow',
                'count(sf.store_id) as follow_number'])
            ->page($page,$size)
            ->order('follow_number desc')
            ->select();
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }
    /**获取热门动态
     *title
     */
    static public function GetHotDynamic($user_id,$page,$size)
    {
        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->field('d.id,d.store_id,st.store_name,st.cover as store_logo,st.address,st.signature,st.lng,st.lat,d.cover,d.title,(d.visit_number + d.look_number) as look_number,d.share_number,d.like_number,d.collect_number,d.comment_number')
            ->group('d.id')
            ->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('store_follow sf', 'd.store_id = sf.store_id and sf.user_id = '.$user_id, 'left')  //  关联店铺收藏表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            -> field([
                'd.id','d.store_id','d.cover','d.title','d.scene_id','(d.visit_number + d.look_number) as look_number','d.topic_id','d.share_number','d.type','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                't.title as topic_title',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])
            ->group('d.id')
            ->page($page,$size)
            ->order('d.create_time desc')
            ->select();
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['id']);
            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['id']);
            }
        }

        $data['type']=2;
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }
    /**获取推荐动态数据
     *title
     */
    static public function GetRecommendDynamic($user_id,$page,$size,$lat,$lng,$category,$sort)
    {

                //排序
        switch ($sort){
            case 1:
                //关注度最高
                $order = ['d.is_recommend'=>'DESC','d.recom_sort'=>'ASC','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 2:
                //浏览量最高
                $order = ['look_number'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 3:
                //推荐值最高
                $order = ['d.share_number'=>'desc','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 4:
                //距离最近
                $order = ['distance'=>'ASC','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            default:
                //默认推荐+权重
                $order = ['is_recommend'=>'DESC','recom_sort'=>'ASC','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
        }
        $where='';
        if($category){
            $category=$category[0];
            $category= explode("],", $category);
            $category=str_replace( "&amp;","",str_replace( "quot;","",str_replace( "&quot;","",str_replace( "[","",str_replace( "]","",str_replace( "{","",str_replace( "}","",str_replace("\"","",$category))))))));
            $new_category=[];
            foreach ($category as $k=>$v){
                $arr= explode(":", $v);
                foreach ($arr as $k1=>$v1){
                    $new_category[]=$v1;
                }
            }
            $lenth=count($new_category);
                if($lenth==2){
                    $arr=[
                        $new_category[0]=>$new_category[1]
                    ];
                }elseif($lenth==4){
                    $arr=[
                        $new_category[0]=>$new_category[1],
                        $new_category[2]=>$new_category[3]
                    ];
                }
            foreach ($arr as $k2=>$v2){
                $where.='ds.type='.$k2 .' AND ds.style_id IN ('.$v2.') OR ';
            }
            $where=rtrim($where,"OR ");
        }

        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where($where)
            ->field('d.store_id,st.store_name,t.id as topic_id,t.title as topic_title,st.cover as store_logo,st.address,st.signature,st.lng,st.lat,d.cover,d.title,(d.visit_number + d.look_number) as look_number,d.share_number,d.like_number,d.collect_number,d.comment_number')
            ->group('d.id')
            ->count();
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where($where)
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.scene_id','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',$distance,
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                'IF( d.is_recommend=1 AND  ( d.recom_start_time>0 AND d.recom_start_time>'.time() .')'.'OR (  d.recom_end_time>0 AND d.recom_end_time <'.time().')'.'  ,0 ,1) is_recommend',
                'IF(d.recom_sort=0,99999,d.recom_sort) recom_sort',
            ])
            ->group('d.id')

            ->page($page,$size)
//            ->order('field(recom_sort,recom_sort,99999) ASC')
//            ->order('field(is_recommend,1) DESC')
            ->order($order)
            ->page($page,$size)
            ->select();
//echo Db::name()->getLastSql();
        //查询top50
        $ids = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            -> field([
                'd.id','(d.visit_number + d.look_number) as new_look_number'
            ])
            ->group('d.id')
            ->order('new_look_number DESC')
            ->limit(50)
            ->select();
            $dynamic_ids=[];
            foreach ($ids as $k1=>$v1){$dynamic_ids[]=$v1['id'];}
        foreach ($list as &$v){
            //判断热门
            $v['is_hot']=0;
            if(in_array($v['id'],$dynamic_ids)){$v['is_hot']=1;
            }else{
                //优质
                if($v['look_number']>0){
                    $quality=$v['like_number']/$v['look_number'];
                    if($quality>0.5){
                        $v['is_hot']=2;
                    }
                }
            }

            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['id']);

            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['id']);
            }

            //判断是否有开启品牌故事和时尚动态
            $v['brand_story'] =self::GetBrandStore($v['store_id']);

        }
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }
    /**推荐页筛选
     *title
     */
    static public function GetSelectData($type)
    {
        //1:探店推荐 2:探店附近
        $list[0]['title']='风格分类';
        $list[0]['data'] = Db::name('dynamic_style')->alias('ds')
            ->join('dynamic d','ds.dynamic_id = d.id','LEFT')
            ->join('style_store ss','ds.style_id = ss.id And ds.type=1','LEFT')
            ->join('style_product sp','ds.style_id = sp.id And ds.type=2','LEFT')
            ->where('d.status','EQ',1)
            ->group('ds.style_id,ds.type')
            ->field('ds.style_id,ds.type,count(ds.style_id) as use_number,sp.title as product_style,ss.title as store_style')
            ->order('use_number DESC')
            ->limit(9)
            ->select();
        if($type==1){
            $list[1]['title']='排序';
            $list[1]['data'] =array(
                array('style_id'=>'1','product_style'=>'关注度最高'),
                array('style_id'=>'2','product_style'=>'浏览量最高'),
                array('style_id'=>'3','product_style'=>'推荐值最高'),
            );
        }
        return $list;
    }
    /**宿友推荐
     *title
     */
    static public function RoommateRecommend($id,$derect)
    {
        $where['status'] = 1;
        $order = 'sort ASC';
        if($id && $derect){
            $sort= Db::name('roommate_recommend') ->where('id',$id)->value('sort');
            //1:上一期，2：下一期
            if($derect==1){
                $where['sort'] = ['<', $sort];
                $order = 'sort DESC';
            }else{
                $where['sort'] = ['>', $sort];
            }
        }

        $list = Db::name('roommate_recommend')
            ->field('id,title,description,bg_cover')
            ->where('delete_time is null')
            ->where($where)
            ->order($order)
            ->find();
        if(!$list){
            return null;
        }else{
            $list['pre_id']="";
            $list['next_id']="";

            $list_sort= Db::name('roommate_recommend') ->where('id',$list['id'])->value('sort');
            $where1['sort'] = ['<', $list_sort];
            $list['pre_id']=self::GetRoommateRecommend($where1);
            if(!$list['pre_id']){$list['pre_id']="";}
            $where2['sort'] = ['>', $list_sort];
            $list['next_id']=self::GetRoommateRecommend($where2);
            if(!$list['next_id']){$list['next_id']="";}

            /*if($list['id']>1){
                $where['id'] = ['<', $list['id']];
                $list['pre_id']=self::GetRoommateRecommend($where);
            }
            $where2['id'] = ['>', $list['id']];
            $list['next_id']=self::GetRoommateRecommend($where2);
            if(!$list['next_id']){$list['next_id']="";}*/

            $list['detail'] = Db::name('roommate_recommend_detail')->alias('rrd')
                ->join('store st','rrd.store_id = st.id','LEFT')
                ->where('st.store_status','EQ',1)
                ->where('rrd.roommate_recommend_id','EQ',$list['id'])
                ->whereNull('rrd.delete_time')
                ->order('rrd.sort','asc')
                ->field('rrd.id,rrd.store_id,rrd.title,rrd.cover,rrd.recommended_reason,rrd.star,st.lat,st.lng,st.address,st.store_name,st.cover as store_logo'
                )->select();
            foreach ( $list['detail'] as &$v){
                $v['style'] = Db::name('store_style_store')->alias('sss')
                    ->join('style_store ss','sss.style_store_id = ss.id','LEFT')
                    ->where('sss.store_id','EQ',$v['store_id'])
                    ->where('sss.delete_time is null')
                    ->where('ss.delete_time is null')
                    ->field('ss.id,ss.title'
                    )->select();
        }
        }
        $total = Db::name('roommate_recommend')
            ->where('status','EQ',1)
            ->count();
        $total_list = Db::name('roommate_recommend')
            ->where('status','EQ',1)
            ->field('id')
            ->order('sort')
            ->select();
        foreach ($total_list as $kt => $vt){
            if($vt['id'] == $list['id']){
                $num = $kt + 1;
                $list['periodical']=(string)$num.'/'.(string)$total.'期';
                break;
            }
        }

        return $list;
    }
    /**探店搜索
     *title
     */
    static public function GetDynamicSearch($user_id,$keywords,$lat,$lng,$page,$size)
    {
        if($keywords){
            $where['d.title|d.description|t.title|s.store_name|s.brand_name'] = ['like',"%$keywords%"];
            $where1['p.product_name'] = ['like',"%$keywords%"];
        }
        //查询所有推荐商品的id
        $dynamic_ids= Db::name('dynamic_product')->alias('dp')
            ->join('dynamic d','dp.dynamic_id = d.id','LEFT')
            ->join('product p','dp.product_id = p.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('p.status','EQ',1)
            ->where($where1)
            ->group('dp.dynamic_id ')
            ->column('dp.dynamic_id');
        $dynamics= Db::name('dynamic')->alias('d')
            ->join('store s','d.store_id = s.id','LEFT')
            ->join('topic t','d.topic_id = t.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('s.store_status','EQ',1)
            ->where($where)
            ->group('d.id')
            ->column('d.id');
        $dynamics=  array_unique(array_merge((array)$dynamic_ids,(array)$dynamics));
        $total=count($dynamics);
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.id','in',$dynamics )
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.scene_id','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',$distance,
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->group('d.id')
            ->page($page,$size)
            ->order($order)
            ->select();
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['id']);

            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['id']);
            }
            //判断是否有开启品牌故事和时尚动态
            $v['brand_story'] =self::GetBrandStore($v['store_id']);

        }
        if($list){
            //增加搜索记录
            $search_keywords = Db::name('search_dynamic_record')->where('search_keywords',$keywords)->where('client_type',2)->find();
            if($search_keywords){
                //+1
                Db::name('search_dynamic_record')->where('id',$search_keywords['id'])->setInc('search_number',1);
            }else{
                //新增
                $newsearch = [
                    'search_keywords' => $keywords,
                    'search_number' => 1,
                    'client_type' => 2
                ];
                Db::table('search_dynamic_record')->insert($newsearch);
            }
        }
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }

    /*店铺动态详情
     *title
     */
    static public function GetDynamicDetail($id,$user_id)
    {
        $dynamic = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.id','EQ',$id)
            ->where('d.delete_time is null')
            ->field('d.id,d.store_id')
            ->find();
            if(!$dynamic){return false;}
        $data = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id AND dc.user_id='.$user_id, 'LEFT')  //  关联收藏表
            ->join('store_follow sf', 'sf.store_id = d.store_id AND sf.user_id='.$user_id, 'LEFT')  //  关联关注表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.id','EQ',$id )
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.scene_id','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy','d.description',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat','st.type as store_type','st.is_ziqu',
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
            ])
            ->find();

             //增加访问量
            self::setIncRecord($id);
             //增加访问记录
           if($user_id>0){self::addIncRecord($id,$user_id);}

            //增加访问记录次数
            if($user_id>0){self::addIncRecordTimes($id,$user_id);}

            //图片数组
            $data['images']= Db::name('dynamic_img') -> field('img_url,type,dynamic_id,cover')->where('dynamic_id',$id)->select();
            //标签数组
            $data['tags']= Db::name('dynamic_style')->alias('ds')
                ->join('style_store ss','ds.style_id = ss.id And ds.type=1','LEFT')
                ->join('style_product sp','ds.style_id = sp.id And ds.type=2','LEFT')
                ->where('ds.dynamic_id','EQ',$id )
                ->group('ds.style_id,ds.type')
                ->field('ds.style_id,ds.type,sp.title as product_style,ss.title as store_style')
                ->select();
            //查询是否有商品

        $subQuery = Db::name('dynamic_product')
            ->alias('dp')
            ->join('dynamic d','d.id = dp.dynamic_id','LEFT')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('product_img pi','pi.id = dp.img_id','LEFT')
            ->join('product p','p.id = dp.product_id','LEFT')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->join('dynamic_product_specs dps','dps.dynamic_product_id = dp.id AND dps.specs_id = ps.id','LEFT')
            ->where(['dp.dynamic_id'=>$id])
            ->field(['dp.dynamic_id','dp.tag_name','dp.product_id', 'p.product_name','p.freight',
                'IFNULL(pi.img_url, ps.cover) as cover',
                'dps.price','ps.price as old_price ','ps.huaxian_price','ps.group_buy_price','ps.share_img','ps.platform_price',
                'ps.product_specs','ps.id as specs_id','ps.stock','st.id as store_id','st.store_name','st.is_ziqu','st.type as store_type'
            ])
            ->order('dps.price','ASC')
            ->buildSql();
        $product = Db::table($subQuery.' a')
            ->group('a.product_id')
            ->select();

        $time = time();
        //获取价格，活动类型

        $total_huaxian=0;//总划线价
        $total_money=0;//总金额
        if($product){
        foreach ($product as &$v){
            $tag = Db::name('activity_product')
                ->alias('ap')
                ->join('activity a','ap.activity_id = a.id','LEFT')
                ->where(['ap.product_id'=>$v['product_id'],'a.start_time' =>['elt',$time],'a.end_time' =>['egt',$time]])
                ->field(['a.activity_type'])
                ->find();
            $v['type'] = !empty($tag['activity_type']) ? $tag['activity_type'] : '1';
            $total_huaxian+=$v['huaxian_price'];
            if($data['is_group_buy'] == 1){ //支持打包购买   取打包价  正常价格

                $total_money+=$v['price'];
            }else{  //不支持打包   取正常价  划线价格
                $total_money+=$v['old_price'];
                $v['price']=$v['old_price'];
            }
            unset($v['old_price']);
        }
            $data['product_info']=$product;
            $data['total_money']=$total_money;
            $data['total_huaxian']=$total_huaxian;
        }else{
            $data['product_info']=[];
            $data['total_money']=$total_money;
            $data['total_huaxian']=$total_huaxian;
        }


//        $product= Db::name('dynamic_product')->alias('dp')
//            ->join('product p','dp.product_id = p.id','LEFT')
//            ->where('dp.dynamic_id','EQ',$id )
//            ->where('p.status','EQ',1 )
//            ->field('dp.id,dp.dynamic_id,dp.product_id')
//            ->select();
//        $total_huaxian=0;//总划线价
//        $total_money=0;//总金额
//            if($product){
//                $price= Db::name('dynamic_product_specs')->field('product_id,MIN(price) as price,dynamic_id')->where('dynamic_id','EQ',$id)->order('price ASC')->group('product_id')->select();
//                $specs_id=[];
//                $product_info=[];
//                foreach ($price as $k=>$v){
//
//                    //判断是否支持打包购买
//                    if($data['is_group_buy']==1){
//                        $total_money+=$v['price'];
//                        $total_huaxian+=$product_info[$k]['huaxian_price'];
//                    }else{
//                        $total_money+=$products['price'];
//                        $total_huaxian+=$product_info[$k]['huaxian_price'];
//                    }
//                }
//                $data['product_info']=$product_info;
//                $data['total_money']=$total_money;
//                $data['total_huaxian']=$total_huaxian;
//            }else{
//                $data['product_info']=[];
//                $data['total_money']=$total_money;
//                $data['total_huaxian']=$total_huaxian;
//            }
            //查询评论数
        $data['total_comment_number']=Db::name('dynamic_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->where('dc.dynamic_id','EQ',$id )
            ->where('dc.pid','EQ',0 )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id')
            ->count();
        $comment=Db::name('dynamic_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->where('dc.dynamic_id','EQ',$id )
            ->where('dc.pid','EQ',0 )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id,dc.dynamic_id,dc.user_id ,dc.rid,dc.content,dc.create_time,u.avatar,u.nickname')
            ->limit(2)
            ->order('dc.create_time DESC')
            ->select();
        foreach ($comment as $k3=>$v3){
           $v3['avatar']==''?$v3['avatar']='/default/user_logo.png': $v3['avatar'];
            $comment[$k3]['reply']=Db::name('dynamic_comment')->alias('dc')
                ->join('user u','dc.user_id = u.user_id','LEFT')
                ->join('user ub','dc.b_user_id = ub.user_id','LEFT')
                ->where('dc.dynamic_id','EQ',$id )
                ->where('dc.pid','EQ',$v3['id'] )
                ->where('u.user_status','IN','1,3')
                ->field('dc.id,dc.dynamic_id,dc.user_id,dc.content,dc.rid,dc.create_time,u.avatar,u.nickname,dc.b_user_id,ub.avatar as b_user_avatar,ub.nickname as b_user_nickname')
                ->limit(2)
                ->order('dc.create_time DESC')
                ->select();
        }
        if($comment){$data['comment']=$comment;}else{$data['comment']=[];}
        return $data;
    }
    /**
     *  一对多关联图片
     * @return \think\model\relation\HasMany
     */
    public function images(){
        return $this -> hasMany('DynamicImgModel', 'dynamic_id', 'id') -> field('img_url,type,dynamic_id,cover');
    }

    /**店铺动态详情推荐
     *title
     */
    static public function GetDynamicDetailRecommend($user_id,$page,$size,$lat,$lng,$scene_id)
    {
        //默认推荐+权重
//        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $where['d.scene_id'] = $scene_id;
        $list=self::GetDynamicList($user_id,$where,$lat,$lng,6);
        $scene_acquaintance_degree=Db::name('scene_acquaintance_degree')->where('scene_one_id',$scene_id)->order('degree DESC')->limit(2)->column('scene_two_id');
       if($scene_acquaintance_degree){
           $num=count($scene_acquaintance_degree);
           foreach ($scene_acquaintance_degree as $k=>$v){
               $where['d.scene_id'] = $v;
               $data[$k]=self::GetDynamicList($user_id,$where,$lat,$lng,3);
           }
           if($num==1){$list=array_merge($list,$data[0]);}elseif ($num==2){$list=array_merge($list,$data[0],$data[1]);
           }
       }
        return $list;
    }

    /**动态评论
     *title
     */
    static public function GetDynamicComment($params)
    {

        $rule = [
            'dynamic_id'  => 'require|number',
            'user_id'    => 'require|number',
            'content'    => 'require',
            'token'    => 'require',
        ];

        $msg = [
            'dynamic_id.require' => '缺少必要参数',
            'dynamic_id.number'  => '参数格式不正确',
            'user_id.require'   => '缺少必要参数',
            'user_id.number'    => '参数格式不正确',
            'content.require'   => '缺少必要参数',
            'token.require'     => '缺少必要参数',
        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return $validate->getError();
        }
        $dynamic_id=intval($params['dynamic_id']);
        $pid=intval(trim($params['pid']));
        $rid=intval(trim($params['rid']));
        $b_user_id=intval(trim($params['b_user_id']));
        //  动态数据是否存在
        $dynamic=DynamicModel::where(['id' => $params['dynamic_id'], 'status' => 1])
            -> field('id,store_id,comment_number')-> find();
        if (!$dynamic)  return '未找到该条动态!';
        if($pid>0){
            $comment_pid=self::GetDynamicCommentId($pid);
            if (!$comment_pid)  return '未找到该条评论!';
        }
        if($rid>0){
            $comment_rid=self::GetDynamicCommentId($rid);
            if (!$comment_rid)  return '未找到该条评论!';
        }
        $insertData = [
            'dynamic_id'  => $params['dynamic_id'],
            'user_id'     => $params['user_id'],
            'content'     => htmlspecialchars($params['content']),
            'create_time' => time(),
            'pid'         => (isset($pid) && is_numeric($pid) && $pid > 0) ? $pid : 0,
            'rid'         => (isset($rid) && is_numeric($rid) && $rid > 0) ? $rid : 0,
            'b_user_id'   => (isset($b_user_id) && is_numeric($b_user_id) && $b_user_id > 0) ? $b_user_id : 0,
            'support'     => 0,
            'hate'        => 0
        ];
        $result = CommentModel::insertGetId($insertData);
        if($result===false){return '评论失败';}
        DynamicModel::where('id', $dynamic['id']) -> setInc('comment_number', 1);
        $data= Db::name('dynamic_comment')
            -> join('user', 'dynamic_comment.user_id = user.user_id','LEFT')
            -> join('user b', 'dynamic_comment.b_user_id = b.user_id','LEFT')
            -> field('dynamic_comment.id,dynamic_comment.content,dynamic_comment.dynamic_id,dynamic_comment.pid,dynamic_comment.rid,dynamic_comment.create_time,dynamic_comment.user_id,user.nickname,user.avatar,dynamic_comment.b_user_id,b.nickname as b_user_nickname,b.avatar as b_user_avatar')
            ->where('dynamic_comment.id',$result )
            ->find();
        if($data['avatar']==''){$data['avatar']='/default/user_logo.png';}
        if($data['b_user_id']>0 && $data['b_user_avatar']==''){$data['b_user_avatar']='/default/user_logo.png';}
        return $result ? $data : false;
    }
    /**时尚新潮列表
     *title
     */
    static public function GetNewTrendList($user_id,$page,$size)
    {
        $total = Db::name('new_trend')->alias('nt')
            ->join('topic t', 'nt.topic_id = t.id', 'LEFT')  //  关联话题表
            -> field(['nt.id', 'nt.title', 'nt.cover', 'nt.content', 'nt.create_time','nt.visit_number','t.title as topic_title'
            ])
            ->where('nt.status','EQ',1 )
            ->where('nt.delete_time is null' )
            -> count();
        $list = Db::name('new_trend')->alias('nt')
            ->join('topic t', 'nt.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('new_trend_dianzan ntd', 'nt.id = ntd.new_trend_id AND ntd.user_id = '.$user_id, 'LEFT')  //  关联点赞表
            -> field(['nt.id', 'nt.title', 'nt.cover', 'nt.content', 'nt.create_time','nt.visit_number','nt.share_number','nt.like_number','nt.comment_number','nt.collect_number','t.title as topic_title',
                'IF(ntd.create_time > 0, 1, 0)  is_dianzan',
                ])
            ->where('nt.status','EQ',1 )
            ->where('nt.delete_time is null' )
            ->page($page,$size)
            ->group('nt.id')
            ->order('sort ASC')
            -> select();
        $config = config('config_common.h5_url');
            foreach ($list as &$v){
                $v['url']=sprintf($config['new_trend'], $v['id']) . "&" . rand(100000,999999);
                $v['hot']=$v['visit_number']+$v['share_number']+$v['like_number']+$v['comment_number']+$v['collect_number'];
                $v['styles'] = Db::name('new_trend_style')->alias('nts')
                    ->join('style_store ss','nts.style_id = ss.id And nts.type=1','LEFT')
                    ->join('style_product sp','nts.style_id = sp.id And nts.type=2','LEFT')
                    ->where('nts.new_trend_id','EQ',$v['id'] )
                    ->field('nts.style_id,nts.type,sp.title as product_style,ss.title as store_style')
                    ->select();
                ##处理content,获取第一张图片前的存文本
                $content = str_replace('&nbsp','',htmlspecialchars_decode(stripslashes(htmlspecialchars_decode($v['content']))));
                $extra = strstr($content, "<img");
                if($extra)$content = str_replace($extra,'',$content);
                $v['content'] = strip_tags($content);
            }
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }

    /**时尚新潮详情
     *title
     */
    static public function GetNewTrendDetail($user_id,$id)
    {
        $data = Db::name('new_trend')->alias('nt')
            ->join('topic t', 'nt.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('new_trend_dianzan ntd', 'nt.id = ntd.new_trend_id AND ntd.user_id = '.$user_id, 'LEFT')  //  关联点赞表
            ->join('new_trend_collection ntc', 'nt.id = ntc.new_trend_id AND ntc.user_id = '.$user_id, 'LEFT')  //  关联收藏表
            -> field(['nt.id', 'nt.title', 'nt.cover', 'nt.content', 'nt.create_time','nt.visit_number','nt.share_number','nt.like_number','nt.comment_number','nt.collect_number','t.title as topic_title',
                'IF(ntd.create_time > 0, 1, 0)  is_dianzan',
                'IF(ntc.create_time > 0, 1, 0)  is_collect',
            ])
            ->where('nt.status','EQ',1 )
            ->where('nt.delete_time is null' )
            ->where('nt.id','EQ',$id )
            -> find();
        if(!$data){
            $data['is_dianzan'] = $data['is_collect'] = 0;
        }
        $data['hot']=$data['visit_number']+$data['share_number']+$data['like_number']+$data['comment_number']+$data['collect_number'];
        //风格
        $data['style']=Db::name('new_trend_style')->alias('nts')
            ->join('style_store ss','nts.style_id = ss.id And nts.type=1','LEFT')
            ->join('style_product sp','nts.style_id = sp.id And nts.type=2','LEFT')
            ->where('nts.new_trend_id','EQ',$id )
            ->field('nts.style_id,nts.type,sp.title as product_style,ss.title as store_style')
            ->select();
        if(!$data){return '未找到该条时尚新潮!';}
        //店铺
        $data['store']=Db::name('new_trend_store')->alias('nts')
            ->join('store s','nts.store_id = s.id','LEFT')
            ->where('nts.new_trend_id','EQ',$id )
            ->field('nts.store_id,s.store_name,s.cover')
            ->select();
        //商品
        $data['product']=Db::name('new_trend_product')->alias('ntp')
            ->join('product p','ntp.product_id = p.id','LEFT')
            ->where('ntp.new_trend_id','EQ',$id )
            ->field('ntp.product_id,p.product_name')
            ->select();
        foreach ($data['product'] as $k=>$v){
            $data['product'][$k]['cover']=Db::name('product_specs')->where('product_id','EQ',$v['product_id'] )->limit(1)->value('cover');
        }
        //查询评论数
        $data['total_comment_number']=Db::name('new_trend_comment')->alias('ntc')
            ->join('user u','ntc.user_id = u.user_id','LEFT')
            ->where('ntc.new_trend_id','EQ',$id )
            ->where('u.user_status','IN','1,3')
            ->field('ntc.id')
            ->count();
        $comment=Db::name('new_trend_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->where('dc.new_trend_id','EQ',$id )
            ->where('dc.pid','EQ',0 )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id,dc.new_trend_id,dc.user_id ,dc.content,dc.create_time,u.avatar,u.nickname')
            ->limit(3)
            ->order('dc.create_time DESC')
            ->select();
        foreach ($comment as $k3=>$v3){
            $v3['avatar']==''?$v3['avatar']='/default/user_logo.png': $v3['avatar'];
            $comment[$k3]['reply']=Db::name('new_trend_comment')->alias('dc')
                ->join('user u','dc.user_id = u.user_id','LEFT')
                ->join('user ub','dc.b_user_id = ub.user_id','LEFT')
                ->where('dc.new_trend_id','EQ',$id )
                ->where('dc.pid','EQ',$v3['id'] )
                ->where('u.user_status','IN','1,3')
                ->field('dc.id,dc.new_trend_id,dc.user_id,dc.content,dc.create_time,u.avatar,u.nickname,dc.b_user_id,ub.avatar as b_user_avatar,ub.nickname as b_user_nickname')
                ->limit(3)
                ->order('dc.create_time DESC')
                ->select();
        }

        $data['content'] = stripslashes(htmlspecialchars_decode($data['content']));

        if($comment){$data['$comment']=$comment;}else{$data['$comment']=[];}
        NewTrendModel::where('id', $id) -> setInc('visit_number', 1);
        return $data;
    }
    /**时尚新潮评论
     *title
     */
    static public function GetNewTrendComment($params)
    {
        $rule = [
            'new_trend_id'  => 'require|number',
            'user_id'    => 'require|number',
            'content'    => 'require',
            'token'    => 'require',
        ];
        $msg = [
            'new_trend_id.require' => '缺少必要参数',
            'new_trend_id.number'  => '参数格式不正确',
            'user_id.require'   => '缺少必要参数',
            'user_id.number'    => '参数格式不正确',
            'content.require'   => '缺少必要参数',
            'token.require'     => '缺少必要参数',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return $validate->getError();
        }
        $new_trend_id=intval($params['new_trend_id']);
        $pid=intval(trim($params['pid']));
        $rid=intval(trim($params['rid']));
        $b_user_id=intval(trim($params['b_user_id']));

        //  时尚新潮数据是否存在
        $newtrend=NewTrendModel::where(['id' => $new_trend_id, 'status' => 1])
            -> field('id,comment_number')-> find();
        if (!$newtrend)  return '未找到该条动态!';
        $insertData = [
            'new_trend_id'  => $new_trend_id,
            'user_id'     => $params['user_id'],
            'content'     => htmlspecialchars($params['content']),
            'create_time' => time(),
            'pid'         => (isset($pid) && is_numeric($pid) && $pid > 0) ? $pid : 0,
            'rid'         => (isset($rid) && is_numeric($rid) && $rid > 0) ? $rid : 0,
            'b_user_id'   => (isset($b_user_id) && is_numeric($b_user_id) && $b_user_id > 0) ? $b_user_id : 0
        ];
        $result = NewTrendCommentModel::insertGetId($insertData);
        if($result===false){return '评论失败';}
        NewTrendModel::where('id', $newtrend['id']) -> setInc('comment_number', 1);

        $data= Db::name('new_trend_comment')
            -> join('user', 'new_trend_comment.user_id = user.user_id','LEFT')
            -> join('user b', 'new_trend_comment.b_user_id = b.user_id','LEFT')
            -> field('new_trend_comment.id,new_trend_comment.content,new_trend_comment.new_trend_id,new_trend_comment.pid,new_trend_comment.rid,new_trend_comment.create_time,new_trend_comment.user_id,user.nickname,user.avatar,new_trend_comment.b_user_id ,b.nickname as b_user_nickname,b.avatar as b_user_avatar')
            ->where('new_trend_comment.id','EQ',$result )
            ->find();
        if($data['avatar']==''){$data['avatar']='/default/user_logo.png';}
        if($data['b_user_id']>0 && $data['b_user_avatar']==''){$data['b_user_avatar']='/default/user_logo.png';}
        return $result ? $data : false;
    }
    /**turtle购筛选查询
     *title
     */
    static public function GetTurtleSelect()
    {
       //分类
        $list[0]['key']=1;
        $list[0]['title']='店铺分类';
        $list[0]['data'] = $store_ids = self::GetStoreCategory();
        $list[1]['key']=2;
        $list[1]['title']='风格';
        $list[1]['data'] = $store_ids = self::GetStoreStyle();
        $list[2]['key']=3;
        $list[2]['title']='商圈';
        $list[2]['data'] = $store_ids = self::GetBusinessCircle();
        return $list;
    }
    /**人气单品
     *title
     */
    static public function GetPopularProducts()
    {
        $total=Db::name('popular_products')
            ->field('id,title,bg_img,visit_num,create_time')
            ->where('status',1)
            ->where('delete_time is null')
            ->order('sort ASC')
            ->count();
        $list=Db::name('popular_products')
            ->field('id,title,bg_img,visit_num,create_time')
            ->where('status',1)
            ->where('delete_time is null')
            ->order('sort ASC')
            ->select();

        foreach ($list as &$v2){
            $v2['product']=Db::name('popular_products_details')
                ->field('id,title,cover,desc,product_id,visit_num,pop_pro_id')
                ->where('pop_pro_id','EQ',$v2['id'])
                ->select();

            foreach ($v2['product'] as  &$v){
                $min_price=Db::name('product_specs')
                    ->where('product_id','EQ',$v['product_id'])
                    ->min('price');
                $max_price=Db::name('product_specs')
                    ->where('product_id','EQ',$v['product_id'])
                    ->max('price');
                $v['price'] = $min_price;
                $v['same'] = '0';
                if($min_price == $max_price){
                    $v['same'] = '1';
                }
            }
        }
        $data['total']=$total;
        $data['data']=$list;
        return $data;
    }

    /**商圈（商圈信息）
     *title
     */
    static public function GetBusinessCircleDynamic($id)
    {
        $data=Db::name('business_circle')
            ->field('id,circle_name,address,visit_number')
            ->where('id',$id )
            ->where('status',1 )
            ->find();
        if(!$data){return '没有找到该商圈或已下架!';}
        $data['images']=Db::name('business_circle_img')
            ->where('business_circle_id','EQ',$id )
            ->column('img_url');
        Db::name('business_circle')->where('id',$id )->setInc('visit_number');

        return $data;
    }
    /**商圈动态列表
     *title
     */
    static public function GetBusinessCircleDynamicList($user_id,$id,$page,$size,$lng,$lat)
    {
        $data=Db::name('business_circle')
            ->field('id,circle_name,address,visit_number')
            ->where('id',$id )
            ->where('status',1 )
            ->find();
        if(!$data){return '没有找到该商圈或已下架!';}
        $store_ids= Db::name('business_circle_store')
            ->where('business_circle_id','EQ',$id )
            ->group('store_id')
            ->column('store_id');
        if(!$store_ids){return '该商圈还没有商家发布动态哦!';}
        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.store_id','IN',$store_ids)
            ->where('d.delete_time is null')
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','d.description','d.scene_id','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',$distance,
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->group('d.id')
            ->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.store_id','IN',$store_ids)
            ->where('d.delete_time is null')
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','d.description','d.scene_id','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',$distance,
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->group('d.id')
            ->page($page,$size)
            ->order($order)
            ->select();
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['id']);
            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['id']);
            }
            //判断是否有开启品牌故事和时尚动态
            $v['brand_story'] =self::GetBrandStore($v['store_id']);
        }

        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }

    /**动态详情评论
     *title
     */
    static public function GetDynamicDetailComment($user_id,$id)
    {
                //查询评论数
            $data['total_comment_number']=Db::name('dynamic_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->where('dc.dynamic_id','EQ',$id )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id')
            ->count();
            $comment=Db::name('dynamic_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->where('dc.dynamic_id','EQ',$id )
            ->where('dc.pid','EQ',0 )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id,dc.dynamic_id,dc.user_id ,dc.content,dc.create_time,u.avatar,u.nickname')
            ->limit(2)
            ->order('dc.create_time DESC')
            ->select();
            foreach ($comment as $k3=>$v3){
            $comment[$k3]['reply']=Db::name('dynamic_comment')->alias('dc')
            ->join('user u','dc.user_id = u.user_id','LEFT')
            ->join('user ub','dc.b_user_id = ub.user_id','LEFT')
            ->where('dc.dynamic_id','EQ',$id )
            ->where('dc.pid','EQ',$v3['id'] )
            ->where('u.user_status','IN','1,3')
            ->field('dc.id,dc.dynamic_id,dc.user_id,dc.content,dc.create_time,u.avatar,u.nickname,dc.b_user_id,ub.avatar as b_user_avatar,ub.nickname as b_user_nickname')
            ->limit(2)
            ->order('dc.create_time DESC')
            ->select();
            }
            if($comment){$data['comment']=$comment;}else{$data['comment']=[];}
            return $data;
    }



    //--------------todu wu-------------//
    /**
     * 品牌故事
     * @param $id  品牌故事id
     */
    static public function BrandStory($id){
        //查找品牌历史，品牌理念，品牌名称
        $brand = Db::name('brand_story')
            ->alias('bs')
            ->join('brand b', 'b.id = bs.brand_id')  //  关联品牌故事表
            -> field(['bs.brand_id','bs.id as bs_id','bs.history', 'bs.notion', 'b.brand_name','b.logo'])
            ->where('bs.id','EQ',$id )
            ->where('status','EQ','1' )
            -> find();
        if(!$brand){return '未找到该品牌故事!';}
        //品牌故事 广告图
        $brand_ads = Db::name('brand_story_ads')
            -> field(['id','url','type', 'cover', 'media_id','status','sort'])
            ->where('brand_story_id','EQ',$brand['bs_id'] )
            ->where('status','EQ','1' )
            -> select();

        //品牌经典款
        $brand_pro = Db::name('brand_product')
            ->alias('bp')
            ->join('product p', 'bp.product_id = p.id','left')  //  关联产品表
            ->join('product_specs psp', 'bp.product_id = psp.product_id','left')  //  关联产品规格表
            -> field(['p.id as product_id','psp.cover','psp.price','psp.product_name','psp.id as specs_id'])
            ->where('bp.brand_id','EQ',$brand['brand_id'] )
            ->group('p.id')
            -> select();

        $brand['ads'] = $brand_ads;
        $brand['brand_pro'] = $brand_pro;

        return $brand;
    }

    /**
     * 时尚动态列表
     * @param $id
     */
    static public function FashionTrends($id){
        $dynamic_ads = Db::name('brand_dynamic_ads')
            -> field(['id','title','url','type','link_type','link_url','cover','media_id'])
            ->where('brand_dynamic_id','EQ',$id )
            ->where('status','EQ','1' )
            -> select();
        $dynamic_article = Db::name('brand_dynamic_article')
            -> field(['id','title','cover','type','video_url','video_cover','media_id'])
            ->where('brand_dynamic_id','EQ',$id )
            ->where('status','EQ','1' )
            ->whereNull('delete_time')
            ->order('sort','asc')
            -> select();
        $config = config('config_common.h5_url');
        foreach ($dynamic_article as &$v){
            $v['type']==3?$v['url']=sprintf($config['brand_dynamic_news'], $v['id']):"";
        }
        $dynamic['ads'] = $dynamic_ads;
        $dynamic['article'] = $dynamic_article;
        return $dynamic;
    }

    /**
     * 时尚动态资讯详情
     * @param $id  资讯id
     * @param $type
     */
    static public function FashionTrendsDetail($id,$type){
        Db::name('brand_dynamic_article')->where('id',$id )->setInc('visit_number');
        $data = [];
        switch ($type){
            case 1://视频
                $data = Db::name('brand_dynamic_article')
                    -> field(['id','title','cover','type','video_url','video_cover','media_id','media_desc','create_time'])
                    ->where('id','EQ',$id )
                    -> select();
                break;
            case 2://影集
                $data = Db::name('brand_dynamic_article')
                    -> field(['id','title','cover','create_time'])
                    ->where('id','EQ',$id )
                    ->find();
                $data['picture'] = Db::name('brand_dynamic_picture')
                    ->field(['id','url','desc','is_cover','sort'])
                    ->where('dynamic_article_id','EQ',$id )
                    -> select();
                break;
            case 3://new
                $data = Db::name('brand_dynamic_article')
                    -> field(['id','title','cover','content','create_time'])
                    ->where('id','EQ',$id )
                    -> find();
                $data['img'] = Db::name('brand_dynamic_news_imgs')
                    ->field(['id','img','is_cover','sort'])
                    ->where('dynamic_news_id','EQ',$id )
                    -> select();
                $data['content'] = htmlspecialchars_decode(stripslashes(htmlspecialchars_decode($data['content'])));
                break;
        }
        return $data;
    }

    /**
     * 收藏 生活动态列表
     * @param $user_id
     */
    static public function liveCollectionList($user_id,$lng,$lat,$page,$size){
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"s.lat","s.lng").", 0) as distance";//计算距离
        $list = Db::name('dynamic_collection')
            ->alias('dc')
            ->join('dynamic d', 'd.id = dc.dynamic_id','left')  //  关联产品表
            ->join('store s', 's.id = dc.store_id','left')  //  关联产品表
            ->join('topic t', 't.id = d.topic_id','left')  //  关联话题表
            ->join('scene sc', 'sc.id = d.scene_id','left')  //  关联场景表
            //->join('brand_store bs', 'bs.store_id = s.id','left')  //  关联品牌 商店表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            //->join('brand_story bst', 'bst.brand_id = bs.brand_id','left')  //  关联品牌故事表
            //->join('brand_dynamic bd', 'bd.brand_id = bs.brand_id','left')  //  关联时尚动态表
            -> field(['d.id as d_id','d.title','d.type','d.description','d.cover as dynamic_cover','d.status','d.look_number','d.scene_id','d.share_number','d.like_number',
                '(d.look_number+d.like_number+d.collect_number+d.comment_number+d.share_number) as hot',
                'd.collect_number', 's.store_name','s.brand_name','s.address','s.id as store_id','s.cover',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                't.title as topic','sc.title as scene',$distance])
            ->where('dc.user_id','EQ',$user_id )
            ->where('d.status','EQ',1 )
            ->whereNull('d.delete_time')
//            ->where('bs.is_selected','EQ',1)
            ->group('d.id')
            ->order('d.create_time desc')
            ->page($page,$size)
            -> select();
        //获取浏览用户头像
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['d_id']);

            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['d_id']);
            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['d_id']);
            }
            $v['brand_story'] =self::GetBrandStore($v['store_id']);
            $v['topic'] = !is_null($v['topic'])?$v['topic']:"";
        }
        return $list;
    }

    /**
     * 店铺详情 动态列表
     */
    static public function storeDynamicLits($store_id,$lng,$lat,$user_id,$page,$size){
        //'bs.brand_id','bs.is_show_story','bs.is_show_dynamic','bst.id as bst_id','bd.id as bd_id',
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"s.lat","s.lng").", 0) as distance";//计算距离
        $list = Db::name('dynamic')
            ->alias('d')
            ->join('store s', 'd.store_id	= s.id','LEFT')  //  关联动态表
            ->join('scene sc', 'sc.id = d.scene_id','LEFT')  //  关联场景表
            ->join('topic t', 't.id = d.topic_id','LEFT')  //  关联话题表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            -> field(['s.id as store_id','s.cover as cover','s.store_name','s.address','s.lng','s.lat',
                'd.id as d_id','d.cover as d_cover','d.description','d.look_number','d.like_number','d.share_number','d.type','d.scene_id',
                '(d.look_number+d.like_number+d.collect_number+d.comment_number+d.share_number) as hot',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                't.title as topic','sc.title as scene',$distance])
            ->where('d.store_id','EQ',$store_id )
            ->where('d.status','EQ',1)
            ->where('s.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->group('d.id')
            ->order('d.create_time desc')
            ->page($page,$size)
            -> select();

        //获取浏览用户头像
        foreach ($list as &$v){
            //获取阅读头像
            $v['user_look_list'] =self::GetUserLookList($v['d_id']);
            if($v['type']==1){
                $v['dynamic_images'] = self::GetDynamicImagesList($v['d_id']);

            }else{
                $v['dynamic_images'] = self::GetDynamicVideoList($v['d_id']);
            }
            $v['brand_story'] =self::GetBrandStore($v['store_id']);
        }

        return $list;
    }

    /**
     * 生活剪影
     * @param $user_id
     * @param $scene_arr
     * @param $pids
     * @param $lat
     * @param $lng
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function lifeSilhouetteList($user_id,$scene_arr,$pids,$lat,$lng){
        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $subQuery = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            //->join('scene sc','sc.id = d.scene_id','LEFT')
            //->join('business_circle_store bcs','bcs.store_id = st.id','LEFT')'bcs.business_circle_id',
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id AND dc.user_id='.$user_id, 'LEFT')  //  关联收藏表
            ->join('store_follow uf', 'st.id = uf.store_id and uf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->where(['scene_main_id'=>['in',$pids],'d.status'=>1])
            ->whereNull('d.delete_time')
            ->field(['d.id','st.cover as store_logo','st.store_name','st.id as store_id','d.description','d.cover','d.type','d.is_group_buy','d.scene_id',
                'd.look_number','d.like_number','d.share_number','d.collect_number','d.comment_number','d.scene_main_id as p_id','d.scene_id as sc_id',
                '(d.look_number+d.like_number+d.collect_number+d.comment_number+d.share_number) as hot','st.signature',
                $distance,
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dc.create_time > 0 ,1 ,0) is_collection',
                'IF(uf.create_time > 0 ,1 ,0) is_follow',
                't.title as topic',
                'ROUND((look_number*0.1+d.like_number*0.3+d.collect_number*0.25+d.comment_number*0.1+d.share_number*0.15+'.$distance.'*0.1),2) as weight'])
            ->order($order)
            ->select();
        //对查询出的数据 做p_id分类
        $result = array();
        foreach ($subQuery as $key => $value) {
            $result[$value['p_id']][] = $value;
        }
        $ret = array();
        foreach ($result as $key => $value) {
            array_push($ret, $value);
        }
        if($user_id > 0){
            //查找用户最近三次浏览的动态
            $user_records_group = Db::name('dynamic_group_look')->alias('dgl')
                ->join('dynamic_group dg','dgl.dynamic_group_id = dg.id','LEFT')
                ->where(['dgl.user_id'=>$user_id])
                ->field('dg.dynamic_ids')
                ->order('dgl.create_time desc')
                ->limit(20)
                ->select();
            $user_records_groups = array_map('array_shift', $user_records_group);
            $dy_str = '';
            foreach ($user_records_groups as $kd => $vd){
                if($kd == 0){ $dy_str .= $vd; }else{ $dy_str .= ','.$vd;}
            }
            $dy_arr = explode(',',$dy_str);
            $dy_arr_look = array_unique($dy_arr);  //最近三次浏览所有的动态
        }

        //取出每组中匹配度高的动态
        $list = [];
        foreach ($ret as &$v){//遍历分类
            $d_arr = [];
            $p_num = count($v);//每组分类的动态数量
            if($user_id > 0){ //用户登录
                foreach ($v as $kv =>$vv) {//遍历分类中每组的动态
                    //判断是否有新的动态未查看
                    $user_records = Db::name('dynamic_user_record')->where(['user_id'=>$user_id,'dynamic_id'=>$vv['id']])->find();
                    if(!$user_records){
                        array_push($d_arr,$vv);
                        break;
                    }
                }
                if(count($d_arr) > 0){
                    array_push($list,$d_arr['0']);
                }else{
                    foreach ($v as $kvv =>$vvv){
                        if(!in_array($vvv['id'],$dy_arr_look)){ //所选生活场景浏览完，选出最近三次未被浏览的动态
                            array_push($d_arr,$vvv);
                            break;
                        }
                    }
                    if(count($d_arr) > 0){
                        array_push($list,$d_arr['0']);
                    }else{
                        $m = $p_num - 1;
                        $n = rand(0,$m);
                        array_push($list,$v[$n]);
                    }
                }
            }else{
                $m = $p_num - 1;
                $n = rand(0,$m);
                array_push($list,$v[$n]);
            }
        }
        $dynamic_ids = [];
        $business_circle_ids = [];
        foreach ($list as &$v){
            if($v['type'] == 2 && strstr($v['cover'],'http') === false){
                $v['cover'] = "http://" . $_SERVER['HTTP_HOST'] . $v['cover'];
            }
            $scene_pid = Db::name('scene')->where(['id'=>$v['p_id']])->find();
            $v['scene'] = $scene_pid['title'];
            $v['scene_desc'] = $scene_pid['description'];
            $v['scene_icon'] = $scene_pid['icon'];
            //获取动态关联商品
            $v['dynamic_product'] = Db::name('dynamic_product')
                ->where('dynamic_id','EQ',$v['id'])
                ->count();
            $v['dynamic_store'] = 1;
            //获取动态图片或者视频
            $v['dynamic_img'] = Db::name('dynamic_img')
                ->where('dynamic_id','EQ',$v['id'])
                ->field('img_url,cover,type')
                ->select();
            //获取生成的动态id组
            array_push($dynamic_ids,$v['id']);
            //获取动态的商圈
//            if(!empty($v['business_circle_id'])){
//                if(!in_array($v['business_circle_id'],$business_circle_ids)){
//                    array_push($business_circle_ids,$v['business_circle_id']);
//                }
//            }
            $business_circle_store = Db::name('business_circle_store')
                ->where(['store_id'=>$v['store_id']])
                ->field('business_circle_id')
                ->select();
            foreach ($business_circle_store as $kb => $vb){
                if(!in_array($vb['business_circle_id'],$business_circle_ids)){
                   array_push($business_circle_ids,$vb['business_circle_id']);
                }
            }

            $v['dynamic_style'] = [];
            //获取动态风格   type 1店铺  2商品
            $style = Db::name('dynamic_style')->alias('ds')
                ->join('dynamic d','ds.dynamic_id = d.id','LEFT')
                ->join('style_store ss','ds.style_id = ss.id And ds.type=1','LEFT')
                ->join('style_product sp','ds.style_id = sp.id And ds.type=2','LEFT')
                ->where('d.id','EQ',$v['id'])
                ->group('ds.style_id,ds.type')
                ->field('ds.style_id,ds.type,sp.title as product_style,ss.title as store_style')
                ->select();
            foreach ($style as $kst => $vst){
                if($vst['type'] == 1){
                    array_push($v['dynamic_style'],$vst['store_style']);
                }elseif ($vst['type'] == 2){
                    array_push($v['dynamic_style'],$vst['product_style']);
                }
            }
        }
        $data['list'] = $list;
        //$data['cover'] = $data['list']['0'];
        //将生成的动态id，保存到dynamic_user_record，如果该浏览动态已被记录，则曝光加一，没有则添加
        if($user_id > 0){
            foreach ($dynamic_ids as $kd => $vd){
                $dynamic_user_record = Db::name('dynamic_user_record')->where(['user_id'=>$user_id,'dynamic_id'=>$vd])->find();
                if($dynamic_user_record){
                    Db::name('dynamic_user_record')->where('id',$dynamic_user_record['id'])->setInc('look_number',1);
                }else{
                    $dynamic_user_record_datas = [
                        'dynamic_id' => $vd,
                        'user_id' => $user_id,
                        'look_number' => 1,//曝光次数
                        'visit_number' => 0,//访问次数
                        'create_time' => time()
                    ];
                    Db::table('dynamic_user_record')->insert($dynamic_user_record_datas);
                }
                //将生成的动态id保存到 dynamic_look_record    在dynamic中 增加一次look_number
                Db::name('dynamic')->where('id',$vd)->setInc('look_number',1);

                $dynamic_look_record_datas = [
                    'dynamic_id' => $vd,
                    'user_id' => $user_id,
                    'create_time' => time()
                ];
                Db::table('dynamic_look_record')->insert($dynamic_look_record_datas);
            }
        }
        //将生成的动态id保存到dynamic_group，如果存在则不用添加
        asort($dynamic_ids);
        $dynamic_ids = implode(",",$dynamic_ids);
        //$group_data = Db::name('dynamic_group')->where('dynamic_ids',$dynamic_ids)->find();
        $group_data = Db::name('dynamic_group')
            ->alias('dg')
            ->join('dynamic_group_collection dgc','dg.id = dgc.dynamic_group_id AND dgc.user_id='.$user_id, 'LEFT')//关联收藏表
            ->join('dynamic_group_dianzan dgd','dg.id = dgd.dynamic_group_id AND dgd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where(['dg.dynamic_ids'=>$dynamic_ids])
            ->field(['dg.*',
                'IF(dgd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dgc.create_time > 0 ,1 ,0) is_collection',
                ])
            ->find();
        if($group_data){
            $data['group_data'] =$group_data;
            Db::name('dynamic_group')->where('id',$group_data['id'])->setInc('visit_num',1);
        }else{
            $dynamic_group_data = [
                'dynamic_ids' => $dynamic_ids,
                'collect_num' => '0',
                'praise_num' => '0',
                'share_num' => '0',
                'create_time' => time(),
                'visit_num' => '1',//总浏览数
            ];
            $groupId = Db::table('dynamic_group')->insertGetId($dynamic_group_data);
            $dynamic_group_data['id'] = strval($groupId);
            $dynamic_group_data['is_dianzan'] = '0';
            $dynamic_group_data['is_collection'] = '0';
            $data['group_data'] =$dynamic_group_data;
        }
        if($user_id > 0){
            //用户记录浏览本次group
            $group_look = [
                'dynamic_group_id' => $data['group_data']['id'],
                'user_id' => $user_id,
                'create_time' => time(),
            ];
            Db::table('dynamic_group_look')->insert($group_look);
        }

        //获取商圈
        $data['dynamic_circle'] = Db::name('business_circle')
            ->alias('bc')
            ->join('business_circle_img bci','bci.business_circle_id = bc.id','LEFT')
            ->where(['bc.id'=>['in',$business_circle_ids],'bc.status'=>1])
            ->field(['bc.id as id','circle_name','address','img_url','visit_number'])
            ->group('bci.business_circle_id')
            ->select();
        //找出每个大类的动态数量
        $scene_main= Db::name('dynamic')
            ->where(['scene_main_id'=>['in',$pids],'status'=>1])
            ->field(['scene_main_id','count(*) as num'])
            ->group('scene_main_id')
            ->select();
        $recommended_amount = 1;
        foreach ($scene_main as $ks => $vs){
            $recommended_amount  *= $vs['num'];
        }
        if($recommended_amount > 50){
            $recommended_amount = rand(20,50);
        }
        $data['recommended_amount'] = $recommended_amount;
        return $data;
    }


    /**
     * 生活剪影  收藏，点赞，浏览，分享
     * @param $user_id
     * @param $group_id
     * @param $type
     * @param $status
     * @return bool
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    static public function lifeSilhouetteCollect($user_id,$group_id,$type,$status){
        switch ($type) {
            case 1://收藏
                if($user_id == 0) return '请登录!';
                if($status == 1){//添加
                    //生活剪影组表中收藏加一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setInc('collect_num',1);
                    $newdata = ['dynamic_group_id' => $group_id, 'user_id' => $user_id, 'create_time' => time()];
                    //收藏记录表中添加一条记录
                    $re2 = Db::table('dynamic_group_collection')->insert($newdata);
                }elseif($status == 2){
                    //生活剪影组表中收藏减一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setDec('collect_num',1);
                    //收藏记录表中减去一条记录
                    $re2 = Db::name('dynamic_group_collection')->where(['dynamic_group_id'=>$group_id,'user_id'=>$user_id])->delete();
                }
                if($re && $re2) return true;
                break;
            case 2://点赞
                if($user_id == 0) return '请登录!';
                if($status == 1){//添加
                    //生活剪影组表中点赞加一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setInc('praise_num',1);
                    $newdata = ['dynamic_group_id' => $group_id, 'user_id' => $user_id, 'create_time' => time()];
                    //点赞记录表中添加一条记录
                    $re2 = Db::table('dynamic_group_dianzan')->insert($newdata);
                }elseif($status == 2){
                    //生活剪影组表中点赞减一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setDec('praise_num',1);
                    //点赞记录表中减去一条记录
                    $re2 = Db::name('dynamic_group_dianzan')->where(['dynamic_group_id'=>$group_id,'user_id'=>$user_id])->delete();
                }
                if($re && $re2) return true;
                break;
            case 3://浏览
                if($user_id == 0) return '请登录!';
                if($status == 1){//添加
                    //生活剪影组表中浏览加一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setInc('visit_num',1);
                    $newdata = ['dynamic_group_id' => $group_id, 'user_id' => $user_id, 'create_time' => time()];
                    //浏览记录表中添加一条记录
                    $re2 = Db::table('dynamic_group_look')->insert($newdata);
                }elseif($status == 2){
                    //生活剪影组表中浏览减一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setDec('visit_num',1);
                    //浏览记录表中减去一条记录
                    $re2 = Db::name('dynamic_group_look')->where(['dynamic_group_id'=>$group_id,'user_id'=>$user_id])->delete();
                }
                if($re && $re2) return true;
                break;
            case 4://分享
                if($status == 1){//添加
                    //生活剪影组表中分享加一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setInc('share_num',1);
                }elseif($status == 2){
                    //生活剪影组表中分享减一
                    $re = Db::name('dynamic_group')->where('id',$group_id)->setDec('share_num',1);
                }
                if($re) return true;
                break;
        }
        return false;
    }

    /**
     * 生活剪影列表-点击商品
     * @param $dynamic_id
     */
    static public function lifeSilhouetteProduct($dynamic_id){
        //'IF(d.is_group_buy = 1, min(dps.price), min(ps.price)) as price'
        $subQuery = Db::name('dynamic_product')
            ->alias('dp')
            ->join('dynamic d','d.id = dp.dynamic_id','LEFT')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('product_img pi','pi.id = dp.img_id','LEFT')
            ->join('product p','p.id = dp.product_id','LEFT')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->join('dynamic_product_specs dps','dps.dynamic_product_id = dp.id AND dps.specs_id = ps.id','LEFT')
            ->where(['dp.dynamic_id'=>$dynamic_id])
            ->field(['dp.dynamic_id as d_id','dp.tag_name','dp.product_id','dp.id as dp_id','d.is_group_buy', 'p.product_name',
                'IFNULL(pi.img_url, ps.cover) as img_url',
                'IFNULL(dps.price,0) as dps_price','IFNULL(ps.price,0) as price','IFNULL(ps.huaxian_price,0)  as huaxian_price',
                'ps.product_specs','ps.id as specs_id','p.freight','ps.stock','st.id as store_id','st.store_name','st.is_ziqu','st.type as store_type'
            ])
            //->group('dp.product_id')
            ->order('dps.price','ASC')
            ->buildSql();
            //->select();
        $list = Db::table($subQuery.' a')
            ->group('a.product_id')
            ->select();
        $data['is_group_buy'] = 0;
        $data['total_price'] = 0;
        $data['dps_total_price'] = 0;
        $data['store_id'] = '0';
        $data['store_name'] = '';
        $data['is_ziqu'] = '0';
        $data['store_type'] = '1';
        if($list){
            $data['store_id'] = $list['0']['store_id'];
            $data['store_name'] = $list['0']['store_name'];
            $data['is_ziqu'] = $list['0']['is_ziqu'];
            $data['store_type'] = $list['0']['store_type'];
        }
        $time = time();
        //获取价格，活动类型
        foreach ($list as $k => $v){
            $tag = Db::name('activity_product')
                ->alias('ap')
                ->join('activity a','ap.activity_id = a.id','LEFT')
                ->where(['ap.product_id'=>$v['product_id'],'a.start_time' =>['elt',$time],'a.end_time' =>['egt',$time]])
                ->field(['a.activity_type'])
                ->find();
            $list[$k]['type'] = !empty($tag['activity_type']) ? $tag['activity_type'] : '1';
            if($v['is_group_buy'] == 1){ //支持打包购买   取打包价  正常价格
                $data['dps_total_price'] += $v['dps_price'];
                $data['total_price'] += $v['price'];//正常价格
                $data['is_group_buy'] = 1;
            }else{  //不支持打包   取正常价  划线价格
                $data['dps_total_price'] += $v['price'];
                $data['total_price'] += $v['huaxian_price'];//划线价格
            }
        }
        $data['list'] = $list;
        return $data;
    }

    /**
     * 生活剪影列表-点击商铺
     * @param $dynamic_id
     */
    static public function lifeSilhouetteStore($dynamic_id){
        $list = Db::name('dynamic')
            ->alias('d')
            ->join('store s','d.store_id = s.id','LEFT')
            ->where(['d.id'=>$dynamic_id])
            ->field(['d.id as d_id','s.id as store_id','s.store_name','s.cover','s.lng','s.lat','s.address',
                '(s.share_number+s.follow_num+s.deal_num+s.read_number+s.collect_number+s.dianzan) as hot'
            ])
            ->select();

        return $list;
    }
    /**
     * 获取用户年龄范围
     */
    static public function getUserAgeRange(){
        $range = Db::name('age_range')->where('status',1)->field(['id','min_age','max_age'])->order('sort desc')->select();

        return $range;
    }

    /**
     * 更新用户性别，年龄
     * @param $user_id
     * @param $gender
     * @param $age_range_id
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function updateUserAge($user_id,$gender,$age_range_id){
        $user = Db::name('user')->where('user_id',$user_id)->find();
        if($user){
            if($user['gender'] == $gender && $user['age_range_id'] == $age_range_id){
                return true;
            }
            $ret = Db::name('user')->where('user_id', $user_id)->update(['gender' => $gender, 'age_range_id' => $age_range_id]);
            if($ret )return true;
        }
        return false;

    }

    /**
     * 我的收藏 生活剪影列表
     * @param $user_id
     */
    static public function myLifeSilhouette($user_id,$page,$size){
        $pre = ($page-1)*$size;
        //根据用户id，找到用户收藏的组id
        $list = Db::name('dynamic_group_collection')
            ->alias('dgc')
            ->join('dynamic_group dg','dg.id = dgc.dynamic_group_id')//关联生活剪影组
            ->join('dynamic_group_dianzan dgd', 'dg.id = dgd.dynamic_group_id AND dgd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where(['dgc.user_id'=>$user_id])
            ->field(['dg.id as dg_id','dg.dynamic_ids','dgc.user_id','dg.praise_num','IF(dgd.create_time > 0 ,1 ,0) is_dianzan',
                '(dg.collect_num+dg.praise_num+dg.share_num+dg.visit_num) as hot'])
            ->limit($pre,$size)
            ->group('dg.id')
            ->select();
        //遍历每组收藏
        foreach ($list as &$v){
            //获取每组收藏中的动态id
            $dynamic_ids = explode(",", $v['dynamic_ids']);
            //获取每组的动态
            $dynamic = Db::name('dynamic')
                ->alias('d')
                ->join('scene sc','sc.id = d.scene_main_id')
                ->where(['d.status'=>1,'d.id'=>['in',$dynamic_ids]])
                ->field(['d.id','d.cover','sc.title','d.type'])
                ->order('sc.id ASC')
                ->select();
            //组装封面   组装title

            $v['title'] = [];
            $v['cover'] = [];
            foreach ($dynamic as &$dv){
                array_push($v['title'],$dv['title']);
                $dv['num'] = Db::name('dynamic_img')->where('dynamic_id','EQ',$dv['id'])->count();
                unset($dv['title']);
                array_push($v['cover'],$dv);
            }
            //获取用户浏览组的头像
            $v['user_look_list'] = self::GetLifeUserLookList($v['dg_id']);

        }
        return $list;
    }

    /**
     * 通过生活剪影组id   查找生活剪影
     * @param $group_id
     */
    static public function LifeSilhouetteDetails($user_id,$group_id,$pids,$lng,$lat){
        $dynamic_group_ids = Db::name('dynamic_group')
            ->where('id','EQ',$group_id)
            ->field('dynamic_ids')
            ->find();
        $dynamic_ids = explode(',',$dynamic_group_ids['dynamic_ids']);

        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];

        $distance="IF({$lat} > 0 OR {$lng}>0,".distance($lat,$lng,"st.lat","st.lng").", 0) as distance";//计算距离
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            //->join('scene sc','sc.id = d.scene_id','LEFT')
            //->join('business_circle_store bcs','bcs.store_id = st.id','LEFT')
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id AND dc.user_id='.$user_id, 'LEFT')  //  关联收藏表
            ->join('store_follow uf', 'st.id = uf.store_id and uf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->where(['d.id'=>['in',$dynamic_ids],'d.status'=>1])
            ->whereNull('d.delete_time')
            ->field(['d.id','st.cover as store_logo','st.store_name','st.id as store_id','d.description','d.cover','d.type','d.is_group_buy',
                'd.look_number','d.like_number','d.share_number','d.collect_number','d.comment_number','d.scene_main_id as p_id','d.scene_id as sc_id',
                '(d.look_number+d.like_number+d.collect_number+d.comment_number+d.share_number) as hot','st.signature',
                $distance,
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dc.create_time > 0 ,1 ,0) is_collection',
                'IF(uf.create_time > 0 ,1 ,0) is_follow',
                't.title as topic',
                'ROUND((look_number*0.1+d.like_number*0.3+d.collect_number*0.25+d.comment_number*0.1+d.share_number*0.15+'.$distance.'*0.1),2) as weight'])
            ->order($order)
            ->select();
        //商圈ids
        $business_circle_ids = [];
        foreach ($list as &$v){
            $scene_pid = Db::name('scene')->where(['id'=>$v['p_id']])->find();
            $v['scene'] = $scene_pid['title'];
            $v['scene_desc'] = $scene_pid['description'];
            $v['scene_icon'] = $scene_pid['icon'];
            //获取动态关联商品
            $v['dynamic_product'] = Db::name('dynamic_product')
                ->where('dynamic_id','EQ',$v['id'])
                ->count();
            $v['dynamic_store'] = 1;
            //获取动态图片或者视频
            $v['dynamic_img'] = Db::name('dynamic_img')
                ->where('dynamic_id','EQ',$v['id'])
                ->field('img_url,cover,type')
                ->select();
            //获取生成的动态id组
            //获取动态的商圈
//            if(!empty($v['business_circle_id'])){
//                if(!in_array($v['business_circle_id'],$business_circle_ids)){
//                    array_push($business_circle_ids,$v['business_circle_id']);
//                }
//            }
            $business_circle_store = Db::name('business_circle_store')
                ->where(['store_id'=>$v['store_id']])
                ->field('business_circle_id')
                ->select();
            foreach ($business_circle_store as $kb => $vb){
                if(!in_array($vb['business_circle_id'],$business_circle_ids)){
                    array_push($business_circle_ids,$vb['business_circle_id']);
                }
            }
            $v['dynamic_style'] = [];
            //获取动态风格   type 1店铺  2商品
            $style = Db::name('dynamic_style')->alias('ds')
                ->join('dynamic d','ds.dynamic_id = d.id','LEFT')
                ->join('style_store ss','ds.style_id = ss.id And ds.type=1','LEFT')
                ->join('style_product sp','ds.style_id = sp.id And ds.type=2','LEFT')
                ->where('d.id','EQ',$v['id'])
                ->group('ds.style_id,ds.type')
                ->field('ds.style_id,ds.type,sp.title as product_style,ss.title as store_style')
                ->select();
            foreach ($style as $kst => $vst){
                if($vst['type'] == 1){
                    array_push($v['dynamic_style'],$vst['store_style']);
                }elseif ($vst['type'] == 2){
                    array_push($v['dynamic_style'],$vst['product_style']);
                }
            }
        }
        $data['list'] = $list;
        //$data['cover'] = $data['list']['0'];
        //将生成的动态id，保存到dynamic_user_record，如果该浏览动态已被记录，则访问加一，没有则添加
        if($user_id > 0){
            foreach ($dynamic_ids as $kd => $vd){
                $dynamic_user_record = Db::name('dynamic_user_record')->where(['user_id'=>$user_id,'dynamic_id'=>$vd])->find();
                if($dynamic_user_record){
                    Db::name('dynamic_user_record')->where('id',$dynamic_user_record['id'])->setInc('visit_number',1);
                }
                //将动态id保存到dynamic_visit_record中    在dynamic中  dynamic_visit_record加一
                Db::name('dynamic')->where('id',$vd)->setInc('visit_number',1);

                $dynamic_visit_record_datas = [
                    'dynamic_id' => $vd,
                    'user_id' => $user_id,
                    'create_time' => time()
                ];
                Db::table('dynamic_look_record')->insert($dynamic_visit_record_datas);
            }
        }
        //将生成的动态id保存到dynamic_group，如果存在则不用添加
        asort($dynamic_ids);
        $dynamic_ids = implode(",",$dynamic_ids);
        //$group_data = Db::name('dynamic_group')->where('dynamic_ids',$dynamic_ids)->find();
        $group_data = Db::name('dynamic_group')
            ->alias('dg')
            ->join('dynamic_group_collection dgc','dg.id = dgc.dynamic_group_id AND dgc.user_id='.$user_id, 'LEFT')//关联收藏表
            ->join('dynamic_group_dianzan dgd','dg.id = dgd.dynamic_group_id AND dgd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where(['dg.dynamic_ids'=>$dynamic_ids])
            ->field(['dg.*',
                'IF(dgd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dgc.create_time > 0 ,1 ,0) is_collection',
            ])
            ->find();
        if($group_data){
            $data['group_data'] =$group_data;
            Db::name('dynamic_group')->where('id',$group_data['id'])->setInc('visit_num',1);
        }
        if($user_id > 0){
            //用户记录浏览本次group
            $group_look = [
                'dynamic_group_id' => $group_data['id'],
                'user_id' => $user_id,
                'create_time' => time(),
            ];
            Db::table('dynamic_group_look')->insert($group_look);
        }

        //获取商圈
        $data['dynamic_circle'] = Db::name('business_circle')
            ->alias('bc')
            ->join('business_circle_img bci','bci.business_circle_id = bc.id','LEFT')
            ->where(['bc.id'=>['in',$business_circle_ids],'bc.status'=>1])
            ->field(['bc.id as id','circle_name','address','img_url','visit_number'])
            ->group('bci.business_circle_id')
            ->select();
        //找出每个大类的动态数量
        $scene_main= Db::name('dynamic')
            ->where(['scene_main_id'=>['in',$pids],'status'=>1])
            ->field(['scene_main_id','count(*) as num'])
            ->group('scene_main_id')
            ->select();
        $recommended_amount = 1;
        foreach ($scene_main as $ks => $vs){
            $recommended_amount  *= $vs['num'];
        }
        if($recommended_amount > 50){
            $recommended_amount = rand(20,50);
        }
        $data['recommended_amount'] = $recommended_amount;

        return $data;
    }
}