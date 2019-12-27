<?php

namespace app\user_v5\model;
use app\user_v5\common\Logic;
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

        $list = Db::view('product','id,huoli_money,see_type,buy_type,product_name,share_price,is_zdy_price,product_type,days')
            ->view('product_specs','product_specs,price,share_img,huaxian_price,price_activity_temp','product_specs.product_id = product.id','left')
            ->view('store','store_status','product.store_id = store.id','left')
            ->where($where)
            ->where('product.status',1)
            ->where('store.store_status',1)
            ->where(['product_specs.id'=>['GT',0]])
            ->limit($page*$size,$size)
            ->group('product.id')
            ->order('')
            ->select();

        if($list){

            ##获取活动中且需要改变价格的商品ids
            $product_ids = Logic::getActivityPros();

            ##获取活动中的商品ids
            $ac_pro = Logic::getOnlineAcProIds();

        }

        foreach ($list as $k=>$v){
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
            $list[$k]['product_imgs'] = Db::name('product_img')->where('product_id',$v['id'])->column('img_url');
            if(isset($ac_pro)){
                $list[$k]['rule'] = in_array($v['id'],$ac_pro['product_ids'])?$ac_pro['ac_pro'][$v['id']]:"";
            }else{
                $list[$k]['rule'] = "";
            }
            if(isset($product_ids)){
                if(in_array($v['id'],$product_ids))$list[$k]['price'] = $v['price_activity_temp'];
            }
        }

        $data['total'] = $total;
        $data['max_page'] = ceil($total/$size);
        $data['list'] = $list;

        return $data;
    }
}
