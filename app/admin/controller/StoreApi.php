<?php


namespace app\admin\controller;


use app\store_v1\model\Store as storeModel;
use think\Exception;

class StoreApi extends ApiBase
{
    /**
     * 子店铺审核列表
     * @throws \think\exception\DbException
     */
    public function reviewStoreList() {
        try{
            $list = \app\admin\model\Store::where(['store_type' => 0,'sh_status'=>0])
                ->field('store_name, id as store_id, cover, is_brand, address, create_time, sh_status')
                ->order('create_time desc')
                ->paginate(10)->toArray();

            return json(self::callback(1, 'success', $list));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }


    /**
     * 子店铺审核详情
     */
    public function reviewStoreDetail() {
        try{
            $store_id = intval(input('store_id')); //店铺ID
            $data = storeModel::get_details($store_id);
            return \json(self::callback(1,'返回成功',$data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 审核子店铺信息
     * @return \think\response\Json
     */
    public function reviewStore() {
        try{
            $params = input('post.');
            $id = intval($params['store_id']);
            if(empty($params['sh_status']) || !in_array($params['sh_status'],[1,2])){ //审核状态
                return json(self::callback(0, '审核状态不正确'));
            }
            if(empty($id)){
                return json(self::callback(0, '店铺信息不正确'));
            }

            if(empty($params['opening_type'])){
                return json(self::callback(0, '请选择合作期限'));
            }
            if(empty($params['opening_type'])){
                return json(self::callback(0, '请选择审核店铺的合约期限'));
            }
            $params['start_time'] = time();
            if($params['opening_type'] == 1)
                $params['end_time'] = strtotime('+6 month', time());
            else
                $params['end_time'] = strtotime('+1 year', time());
            if($params['sh_status'] ==2){
                $params['sh_status'] =-1;
                //记录不通过操作
                if(empty($params['reason'])){
                    return json(self::callback(0, '请填写不通过原因'));
                }
            }
            $platform_ticheng = intval($params['platform_ticheng']); //提成比例
            if($platform_ticheng<0 || $platform_ticheng>100){
                return json(self::callback(0, '店铺提成比例不正确'));
            }
            unset($params['uid']);
            $res = \app\admin\model\Store::update($params, ['id' => $id]);
            if($res === false)
                return json(self::callback(0, '操作失败'));

            return json(self::callback(1, '操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }
}