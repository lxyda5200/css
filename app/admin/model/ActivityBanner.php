<?php


namespace app\admin\model;


use think\Model;

class ActivityBanner extends Model
{

    protected $autoWriteTimestamp = false;

    /**
     * 新增banner
     * @param $data
     * @return int|string
     */
    public static function add($data){
        $data['create_time'] = time();
        return (new self())->insertGetId($data);
    }

    /**
     * 更新banner
     * @param $id
     * @param $data
     * @return ActivityBanner
     */
    public static function edit($id, $data){
        return (new self())->where(['id'=>$id])->update($data);
    }

    /**
     * 根据id获取banner
     * @param $ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getBannerByIds($ids){
        return (new self())->where(['id'=>['IN',$ids]])->field('id,img as banner,link_id,type')->select();
    }

}