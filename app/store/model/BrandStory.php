<?php


namespace app\store\model;


use think\Model;

class BrandStory extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'brand_story';


    /**
     * 添加品牌故事
     * @param $data
     * @return int|string
     */
    public static function addBrandStory($data) {
        return self::insertGetId($data);
    }


    /**
     * 更新品牌故事
     * @param $brand_id
     * @param $data
     * @return BrandStory
     */
    public static function updateBrandStory($brand_id, $data) {
        return self::where(['brand_id' => $brand_id])->update($data);
    }


    /**
     * 获取品牌故事id
     * @param $brand_id
     * @return mixed
     */
    public static function getStoryId($brand_id) {
        return self::where(['brand_id' => $brand_id])->value('id');
    }


    /**
     * 获取品牌故事基本信息
     * @param $brand_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getStoryInfo($brand_id) {
        return self::where(['brand_id' => $brand_id])->field('history, notion')->find();
    }
}