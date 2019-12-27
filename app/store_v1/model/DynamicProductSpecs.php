<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;
use app\store_v1\validate\Dynamic;

class DynamicProductSpecs extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 添加动态商品规格
     * @param $dynamic_id
     * @param $product_id
     * @param $dynamic_product_id
     * @param $specs
     * @param $batch_setup_price
     * @return false|int
     * @throws Exception
     */
    public function add($dynamic_id, $product_id, $dynamic_product_id, $specs, $batch_setup_price){
        $dynamic = new Dynamic();
        $data = [];
        foreach($specs as $v){
            $per_data = [];
            ##验证商品规格
            $check = $dynamic->scene('dynamic_product_specs')->check($v);
            if(!$check)throw new Exception($dynamic->getError());

            $per_data['price'] = $batch_setup_price > 0?$batch_setup_price:$v['price'];
            $per_data['specs_id'] = $v['specs_id'];
            $per_data['dynamic_id'] = $dynamic_id;
            $per_data['product_id'] = $product_id;
            $per_data['dynamic_product_id'] = $dynamic_product_id;

            $data[] = $per_data;
        }
        $res = $this->isUpdate(false)->saveAll($data);
        return $res;
    }

    /**
     * 获取商品动态规格
     * @param $dynamic_product_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getDynamicSpecs($dynamic_product_id){
        return (new self())->alias('dps')
            ->join('product_specs ps','dps.specs_id = ps.id','LEFT')
            ->where(['dps.dynamic_product_id'=>$dynamic_product_id])
            ->field('
                dps.price,
                ps.product_specs,ps.price as price_yj
            ')
            ->select();
    }

    /**
     * 删除动态的商品规格
     * @param $dynamic_id
     * @return int
     */
    public static function del($dynamic_id){
        return (new self())->where(compact('dynamic_id'))->delete();
    }

}