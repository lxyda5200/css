<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\store\controller;


use app\common\controller\Base;
use app\store\model\TagModel;
use app\store\model\TopicModel;
use app\wxapi\common\Images;
use think\Cache;
use think\Config;
use think\Db;
use think\Exception;
use think\response\Json;
use app\store\common\Logic;
use app\store\model\Product as productModel;
use app\store\model\ProductAttributeKey as attributeKeyModel;
use app\store\model\ProductAttributeValue as attributeValueModel;
use app\store\model\ProductSpecs as specsModel;
use app\store\validate\Store as StoreValidate;

class Product extends Base
{

    /**
     * 商品分类商品风格
     */
    public function ProductCategoryAndStyle(){
        try{
            $data['product_category']=  Db::name('cate_product')
                ->field('id,title,suit')
                ->where('delete_time is null')
                ->select();
            $data['product_style']=  Db::name('style_product')
                ->field('id,title')
                ->where('delete_time is null')
                ->select();
            return \json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品列表
     */
    public function productList(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page');
            $size = input('size');

            $total = Db::name('product')
                ->where('store_id',$store_info['id'])
                ->count();

            $list = Db::view('product','id,product_name,sales,status,sh_status,huoli_money')
                ->view('product_category','category_name','product_category.id = product.category_id','left')
                ->where('product.store_id',$store_info['id'])
                ->page($page,$size)
                ->order('product.create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('price');
                $list[$k]['product_specs'] = Db::name('product_specs')->where('product_id',$v['id'])->value('product_specs');
                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
                $list[$k]['platform_price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('platform_price');
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 小程序banner列表
     */
    public function Banner(){
        try {
        $data = Db::name('store_category')
            ->field('id,category_name')
            ->where('is_show', 1)
            ->where('client_type', 1)
            ->order('paixu asc')
            ->select();
        return json(self::callback(1, '', $data));
    } catch (\Exception $e) {
        Db::rollback();
        return json(self::callback(0, $e->getMessage()));
    }
    }

    /**
     * 商品上架/下架
     */
    public function product_status(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $product_id = input('product_id');
            $status = input('status');

            if (!isset($status) || !$product_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $res = Db::name('product')->where('id',$product_id)->where('store_id',$store_info['id'])->setField('status',$status);

            if ($res){
                return \json(self::callback(1,'操作成功'));
            }else{
                return \json(self::callback(0,'操作失败'));
            }

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 添加商品
     */
    public function addProduct(){
        try{

            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $store_info = \app\store\common\Store::checkToken($post['store_id'],$post['token']);
            if ($store_info instanceof json){
                return $store_info;
            }
            /*$param = [
                'id' => '店铺id',
                'token' => 'token',
                'product_name' => '商品标题',
                'product_img' => ['商品展示图','商品展示图'],
                'freight' => '运费',
                'huoli_money' => '代购获利金额',
                'see_type' => '普通用户是否可查看 1是 2否',
                'buy_type' => '普通用户是否可购买 1是 2否',
                'category_id' => '分类id',
                'start_time' => '开售时间',
                'end_time' => '下架结束时间',
                'is_group_buy' => '是否支持团购',
                'pt_size' => '拼团人数',
                'pt_validhours' => '拼团有效期 小时',
                'content' => '详情介绍富文本',
                'type' => '商品类型 1普通商品 2会员商品',
                'attribute' => [
                    [
                        'attribute_name' => '内存',
                        'attribute_value' => ['64G','128G','256G']
                    ],
                    [
                        'attribute_name' => '颜色',
                        'attribute_value' => ['红色','黄色','绿色']
                    ]
                ],
                'specs_info' => [
                    [
                        'cover' => '封面',
                        'product_name' => '商品标题拼接商品规格属性',
                        'product_specs' => '商品规格属性 格式：{"内存":"64G","颜色":"红色"}',
                        'stock' => '库存',
                        'price' => '单价',
                        'group_buy_price' => '团购价'
                    ],
                    [
                        'cover' => '封面',
                        'product_name' => '{"内存":"64G","颜色":"黄色"}',
                        'product_specs' => '商品规格属性',
                        'stock' => '库存',
                        'price' => '单价',
                        'group_buy_price' => '团购价'
                    ]
                ]
            ];*/
            $attribute = $post['attribute'];
            $specs_info = $post['specs_info'];
            $cate_id = trim($post['cate_id']);//商品分类id
            $style_product = trim($post['style_product']);//商品风格id
            if($cate_id && $cate_id>0){
                $attribute_id = Db::name('cate_product')->where('id',$cate_id)->find();
                if(!$attribute_id){
                    return \json(self::callback(0,'没有该商品分类'));
                }
            }
            Db::startTrans();
            $time = time();
            $productModel = new productModel();
            $post['start_time'] = strtotime($post['start_time']);
            $post['end_time'] = strtotime($post['end_time']);
            $productModel->allowField(true)->save($post);
            if (!$productModel){
                throw new \Exception('操作失败:001');
            }
            $product_img = $post['product_img'];
            foreach ($product_img as $k1=>$v1){
                $img_arr[$k1]['img_url'] = $v1;
                $img_arr[$k1]['product_id'] = $productModel->id;
            }
            $img = Db::name('product_img')->insertAll($img_arr);
            if (!$img){
                throw new \Exception('操作失败:002');
            }
            foreach ($attribute as $k=>$v){
                $attribute[$k]['product_id'] = $productModel->id;
                $attribute[$k]['create_time'] = $time;
                $attribute_id = Db::name('product_attribute_key')->strict(false)->insertGetId($attribute[$k]);
                if (!$attribute_id){
                    throw new \Exception('操作失败:003');
                }
                $attribute_values = $attribute[$k]['attribute_value'];
                foreach ($attribute_values as $v2){
                    $value = Db::name('product_attribute_value')->strict(false)->insert(['attribute_id'=>$attribute_id,'attribute_value'=>$v2,'create_time'=>$time]);
                    if (!$value){
                        throw new \Exception('操作失败:004');
                    }
                }
            }
            foreach ($specs_info as $k3=>$v3){
                $specs_info[$k3]['product_id'] = $productModel->id;
                $specs_info[$k3]['create_time'] = $time;
            }
            $result = Db::name('product_specs')->strict(false)->insertAll($specs_info);
            if (!$result){
                throw new \Exception('操作失败:005');
            }
            //写入商品风格
            $product_id=$productModel->id;
            if($style_product){
                $style = ProductType($style_product,$product_id);
                $rst=Db::name('product_style_product')->strict(false)->insertAll($style);
                    if($rst===false){throw new \Exception('添加店铺风格错误!');}
            }
            Db::commit();
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 修改商品
     */
    public function editProduct(){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $store_info = \app\store\common\Store::checkToken($post['store_id'],$post['token']);
            if ($store_info instanceof json){
                return $store_info;
            }
            $attribute = $post['attribute'];
            $specs_info = $post['specs_info'];
            $time = time();
            $product_id = $post['product_id'];
            $cate_id = trim($post['cate_id']);//商品分类id
            $style_product_add = trim($post['style_product_add']);//商品风格id
            $style_product_del = trim($post['style_product_del']);//商品风格id
            if($cate_id && $cate_id>0){
                $attribute_id = Db::name('cate_product')->where('id',$cate_id)->find();
                if(!$attribute_id){return \json(self::callback(0,'没有该商品分类'));}
            }
            Db::startTrans();
            $productModel = new productModel();
            $post['sh_status'] = 0;
            $post['status'] = 0;
            $result = $productModel->allowField(true)->save($post,['id'=>$product_id]);  //修改商品基础信息
            if (!$result){throw new \Exception('操作失败:001');}
            $product_img = $post['product_img'];
            foreach ($product_img as $k1=>$v1){
                $img_arr[$k1]['img_url'] = $v1;
                $img_arr[$k1]['product_id'] = $product_id;
            }
            //删除原图
            $del1 = Db::name('product_img')->where('product_id',$product_id)->delete();
            if (!$del1){throw new \Exception('操作失败:002');}
            $img = Db::name('product_img')->insertAll($img_arr);   //修改图片信息
            if (!$img){throw new \Exception('操作失败:003');}
            $attribute_key_id_arr = Db::name('product_attribute_key')->where('product_id',$product_id)->column('id');
            $del2 = Db::name('product_attribute_key')->where('product_id',$product_id)->delete();
            if ($del2 === false){throw new \Exception('操作失败:004');}
            $del3 = Db::name('product_attribute_value')->where('attribute_id','in',$attribute_key_id_arr)->delete();
            if ($del3 === false){throw new \Exception('操作失败:005');}
//            $del4 = Db::name('product_specs')->where('product_id',$product_id)->delete();
//            if ($del4 === false){throw new \Exception('操作失败:006');}
            foreach ($attribute as $k=>$v){
                $attribute[$k]['product_id'] = $product_id;
                $attribute[$k]['create_time'] = $time;
                $attribute_id = Db::name('product_attribute_key')->strict(false)->insertGetId($attribute[$k]);   //添加属性名
                if (!$attribute_id){throw new \Exception('操作失败:007');}
                $attribute_values = $attribute[$k]['attribute_value'];
                foreach ($attribute_values as $v2){
                    $value = Db::name('product_attribute_value')->strict(false)->insert(['attribute_id'=>$attribute_id,'attribute_value'=>$v2,'create_time'=>$time]);
                    if (!$value){throw new \Exception('操作失败:008');}
                }
            }

            # 删除规格
//            if(isset($post['del_id'])){
                $res = Db::name('product_specs')->where(['product_id'=>$product_id])->delete();
                if($res===false) throw new Exception('修改失败');
//            }

            foreach ($specs_info as $k3=>$v3){
                $specs_info[$k3]['product_id'] = $product_id;
                $specs_info[$k3]['create_time'] = $time;
                if(!isset($v['id'])) {
                    $res = Db::name('product_specs')->strict(false)->insert($specs_info[$k3]);
                    if($res === false)throw new Exception('修改失败');
                }else {
                    $res = Db::name('product_specs')->strict(false)->update($specs_info[$k3]);
                    if($res === false)throw new Exception('修改失败');
                }
            }
//            $result = Db::name('product_specs')->strict(false)->insertAll($specs_info);
//            print_r($specs_info);die;
            if (!$result){throw new \Exception('操作失败:009');}
            //删除商品风格
            if($style_product_del){
                $style_product = explode(",",$style_product_del);
                $rst=Db::name('product_style_product')->where('style_product_id','IN',$style_product)->where('product_id',$product_id)->delete();
                if($rst===false){throw new \Exception('删除商品风格错误!');}
            }
            //新增商品风格
            if($style_product_add){
                $style = ProductType($style_product_add,$product_id);
                $rst=Db::name('product_style_product')->strict(false)->strict(false)->insertAll($style);
                if($rst===false){throw new \Exception('添加商品风格错误!');}
            }
            Cache::clear();
            Db::commit();
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品详情
     */
    public function productDetail(){
        try{

            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $id = input('id') ? intval(input('id')) : 0 ;//商品id

            if (!$id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $data = Db::view('product')
                ->view('product_category','category_name','product_category.id = product.category_id','left')
                ->where('product.id',$id)
                ->find();
            //商品主营分类
//            if($data['cate_id']>0){
                $data['cate_product']=Db::name('cate_product')
                    ->field('id,title')
                    ->where('id',$data['cate_id'])
                    ->select();
//            }
            //商品主营风格
            $data['style_product'] = Db::view('product_style_product')->alias('psp')
                ->join('style_product sp','psp.style_product_id = sp.id','LEFT')
                ->field('sp.id,sp.title')
                ->where('psp.product_id',$id)
                ->select();
            //商品轮播图
            $data['product_img'] = Db::name('product_img')->where('product_id',$data['id'])->column('img_url');

            $key = Db::name('product_attribute_key')->field('id,attribute_name')->where('product_id',$data['id'])->select();
            foreach ($key as $k=>$v) {
                $key[$k]['attribute_value'] = Db::name('product_attribute_value')->field('id,attribute_value as value')->where('attribute_id',$v['id'])->select();
            }

            //商品规格属性
            $data['attribute'] = $key;

            $specs_info = Db::name('product_specs')->field('cover,share_img,product_specs,stock,price,group_buy_price,huaxian_price,platform_price')->where('product_id',$data['id'])->select();

            foreach ($specs_info as $k2=>$v2){
                $specs_info[$k2]['product_specs'] = json_decode($v2['product_specs']);
            }

            $data['specs_info'] = $specs_info;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }



    /**
     * 图片上传方法 返回url
     * @return [type] [description]
     */
    public function uploadFile()

    {

        $module = 'product';
        $use = input('use');

        if (!$use) $use = 'cover';

        if($this->request->file('file')){
            $file = $this->request->file('file');
        }else{
            return json(self::callback(0,'没有上传文件'));
        }
        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块

        $info = $file->validate(['size'=>500*1024*1024,'ext'=>'jpg,png,gif,mp4,zip,webp,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);

        if($info) {
           $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();

            return json(self::callback(1,'上传成功',$res));
        } else {
            // 上传失败获取错误信息
            return \json(self::callback(0,'上传失败：'.$file->getError()));
        }
    }

    /**
     * 卡券码验证
     */
    public function verifyCode(){

        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $card_code = input('card_code');

            if (!$card_code){
                return \json(self::callback(0,'参数错误'),400);
            }

            $card_info = Db::name('user_card')->where('card_code',$card_code)->find();

            if (!$card_info){
                throw new \Exception('卡券码不存在');
            }

            if (!Db::name('user_card_store')->where('store_id',$store_info['id'])->where('card_id',$card_info['id'])->count()){
                throw new \Exception('卡券店铺错误');
            }

            if ($card_info['status'] == 2){
                throw new \Exception('卡券已使用');
            }

            if ($card_info['status'] == 1 and $card_info['end_time'] < time()){
                throw new \Exception('卡券已过期');
            }

            Db::name('user_card')->where('id',$card_info['id'])->setField('status',2);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     *  获取话题数据
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
        public function getTopicList(){
        $data = TopicModel::where(['status' => 1]) -> field(['id','title','description','bg_cover']) -> order('id desc') -> select();

        return json(self::callback(1,'',$data));
    }

    /**
     *  获取标签数据
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTagsList(){
        $data = TagModel::where(['status' => 1]) -> field(['id','title','description','bg_cover']) -> order('id desc') -> select();

        return json(self::callback(1,'',$data));
    }


    /*
     * 发布潮搭
     * */
    public function createChaoda(){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            $params = input('post.*');
            //token 验证
            $store_info = \app\store\common\Store::checkToken($post['store_id'],$post['token']);
            if ($store_info instanceof json){
                return $store_info;
            }
            // TODO
            $topic_id=isset($post['topic_id']) ? $post['topic_id'] : 0;//话题id
//            if (!$topic_id || $topic_id < 0){
//                return \json(self::callback(0,'未选择话题'));
//            }
            $tag_ids= $post['tag_ids'];
            if(isset($tag_ids)){
                $tag_id=explode(",",$tag_ids);
                $tag_ids='';
                foreach ($tag_id as $k=>$v){
                    $tag_ids.="[".$v."],";
                }
                $tag_ids=  rtrim($tag_ids,",");
            }
            // TODO
            $tag_info = $post['tag_info'];
            $images = $post['images'];
            if (empty($tag_info)) {
                return \json(self::callback(0,'标签信息不能为空'));
            }
            //--------------预写20190710
            //接收小程序分类和是否拼团
            $category_id = $post['banner_id'];
            $is_group = $post['is_group'];
            $title = trim($post['title']);
            $freight = $post['freight'];//运费
            $category_id=implode(",",$category_id); //小程序分类数组
            $is_group=intval($is_group); //团购
            $pt_validhours = $post['pt_validhours'];
            if(empty($pt_validhours)){
                $pt_validhours=24;
            }
            $description = $post['description'];
            if(empty($description)){
                return \json(self::callback(0,'描述不能为空'));
            }
            // TODO
            $type=$post['cover']['type'];
            //获取封面图
            if($type=='image'){
                //第一个为图片
                $info['cover'] = $post['cover']['img_url'];//封面路径
                $path = trim($post['cover']['img_url'],'/');
                if(file_exists($path)){  //生成缩略图
                    $path = createThumb($path,"uploads/product/thumb/",'chaoda');
                }
                $info['type'] = 'image';
                $info['cover_thumb'] = $path;
            }else if($type=='video'){
                //第一个为视频
                ##检查视频信息是否已经保存
                $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($post['cover']['img_url']);
                if($chaoda_img_info && $chaoda_img_info['cover']){  //阿里云返回有封面
                    $info['cover'] = $chaoda_img_info['cover'];
                    $info['cover_thumb'] = $chaoda_img_info['cover'];
                }else{
                    $cover = $post['cover']['img_url'];//封面路径
                    //如果不存在cover则用默认的cover
                    if(!$cover){
                        $info['cover'] = '/default/video_default.png';
                        $info['cover_thumb'] = '/default/video_default.png';
                    }else{
                        $info['cover'] = $post['cover']['img_url'];
                        $info['cover_thumb'] = $post['cover']['img_url'];
                    }
                }
                $info['type']='video';
            }else{
                //报错
                return json(self::callback(0,'未知错误',1));
            }
            // 判断type类型 是单图、多图、视频 ？
            if ($info['type'] == 'image'){
                if (!empty($images)){
                    $types = array_column($images, 'type');
                    if (in_array('video', $types)){
                        $info['type'] = 'video';
                    }else{
                        $info['type'] = 'images';
                    }
                }
            }
            // TODO
            Db::startTrans();
            $chaoda_id = Db::name('chaoda')->insertGetId([
                'store_id' => $store_info['id'],
                'cover' => $post['cover']['img_url'],
                'cover_thumb' => $info['cover_thumb'],
                'title' => $title,
                'topic_id' => $topic_id,
                'tag_ids' => $tag_ids,
                'type' => $info['type'],
                'freight' => $freight,
                'description' => $description,
                'pt_validhours' => $pt_validhours,
                'category_id' => $category_id,
                'is_group' => $is_group,
                'status' => 2,
//                'styles' => $styles,
                'create_time' => time()
            ]);
            if ($chaoda_id===false){
                Db::rollback();
                throw new \Exception('操作失败:001');
            }

            // TODO
            //判断是否有话题背景图
            if($topic_id>0){
                $bg_cover=  Db::name('topic')->where('id',$topic_id)->value('bg_cover');
                if(!$bg_cover){
                    if(strpos($post['cover']['img_url'],'http')!== false){
                        $p= $post['cover']['img_url'];
                    }else{
                        $url=$post['cover']['img_url'];
                        $web_path = Config::get('web_path');
                        $p= $web_path.$url;
                    }
                    $rst = Images::gaussian_blur($p,null,null,2);
                    $url2=strstr($rst,"/uploads/gaosi/");
                    $bg=  Db::name('topic')->where('id',$topic_id)->setField('bg_cover',$url2);
                    if ($bg===false){return json(self::callback(0,'话题图片更新失败!'));}
                }
            }
            // TODO
            foreach ($tag_info as $k=>$v){
                $data = [
                    'chaoda_id' => $chaoda_id,
                    'tag_name'=>$v['tag_name'],
                    'product_id'=>$v['product_id'],
                    'x_postion'=>$v['x_postion'],
                    'y_postion'=>$v['y_postion'],
                    'price' => $v['price']
                ];
                $insert_chaoda_tag =Db::table('chaoda_tag')->insert($data);
                if ($insert_chaoda_tag===false){
                    Db::rollback();
                    throw new \Exception('操作失败:002,没有关联的标签信息');
                }
            }
            foreach ($images as $k1=>$v1){
                $img[$k1]['type'] = $v1['type'];
                $img[$k1]['img_url'] = $v1['src'];
                $img[$k1]['chaoda_id'] = $chaoda_id;
            }
            // TODO 将封面图跟随商品图一起存入【chaoda_img】表
            array_unshift($img,['type' => 'image','img_url'=>$post['cover']['img_url'],'chaoda_id'=>$chaoda_id]);
            $insert_chaoda_img=Db::name('chaoda_img')->strict(false)->insertAll($img);
            // TODO
            //增加标签使用次数
            if(!empty($tag_ids)){
                foreach ($tag_ids as $k=>$v){
                    $rst= Db::name('tag')->where('id',$v)->setInc('use_number');
                }
            }
            $rst= Db::name('topic')->where('id',$topic_id)->setInc('use_number');
            // TODO
            if ($insert_chaoda_img===false){
                Db::rollback();
                throw new \Exception('操作失败:003,没有上传图片');
            }
            //插入chaoda_and_dynamic表
//            $chaoda_and_dynamic = ['chaoda_id' => $chaoda_id, 'create_time' =>time()];
//            $rst= Db::name('chaoda_and_dynamic')->insert($chaoda_and_dynamic);
//            if ($rst===false){
//                Db::rollback();
//                throw new \Exception('操作失败:004,关系表插入失败');
//            }
            Cache::clear();
            Db::commit();
            return \json(self::callback(1,'发布成功！'));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
    * 修改潮搭商品
    *lxy
    */
    public function editChaoda(){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);
            //token 验证
            $store_info = \app\store\common\Store::checkToken($post['store_id'],$post['token']);
            if ($store_info instanceof json){
                return $store_info;
            }
            $chaoda_id = $post['chaoda_id'];//潮搭ID
            $tag_info = $post['tag_info'];
            $images = $post['images'];
            if (isset($chaoda_id)) {
            }else{
                return \json(self::callback(0,'潮搭ID不能为空'));
            }
            //潮搭类型数组
            $category_id = $post['banner_id'];
            $is_group = $post['is_group'];
            $title = trim($post['title']);
            $freight = $post['freight'];//运费
            $category_id=implode(",",$category_id); //小程序分类数组
            $is_group=intval($is_group); //团购
            $pt_validhours = $post['pt_validhours'];
            if(empty($pt_validhours)){
                $pt_validhours=24;
            }
            $description = $post['description'];
            if(empty($description)){
                return \json(self::callback(0,'描述不能为空'));
            }

            if (empty($tag_info)) {
                return \json(self::callback(0,'标签信息不能为空'));
            }
            $chaoda_info = Db::table('chaoda') -> where('id', $chaoda_id)->find();
            if (!$chaoda_info) return \json(self::callback(0,'潮哒不存在'));
            if ($chaoda_info['is_delete'] != 0) return \json(self::callback(0,'潮哒已删除或禁用'));

            Db::startTrans();

            // TODO
            $topic_id=isset($post['topic_id']) ? $post['topic_id'] : 0;//话题id
            if (!$topic_id || $topic_id < 0){
                return \json(self::callback(0,'未选择标签'));
            }
            $tag_ids= $post['tag_ids'];
            if(isset($tag_ids)){
                $tag_id=explode(",",$tag_ids);
                $tag_ids='';
                foreach ($tag_id as $k=>$v){
                    $tag_ids.="[".$v."],";
                }
                $tag_ids=  rtrim($tag_ids,",");
            }
            // TODO
            $type=$post['cover']['type'];
            //获取封面图
            if($type=='image'){
                //第一个为图片
                $info['cover'] = $post['cover']['img_url'];//封面路径
                $path = trim($post['cover']['img_url'],'/');
                if(file_exists($path)){  //生成缩略图
                    $path = createThumb($path,"uploads/product/thumb/",'chaoda');
                }
                $info['type'] = 'image';
                $info['cover_thumb'] = $path;
            }else if($type=='video'){
                //第一个为视频
                ##检查视频信息是否已经保存
                $chaoda_img_info = Logic::getChaodaImgInfosByMediaId($post['cover']['img_url']);
                if($chaoda_img_info && $chaoda_img_info['cover']){  //阿里云返回有封面
                    $info['cover'] = $chaoda_img_info['cover'];
                    $info['cover_thumb'] = $chaoda_img_info['cover'];
                }else{
                    $cover = $post['cover']['img_url'];//封面路径
                    //如果不存在cover则用默认的cover
                    if(!$cover){
                        $info['cover'] = '/default/video_default.png';
                        $info['cover_thumb'] = '/default/video_default.png';
                    }else{
                        $info['cover'] = $post['cover']['img_url'];
                        $info['cover_thumb'] = $post['cover']['img_url'];
                    }
                }
                $info['type']='video';
            }else{
                //报错
                return json(self::callback(0,'未知错误',1));
            }

            // 判断type类型 是单图、多图、视频 ？
            if ($info['type'] == 'image'){
                if (!empty($images)){
                    $types = array_column($images, 'type');
                    if (in_array('video', $types)){
                        $info['type'] = 'video';
                    }else{
                        $info['type'] = 'images';
                    }
                }
            }
            // TODO
            //更新数据
            $chaoda_result = Db::table('chaoda')->where('id', $chaoda_id)->update([
                    'cover' => $info['cover'], // 封面
                    'cover_thumb' => $info['cover_thumb'], // 封面压缩图
                    'topic_id' => $topic_id, // 话题
                    'tag_ids' => $tag_ids, // 标签
                    'description' => $description, // 描述
                    'title' => $title,  // 标题
                    'type' => $info['type'], // 类型
                    'freight' => $freight,
                    'category_id' => $category_id,
                    'is_group' => $is_group,
                    'pt_validhours' => $pt_validhours
                ]);
            if ($chaoda_result===false){
                Db::rollback();
                throw new \Exception('操作失败:001');
            }

            //删除原tag
            $del1 = Db::name('chaoda_tag')->where('chaoda_id',$chaoda_id)->delete();
            if ($del1===false){
                Db::rollback();
                throw new \Exception('操作失败:002');
            }
            foreach ($tag_info as $k=>$v){
                $data = [
                    'chaoda_id' => $chaoda_id,
                    'tag_name' => $v['tag_name'],
                    'product_id' => $v['product_id'],
                    'x_postion' => $v['x_postion'],
                    'y_postion' => $v['y_postion'],
                    'price' => $v['price']];
                $insert_chaoda_tag = Db::table('chaoda_tag')->insert($data);
                if ($insert_chaoda_tag===false){
                    Db::rollback();
                    throw new \Exception('操作失败:003,没有关联的标签信息');
                }
            }

            //删除原img
            $del2 = Db::name('chaoda_img')->where('chaoda_id',$chaoda_id)->delete();


            // TODO
            //判断是否有话题背景图
            if($topic_id>0){
                $bg_cover=  Db::name('topic')->where('id',$topic_id)->value('bg_cover');
                if(!$bg_cover){
                    if(strpos($post['cover']['img_url'],'http')!== false){
                        $p= $post['cover']['img_url'];
                    }else{
                        $url=$post['cover']['img_url'];
                        $web_path = Config::get('web_path');
                        $p= $web_path.$url;
                    }
                    $rst = Images::gaussian_blur($p,null,null,2);
                    $url2=strstr($rst,"/uploads/gaosi/");
                    $bg=  Db::name('topic')->where('id',$topic_id)->setField('bg_cover',$url2);
                    if ($bg===false){return json(self::callback(0,'话题图片更新失败!'));}
                }
            }
            // TODO

            if ($del2===false){
                Db::rollback();
                throw new \Exception('操作失败:004');
            }
            foreach ($images as $k1=>$v1){
                $img[$k1]['type'] = $v1['type'];
                $img[$k1]['img_url'] = $v1['src'];
                $img[$k1]['chaoda_id'] = $chaoda_id;
            }
            // TODO 将封面图跟随商品图一起存入【chaoda_img】表
            array_unshift($img,['type' => 'image','img_url'=>$post['cover']['img_url'],'chaoda_id'=>$chaoda_id]);
            $imginsert=Db::name('chaoda_img')->strict(false)->insertAll($img);

            // TODO
            //增加标签使用次数
            if(!empty($tag_ids)){
                foreach ($tag_ids as $k=>$v){
                    $rst= Db::name('tag')->where('id',$v)->setInc('use_number');
                }
            }
            $old_tag = explode(',',$tag_info['tag_ids']);

            // 将减少原标签使用次数
            foreach ($old_tag as $ok => $ov){
                Db::name('tag')->where('id',substr($ov,1,strlen($ov)-2))->setDec('use_number');
            }
            // 增加话题使用次数
            if ($tag_info['topic_id'] != $topic_id){
                $rst= Db::name('topic')->where('id',$topic_id)->setInc('use_number');
                Db::name('topic')->where('id',$tag_info['topic_id'])->setDec('use_number');
            }


            // TODO

            if ($imginsert===false){
                Db::rollback();
                throw new \Exception('操作失败:005,没有上传图片');
            }
            Cache::clear();
            Db::commit();
            return \json(self::callback(1,'更新成功！'));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 潮搭列表
     * */
    public function chaodaList(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $total = Db::name('chaoda')->where('store_id',$store_info['id'])->where('is_delete','eq',0)->count();
            $list=Db::name('chaoda')->alias('cd')
                ->join('topic t','cd.topic_id = t.id','LEFT')
                ->where('cd.store_id',$store_info['id'])
                ->where('cd.is_delete','eq',0)
                ->field('cd.id,cd.cover,cd.description,cd.pt_validhours,cd.style_id,cd.styles,cd.is_group,cd.title,cd.category_id,cd.freight,cd.tag_ids,t.id as topic_id,t.title as topic_title')
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v){
                /*$list[$k]['tag'] = Db::view('chaoda_tag','id')
                    ->view('product_specs','product_name,cover','product_specs.id = chaoda_tag.specs_id','left')
                    ->select();*/
                /*$list[$k]['product_cover'] = Db::view('chaoda_tag','id')
                    ->view('product_specs','product_name,cover','product_specs.id = chaoda_tag.specs_id','left')
                    ->column('cover');*/

                /*开始*/
                //查询style_id
//                $style_id=$v['styles'];
//                $style_id= explode(',',$style_id);
//                $product_style_id = [];
//                foreach ($style_id as $k5=>$v5){
//                    $product_style_id[$k5] = intval($v5);
//                }
//                $list[$k]['style_id'] = $product_style_id;
                /*结束*/
                //----------
                $category_id=$v['category_id'];
                $category_id= explode(',',$category_id);
                $product_category_id = [];
                foreach ($category_id as $k6=>$v6){
                    $product_category_id[$k6] = intval($v6);
                }
                $list[$k]['banner_id'] = $product_category_id;
                //----------------


                $product_id = Db::name('chaoda_tag')->where('chaoda_id',$v['id'])->column('product_id');
                //获取chaoda_tag
                $list[$k]['product_chaoda_tag'] = Db::name('chaoda_tag')->field('id,tag_name,product_id,x_postion,y_postion,price')->where('chaoda_id',$v['id'])->select();
                //获取chaoda_img
                $list[$k]['product_chaoda_img'] = Db::name('chaoda_img')->where('chaoda_id',$v['id'])->column('img_url');
                //获取product_specs
                $product_cover = [];
                foreach ($product_id as $k4=>$v4){
                    $product_cover[$k4] = Db::name('product_specs')->where('product_id',$v4)->value('cover');
                }
                $list[$k]['product_cover'] = $product_cover;

                /*$list[$k]['tag_name'] = Db::view('chaoda_tag','id')
                    ->view('product_specs','product_name,cover','product_specs.id = chaoda_tag.specs_id','left')
                    ->column('tag_name');*/

                $list[$k]['tag_name'] = Db::name('chaoda_tag')->where('chaoda_id',$v['id'])->column('tag_name');
                if($v['tag_ids']){
                    $tags[$k]=str_replace("[","",$v['tag_ids']);
                    $tags[$k]=str_replace("]","",$tags[$k]);
//                    $tags[$k]=explode(",",$tags[$k]);

                    //查询标签
                    $list[$k]['tags'] = Db::name('tag')->field('id,title')->where('id','in',$tags[$k])->select();
                    $tags = [];
                    foreach ($list[$k]['tags'] as $k2=>$v2){
                        $tags[$k2]=[$v2['id']=>$v2['title']];
                    }
                    $list[$k]['tags'] = $tags;
                }else{
                    $list[$k]['tags'] =[];
                }

                //话题
                $list[$k]['topic']=[$v['topic_id']=>$v['topic_title']];
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 删除潮搭
     * */
    public function deleteChaoda(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $chaoda_id = input('chaoda_id');

            Db::name('chaoda')->where('id',$chaoda_id)->setField('is_delete',1);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /*
     * 潮搭详情
     * */
    public function chaodaDetail(){
        try{

            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $chaoda_id = input('chaoda_id');

            $data = Db::name('chaoda')->field('id,cover,description,style_id')
//                ->view('product_style','style_name','product_style.id = chaoda.style_id','left')
                ->where('chaoda.id',$chaoda_id)
                ->find();

            if (!$data){
                return \json(self::callback(0,'潮搭不存在'));
            }

            $data['img'] = Db::name('chaoda_img')->field('id,img_url')->where('chaoda_id',$chaoda_id)->select();

            $data['tag_info'] = Db::view('chaoda_tag','id,tag_name,product_id,specs_id,x_postion,y_postion,price')
                ->view('product_specs','product_name,cover','product_specs.id = chaoda_tag.specs_id','left')
                ->where('chaoda_tag.chaoda_id',$chaoda_id)
                ->select();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /*
     * 风格
     * */
    public function getStyleInfo(){
        $data = Db::name('product_style')->where('status',1)->select();
        return \json(self::callback(1,'',$data));
    }

    /**
     * 优惠券搜索商品
     * @param StoreValidate $StoreValidate
     * @param productModel $productModel
     * @return Json
     */
    public function searchProductLists(StoreValidate $StoreValidate, productModel $productModel){
        try{
            #验证
            ##权限验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            ##参数验证
            $res = $StoreValidate->scene('coupon_product')->check(input());
            if(!$res)throw new Exception($StoreValidate->getError());

            #逻辑
            $store_id = $store_info['id'];
            $keywords = input('post.keywords','','addslashes,strip_tags,trim');

            ##商品列表
            $data = $productModel->alias('p')
                ->join('product_specs ps','ps.product_id = p.id','LEFT')
                ->where(['p.store_id'=>$store_id,'p.product_name'=>['LIKE',"%{$keywords}%"], 'p.status'=>1, 'p.sh_status'=>1])
                ->field('p.id,p.product_name,ps.cover,ps.price,ps.stock')
                ->group('p.id')
                ->paginate(6,false,['query'=>request()->param()]);

            $data = $data->toArray();

            $list = $data['data'];
//            foreach($list as &$v){
//                $v['product_name'] = str_replace($keywords,"<span style='color:red;'>{$keywords}</span>",$v['product_name']);
//            }
            $data['data'] = $list;

            #返回
            return \json(self::callback(1,'',$data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

}