<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/6
 * Time: 17:40
 */

namespace app\user_v4\model;


use think\Model;

class GoodsOrderDetail extends Model
{
    public function goodsOrder(){
        return $this->belongsTo('GoodsOrder','order_id');
    }

    public function goods()
    {
        return $this->hasOne('Goods','id','goods_id')->field('id,goods_name,description,price');
    }

    /**
     * 获取用户购物车商品信息
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoodsOrderDetailInfo($order_id){
        $goodsInfo = self::with('goods,goods.goodsImg')->where('order_id',$order_id)->field('id,goods_id,number')->select();
        return $goodsInfo;
    }
}