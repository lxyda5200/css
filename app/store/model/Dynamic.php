<?php


namespace app\store\model;


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
                d.id,d.title,d.cover,d.description,d.create_time,d.status,d.type,
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
            $v['specs'] = DynamicProductSpecs::getDynamicSpecs($v['id'], $id);
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
            ->join('product p','p.id = dynamic_product.product_id','RIGHT')
            ->field('
                p.id as product_id,p.product_name,
                dynamic_product.id,dynamic_product.is_batch_setup,dynamic_product.batch_setup_price,dynamic_product.img_id,dynamic_product.dynamic_id
            ');
    }

    /**
     * 获取动态封面及图片集
     * @return array
     * @throws Exception
     */
    public function getDynamicImgList(){
        $dynamic_id = input('post.id',0,'intval');
        ##封面
        $cover = $this->where(['id'=>$dynamic_id, 'type'=>1])->value('cover');
        if(!$cover)throw new Exception('动态不存在或非图片类型');
        ##图片集
        $imgs = DynamicImg::getDynamicImgs($dynamic_id);
        return compact('cover','imgs');
    }

    /**
     * 更新封面
     * @param $id
     * @param $cover
     */
    public static function editCover($id, $cover){
        $res = (new self())->where(['id'=>$id])->setField('cover',$cover);
        if($res === false)throw new Exception('封面更新失败');
    }

    /**
     * 获取动态商品信息
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public static function getDynamicProInfo(){
        $id = input('post.id',0,'intval');
        $data = (new self())->where(['id'=>$id])->field('id,is_group_buy')->with(['dynamicProduct'])->find();
        $data = json_decode(json_encode($data),true);
        if(!$data)return [];
        ##获取规格
        foreach($data['dynamic_product'] as $k => &$v){
            $v['min_price'] = ProductSpecs::getProMinPrice($v['product_id']);
            $v['specs'] = self::getProSpecsInfo($v['product_id'], $v['id'], $id);
        }
        return $data;
    }

    /**
     * 获取规格
     * @param $pro_id
     * @param $dynamic_pro_id
     * @param $dynamic_id
     * @return array
     */
    protected static function getProSpecsInfo($pro_id, $dynamic_pro_id, $dynamic_id){
        $specs_set = DynamicProductSpecs::getDynamicSpecs($dynamic_pro_id, $dynamic_id);
        $specs = ProductSpecs::getProSpecs($pro_id);
        foreach($specs as &$v){
            foreach($specs_set as $vv){
                if($vv['specs_id'] == $v['specs_id'])$v['group_buy_price'] = $vv['price'];
            }
        }
        return $specs;
    }

    /**
     * 修改打包购买状态
     * @param $post
     * @return int
     */
    public static function editIsGroupBuy($post){
       $res = (new self())->where(['id'=>(int)$post['id']])->setField('is_group_buy', (int)$post['is_group_buy']);
       if($res === false)throw new Exception('打包购买状态更新失败');
    }

}