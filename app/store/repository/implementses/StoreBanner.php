<?php
namespace app\store\repository\implementses;

use \app\store\model\StoreBanner as StoreBannerModel;
use app\store\repository\interfaces\IStoreBanner;

class StoreBanner implements IStoreBanner
{
    /**
     * 获取APP店铺banner广告列表
     * @param $store_id
     * @return false|PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getList($store_id) {
        return StoreBannerModel::where(['store_id' => $store_id, 'status' => 1])
            ->field('id, title, banner_type, cover, type, link, content')
            ->order('sort', 'asc')
            ->select();
    }


    /**
     * 添加APP店铺banner广告
     * @param $data
     * @return StoreBannerModel
     */
    public static function addBanner($data) {
        return StoreBannerModel::create($data);
    }


    /**
     * 删除APP店铺banner广告
     * @param $id
     * @return int
     */
    public static function delBanner($id) {
        return StoreBannerModel::destroy($id);
    }


    /**
     * 获取单个banner信息
     * @param $id
     * @return StoreBannerModel|null
     * @throws \think\exception\DbException
     */
    public static function getBannerInfo($id) {
        return StoreBannerModel::get($id);
    }


    /**
     * 更新banner信息
     * @param $id
     * @param $data
     * @return StoreBannerModel
     */
    public static function updateBannerInfo($id, $data) {
        return StoreBannerModel::where(['id' => $id])->update($data);
    }


    /**
     * 更改排序
     * @param $store_id
     * @param $id
     * @param $sort
     * @return bool
     * @throws \think\Exception
     */
    public static function changeSort($store_id, $id, $sort) {
        ##获取以前的排序
        $prev_sort = StoreBannerModel::where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)
            return true;
        ##更新
        if($prev_sort > $sort){
            $ids = StoreBannerModel::where(['sort'=>['BETWEEN',[$sort,$prev_sort]],'store_id'=>$store_id])->column('id');
            foreach($ids as $v)
                StoreBannerModel::where(['id'=>$v])->setInc('sort',1);
        }else{
            $ids = StoreBannerModel::where(['sort'=>['BETWEEN',[$prev_sort,$sort]],'store_id'=>$store_id])->column('id');
            foreach($ids as $v)
                StoreBannerModel::where(['id'=>$v])->setDec('sort',1);
        }
        $res = StoreBannerModel::where(['id'=>$id])->setField('sort', $sort);
        if($res === false)
            return false;
        return true;
    }
}