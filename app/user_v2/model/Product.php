<?php

namespace app\user_v2\model;
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
            ->view('product_specs','product_specs,price,share_img,huaxian_price','product_specs.product_id = product.id','left')
            ->where($where)
            ->where('product.status',1)
            ->where(['product_specs.id'=>['GT',0]])
            ->limit($page*$size,$size)
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

        return $data;
    }
}
