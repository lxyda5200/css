<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/15
 * Time: 10:04
 */

namespace app\store_v1\model;


use think\Model;

class Product extends Model
{

    protected $resultSetType = '\think\Collection';

    /**
     * 获取优惠券绑定商品
     * @param $ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProNames($ids){
        return (new self())->where(['id'=>['IN',$ids]])->field('id,product_name')->select();
    }

    /**
     * 获取动态推荐商品列表
     * @return array
     */
    public function getRecomProductList(){
        $product_ids = input('post.product_ids','','addslashes,strip_tags,trim');
        $product_ids = explode(',',trim($product_ids,','));
        $keywords = input('post.keywords','','addslashes,strip_tags,trim');
        $page = input('post.page',1,'intval');
        $data =  $this->alias('p')
            ->join('store s','s.id = p.store_id','LEFT')
            ->where([
                'p.product_name'=>['LIKE',"%{$keywords}%"],
                'p.sh_status' => 1,
                'p.id'=>['NOT IN',$product_ids],
                's.sh_status' => 1,
                's.store_status' => 1
            ])
            ->field('
                p.id,p.product_name,
                s.store_name,s.mobile,s.address
            ')
            ->with(['specsPriceMin'])
            ->paginate(9,false,['page'=>$page])
            ->toArray();

        $data['max_page'] = ceil($data['total']/$data['per_page']);

        return $data;
    }

    /**
     * 一对一 获取最低价格的规格
     * @return \think\model\relation\HasOne
     */
    public function specsPriceMin(){
        return $this->hasOne('ProductSpecs','product_id','id')->field('id as specs_id,product_id,price,cover')->order('price','desc');
    }


    /**
     * 获取商家所有产品
     * @param $store_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function storeProductMin($store_id, $key = '') {
        $where = ['p.store_id' => $store_id, 'p.status' => 1, 'p.sh_status' => 1];
        if(!empty($key))
            $where['p.product_name'] = ['like', "%{$key}%"];

        return $this->alias('p')
            ->join(['product_specs' => 'ps'], 'ps.product_id=p.id')
            ->join(['store' => 's'], 's.id=p.store_id')
            ->where($where)
            ->group('ps.product_id')
            ->field('p.id, s.store_name, s.address, ps.cover, ps.product_name, min(ps.price) as price')
            ->paginate(3)->toArray();
    }

}