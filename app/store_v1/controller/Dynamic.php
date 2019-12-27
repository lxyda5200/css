<?php


namespace app\store_v1\controller;

use app\store_v1\model\DynamicProduct;
use app\store_v1\model\DynamicStyle;
use app\store_v1\model\Scene;
use app\store_v1\model\StoreStyleStore;
use app\store_v1\model\StyleProduct;
use app\store_v1\model\TopicModel;
use think\Db;
use think\Exception;
use app\store_v1\common\Store;
use think\response\Json;
use app\store_v1\model\Product;
use app\store_v1\validate\Dynamic as DynamicValidate;
use app\store_v1\model\Dynamic as DynamicModel;
use app\store_v1\model\DynamicImg;

class Dynamic extends Base
{

    /**
     * 获取生活场景列表
     * @param Scene $scene
     * @return \think\response\Json
     * @throws Exception
     */
    public function dynamicSceneList(Scene $scene){

        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##逻辑
            $list = $scene->getSceneTree();
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取风格与话题(店铺添加动态)
     * @param StoreStyleStore $storeStyleStore
     * @param StyleProduct $styleProduct
     * @param TopicModel $topicModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function styleAndTopic(StoreStyleStore $storeStyleStore, StyleProduct $styleProduct, TopicModel $topicModel){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##逻辑
            $store_id = input('post.store_id',0,'intval');
            ###获取风格
            ####店铺风格
            $style_store = $storeStyleStore->getStoreStyleList($store_id);
            ####商品风格
            $style_product = $styleProduct->getStyleProductList();
            $style_list = array_merge($style_store,$style_product);
            ###获取话题
            $topic_list = $topicModel->appTopicList();

            ##返回
            return json(self::callback(1,'',compact('style_list','topic_list')));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商家动态发布推荐商品列表
     * @param DynamicValidate $dynamic
     * @param Product $product
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function recomProductList(DynamicValidate $dynamic,Product $product){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }

            ##验证参数
            $res = $dynamic->scene('recom_product_list')->check(input());
            if(!$res)throw new Exception($dynamic->getError());

            ##逻辑
            ###获取列表
            $data = $product->getRecomProductList();

            return \json(self::callback(1,'',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增动态
     * @param DynamicValidate $dynamic
     * @param DynamicModel $dynamicModel
     * @param DynamicImg $dynamicImg
     * @param Scene $scene
     * @param DynamicStyle $dynamicStyle
     * @param DynamicProduct $dynamicProduct
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function addDynamic(DynamicValidate $dynamic, DynamicModel $dynamicModel, DynamicImg $dynamicImg, Scene $scene, DynamicStyle $dynamicStyle, DynamicProduct $dynamicProduct){
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        ##验证
        ##token 验证
        $store_info = Store::checkToken($post['store_id'],$post['token']);
        if ($store_info instanceof Json){
            return $store_info;
        }
        ## 参数验证
        $res = $dynamic->scene('add_dynamic')->check($post);
        if(!$res)return json(self::callback(0,$dynamic->getError()));

        ##逻辑
        Db::startTrans();
        try{
            ###增加动态主信息
            $res = $dynamicModel->add($post);
            $dynamic_id = $dynamicModel->getLastInsID();
            if($res === false)throw new Exception('添加失败');
            ###增加场景使用数
            if(isset($post['scene_id']) && $post['scene_id']){
                $res = $scene->incUseNumber($post['scene_id']);
                if($res === false)throw new Exception('添加失败');
            }
            ###增加banner或者视频
            $img_ids = $dynamicImg->add($dynamic_id,$post);
            if($img_ids === false)throw new Exception('添加失败');
            ###增加风格
            $res = $dynamicStyle->add($dynamic_id, $post['styles']);
            if($res === false)throw new Exception('添加失败');
            ###增加标签
            $res = $dynamicProduct->add($dynamic_id, $post['products'], $img_ids);
            if(!$res)throw new Exception('添加失败');

            Db::commit();
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 店铺动态列表
     * @param DynamicValidate $dynamicValidate
     * @param DynamicModel $dynamic
     * @return Json
     */
    public function dynamicList(DynamicValidate $dynamicValidate, DynamicModel $dynamic){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##验证
            $res = $dynamicValidate->scene('dynamic_list')->check(input());
            if(!$res)throw new Exception($dynamicValidate->getError());
            ##逻辑
            $list = $dynamic->getList();
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改动态的状态
     * @param DynamicValidate $dynamicValidate
     * @param DynamicModel $dynamic
     * @return Json
     */
    public function editStatus(DynamicValidate $dynamicValidate, DynamicModel $dynamic){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##验证
            $res = $dynamicValidate->scene('edit_status')->check(input());
            if(!$res)throw new Exception($dynamicValidate->getError());

            ##逻辑
            $res = $dynamic->editStatus();
            if($res === false)throw new Exception('操作失败');

            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除动态
     * @param DynamicValidate $dynamicValidate
     * @return Json
     */
    public function delDynamic(DynamicValidate $dynamicValidate){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##验证
            $res = $dynamicValidate->scene('del_dynamic')->check(input());
            if(!$res)throw new Exception($dynamicValidate->getError());

            ##逻辑
            $id = input('post.id',0,'intval');
            $res = DynamicModel::destroy($id);
            if($res === false)throw new Exception('删除失败');
            ##返回
            return json(self::callback(1,'删除成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取动态的修改信息
     * @param DynamicValidate $dynamicValidate
     * @param DynamicModel $dynamic
     * @return Json
     */
    public function dynamicInfo(DynamicValidate $dynamicValidate, DynamicModel $dynamic){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ##验证
            $res = $dynamicValidate->scene('dynamic_info')->check(input());
            if(!$res)throw new Exception($dynamicValidate->getError());

            ##逻辑
            $info = $dynamic->getDynamicInfo();

            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改动态
     * @param DynamicValidate $dynamic
     * @param DynamicModel $dynamicModel
     * @param DynamicImg $dynamicImg
     * @param Scene $scene
     * @param DynamicStyle $dynamicStyle
     * @param DynamicProduct $dynamicProduct
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function editDynamic(DynamicValidate $dynamic, DynamicModel $dynamicModel, DynamicImg $dynamicImg, Scene $scene, DynamicStyle $dynamicStyle, DynamicProduct $dynamicProduct){
        ##接收参数
        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        ##验证
        ##token 验证
        $store_info = Store::checkToken($post['store_id'],$post['token']);
        if ($store_info instanceof Json){
            return $store_info;
        }
        ## 参数验证
        $res = $dynamic->scene('edit_dynamic')->check($post);
        if(!$res)return json(self::callback(0,$dynamic->getError()));

        ##逻辑
        Db::startTrans();
        try{
            ###增加动态主信息
            $info = $dynamicModel->edit($post);
            $dynamic_id = $info['id'];
            ###增加场景使用数
            $scene->decUseNumber($info['scene_id']);
            $res = $scene->incUseNumber($post['scene_id']);
            if($res === false)throw new Exception('操作失败');
            ###增加banner或者视频
            $dynamicImg->del($dynamic_id);
            $img_ids = $dynamicImg->add($dynamic_id,$post);
            if($img_ids === false)throw new Exception('操作失败');
            ###增加风格
            $dynamicStyle->del($dynamic_id);
            $res = $dynamicStyle->add($dynamic_id, $post['styles']);
            if($res === false)throw new Exception('操作失败');
            ###增加标签
            $dynamicProduct->del($dynamic_id);
            $res = $dynamicProduct->add($dynamic_id, $post['products'], $img_ids);
            if(!$res)throw new Exception('操作失败');

            Db::commit();
            return json(self::callback(1,'修改成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取商品规格
     * @param DynamicValidate $dynamic
     * @param Product $product
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function productSpecs(DynamicValidate $dynamic, Product $product){
        try{
            ##token 验证
            $store_info = Store::checkToken();
            if ($store_info instanceof Json){
                return $store_info;
            }
            ## 参数验证
            $res = $dynamic->scene('product_specs')->check(input());
            if(!$res)return json(self::callback(0,$dynamic->getError()));
            ##逻辑
            $list = $product->productSpecsList();
            #返回
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}