<?php
/**
 * Created by PhpStorm.
 * User: win 10
 * Date: 2018/8/2
 * Time: 20:25
 */

namespace app\user_v2\model;


use think\Model;

class Goods extends Model
{

    protected $hidden = ['create_time'];

    public function goodsImg()
    {
        return $this->hasMany('GoodsImg','goods_id','id');
    }

    public function goodsComment(){
        return $this->hasMany('GoodsComment','goods_id','id');
    }

    public function shoppingCart(){
        return $this->belongsTo('ShoppingCart');
    }

    public function goodsOrderDetail(){
        return $this->belongsTo('GoodsOrderDetail');
    }

    /**
     * 查询商品详情
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoodsDetail($id){
        $goodsInfo = self::with('goodsImg')->field('id,class_id,goods_name,description,spec,unit,price,number,status,sales')->find($id);
        return $goodsInfo;
    }

    /**
     * 查询全部商品列表
     * @param string $page
     * @param string $size
     * @param array $where
     * @param array $order
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoodsList($page='',$size='',$where=[],$order=[]){
        $goodsInfo = self::with('goodsImg')->where($where)->field('id,goods_name,price')->page($page,$size)->order($order)->select();
        return $goodsInfo;
    }


}