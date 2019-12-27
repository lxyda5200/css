<?php

namespace app\user_v6\model;
use app\user_v6\common\Logic;
use think\Db;
use think\Model;

class Product extends Model
{

    /**根据关键字搜索会员店铺商品
     * @param $words 关键字
     * @param $page 页码
     * @param $size 每页条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function getProductsByKeyWords($words,$page = 0,$size = 10)
    {
        $where['product_name'] = ['like',"%$words%"];
//        $total = Db::name('product')
//            ->where($where)
//            ->where('status',1)
//            ->count();

        $list = Db::view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days')
            ->view('product_specs','product_specs,price,share_img,huaxian_price','product_specs.product_id = product.id','left')
            ->where($where)
            ->where('product.status',1)
//            ->page($page,$size)
            ->limit($size)
            ->group('product_id')
            ->order('')
            ->select();

//        if ($total == 0 || empty($list)) return [];
        foreach ($list as $k=>$v){
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
            $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
        }

        $total = 100;
        $data['total'] = $total;
        $data['max_page'] = ceil($total/$size);
        $data['list'] = $list;
        return $list;
    }










    /**根据关键字搜索会员店铺商品
     * @param $words 关键字
     * @param $page 页码
     * @param $size 每页条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function getProductsByKeyWordsForIos($words,$page = 0,$size = 10)
    {
        $where['product_name'] = ['like',"%$words%"];
//        $total = Db::name('product')
//            ->where($where)
//            ->where('status',1)
//            ->count();

        $list = Db::view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days')
            ->view('product_specs','product_specs,price,share_img,huaxian_price','product_specs.product_id = product.id','left')
            ->where($where)
            ->where('product.status',1)
//            ->page($page,$size)
            ->limit($size)
            ->group('product_id')
            ->order('')
            ->select();

//        if ($total == 0 || empty($list)) return [];
        foreach ($list as $k=>$v){
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
            $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
        }

        $total = 100;
        $data['total'] = $total;
        $data['max_page'] = ceil($total/$size);
        $data['list'] = $list;
        return $list;
    }

    /**根据关键字搜索会员店铺商品
     * @param $words 关键字
     * @param $page 页码
     * @param $size 每页条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function getProductsByKeyWordsForIos0817($words,$page = 1,$size = 10)
    {
        $where['product_name'] = ['like',"%$words%"];
        $total = Db::name('product')
            ->where($where)
            ->where('status',1)
            ->count();

        $list = Db::view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days,is_buy')
            ->view('product_specs','id as specs_id,product_specs,price,share_img,huaxian_price','product_specs.product_id = product.id','left')
            ->view('store','store_status,lat,lng','product.store_id = store.id','left')
            ->where($where)
            ->where('product.status',1)
            ->where('store.store_status',1)
            ->where(['product_specs.id'=>['GT',0]])
            ->limit($page*$size,$size)
            ->group('product.id')
            ->order('')
            ->select();

        ##获取活动中且需要改变价格的商品ids
        $product_ids = Logic::getActivityPros();
        ##获取活动中的商品ids
        $ac_pro = Logic::getOnlineAcProIds();

        foreach ($list as $k=>$v){
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
            $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');

            if(in_array($v['id'],$product_ids))$list[$k]['price'] = $v['price_activity_temp'];
            $list[$k]['rule'] = in_array($v['id'],$ac_pro['product_ids'])?$ac_pro['ac_pro'][$v['id']]:"";

        }
        $data['total'] = $total;
        $data['max_page'] = ceil($total/$size);
        $data['list'] = $list;

        return $data;
    }
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
    static public function GetStoreFollowDynamic($user_id,$page,$size)
    {
        $store_ids = self::GetStoreFollowIds($user_id);
        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.store_id','in',$store_ids)
            -> field([
                'd.id','d.store_id','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->join('dynamic_collection dc', 'd.id = dc.dynamic_id and dc.user_id = '.$user_id, 'left')  //  关联收藏表
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->where('d.store_id','in',$store_ids)
            -> field([
                'd.id','d.store_id','d.cover','d.title','(d.visit_number + d.look_number) as look_number','d.share_number','d.like_number','d.collect_number','d.comment_number',
                'st.store_name', 'st.cover as store_logo','st.address','st.signature','st.lng','st.lat',
                'IF(dc.create_time > 0 ,1 ,0) is_collect',
            ])
            ->limit(($page-1)*$size,$size)
            ->order('d.create_time desc')
            ->select();
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
    static public function GetRecommendStoreFollow($page,$size)
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
            ->where('sf.store_id','GT',0)
            ->where('sf.type','EQ',1)
            ->group('sf.store_id')
            ->field('sf.store_id,s.store_name,s.cover,s.signature,count(sf.store_id) as follow_number')
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
    static public function GetHotDynamic($page,$size)
    {
        $total = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->field('d.store_id,st.store_name,st.cover as store_logo,st.address,st.signature,st.lng,st.lat,d.cover,d.title,(d.visit_number + d.look_number) as look_number,d.share_number,d.like_number,d.collect_number,d.comment_number')
            ->count();
        $list = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            ->where('d.status','EQ',1)
            ->where('d.delete_time is null')
            ->field('d.store_id,st.store_name,st.cover as store_logo,st.address,st.signature,st.lng,st.lat,d.cover,d.title,(d.visit_number + d.look_number) as look_number,d.share_number,d.like_number,d.collect_number,d.comment_number')
            ->limit(($page-1)*$size,$size)
            ->order('d.create_time desc')
            ->select();
        $data['type']=2;
        $data['page']=$page;
        $data['total']=$total;
        $data['max_page'] = ceil($total/$size);
        $data['data']=$list;
        return $data;
    }

}
