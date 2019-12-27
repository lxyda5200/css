<?php


namespace app\admin\model;

use \app\admin\validate\Brand;
use think\Exception;
use think\Model;

class BrandStoryAds extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 新增品牌故事广告
     * @param $brand_story_id
     * @param $post
     * @throws Exception
     */
    public function add($brand_story_id, $post){
        $banners = $post['banners'];
        $brand_id = intval($post['brand_id']);
        $brand = new Brand();
        $data = [];
        foreach($banners as $v){
            #验证
            $check = $brand->scene('add_brand_store_ads')->check($v);
            if(!$check)throw new Exception($brand->getError());
            $item = [
                'brand_story_id' => $brand_story_id,
                'brand_id' => $brand_id,
                'url' => trimStr($v['url']),
                'type' => intval($v['type'])
            ];
            if(isset($v['cover']))$item['cover'] = trimStr($v['cover']);
            if(isset($v['media_id']))$item['media_id'] = trimStr($v['media_id']);
            $data[] = $item;
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('品牌故事广告添加失败');
    }

    /**
     * 删除品牌故事的广告
     * @param $brand_story_id
     * @throws Exception
     */
    public function del($brand_story_id){
        $res = $this->where(['brand_story_id'=>$brand_story_id])->delete();
        if($res === false)throw new Exception('品牌故事广告更新失败');
    }

}