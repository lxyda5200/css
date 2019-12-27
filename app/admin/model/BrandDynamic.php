<?php


namespace app\admin\model;


use think\Exception;
use think\Model;

class BrandDynamic extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增时尚动态
     * @param $post
     * @return string
     * @throws Exception
     */
    public function add($post){
        $brand_id = intval($post['brand_id']);
        $data = compact('brand_id');

        $res = $this->isUpdate(false)->save($data);
        if($res === false)throw new Exception('时尚动态添加失败');
        return $this->getLastInsID();
    }

    /**
     * 获取动态id
     * @param $brand_id
     * @return mixed
     */
    public static function getIdByBrandId($brand_id){
        return (new self())->where(['brand_id'=>$brand_id])->value('id');
    }

    /**
     * 默认增加品牌时尚动态
     * @param $brand_id
     * @throws Exception
     */
    public static function autoAdd($brand_id){
        $data = compact('brand_id');

        $res = (new self())->isUpdate(false)->save($data);
        if($res === false)throw new Exception('时尚动态添加失败');
    }

}