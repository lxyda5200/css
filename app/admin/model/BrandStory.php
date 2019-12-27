<?php


namespace app\admin\model;


use think\Exception;
use think\Model;

class BrandStory extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 默认创建品牌故事
     * @param $brand_id
     * @throws Exception
     */
    public static function autoAdd($brand_id){
        $data= [
            'brand_id' => $brand_id
        ];
        $res = (new self())->isUpdate(false)->save($data);
        if($res === false)throw new Exception('品牌故事添加失败');
    }

    /**
     * 更新品牌故事
     * @param $post
     * @return int
     * @throws Exception
     */
    public function edit($post){
        $id = intval($post['brand_story_id']);
        $history = trimStr($post['history']);
        $notion = trimStr($post['notion']);

        $data = compact('history','notion');
        $res = $this->save($data,['id'=>$id]);
        if($res === false)throw new Exception('品牌故事更新失败');
        return $id;
    }

    /**
     * 获取品牌故事信息
     * @return array|false|\PDOStatement|string|Model
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo(){
        $brand_story_id = input('post.id',0,'intval');
        $info = $this->where(['id'=>$brand_story_id])->field('id,history,notion,brand_id')->with(['bannerList'])->find();
        if(!$info)throw new Exception('数据不存在或已删除');
        $product_list = BrandProduct::getBrandProList($info['brand_id']);
        $info['product_list'] = $product_list;
        return $info;
    }

    /**
     * 一对多 品牌故事广告
     * @return \think\model\relation\HasMany
     */
    public function bannerList(){
        return $this->hasMany('BrandStoryAds','brand_story_id','id')->field('id,brand_story_id,url,type,cover,media_id');
    }

}