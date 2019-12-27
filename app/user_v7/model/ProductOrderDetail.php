<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/20
 * Time: 9:49
 */

namespace app\user_v7\model;


use think\Model;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

class ProductOrderDetail extends Model
{

    /**
     * 获取用户下单30天内商品信息
     * @param $user_id
     * @return false|mixed|\PDOStatement|string|\think\Collection
     */
    public static function getUserOrderProNearMonth($user_id){
        $limit_time = strtotime("-1 month");
        $pro_list = (new self())->alias('pod')
            ->join('product_order po','po.id = pod.order_id','LEFT')
            ->join('product p','p.id = pod.product_id','LEFT')
            ->join('store s','s.id = po.store_id','LEFT')
            ->with([
                'productSpecs' => function(HasOne $hasOne){
                    $hasOne->field('price,huaxian_price,cover,product_id')->order('price','desc');
                }
            ])
            ->where([
                'po.user_id' => $user_id,
                'po.create_time' => ['EGT', $limit_time],
                's.sh_status' => 1,
                's.store_status' => 1,
                's.type' => 1,
                'p.sh_status' => 1,
                'p.status' => 1
            ])
            ->group('pod.product_id')
            ->order('po.create_time','desc')
            ->field('p.product_name,pod.product_id')
            ->select();
        $pro_list = json_decode(json_encode($pro_list),true);
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