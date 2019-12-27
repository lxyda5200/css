<?php


namespace app\admin\model;


use app\admin\validate\Brand;
use think\Exception;
use think\Model;

class BrandProduct extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增经典款商品
     * @param $post
     * @throws Exception
     */
    public function add($post){
        $products = $post['products'];
        $brand_id = intval($post['brand_id']);
        $brand = new Brand();

        $data = [];
        foreach($products as $v){
            #验证
            $check = $brand->scene('add_brand_product')->check($v);
            if(!$check)throw new Exception($brand->getError());

            $data[] = [
                'product_id' => intval($v['product_id']),
                'brand_id' => $brand_id
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('经典款商品添加失败');
    }

    /**
     * 删除品牌经典款商品
     * @param $post
     * @throws Exception
     */
    public function del($post){
        $brand_id = intval($post['brand_id']);
        $res = $this->where(['brand_id'=>$brand_id])->delete();
        if($res === false)throw new Exception('经典款商品更新失败');
    }

    /**
     * 获取品牌经典款商品
     * @param $brand_id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getBrandProList($brand_id){
        return (new self())->where(['brand_id'=>$brand_id])->field('id,product_id')->with(['specs'])->select()->toArray();
    }

    /**
     * 一对一 商品规格
     * @return \think\model\relation\HasOne
     */
    public function specs(){
        return $this->hasOne('ProductSpecs','product_id','product_id')->field('product_id,cover,product_name,price')->order('price','desc');
    }

}