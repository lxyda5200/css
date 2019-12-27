<?php


namespace app\admin\controller;

use app\admin\model\BusinessCircleImg;
use app\admin\model\BusinessCircleStore;
use app\admin\validate\BusinessCircle;
use think\Db;
use think\Exception;
use app\admin\model\BusinessCircle as Circle;

class BusinessCircleApi extends ApiBase
{

    /**
     *  省市区列表
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function regionList(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $res = $businessCircle->scene('region_list')->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $data = $circle->getRegion();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 添加商圈
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @param BusinessCircleImg $businessCircleImg
     * @param BusinessCircleStore $businessCircleStore
     * @return \think\response\Json
     */
    public function addBusinessCircle(BusinessCircle $businessCircle, Circle $circle, BusinessCircleImg $businessCircleImg, BusinessCircleStore $businessCircleStore){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $res = $businessCircle->scene('add_business_circle')->check($post);
        if(!$res)return json(self::callback(0,$businessCircle->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##添加商圈主信息
            $circle_id = $circle->add($post);
            ##添加商圈图片
            $businessCircleImg->addCircleImg($circle_id, $post['imgs']);
            if(isset($post['store_ids']) && $post['store_ids']){
                $store_ids = explode(',',$post['store_ids']);
                ##为商家绑定商圈
                $businessCircleStore->pushStoresToCircle($circle_id, $store_ids);
            }
            Db::commit();
            #返回
            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑商圈信息
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @param BusinessCircleImg $businessCircleImg
     * @param BusinessCircleStore $businessCircleStore
     * @return \think\response\Json
     */
    public function editBusinessCircle(BusinessCircle $businessCircle, Circle $circle, BusinessCircleImg $businessCircleImg, BusinessCircleStore $businessCircleStore){

        $postJson = trim(file_get_contents('php://input'));
        $post = json_decode($postJson,true);
        if(!$post || !is_array($post))return json(self::callback(0,'参数缺失'));

        #验证
        $rule = [
            'id' => 'require|number|>=:1'
        ];
        $res = $businessCircle->scene('edit_business_circle')->rule($rule)->check($post);
        if(!$res)return json(self::callback(0,$businessCircle->getError()));

        #逻辑
        Db::startTrans();
        try{
            ##修改主信息
            $circle_id = $circle->edit($post);
            ##删除原来的商圈图片
            $businessCircleImg->delCircleImgByCircleId($circle_id);
            ##添加商圈图片
            $businessCircleImg->addCircleImg($circle_id, $post['imgs']);
            ##删除商圈店铺
            if(isset($post['del_store_ids']) && $post['del_store_ids']){
                $del_store_ids = explode(',',$post['del_store_ids']);
                $businessCircleStore->delStoresFromCircle($circle_id, $del_store_ids);
            }
            ##新增商圈店铺
            if(isset($post['add_store_ids']) && $post['add_store_ids']){
                $add_store_ids = explode(',',$post['add_store_ids']);
                ##去除重复的店铺
                $add_store_ids = BusinessCircleStore::uniqueCircleStore($circle_id, $add_store_ids);
                $businessCircleStore->pushStoresToCircle($circle_id, $add_store_ids);
            }
            Db::commit();

            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 商圈列表
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function businessCircleList(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $rule = [
                'province' => 'number|>=:1',
                'city' => 'number|>=:1',
                'area' => 'number|>=:1'
            ];
            $res = $businessCircle->scene('business_circle_list')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $data = $circle->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取商圈详情
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function businessCircleDetail(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $rule = [
                'id' => 'require|number|>=:1'
            ];
            $res = $businessCircle->scene('business_circle_detail')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $data = $circle->getInfo();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取商圈下店铺列表
     * @param BusinessCircle $businessCircle
     * @param BusinessCircleStore $businessCircleStore
     * @return \think\response\Json
     */
    public function businessCircleStoreList(BusinessCircle $businessCircle, BusinessCircleStore $businessCircleStore){
        try{
            #验证
            $rule = [
                'circle_id' => 'require|number|>=:0'
            ];
            $res = $businessCircle->scene('business_circle_store_list')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $data = $businessCircleStore->getBusinessCircleStore();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑商圈状态
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function editBusinessCircleStatus(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $rule = [
                'id' => 'require|number|>=:1'
            ];
            $res = $businessCircle->scene('edit_business_circle_status')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $circle->editStatus();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除商圈
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function delBusinessCircle(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $rule = [
                'id' => 'require|number|>=:1'
            ];
            $res = $businessCircle->scene('del_business_circle')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            Db::startTrans();
            $circle->del();
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商圈信息【编辑时使用】
     * @param BusinessCircle $businessCircle
     * @param Circle $circle
     * @return \think\response\Json
     */
    public function BusinessCircleInfo(BusinessCircle $businessCircle, Circle $circle){
        try{
            #验证
            $rule = [
                'id' => 'require|number|>=:1'
            ];
            $res = $businessCircle->scene('business_circle_info')->rule($rule)->check(input());
            if(!$res)throw new Exception($businessCircle->getError());
            #逻辑
            $info = $circle->getEditInfo();
            #返回
            return json(self::callback(1,'',$info));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}