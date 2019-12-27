<?php


namespace app\store\model;


use think\Db;
use think\Model;

class BrandStoryAds extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'brand_story_ads';


    /**
     * 添加品牌故事广告
     * @param $data
     * @return int|string
     */
    public static function addBrandStoryAds($data) {
        return self::insertAll($data);
    }


    /**
     * 删除广告
     * @param $brand_id
     * @return int
     */
    public static function delBrandStoryAds($brand_id) {
        $res = self::where(['brand_id' => $brand_id])->select();
        if(!$res)
            return true;
        return self::where(['brand_id' => $brand_id])->delete();
    }


    /**
     * 获取广告
     * @param $brand_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getAds($brand_id) {
        return self::where(['brand_id' => $brand_id, 'status' => 1])->field('id, url, type, cover, media_id')
            ->order('sort desc')->select();
    }


    /**
     * 更改排序
     * @param $id
     * @param $store_id
     * @param $sort
     * @return bool
     * @throws \think\Exception
     */
    public static function changeSort($id, $store_id, $sort) {
        $brand_story_id = Db::table('brand_store')->alias('bs')
            ->join(['brand_story' => 'bsy'], 'bs.brand_id=bsy.brand_id')
            ->where(['bs.store_id' => $store_id, 'type' => 2])->value('bsy.id');
        if(!$brand_story_id)
            return false;

        ##获取以前的排序
        $prev_sort = self::where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)
            return true;
        ##更新
        if($prev_sort > $sort){
            $ids = self::where(['sort'=>['BETWEEN',[$sort,$prev_sort]],'brand_story_id'=>$brand_story_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setInc('sort',1);
        }else{
            $ids = self::where(['sort'=>['BETWEEN',[$prev_sort,$sort]],'brand_story_id'=>$brand_story_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setDec('sort',1);
        }
        $res = self::where(['id'=>$id])->setField('sort', $sort);
        if($res === false)
            return false;
        return true;
    }
}