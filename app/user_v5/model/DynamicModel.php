<?php


namespace app\user_v5\model;
use think\Model;
use think\db\Query;
use think\Validate;
use think\Db;
use app\common\controller\Base;

class DynamicModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic';
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
        return Db::name('store_follow')
            ->where('store_id','GT',0)
            ->where('type','eq',1)
            ->where('user_id','eq',$user_id)
            ->count();
    }
    /**获取关注店铺动态
     *title
     */
    static public function GetStoreFollowDynamic($user_id,$page,$size,$lng,$lat)
    {
        $store_ids = self::GetStoreFollowIds($user_id);

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
                't.title',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'left')  //  关联话题表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->join('store_follow sf', 'd.store_id = sf.store_id and sf.user_id = '.$user_id, 'left')  //  关联店铺收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.store_id','in',$store_ids)
            -> field([
                'd.id','d.store_id','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.topic_id','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                'IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0) as distance',
                't.title',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])
            ->limit(($page-1)*$size,$size)
            ->order('d.create_time desc')
            ->select();
        foreach ($list as &$v){
            $v['user_look_list'] = Db::name('dynamic_user_record')->alias('dur')
                ->join('user u','dur.user_id = u.user_id','LEFT')
                ->where('dur.dynamic_id','EQ',$v['id'])
                ->field('u.user_id,u.avatar')
                ->limit(30)
                ->order('dur.create_time desc')
                ->select();
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
        $total = Db::name('store')->alias('s')
            ->join('store_follow sf','s.id = sf.store_id','LEFT')
            ->where('sf.store_id','GT',0)
            ->where('sf.type','EQ',1)
            ->group('sf.store_id')
            ->field('sf.store_id,s.store_name,s.cover,s.signature,count(sf.store_id) as follow_number')
            ->count();
        $list['list'] = Db::name('store')->alias('s')
            ->join('store_follow sf','s.id = sf.store_id','LEFT')
            ->join('store_follow uf', 's.id = uf.store_id and uf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            ->where('sf.store_id','GT',0)
            ->where('sf.type','EQ',1)
            ->group('sf.store_id')
            ->field(['sf.store_id','s.store_name','s.cover','s.signature',
                'IF(uf.create_time > 0 ,1 ,0) is_follow',
                'count(sf.store_id) as follow_number'])
            ->limit(($page-1)*$size,$size)
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
            ->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('store_follow sf', 'd.store_id = sf.store_id and sf.user_id = '.$user_id, 'left')  //  关联店铺收藏表
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            -> field([
                'd.id','d.store_id','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.topic_id','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                't.title',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])
            ->limit(($page-1)*$size,$size)
            ->order('d.create_time desc')
            ->select();
        foreach ($list as &$v){

            $v['user_look_list'] = Db::name('dynamic_user_record')->alias('dur')
                ->join('user u','dur.user_id = u.user_id','LEFT')
                ->where('dur.dynamic_id','EQ',$v['id'])
                ->field('u.user_id,u.avatar')
                ->limit(30)
                ->order('dur.create_time desc')
                ->select();
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
                $order = ['weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 2:
                //浏览量最高
                $order = ['look_number'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 3:
                //推荐值最高
                $order = ['d.is_recommend'=>'desc','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
            case 4:
                //距离最近
                $order = ['distance'=>'ASC','d.create_time'=>'DESC'];
                break;
            default:
                //默认推荐+权重
                $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
                break;
        }
        $where='';
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
                $new_category[2]=>$new_category[3]
            ];
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
            ->count();
        $distance='IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0)';
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_style ds', 'd.id = ds.dynamic_id', 'LEFT')  //  关联动态风格表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->where($where)
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat','IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0) as distance',
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->limit(($page-1)*$size,$size)
            ->order($order)
            ->select();
        foreach ($list as &$v){
            $v['user_look_list'] = Db::name('dynamic_user_record')->alias('dur')
                ->join('user u','dur.user_id = u.user_id','LEFT')
                ->where('dur.dynamic_id','EQ',$v['id'])
                ->field('u.user_id,u.avatar')
                ->limit(30)
                ->order('dur.create_time desc')
                ->select();

            if($v['type']==1){
                $v['dynamic_images'] = Db::name('dynamic_img')
                    ->where('dynamic_id','EQ',$v['id'])
                    ->where('can_use','EQ',1)
                    ->field('img_url,type,cover')
                    ->limit(4)
                    ->order('id asc')
                    ->select();
            }else{
                $v['dynamic_images'] = Db::name('dynamic_img')
                    ->where('dynamic_id','EQ',$v['id'])
                    ->where('can_use','EQ',1)
                    ->where('type','EQ',2)
                    ->field('img_url,type,cover')
                    ->limit(1)
                    ->order('id asc')
                    ->select();
            }


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
    static public function RoommateRecommend()
    {
        $list = Db::name('roommate_recommend')
            ->where('status','EQ',1)
            ->field('id,title,description,bg_cover')
            ->order('id DESC')
            ->find();
        if(!$list){
            return [];
        }else{
            $list['detail'] = Db::name('roommate_recommend_detail')->alias('rrd')
                ->join('store st','rrd.store_id = st.id','LEFT')
                ->where('st.store_status','EQ',1)
                ->where('rrd.roommate_recommend_id','EQ',$list['id'])
                ->field('rrd.id,rrd.store_id,rrd.title,rrd.recommended_reason,rrd.star,st.lat,st.lng,st.address,st.store_name,st.cover as store_logo'
                )->select();
            foreach ( $list['detail'] as &$v){
                $v['style'] = Db::name('store_style_store')->alias('sss')
                    ->join('style_store ss','sss.style_store_id = ss.id','LEFT')
                    ->where('sss.store_id','EQ',$v['store_id'])
                    ->where('sss.delete_time is null')
                    ->field('ss.id,ss.title'
                    )->select();
        }
        }
        $total = Db::name('roommate_recommend')
            ->where('status','EQ',1)
            ->field('id,title,description,bg_cover')
            ->count();
        $list['periodical']=(string)$list['id'].'/'.(string)$total.'期';
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
        $dynamics=  array_unique(array_merge($dynamic_ids,$dynamics));
        $total=count($dynamics);
        $distance='IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0)';
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
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat','IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0) as distance',
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->limit(($page-1)*$size,$size)
            ->order($order)
            ->select();
        foreach ($list as &$v){
            $v['user_look_list'] = Db::name('dynamic_user_record')->alias('dur')
                ->join('user u','dur.user_id = u.user_id','LEFT')
                ->where('dur.dynamic_id','EQ',$v['id'])
                ->field('u.user_id,u.avatar')
                ->limit(30)
                ->order('dur.create_time desc')
                ->select();

            if($v['type']==1){
                $v['dynamic_images'] = Db::name('dynamic_img')
                    ->where('dynamic_id','EQ',$v['id'])
                    ->where('can_use','EQ',1)
                    ->field('img_url,type,cover')
                    ->limit(4)
                    ->order('id asc')
                    ->select();
            }else{
                $v['dynamic_images'] = Db::name('dynamic_img')
                    ->where('dynamic_id','EQ',$v['id'])
                    ->where('can_use','EQ',1)
                    ->where('type','EQ',2)
                    ->field('img_url,type,cover')
                    ->limit(1)
                    ->order('id asc')
                    ->select();
            }
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
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type','d.is_group_buy',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
                'IF(sf.create_time > 0 ,1 ,0) is_follow',
            ])
            ->find();

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
        $product= Db::name('dynamic_product')->alias('dp')
            ->join('product p','dp.product_id = p.id','LEFT')
            ->where('dp.dynamic_id','EQ',$id )
            ->where('p.status','EQ',1 )
            ->field('dp.id,dp.dynamic_id,dp.product_id')
            ->select();
            if($product){
                $total_huaxian=0;//总划线价
                $total_money=0;//总金额
                $price= Db::name('dynamic_product_specs')->field('product_id,MIN(price) as price,dynamic_id')->where('dynamic_id','EQ',45)->order('price ASC')->group('product_id')->select();
                $specs_id=[];
                $product_info=[];
                foreach ($price as $k=>$v){
                    $specs_id[$k]= Db::name('dynamic_product_specs')
                        ->where('dynamic_id','EQ',$v['dynamic_id'])
                        ->where('product_id','EQ',$v['product_id'])
                        ->where('price','EQ',$v['price'])
                        ->value('specs_id');
                    $products= Db::name('product_specs')->alias('ps')
                        ->join('product p','ps.product_id = p.id ','LEFT')
                        ->where('ps.id','EQ',$specs_id[$k] )
                        ->where('ps.product_id','EQ',$v['product_id'] )
                        ->field('ps.id,ps.cover,ps.product_specs,p.product_name,ps.price,ps.group_buy_price,ps.huaxian_price,ps.stock,ps.share_img,ps.platform_price')
                        ->find();

                    $product_info[$k]['specs_id'] = $products['id'];
                    $product_info[$k]['cover'] = $products['cover'];
                    $product_info[$k]['product_specs'] = $products['product_specs'];
                    $product_info[$k]['product_name'] = $products['product_name'];
                    $product_info[$k]['price'] = $data['is_group_buy']==1?$v['price']:$products['price'];
                    $product_info[$k]['group_buy_price'] = $products['group_buy_price'];
                    $product_info[$k]['stock'] = $products['stock'];
                    $product_info[$k]['huaxian_price'] = $products['price'];
                    $product_info[$k]['share_img'] = $products['share_img'];
                    $product_info[$k]['platform_price'] = $products['platform_price'];
                    //判断是否支持打包购买
                    if($data['is_group_buy']==1){
                        $total_money+=$v['price'];
                    }else{
                        $total_money+=$products['price'];
                    }
                }
                $data['product_info']=$product_info;
                $data['total_money']=$total_money;
            }else{
                $data['product_info']=[];
            }
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
            if($comment){$data['$comment']=$comment;}else{$data['$comment']=[];}

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
    static public function GetDynamicDetailRecommend($user_id,$page,$size,$lat,$lng)
    {
        //默认推荐+权重
        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $distance='IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0)';
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('topic t', 'd.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('dynamic_dianzan dd', 'd.id = dd.dynamic_id AND dd.user_id='.$user_id, 'LEFT')  //  关联点赞表
            ->where('d.status','EQ',1)
            ->where('st.store_status','EQ',1)
            ->where('d.delete_time is null')
            ->field(['d.id','d.store_id','d.create_time','d.cover','d.title','d.share_number','d.like_number','d.collect_number','d.comment_number','d.type',
                'st.store_name','st.cover as store_logo','st.address','st.signature','st.lng','st.lat','IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0) as distance',
                't.title as topic_title','t.id as topic_id','t.title as topic_title',
                '(look_number*0.1+d.like_number*0.3+d.share_number*0.35+'.$distance*0.25.')/4 as weight',
                'IF(dd.create_time > 0 ,1 ,0) is_dianzan',
            ])
            ->limit(($page-1)*$size,$size)
            ->order($order)
            ->select();
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
        $b_user_id=intval(trim($params['b_user_id']));

        //  动态数据是否存在
        $dynamic=DynamicModel::where(['id' => $params['dynamic_id'], 'status' => 1])
            -> field('id,store_id,comment_number')-> find();
        if (!$dynamic)  return '未找到该条动态!';
        $insertData = [
            'dynamic_id'  => $params['dynamic_id'],
            'user_id'     => $params['user_id'],
            'content'     => htmlspecialchars($params['content']),
            'create_time' => time(),
            'pid'         => (isset($pid) && is_numeric($pid) && $pid > 0) ? $pid : 0,
            'b_user_id'   => (isset($b_user_id) && is_numeric($b_user_id) && $b_user_id > 0) ? $b_user_id : 0,
            'support'     => 0,
            'hate'        => 0
        ];
        $result = CommentModel::insertGetId($insertData);
        if($result===false){return '评论失败';}
        DynamicModel::where('id', $dynamic['id']) -> setInc('comment_number', 1);
        return $result ? true : false;
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
            -> count();
        if($total<=0){return [];}
        $list = Db::name('new_trend')->alias('nt')
            ->join('topic t', 'nt.topic_id = t.id', 'LEFT')  //  关联话题表
            ->join('new_trend_dianzan ntd', 'nt.id = ntd.new_trend_id AND ntd.user_id = '.$user_id, 'LEFT')  //  关联点赞表
            -> field(['nt.id', 'nt.title', 'nt.cover', 'nt.content', 'nt.create_time','nt.visit_number','nt.share_number','nt.like_number','nt.comment_number','nt.collect_number','t.title as topic_title',
                'IF(ntd.create_time > 0, 1, 0)  is_dianzan',
                ])
            ->where('nt.status','EQ',1 )
            ->page($page,$size)
            ->order('id DESC')
            -> select();
            foreach ($list as &$v){
                $v['hot']=$v['visit_number']+$v['share_number']+$v['like_number']+$v['comment_number']+$v['collect_number'];
                $v['styles'] = Db::name('new_trend_style')->alias('nts')
                    ->join('style_store ss','nts.style_id = ss.id And nts.type=1','LEFT')
                    ->join('style_product sp','nts.style_id = sp.id And nts.type=2','LEFT')
                    ->where('nts.new_trend_id','EQ',$v['id'] )
                    ->field('nts.style_id,nts.type,sp.title as product_style,ss.title as store_style')
                    ->select();
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
            ->where('nt.id','EQ',$id )
            -> find();
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
            'b_user_id'   => (isset($b_user_id) && is_numeric($b_user_id) && $b_user_id > 0) ? $b_user_id : 0
        ];
        $result = NewTrendCommentModel::insertGetId($insertData);
        if($result===false){return '评论失败';}
        NewTrendModel::where('id', $newtrend['id']) -> setInc('comment_number', 1);
        return $result ? true : false;
    }
    /**turtle购筛选查询
     *title
     */
    static public function GetTurtleSelect()
    {
       //分类
        $list[0]['title']='店铺分类';
        $list[0]['data'] = $store_ids = self::GetStoreCategory();
        $list[1]['title']='风格';
        $list[1]['data'] = $store_ids = self::GetStoreStyle();
        $list[2]['title']='商圈';
        $list[2]['data'] = $store_ids = self::GetBusinessCircle();
        return $list;
    }
}