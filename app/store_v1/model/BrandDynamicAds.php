<?php


namespace app\store_v1\model;


use think\Db;
use think\Model;

class BrandDynamicAds extends Model
{
    /**
     * 定义操作表
     * @var string
     */
    protected $table = 'brand_dynamic_ads';


    /**
     * 广告类型获取器
     * @param $val
     * @return mixed
     */
    public function getTypeAttr($val) {
        $type = [
            1 => '图片广告',
            2 => '视频广告'
        ];
        return $type[$val];
    }


    /**
     * 添加时尚动态广告
     * @param $data
     * @return int|string
     */
    public function addDynamicAds($data) {
        return $this->insertGetId($data);
    }


    /**
     * 获取时尚动态广告列表
     * @param $brand_dynamic_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDynamicAdsList($brand_dynamic_id) {
        return $this->where(['brand_dynamic_id' => $brand_dynamic_id,'status' => 1])->select();
    }


    /**
     * 获取单个时尚动态广告内容
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDynamicAds($id) {
        return $this->where(['id' => $id])->find();
    }


    /**
     * 编辑时尚动态广告内容
     * @param $id
     * @param $data
     * @return BrandDynamicAds
     */
    public function editDynamicAds($id, $data) {
        return $this->where(['id' => $id])->update($data);
    }


    /**
     * 删除时尚动态广告内容
     * @param $id
     * @return BrandDynamicAds
     */
    public function delDynamicAds($id) {
        return $this->where(['id' => $id])->update(['status' => 2]);
    }


    /**
     * 更改时尚动态广告位排序
     * @param $id
     * @param $store_id
     * @param $sort
     * @return bool
     * @throws \think\Exception
     */
    public static function changeSort($id, $store_id, $sort) {
        $brand_dynamic_id = Db::table('brand_store')->alias('bs')
            ->join(['brand_dynamic' => 'bsy'], 'bs.brand_id=bsy.brand_id')
            ->where(['bs.store_id' => $store_id, 'type' => 2])->value('bsy.id');
        if(!$brand_dynamic_id)
            return false;

        ##获取以前的排序
        $prev_sort = self::where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)
            return true;
        ##更新
        if($prev_sort > $sort){
            $ids = self::where(['sort'=>['BETWEEN',[$sort,$prev_sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setInc('sort',1);
        }else{
            $ids = self::where(['sort'=>['BETWEEN',[$prev_sort,$sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setDec('sort',1);
        }
        $res = self::where(['id'=>$id])->setField('sort', $sort);
        if($res === false)
            return false;
        return true;
    }
}