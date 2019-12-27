<?php


namespace app\store\model;


use think\Model;
use traits\model\SoftDelete;

class StyleProduct extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $delete_time = 'delete_time';

    protected $resultSetType = '\think\Collection';

    /**
     * 设置风格类型为店铺
     * @return int
     */
    public function getTypeAttr(){
        return 2;
    }

    /**
     * 获取商品风格列表信息
     * @return array
     */
    public function getStyleProductList(){
        return $this->field('id,title,create_time as type')->select()->toArray();
    }

}