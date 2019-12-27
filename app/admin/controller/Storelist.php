<?php


namespace app\admin\controller;


use app\store_v1\model\Store as storeModel;
use app\store_v1\model\StoreStatusLog;
use think\Exception;
use think\Request;

class Storelist extends ApiBase
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }


    /**
     * 子店铺审核列表
     * @throws \think\exception\DbException
     */
    public function index() {
        try{
            $store_name = trim(input('store_name'));
            $is_brand = intval(input('is_brand'));
            $ctime = trim(input('ctime'));
            $etime = trim(input('etime'));
            $where['store_type'] = ['eq',0];
            $where['sh_status'] = ['eq',0];
            if(!empty($store_name)){
                $where['store_name'] = ['like','%'.$store_name.'%'];
            }
            if(in_array($is_brand,[2,1])){
                $where['type'] = ['eq',$is_brand];
            }
            if($ctime && $etime){
                $where['create_time'] = ['between',[strtotime($ctime),strtotime($etime)]];
            }else{
                if($ctime){
                    $where['create_time'] = ['>=',strtotime($ctime)];
                }
                if($etime){
                    $where['create_time'] = ['<=',strtotime($etime)];
                }
            }
            $list = \app\admin\model\Store::where($where)
                ->field('store_name, id as store_id, cover, type as is_brand , address, create_time, sh_status')
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
            //查询店铺品牌审核信息
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
            if($params['sh_status'] ==1){
                if(empty($params['opening_type']) || !in_array($params['opening_type'],[1,2])){
                    return json(self::callback(0, '请选择店铺的合约期限'));
                }
            }
            //检查店铺品牌审核信息

            if($params['sh_status'] ==1){
                $params['start_time'] = time();
                if($params['opening_type'] == 1){
                    $params['end_time'] = strtotime('+6 month', time());
                } else{
                    $params['end_time'] = strtotime('+1 year', time());
                }
            }
            if($params['sh_status'] ==2){
                $params['sh_status'] =-1;
                //记录不通过操作
                StoreStatusLog::create([
                    'store_id' => intval($params['store_id']),
                    'status' => 6,
                    'create_time'=>time()
                ]);
                if(empty($params['reason'])){
                    return json(self::callback(0, '请填写不通过原因'));
                }
            }
            if($params['sh_status']==1){
                $platform_ticheng = intval($params['platform_ticheng']); //提成比例
                if($platform_ticheng<0 || $platform_ticheng>100){
                    return json(self::callback(0, '店铺提成比例不正确'));
                }
            }
            unset($params['store_id']);
            $params['sh_time'] = time();
            $res = \app\admin\model\Store::update($params, ['id' => $id]);
            if($res === false)
                return json(self::callback(0, '审核操作失败'));

            return json(self::callback(1, '审核操作成功'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }
}