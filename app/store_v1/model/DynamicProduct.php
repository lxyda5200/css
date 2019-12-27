<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;
use app\store_v1\validate\Dynamic;
use app\store_v1\model\DynamicProductSpecs;

class DynamicProduct extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 增加动态推荐商品
     * @param $dynamic_id
     * @param $products
     * @return bool
     * @throws Exception
     */
    public function add($dynamic_id, $products, $img_ids){
        $dynamic = new Dynamic();
        $dynamicProSpecs = new DynamicProductSpecs();
        foreach($products as $v){
            ##验证
            $check = $dynamic->scene('dynamic_product')->check($v);
            if(!$check)throw new Exception($dynamic->getError());
            ##增加商品
            $res = $this->addDynamicPro($dynamic_id, $v, $img_ids);
            if($res === false)throw new Exception('添加失败');

            $dynamic_product_id = $res;
            $batch_setup_price = $v['batch_setup_price'];
            ##增加商品规格
            $specs = $v['specs'];
            if($v['is_batch_setup']==0){  //不统一定价或者不开启打包购买
                $specs = ProductSpecs::getProSpecs($v['product_id']);
            }
            $res = $dynamicProSpecs->add($dynamic_id,$v['product_id'],$dynamic_product_id,$specs,$batch_setup_price);
            if($res === false)throw new Exception('添加失败');
        }
        return true;
    }

    /**
     * 添加动态商品
     * @param $dynamic_id
     * @param $product
     * @return false|int
     */
    public function addDynamicPro($dynamic_id, $product, $img_ids){
        $data = [
            'dynamic_id' => $dynamic_id,
            'tag_name' => trimStr($product['tag_name']),
            'product_id' => intval($product['product_id']),
            'x_postion' => intval($product['x_position']),
            'y_postion' => intval($product['y_position']),
            'direction' => intval($product['direction']),
            'img_id' => intval($img_ids[$product['img_idx']]),
            'is_batch_setup' => intval($product['is_batch_setup']),
            'batch_setup_price' => floatval($product['batch_setup_price']),
            'create_time' => time()
        ];
        return $this->isUpdate(false)->insertGetId($data);
    }

    /**
     * 获取图片的标签
     * @param $img_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getImgTags($img_id){
        return (new self())->where(compact('img_id'))->field('id,tag_name,product_id,x_postion,y_postion,direction')->select();
    }

    /**
     * 删除动态商品
     * @param $dynamic_id
     * @throws Exception
     */
    public function del($dynamic_id){
        ##删除商品规格
        $res = DynamicProductSpecs::del($dynamic_id);
        if($res === false)throw new Exception('操作失败');
        ##删除商品
        $res = $this->where(compact('dynamic_id'))->delete();
        if($res === false)throw new Exception('操作失败');
    }

}