<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;
use traits\model\SoftDelete;
use app\store\model\Store;
use app\store\model\DynamicStyle;

class Dynamic extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    protected $delete_time = 'delete_time';

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增店铺动态
     * @param $post
     * @return false|int
     */
    public function add($post){
        $store_id = intval($post['store_id']);
        $title = trimStr($post['title']);
        $scene_id = intval($post['scene_id']);
        $cover = trimStr($post['cover']);
        $type = intval($post['type']);
        $description = trimStr($post['description']);
        $is_group_buy = intval($post['is_group_buy']);
        $topic_id = intval($post['topic_id']);
        $scene_main_id = Scene::getMainId($scene_id);

        ##获取店铺的经纬度
        $store_info = Store::getStorePosition($store_id);

        $data = compact('store_id','title','scene_id','cover','type','description','is_group_buy','topic_id','scene_main_id');
        $data['lat'] = $store_info['lat'];
        $data['lng'] = $store_info['lng'];
        $res = $this->isUpdate(false)->save($data);
        return $res;
    }

    /**
     * 修改店铺动态
     * @param $post
     * @return false|int
     */
    public function edit($post){

        $id = intval($post['id']);
        ##获取动态的信息
        $info = $this->where(['id'=>$id])->field('id,scene_id')->find();
        if(!$info)throw new Exception('动态不存在或已删除');

        $store_id = intval($post['store_id']);
        $title = trimStr($post['title']);
        $scene_id = intval($post['scene_id']);
        $cover = trimStr($post['cover']);
        $type = intval($post['type']);
        $description = trimStr($post['description']);
        $is_group_buy = intval($post['is_group_buy']);
        $topic_id = intval($post['topic_id']);

        $data = compact('title','scene_id','cover','type','description','is_group_buy','topic_id');

        $res = $this->save($data,compact('id','store_id'));
        if($res === false)throw new Exception('修改失败');

        return $info;

    }

    /**
     * 获取店铺动态列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getList(){
        $page = input('post.page',1,'intval');
        $store_id = input('post.store_id',0,'intval');
        $list = $this->alias('d')
            ->join('store s','s.id = d.store_id','LEFT')
            ->where([
                'd.store_id' => $store_id
            ])
            ->field('
                d.id,d.title,d.description,d.create_time,d.status,d.type,
                s.address
            ')
            ->paginate(10,false,['page'=>$page])
            ->toArray();
        $list['max_page'] = ceil($list['total']/$list['per_page']);
        return $list;
    }

    /**
     * 更新动态状态
     * @return false|int
     */
    public function editStatus(){
        $id = input('post.id',0,'intval');
        $status = input('post.status',1,'intval');
        $status = $status>0?-1:1;
        return $this->save(compact('status'),compact('id'));
    }

    /**
     * 获取动态信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getDynamicInfo(){
        $id = input('post.id',0,'intval');
        ##获取信息
        $info = $this->alias('d')
            ->join('scene s','d.scene_id = s.id','LEFT')
            ->where([
                'd.id' => $id
            ])
            ->field('
                d.id,d.title,d.description,d.cover,d.scene_id,d.topic_id,d.type,
                s.title as scene
            ')
            ->with(['dynamicImage','dynamicProduct'])
            ->find();
        ##获取风格
        $info['dynamic_style'] = DynamicStyle::getDynamicStyle($id);

        ##商品图片的标签
        foreach($info['dynamic_image'] as &$v){
            $v['product'] = DynamicProduct::getImgTags($v['id']);
        }

        ##商品规格
        foreach($info['dynamic_product'] as &$v){
            $v['specs'] = DynamicProductSpecs::getDynamicSpecs($v['id']);
        }
        return $info;
    }

    public function dynamicImage(){
        return $this->hasMany('DynamicImg','dynamic_id','id')
            ->field('dynamic_img.id,dynamic_img.dynamic_id,dynamic_img.img_url,dynamic_img.type,dynamic_img.cover,dynamic_img.is_cover');
    }

    /*public function dynamicStyle(){
        return $this->hasMany('DynamicStyle','dynamic_id','id')->field('id,dynamic_id,style_id,type');
    }*/

    public function dynamicProduct(){
        return $this->hasMany('DynamicProduct','dynamic_id','id')
            ->join('product p','p.id = dynamic_product.product_id','LEFT')
            ->field('
                p.id as product_id,p.product_name,
                dynamic_product.id,dynamic_product.is_batch_setup,dynamic_product.batch_setup_price,dynamic_product.img_id,dynamic_product.dynamic_id
            ');
    }

}