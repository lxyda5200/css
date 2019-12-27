<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/17
 * Time: 13:52
 */

namespace app\admin\model;


use think\Model;

class Product extends Model
{

    /**
     * 获取店铺选择商品的列表
     * @param $store_id
     * @param $pro_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getProductByStore($store_id, $pro_ids){
        return (new self())->where(['store_id'=>$store_id,'status'=>1,'sh_status'=>1, 'id'=>['NOT IN', $pro_ids]])->field('id,product_name')->select();
    }

    /**
     * 更新商品人工干预得分
     * @param $id
     * @param $score_meddle
     * @return int
     */
    public static function updateScoreMeddle($id, $score_meddle){
        return (new self())->where(['id'=>$id])->setField('score_meddle',$score_meddle);
    }

    /**
     * 清除商品分类
     * @param $cate_id 分类id
     * @return false|int
     */
    public static function updateProCate($cate_id){
        return (new self())->save(['cate_id'=>0],compact('cate_id'));
    }

    /**
     * 获取人气单品筛选商品列表
     * @param $where
     * @param $page
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function getPopProList($where, $page){
        $list = $this->alias('p')
            ->join('store s','s.id = p.store_id','LEFT')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->where($where)
            ->group('p.id')
            ->field(
                'p.id,p.product_name,p.read_number,
                      ps.cover,
                      s.store_name,s.mobile,s.address
            ')
            ->with(['styles'])
            ->paginate(10,false,['page'=>$page])
            ->toArray();
        $list['max_page'] = ceil($list['total']/$list['per_page']);

        return $list;
    }

    /**
     * 商品风格
     * @return \think\model\relation\HasMany
     */
    public function styles(){
        return $this->hasMany('ProductStyleProduct','product_id','id')->alias('sps')
            ->join('style_product ps','sps.style_product_id = ps.id','LEFT')
            ->where(['ps.delete_time'=>null])
            ->field('
                ps.id as style_id,ps.title,sps.id,sps.product_id
            ');
    }

    /**
     * 获取品牌筛选商品列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getProList(){
        $page = input('post.page',1,'intval');
        $keywords = input('post.keywords','','trimStr');
        $where = [
            'p.status' => 1,
            'p.sh_status' => 1,
            's.sh_status' => 1,
            's.store_status' => 1
        ];
        if($keywords)$where['p.product_name'] = ['LIKE',"%{$keywords}%"];

        $list = $this->alias('p')
            ->join('store s','s.id = p.store_id','LEFT')
            ->where($where)
            ->field('
                p.id,p.product_name,p.read_number,
                s.store_name,s.mobile,s.address
            ')
            ->with(['specs'])
            ->paginate(12,false,['page'=>$page])
            ->toArray();

        $list['max_page'] = ceil($list['total']/$list['per_page']);
        return $list;
    }

    /**
     * 获取价格最低的规格
     * @return \think\model\relation\HasOne
     */
    public function specs(){
        return $this->hasOne('ProductSpecs','product_id','id')->field('id,product_id,cover,price,product_name')->order('price','desc');
    }

}