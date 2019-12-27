<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\store\controller;


use app\common\controller\Base;
use think\Db;
use think\Exception;
use think\response\Json;
use think\Session;
use app\store\model\Store as storeModel;
class Store extends Base
{

    /**
     * 修改店铺信息
     */
    public function modifyStoreInfo(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            if ($store_info['type'] != 1){throw new \Exception('店铺类型错误');}
            $post = $this->request->post();
            $store['store_name'] = htmlspecialchars_decode($post['store_name']);
            $store['business_img'] = trim($post['business_img']);
            $store['category_id'] = trim($post['category_id']);
            $store['cover'] = trim($post['cover']);
            $store['description'] = htmlspecialchars_decode($post['description']);
            $store['platform_ticheng'] = trim($post['platform_ticheng']);
            $store['refund_address'] = htmlspecialchars_decode($post['refund_address']);
            $store['refund_name'] = htmlspecialchars_decode($post['refund_name']);
            $store['refund_mobile'] = htmlspecialchars_decode($post['refund_mobile']);
            #$store_img = input('store_img/a');
            //新增
            $store_category_add = trim($post['store_category_add']);
            $store_style_add = trim($post['store_style_add']);
            //删除
            $store_category_del = trim($post['store_category_del']);
            $store_style_del = trim($post['store_style_del']);

            Db::startTrans();
            $storeModel = new storeModel();
            $store['sh_type'] = 2;
            $store['sh_status'] = 0;
            $storeModel->allowField(true)->save($store,['id'=>$store_info['id']]);
            //删除分类
            if($store_category_del){
                $store_category = explode(",",$store_category_del);
                $rst=Db::name('store_cate_store')->where('cate_store_id','IN',$store_category)->where('store_id',$store_info['id'])->delete();
                if($rst===false){throw new \Exception('删除原店铺分类错误!');}
            }

            //新增分类
            if($store_category_add){
                $category = StoreCategory($store_category_add,$store_info['id']);
                $rst=Db::name('store_cate_store')->strict(false)->insertAll($category);
                if($rst===false){throw new \Exception('添加店铺分类错误!');}
            }
            //删除风格
            if($store_style_del){
                $store_type = explode(",",$store_style_del);
                $rst=Db::name('store_style_store')->where('style_store_id','IN',$store_type)->where('store_id',$store_info['id'])->delete();
                if($rst===false){throw new \Exception('删除原店铺风格错误!');}
            }
            //新增风格
            if($store_style_add){
                $type = StoreType($store_style_add,$store_info['id']);
                $rst=Db::name('store_style_store')->strict(false)->insertAll($type);
                if($rst===false){throw new \Exception('添加店铺风格错误!');}
            }
            //修改店铺主图
            /*if ($store_img) {
                foreach ($store_img as $k=>$v){
                    $img_data[$k]['img_url'] = $v['img_url'];
                    $img_data[$k]['store_id'] = $store_info['id'];
                }
                Db::name('store_img')->where('store_id',$store_info['id'])->delete();
                Db::name('store_img')->insertAll($img_data);
            }*/
            Db::commit();
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 店铺主营分类主营风格
     */
    public function StoreCategoryAndStyle(){
        try{
            $data['store_category']=  Db::name('cate_store')
                ->field('id,title')
                ->where('delete_time is null')
                ->select();
            $data['store_style']=  Db::name('style_store')
                ->field('id,title')
                ->where('delete_time is null')
                ->select();
            return \json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 修改店铺信息 - 会员店铺
     */
    public function modifyMemberStoreInfo(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            if ($store_info['type'] != 2){
                throw new \Exception('店铺类型错误');
            }

            $post = $this->request->post();
            $post['store_name'] = htmlspecialchars_decode($post['store_name']);
            $post['description'] = htmlspecialchars_decode($post['description']);
            $post['refund_address'] = htmlspecialchars_decode($post['refund_address']);
            $post['refund_name'] = htmlspecialchars_decode($post['refund_name']);
            $post['refund_mobile'] = htmlspecialchars_decode($post['refund_mobile']);
            #$store_img = input('store_img/a');

            //新增
            $store_category_add = trim($post['store_category_add']);
            $store_style_add = trim($post['store_style_add']);
            //删除
            $store_category_del = trim($post['store_category_del']);
            $store_style_del = trim($post['store_style_del']);

            Db::startTrans();
            $storeModel = new storeModel();
            $post['sh_type'] = 2;
            $post['sh_status'] = 0;
            $storeModel->allowField(true)->save($post,['id'=>$store_info['id']]);
            //删除分类
            if($store_category_del){
                $store_category = explode(",",$store_category_del);
                $rst=Db::name('store_cate_store')->where('cate_store_id','IN',$store_category)->where('store_id',$store_info['id'])->delete();
                if($rst===false){throw new \Exception('删除原店铺分类错误!');}
            }
            //新增分类
            if($store_category_add){
                $category = StoreCategory($store_category_add,$store_info['id']);
                $rst=Db::name('store_cate_store')->strict(false)->insertAll($category);
                if($rst===false){throw new \Exception('添加店铺分类错误!');}
            }
            //删除风格
            if($store_style_del){
                $store_type = explode(",",$store_style_del);
                $rst=Db::name('store_style_store')->where('style_store_id','IN',$store_type)->where('store_id',$store_info['id'])->delete();
                if($rst===false){throw new \Exception('删除原店铺风格错误!');}
            }
            //新增风格
            if($store_style_add){
                $type = StoreType($store_style_add,$store_info['id']);
                $rst=Db::name('store_style_store')->strict(false)->insertAll($type);
                if($rst===false){throw new \Exception('添加店铺风格错误!');}
            }
            //修改店铺主图
            /*if ($store_img) {

                foreach ($store_img as $k=>$v){
                    $img_data[$k]['img_url'] = $v['img_url'];
                    $img_data[$k]['store_id'] = $store_info['id'];
                }
                Db::name('store_img')->where('store_id',$store_info['id'])->delete();
                Db::name('store_img')->insertAll($img_data);

            }*/
            Db::commit();

            return \json(self::callback(1,''));


        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 添加店铺图
     */
    public function addStoreImg(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $param = $this->request->post();

            $result = $this->validate($param,[
                'img_url' => 'require',
                'product_id' => 'require|number',
            ]);

            if (true !== $result) {
                // 验证失败 输出错误信息
                return \json(self::callback(0,$result),400);
            }

            $store_img_number = Db::name('store_img')->where('store_id',$store_info['id'])->count();

            if ($store_img_number >= 9){
                throw new \Exception('最多上传9张主图');
            }

            $param['product_specs'] = html_entity_decode($param['product_specs']);

            $store_img = Db::name('store_img')->strict(false)->insert($param);

            if (!$store_img) {
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺主图详情
     */
    public function storeImgDetail(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $img_id = input('img_id') ? intval(input('img_id')) : 0 ;

            if (!$img_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $data = Db::name('store_img')->where('id',$img_id)->find();

            if (!$data){
                throw new \Exception('id不存在');
            }

            if ($data['chaoda_id']){
                $data['chaoda_info'] = Db::name('chaoda')->field('cover,description')->where('id',$data['chaoda_id'])->find();
            }

            if ($data['product_id'] != 0 ) {

                $product_specs = htmlspecialchars_decode($data['product_specs']);
                $data['product'] = Db::name('product_specs')->field('cover as product_img,product_name,price')->where('product_id',$data['product_id'])->where('product_specs','eq',"{$product_specs}")->find();
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改店铺主图
     */
    public function editStoreImg(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $img_id = input('img_id') ? intval(input('img_id')) : 0 ;

            if (!$img_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $param = $this->request->post();

            if ($param['product_specs']){
                $param['product_specs'] = html_entity_decode($param['product_specs']);
            }

            Db::name('store_img')->where('id',$img_id)->strict(false)->update($param);

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除店铺主图
     */
    public function deleteStoreImg(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $img_id = input('img_id') ? intval(input('img_id')) : 0 ;

            if (!$img_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $res = Db::name('store_img')->where('id',$img_id)->delete();

            if (!$res){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改营业执照
     */
    public function editbusiness_img(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $img_url=$param['img_url'];
            if (!$img_url) {
                return \json(self::callback(0,'参数错误'),400);
            }
           $rst= Db::name('store')->where('id',$store_info['id'])->setField('business_img', $img_url);

            if($rst===false){
                return \json(self::callback(0,'更新营业执照失败',-1));
            }
            return \json(self::callback(1,'更新成功',true));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 修改到店买单员工提成比例
     * @param storeModel $store
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function changeBussinessDeduct(\app\store\model\Store $store) {
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $params = input('post.');
            # 数据验证
            $validate = new \app\store\validate\Store();
            if(!$validate->scene('changeDeduct')->check($params))
                throw new Exception($validate->getError());
            # 修改提成比例
            $res = $store->changeDeduct($params['store_id'], $params['bussiness_deduct']);
            if($res === false)
                throw new Exception('修改失败');

            return \json(self::callback(1, '修改成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 获取到店买单员工提成比例
     * @param storeModel $store
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getBussinessDeduct(\app\store\model\Store $store) {
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $params = input('post.');
            # 获取提成比例
            $res = $store->getDeduct($params['store_id']);
            if(!$res)
                throw new Exception('暂未设置');

            return \json(self::callback(1, 'success', $res));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }
}