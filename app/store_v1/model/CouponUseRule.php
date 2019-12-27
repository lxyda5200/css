<?php


namespace app\store_v1\model;


use think\Model;
use traits\model\SoftDelete;

class CouponUseRule extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    /**
     * 获取公共规则列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getCommonLists(){
        return (new self())->where(['is_common'=>1, 'status'=>1])->field('id,title')->order('create_time','desc')->select();
    }

    /**
     * 获取优惠券模板
     * @param $ids
     * @return array
     */
    public static function getRuleTitles($ids){
        return (new self())->where(['id'=>['IN',$ids]])->column('title');
    }

    /**
     * 添加自定义规则
     * @param $data
     * @return int|string
     */
    public static function addDiyRule($data){
        return (new self())->insertGetId($data);
    }

}