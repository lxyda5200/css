<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\mainstore\controller;


use app\common\controller\Base;
use think\Cache;
use think\Db;
use think\Exception;
use think\response\Json;
use app\mainstore\model\Product as productModel;
use app\mainstore\model\ProductAttributeKey as attributeKeyModel;
use app\mainstore\model\ProductAttributeValue as attributeValueModel;
use app\mainstore\model\ProductSpecs as specsModel;
class Product extends Base
{
    /**
     * 商品列表
     */
    public function productList(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id') ? intval(input('id')) : 0 ;
            $status = input('status');
            if (!$id){throw new \Exception('参数错误');}
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if (!empty($keywords)) {
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where['id|product_name'] = ['like', "%{$keywords}%"];
            }
            if(isset($status)){
                if($status==1){
                    //上架
                    $where['status'] = ['eq', 1];
                }elseif($status==-1){
                    //下架
                    $where['status'] = ['eq', 0];
                }
            }
            $total = Db::name('product')
                ->where('store_id',$id)
                ->where($where)
                ->count();

            $list = Db::view('product','id,product_name,sales,status,shangjia_time,xiajia_time')
                ->where('store_id',$id)
                ->where($where)
                ->page($page,$size)
                ->order('product.id','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['stock'] = Db::name('product_specs')->where('product_id',$v['id'])->sum('stock');
                $list[$k]['price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('price');
                //查询规格
                $product_attribute_key= Db::name('product_attribute_key')->where('product_id',$v['id'])->column('id');
                foreach ($product_attribute_key as $k1=>$v1){
                    $attribute_value=Db::name('product_attribute_value')->where('attribute_id',$v1)->column('attribute_value');
                    $attribute_values[$k1]= implode(",",$attribute_value);
                }
                $list[$k]['product_specs']= implode(";",$attribute_values);

                $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
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
                return \json(self::callback(1,'操作成功',true));
            }else{
                return \json(self::callback(0,'操作失败',false));
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

            Db::startTrans();

            $time = time();
            $productModel = new productModel();

            $post['start_time'] = strtotime($post['start_time']);
            $post['end_time'] = strtotime($post['end_time']);
            $productModel->allowField(true)->save($post);

            if (!$productModel){
                Db::rollback();
                throw new \Exception('操作失败:001');
            }

            $product_img = $post['product_img'];
            foreach ($product_img as $k1=>$v1){
                $img_arr[$k1]['img_url'] = $v1;
                $img_arr[$k1]['product_id'] = $productModel->id;
            }

            $img = Db::name('product_img')->insertAll($img_arr);
            if (!$img){
                Db::rollback();
                throw new \Exception('操作失败:002');
            }

            foreach ($attribute as $k=>$v){
                $attribute[$k]['product_id'] = $productModel->id;

                $attribute[$k]['create_time'] = $time;

                $attribute_id = Db::name('product_attribute_key')->strict(false)->insertGetId($attribute[$k]);

                if (!$attribute_id){
                    Db::rollback();
                    throw new \Exception('操作失败:003');
                }

                $attribute_values = $attribute[$k]['attribute_value'];

                foreach ($attribute_values as $v2){
                    $value = Db::name('product_attribute_value')->strict(false)->insert(['attribute_id'=>$attribute_id,'attribute_value'=>$v2,'create_time'=>$time]);

                    if (!$value){
                        Db::rollback();
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
                Db::rollback();
                throw new \Exception('操作失败:005');
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
            Db::startTrans();
            $productModel = new productModel();
            $post['sh_status'] = 0;
            $post['status'] = 0;
            $result = $productModel->allowField(true)->save($post,['id'=>$product_id]);  //修改商品基础信息
            if (!$result){
                Db::rollback();
                throw new \Exception('操作失败:001');
            }

            $product_img = $post['product_img'];
            foreach ($product_img as $k1=>$v1){
                $img_arr[$k1]['img_url'] = $v1;
                $img_arr[$k1]['product_id'] = $product_id;
            }

            //删除原图
            $del1 = Db::name('product_img')->where('product_id',$product_id)->delete();

            if (!$del1){
                Db::rollback();
                throw new \Exception('操作失败:002');
            }

            $img = Db::name('product_img')->insertAll($img_arr);   //修改图片信息
            if (!$img){
                Db::rollback();
                throw new \Exception('操作失败:003');
            }


            $attribute_key_id_arr = Db::name('product_attribute_key')->where('product_id',$product_id)->column('id');

            $del2 = Db::name('product_attribute_key')->where('product_id',$product_id)->delete();

            if (!$del2){
                Db::rollback();
                throw new \Exception('操作失败:004');
            }

            $del3 = Db::name('product_attribute_value')->where('attribute_id','in',$attribute_key_id_arr)->delete();

            if (!$del3){
                Db::rollback();
                throw new \Exception('操作失败:005');
            }


            $del4 = Db::name('product_specs')->where('product_id',$product_id)->delete();

            if ($del4 === false){
                Db::rollback();
                throw new \Exception('操作失败:006');
            }


            foreach ($attribute as $k=>$v){
                $attribute[$k]['product_id'] = $product_id;

                $attribute[$k]['create_time'] = $time;


                $attribute_id = Db::name('product_attribute_key')->strict(false)->insertGetId($attribute[$k]);   //添加属性名

                if (!$attribute_id){
                    Db::rollback();
                    throw new \Exception('操作失败:007');
                }

                $attribute_values = $attribute[$k]['attribute_value'];

                foreach ($attribute_values as $v2){

                    $value = Db::name('product_attribute_value')->strict(false)->insert(['attribute_id'=>$attribute_id,'attribute_value'=>$v2,'create_time'=>$time]);

                    if (!$value){
                        Db::rollback();
                        throw new \Exception('操作失败:008');
                    }
                }

            }

            foreach ($specs_info as $k3=>$v3){
                $specs_info[$k3]['product_id'] = $product_id;
                $specs_info[$k3]['create_time'] = $time;
                $res = Db::name('product_specs')->strict(false)->insert($specs_info[$k3]);
                if($res === false)throw new Exception('修改失败');
            }

//            $result = Db::name('product_specs')->strict(false)->insertAll($specs_info);

//            print_r($specs_info);die;

            if (!$result){
                Db::rollback();
                throw new \Exception('操作失败:009');
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

            $id = input('id') ? intval(input('id')) : 0 ;

            if (!$id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $data = Db::view('product')
                ->view('product_category','category_name','product_category.id = product.category_id','left')
                ->where('product.id',$id)
                ->find();

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

        $info = $file->validate(['size'=>500*1024*1024,'ext'=>'jpg,png,gif,mp4,zip,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);

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




    /*
     * 发布潮搭
     * */
    public function createChaoda(){
        try{
            $postJson = trim(file_get_contents('php://input'));
            $post = json_decode($postJson,true);

            //token 验证
            $store_info = \app\store\common\Store::checkToken($post['store_id'],$post['token']);
            if ($store_info instanceof json){
                return $store_info;
            }

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
            }else{}
            $description = $post['description'];
            if(empty($description)){
                return \json(self::callback(0,'描述不能为空'));
            }

           //潮搭类型数组
//            $styles = $post['style_id'];
//            foreach ($styles as $k2=>$v2){
//                if (!Db::name('product_style')->where('id',$v2)->count()) {
//                    return \json(self::callback(0,'搭配风格不存在'));
//                }
//            }
           //默认取值第一个存style_id
//            $style_id=intval($styles['0']);
//            $styles=implode(",",$styles);
            Db::startTrans();

            $chaoda_id = Db::name('chaoda')->insertGetId([
                'store_id' => $store_info['id'],
                'cover' => $post['cover'],
//                'style_id' => $style_id,
                'title' => $title,
                'freight' => $freight,
                'description' => $description,
                'pt_validhours' => $pt_validhours,
                'category_id' => $category_id,
                'is_group' => $is_group,
//                'styles' => $styles,
                'create_time' => time()
            ]);
            if ($chaoda_id===false){
                Db::rollback();
                throw new \Exception('操作失败:001');
            }

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
                $img[$k1]['img_url'] = $v1;
                $img[$k1]['chaoda_id'] = $chaoda_id;
            }

           $insert_chaoda_img=Db::name('chaoda_img')->strict(false)->insertAll($img);

            if ($insert_chaoda_img===false){
                Db::rollback();
                throw new \Exception('操作失败:003,没有上传图片');
            }

            Cache::clear();
            Db::commit();

            return \json(self::callback(1,'发布成功！',true));

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
            }else{}
            $description = $post['description'];
            if(empty($description)){
                return \json(self::callback(0,'描述不能为空'));
            }

//            $styles = $post['style_id'];
//
//            foreach ($styles as $k2=>$v2){
//                if (!Db::name('product_style')->where('id',"$v2")->count()) {
//                    return \json(self::callback(0,'搭配风格不存在'));
//                }
//            }

            if (empty($tag_info)) {
                return \json(self::callback(0,'标签信息不能为空'));
            }
            //默认取值第一个存为style_id
//            $style_id=intval($styles['0']);
//            $styles=implode(",",$styles);
            Db::startTrans();
            //更新数据
            $chaoda_result = Db::table('chaoda')->where('id', $chaoda_id)->update([
                    'cover' => $post['cover'],
//                    'style_id' => $style_id,
                    'description' => $description,
//                    'styles' => $styles,
                    'title' => $title,
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
//                $tag_info[$k]['chaoda_id'] = $chaoda_id;
            }
//            $insert_chaoda_tag = Db::name('chaoda_tag')->strict(false)->insertAll($tag_info);

            //删除原img
            $del2 = Db::name('chaoda_img')->where('chaoda_id',$chaoda_id)->delete();

            if ($del2===false){
                Db::rollback();
                throw new \Exception('操作失败:004');
            }

            foreach ($images as $k1=>$v1){
                $img[$k1]['img_url'] = $v1;
                $img[$k1]['chaoda_id'] = $chaoda_id;
            }
            $imginsert=Db::name('chaoda_img')->strict(false)->insertAll($img);

            if ($imginsert===false){
                Db::rollback();
                throw new \Exception('操作失败:005,没有上传图片');
            }
            Cache::clear();
            Db::commit();
            return \json(self::callback(1,'更新成功！',true));

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
            $list = Db::name('chaoda')->field('id,cover,description,pt_validhours,style_id,styles,is_group,title,category_id,freight')
//                ->view('product_style','style_name','product_style.id = chaoda.style_id','left')
                ->where('chaoda.store_id',$store_info['id'])
                ->where('chaoda.is_delete','eq',0)
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

}