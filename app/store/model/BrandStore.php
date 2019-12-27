<?php


namespace app\store\model;


use think\Exception;
use think\Model;

class BrandStore extends Model
{
    /**
     * 定义表
     * @var string
     */
    protected $table = 'brand_store';


    /**
     * 判断是否存在该店铺的自有品牌和知名品牌
     * @param $param
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exitsStore($store_id, $type) {
        return $this->where(['store_id' => $store_id, 'type' => $type])->find();
    }


    /**
     * 添加品牌店铺关系
     * @param $param
     * @param $type
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function addRelation($param, $type) {
        self::startTrans();
        try{
            # 修改选中状态
            $res1 = self::changeSelect(intval($param['store_id']), $type, $param['is_selected']);
            if(!$res1)
                throw new Exception(false);

            $data = [
                'store_id' => intval($param['store_id']),
                'brand_id' => intval($param['brand_id']),
                'type' => $type,
                'is_show_story' => isset($param['is_show_story'])?$param['is_show_story']:2,
                'is_show_dynamic' => isset($param['is_show_dynamic'])?$param['is_show_dynamic']:2,
                'is_selected' => isset($param['is_selected'])?$param['is_selected']:2
            ];
            $res = self::insert($data);
            if(!$res)
                throw new Exception(false);

            self::commit();

            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 更新品牌关系
     * @param $param
     * @param $type
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function updateRelation($param, $type) {
        self::startTrans();
        try{
            # 修改选中状态
            $res1 = self::changeSelect(intval($param['store_id']), $type, $param['is_selected']);
            if(!$res1)
                throw new Exception(false);

            $data = [
                'brand_id' => intval($param['brand_id']),
                'is_show_story' => isset($param['is_show_story'])?$param['is_show_story']:2,
                'is_show_dynamic' => isset($param['is_show_dynamic'])?$param['is_show_dynamic']:2,
                'is_selected' => isset($param['is_selected'])?$param['is_selected']:2
            ];
            $res = self::where(['store_id' => intval($param['store_id']), 'type' => $type])->update($data);
            if($res === false)
                throw new Exception(false);

            self::commit();
            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 获取知名品牌信息
     * @param $store_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getFamousBrand($store_id) {
        $data = $this->alias('bs')
            ->join(['brand' => 'b'], 'b.id=bs.brand_id')
            ->where(['bs.store_id' => $store_id, 'bs.type' => 1])
            ->field('b.brand_name, b.logo, bs.brand_id, bs.is_show_story, bs.is_show_dynamic, bs.is_selected')
            ->find();

        return $data;
    }


    /**
     * 获取品牌id
     * @param $store_id
     * @param $type
     * @return mixed
     */
    public static function getBrandId($store_id, $type) {
        return self::where(['store_id' => $store_id, 'type' => $type])->value('brand_id');
    }



    /**
     * 修改选中状态
     * @param $store_id
     * @param $type
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function changeSelect($store_id, $type, $selected) {
        $type1 = ($type == 1?2:1);
        self::startTrans();
        try{
            $res = self::where(['store_id' => $store_id, 'type' => $type1])->value('is_selected');
            if($res && $res == $selected){
                $res1 = self::where(['store_id' => $store_id, 'type' => $type1])->update(['is_selected' => ($selected==1?2:1)]);
                if($res1 === false)
                    throw new Exception(false);
            }

            self::commit();

            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 获取时尚动态是否展示状态
     * @param $store_id
     * @return mixed
     */
    public function getDynamicStatus($store_id) {
        return $this->where(['store_id' => $store_id, 'type' => 2])->value('is_show_dynamic');
    }


    /**
     * 修改时尚动态展示状态
     * @param $store_id
     * @param $status
     * @return BrandStore
     */
    public function changeDynamicStatus($store_id, $status) {
        return $this->where(['store_id' => $store_id, 'type' => 2])->update(['is_show_dynamic' => $status]);
    }

}