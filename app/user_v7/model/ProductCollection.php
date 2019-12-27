<?php


namespace app\user_v7\model;


use think\Model;
use think\model\relation\HasOne;

class ProductCollection extends Model
{
    protected $resultSetType = '\think\Collection';
    protected $dateFormat=false;
    /**
     * 获取用户商品收藏数
     * @param $user_id
     * @return int|string
     */
    public static function userCollectNum($user_id){
        $num = (new self())->alias('pc')
            ->join('product_specs ps','pc.specs_id = ps.id','RIGHT')
            ->join('product p','p.id = pc.product_id','RIGHT')
            ->join('store s','s.id = p.store_id','RIGHT')
            ->where('pc.user_id',$user_id)
            ->where('ps.id',['GT',0])
            ->where(['p.status'=>1,'p.sh_status'=>1,'s.store_status'=>1])
            ->count();
        return $num;
    }

    /**
     * 获取用户收藏商品信息
     * @param $user_id
     * @param $product_ids
     * @return array
     */
    public static function getUserCollectionPro($user_id, $product_ids){
        $pro_list = (new self())->alias('pc')
            ->join('product p','p.id = pc.product_id','LEFT')
            ->join('store s','s.id = p.store_id','LEFT')
            ->with([
                'productSpecs' => function(HasOne $hasOne){
                    $hasOne->field('price,huaxian_price,cover,product_id')->order('price','desc');
                }
            ])
            ->where([
                'pc.user_id' => $user_id,
                'p.id' => ['NOT IN', $product_ids],
                's.sh_status' => 1,
                's.store_status' => 1,
                's.type' => 1,
                'p.sh_status' => 1,
                'p.status' => 1
            ])
            ->order('pc.create_time','desc')
            ->field('p.product_name,pc.product_id')
            ->select()
            ->toArray();
        foreach($pro_list as $k =>$v){
            $pro_list[$k]['price'] = $v['product_specs']['price'];
            $pro_list[$k]['huaxian_price'] = $v['product_specs']['huaxian_price'];
            $pro_list[$k]['cover'] = $v['product_specs']['cover']?:"";
            unset($pro_list[$k]['product_specs']);
        }
        return $pro_list;
    }

    /**
     * 一对多 商品规格
     * @return \think\model\relation\HasOne
     */
    public function productSpecs(){
        return $this->hasOne('ProductSpecs','product_id','product_id');
    }

}