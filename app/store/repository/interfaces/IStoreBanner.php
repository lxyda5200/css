<?php
namespace app\store\repository\interfaces;

interface IStoreBanner
{
    /**
     * 获取banner广告列表
     * @param $store_id  商户id
     * @return mixed
     */
    public static function getList($store_id);

    /**
     * 添加banner广告
     * @param $data  banner广告数据
     * @return mixed
     */
    public static function addBanner($data);

    /**
     * 删除banner广告
     * @param $id
     * @return mixed
     */
    public static function delBanner($id);

    /**
     * 获取单个banner广告信息
     * @param $id   banner广告id
     * @return mixed
     */
    public static function getBannerInfo($id);

    /**
     * 更新banner广告信息
     * @param $id   banner广告id
     * @param $data   banner广告数据
     * @return mixed
     */
    public static function updateBannerInfo($id, $data);

    /**
     * 修改banner广告排序
     * @param $store_id   商户id
     * @param $id    banner广告id
     * @param $sort   banner广告排序
     * @return mixed
     */
    public static function changeSort($store_id, $id, $sort);
}