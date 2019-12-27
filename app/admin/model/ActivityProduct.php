<?php


namespace app\admin\model;


use think\Model;
use app\admin\model\ActivityType as ActivityTypeModel;

class ActivityProduct extends Model
{

    public static function del($activity_id){
        return (new self())->where(['activity_id'=>$activity_id])->delete();
    }

    public static function add($data){
        return (new self())->insertAll($data);
    }

    /**
     * 获取在活动中，草稿中，待上线的活动商品
     * @param $activity_id
     * @return array
     */
    public static function productInActivity($activity_id){
        $list = (new self())->alias('ap')
            ->join('activity a','a.id = ap.activity_id','LEFT')
            ->where(['a.end_time'=>['GT',time()],'a.status'=>['IN',[1,2]],'a.id'=>['NEQ',$activity_id]])
            ->column('ap.product_id');
        return $list;
    }

    /**
     * 获取当前活动商品
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityPro($activity_id){
        return (new self())->alias('ap')
            ->join('product p','ap.product_id = p.id','LEFT')
            ->where(['ap.activity_id'=>$activity_id])
            ->field('ap.store_id,ap.product_id,p.product_name,ap.type_id')
            ->select();
    }

    /**
     * 获取店铺活动商品
     * @param $activity_id
     * @return array
     */
    public static function getActivityStorePro($activity_id){
        $stores = self::getActivityStore($activity_id);
        $pro_list = [];
        foreach($stores as $v){
            $pro_list[$v['store_id']]['store_id'] = $v['store_id'];
            $pro_list[$v['store_id']]['name'] = $v['store_name'];
            $pro_list[$v['store_id']]['pro_data'] = self::getActivityProByStoreId($activity_id, $v['store_id']);
            $pro_list[$v['store_id']]['count'] = self::countAcProByStoreId($activity_id, $v['store_id']);
        }
        return $pro_list;
    }

    /**
     * 获取
     * @param $activity_id
     * @return array
     */
    public static function getActivityTypePro($activity_id){
        $types = ActivityTypeModel::getActivityType($activity_id);
        $pro_list = [];
        foreach($types as $v){
            $pro_list[$v['id']]['type_id'] = $v['id'];
            $pro_list[$v['id']]['name'] = $v['type_name'];
            $pro_list[$v['id']]['pro_data'] = self::getActivityProByTypeId($activity_id, $v['id']);
            $pro_list[$v['id']]['count'] = self::countAcProByTypeId($activity_id, $v['id']);
        }
        return $pro_list;
    }

    /**
     * 获取参加当前活动的商家id
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityStore($activity_id){
        return (new self())->alias('as')
            ->join('store s','as.store_id = s.id','LEFT')
            ->where(['as.activity_id'=>$activity_id])
            ->field('as.store_id,s.store_name')
            ->group('as.store_id')
            ->select();
    }

    /**
     * 通过店铺id获得该活动的商品信息
     * @param $activity_id
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getActivityProByStoreId($activity_id, $store_id){
        $list = (new self())->alias('as')
            ->join('product_specs ps','as.product_id = ps.product_id','RIGHT')
            ->where(['as.activity_id'=>$activity_id,'as.store_id'=>$store_id])
            ->field('ps.cover,ps.product_name,as.product_id,ps.price,ps.price_activity_temp')
            ->select();
        return json_decode(json_encode($list),true);
    }

    /**
     * 通过type_id获得该活动的商品信息
     * @param $activity_id
     * @param $type_id
     * @return mixed
     */
    public static function getActivityProByTypeId($activity_id,$type_id){
        $list = (new self())->alias('as')
            ->join('product_specs ps','as.product_id = ps.product_id','RIGHT')
            ->where(['as.activity_id'=>$activity_id,'as.type_id'=>$type_id])
            ->field('ps.cover,ps.product_name,as.product_id,ps.price,ps.price_activity_temp')
            ->select();
        return json_decode(json_encode($list),true);
    }

    /**
     * 获取活动下某店铺的商品数量
     * @param $activity_id
     * @param $store_id
     * @return int|string
     */
    public static function countAcProByStoreId($activity_id, $store_id){
        return (new self())->where(['activity_id'=>$activity_id,'store_id'=>$store_id])->count('id');
    }

    /**
     * 获取活动下某类别的商品数量
     * @param $activity_id
     * @param $type_id
     * @return int|string
     */
    public static function countAcProByTypeId($activity_id, $type_id){
        return (new self())->where(['activity_id'=>$activity_id,'type_id'=>$type_id])->count('id');
    }

}