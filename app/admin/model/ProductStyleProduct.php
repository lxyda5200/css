<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class ProductStyleProduct extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time','update_time'];

    protected $update = ['update_time'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    public function setUpdateTimeAttr(){
        return time();
    }

    public function getTypeAttr(){
        return 2;
    }

    /**
     * 删除改风格的商品绑定
     * @param $style_product_id
     * @return false|int
     */
    public function delStyleProduct($style_product_id){
        return $this->save(['delete_time'=>time()],compact('style_product_id'));
    }

    /**
     * 获取商品风格列表
     * @param $product_ids
     * @return array
     */
    public function getProductStyleList($product_ids){
        $product_ids = explode(',',$product_ids);
        $list = $this->alias('psp')
            ->join('style_product sp','sp.id = psp.style_product_id','LEFT')
            ->where(['psp.product_id'=>['IN',$product_ids],'sp.delete_time'=>null])
            ->field('sp.id as style_id,sp.title,sp.create_time as type')
            ->distinct(true)
            ->select()
            ->toArray();
        return $list;
    }

}