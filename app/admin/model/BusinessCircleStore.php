<?php


namespace app\admin\model;


use think\Exception;
use think\Model;

class BusinessCircleStore extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 微商圈添加商家集
     * @param $circle_id  ##商圈id
     * @param $store_ids  ##店铺id array
     * @throws Exception
     */
    public function pushStoresToCircle($circle_id, $store_ids){
        $data = [];
        foreach($store_ids as $v){
            $data[] = [
                'business_circle_id' => $circle_id,
                'store_id' => $v
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('为商圈添加商家失败');
    }

    /**
     *
     * @return array
     * @throws \think\exception\DbException
     */
    public function getBusinessCircleStore(){
        $business_circle_id = input('post.circle_id',0,'intval');
        $page = input('post.page',1,'intval');

        $data = $this->alias('bcs')
            ->join('store s','s.id = bcs.store_id','LEFT')
            ->join('store_category sc','sc.id = s.category_id','LEFT')
            ->where([
                'bcs.business_circle_id' => $business_circle_id
            ])
            ->field('
                s.id as store_id,s.store_name,s.address,s.description,s.mobile,s.create_time,s.id as brand_name,
                sc.category_name
            ')
            ->paginate(15,false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total'] / $data['per_page']);

        return $data;
    }

    /**
     * 获取店铺品牌
     * @param $store_id
     * @return string
     */
    public function getBrandNameAttr($store_id){
        return BrandStore::getStoreBrandName($store_id);
    }

    /**
     * 删除商圈下的店铺
     * @param $circle_id
     * @throws Exception
     */
    public static function delByCircleId($circle_id){
        $res = (new self())->where(['business_circle_id'=>$circle_id])->delete();
        if($res === false)throw new Exception('删除失败');
    }

    /**
     * 商圈店铺去重
     * @param $business_circle_id
     * @param $store_ids
     * @return array  返回未重复的店铺
     */
    public static function uniqueCircleStore($business_circle_id, $store_ids){
        $list =(new self())->where(['business_circle_id'=>$business_circle_id, 'store_id'=>['IN', $store_ids]])->column('store_id');
        if(empty($list))return $store_ids;
        return array_diff($store_ids, $list);
    }

    /**
     * 删除商圈下的店铺
     * @param $business_circle_id
     * @param $store_ids
     * @return int
     */
    public function delStoresFromCircle($business_circle_id, $store_ids){
        return $this->where(['business_circle_id'=>$business_circle_id, 'store_id'=>['IN', $store_ids]])->delete();
    }

}