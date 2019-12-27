<?php


namespace app\admin\controller;


use think\Db;
use think\Exception;
use app\admin\validate\Dynamic;
use app\admin\model\Dynamic as DynamicModel;

class DynamicApi extends ApiBase
{

    /**
     * 获取推荐动态列表
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function recommendDynamicList(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $res = $dynamic->scene('recommend_dynamic_list')->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $data = $dynamicModel->getRecommendList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑动态推荐状态
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function editRecommendDynamicStatus(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $rule = [
                'dynamic_id' => 'require|number|>=:1'
            ];
            $res = $dynamic->scene('edit_recommend_dynamic_status')->rule($rule)->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $dynamicModel->editIsRecommend();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改动态推荐信息
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function editRecommendDynamic(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $res = $dynamic->scene('edit_recommend_dynamic')->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            Db::startTrans();
            $dynamicModel->editRecommendInfo();
            Db::commit();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取动态列表
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function dynamicList(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $res = $dynamic->scene('dynamic_list')->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $data = $dynamicModel->getList();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 编辑动态状态
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function editDynamicStatus(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $res = $dynamic->scene('edit_dynamic_status')->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $dynamicModel->editStatus();
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除动态
     * @param Dynamic $dynamic
     * @return \think\response\Json
     */
    public function delDynamic(Dynamic $dynamic){
        try{
            #验证
            $rule = [
                'dynamic_id' => 'require|number|>=:1'
            ];
            $res = $dynamic->scene('del_dynamic')->rule($rule)->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $id = input('post.dynamic_id',0,'intval');
            DynamicModel::destroy($id);
            #返回
            return json(self::callback(1,'操作成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取动态详情信息
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function dynamicDetail(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $rule = [
                'dynamic_id' => 'require|number|>=:1'
            ];
            $res = $dynamic->scene('del_dynamic')->rule($rule)->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $data = $dynamicModel->getInfo();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取动态的数据统计
     * @param Dynamic $dynamic
     * @param DynamicModel $dynamicModel
     * @return \think\response\Json
     */
    public function dynamicData(Dynamic $dynamic, DynamicModel $dynamicModel){
        try{
            #验证
            $rule = [
                'dynamic_id' => 'require|number|>=:1'
            ];
            $res = $dynamic->scene('dynamic_data')->rule($rule)->check(input());
            if(!$res)throw new Exception($dynamic->getError());
            #逻辑
            $data = $dynamicModel->getDynamicData();
            #返回
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}