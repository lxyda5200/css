<?php


namespace app\admin\model;


use app\admin\validate\Operate;
use think\Exception;
use think\Model;

class NewTrendProduct extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time', 'sort'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增时尚新潮推荐商品
     * @param $products
     * @param $new_trend_id
     * @throws Exception
     */
    public function add($products, $new_trend_id){
        $data = [];
        $operate = new Operate();
        foreach($products as $v){
            ##验证
            $check = $operate->scene('add_new_trend_product')->check($v);
            if(!$check)throw new Exception($operate->getError());
            $data[] = [
                'new_trend_id' => $new_trend_id,
                'product_id' => (int)$v['product_id'],
                'sort' => (int)$v['sort']
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('推荐商品添加失败');
    }

    /**
     * 删除时尚新潮推荐商品
     * @param $new_trend_id
     * @throws Exception
     */
    public function del($new_trend_id){
        $res = $this->where(compact('new_trend_id'))->delete();
        if($res === false)throw new Exception('时尚新潮推荐商品更新失败');
    }

}