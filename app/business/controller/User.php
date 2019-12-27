<?php
namespace app\business\controller;


use app\business\model\BusinessModel;
use app\business\model\BusinessPowerDetailsModel;
use app\business\model\BusinessPowerModel;
use app\business\model\BusinessRoleModel;
use app\business\model\BusinessSpreadModel;
use app\business\model\BusinessSpreadStatisticsModel;
use app\business\model\BusinessTiXianModel;
use app\business\model\CouponModel;
use app\business\model\MaiOrderModel;
use app\business\model\MsgModel;
use app\business\model\MsgReadModel;
use app\business\model\OrderBusinessModel;
use app\business\model\OrderDetailsModel;
use app\business\model\ProductGoodsModel;
use app\business\model\ProductModel;
use app\business\model\ProductShouHouModel;
use app\business\model\ProductSpecsModel;
use app\business\model\StoreModel;
use app\business\model\UserModel;
use app\business\model\BussinessProfitModel;
use think\Exception;
use think\Db;
use think\Loader;
use think\Request;
use think\Validate;
use jiguang\JiG;
class User extends Base
{

    /**
     *  用户登录接口
     * @return false|string
     */
    public function login(){
        $params = self::$requestInstance->only(['mobile', 'password']);

        // 模型登录验证
        $loginInfo = UserModel::userLogin($params);

        // 验证错误信息
        if (is_string($loginInfo)) return self::returnResponse(1, $loginInfo, null);

        // 登录失败
        if ($loginInfo === false) return self::returnResponse(1, '登录失败', null);

        return self::returnResponse(0, '成功', $loginInfo);
    }

    /**
     * 退出登录
     * @return false|string
     */
    public function logout(){
        // 模型登录验证
        $loginInfo = UserModel::userLogout(self::$user_id);

        // 验证错误信息
        if (is_string($loginInfo)) return self::returnResponse(1, $loginInfo, null);

        // 登录失败
        if ($loginInfo === false) return self::returnResponse(1, '失败', null);

        return self::returnResponse(0, '成功', $loginInfo);
    }

    /**
     * 用户首页总览数据统计
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function home(){

        $un = ProductModel::getPandect(self::$user_info['store_id']);
        // 代付款订单总数
        $unpaidNum = (string)$un['one'];
        // 代发货订单总数
        $unsendNum = (string)$un['three'];
        // 待评价订单总数
        $unCommentNum = (string)$un['five'];
        // 售后订单总数
        $afterSaleNum = (string)ProductModel::getShouNum(self::$user_info['store_id']);
        //本月统计
        $num = ProductModel::getOrderNum(self::$user_info['store_id'],self::$user_info);
        // 商城订单总数 order_status >= 6
        $shopOrderNum = (string)$num['shopOrderNum'];
        // 到店订单总数
        $maiOrderNum = (string)$num['maiOrderNum'];

        return self::returnResponse(0, '成功', compact('unpaidNum', 'unsendNum', 'unCommentNum', 'afterSaleNum', 'shopOrderNum', 'maiOrderNum'));

    }

    /**
     *  首页我的任务接口
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myHomeTask(){
        //待发货
        $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>3,'ob.buniess_id'=>self::$user_id];
        $unsendNum = (string)ProductModel::getOrderHomeTask($where);
        //售后
        $afterSaleNum = (string)ProductModel::getOrderHomeTaskShou(self::$user_info['store_id'],self::$user_id);

        //本月商城订单
        $num = ProductModel::getOrderNum(self::$user_info['store_id'],self::$user_info);
        // 商城订单总数 order_status >= 6
        $shopOrderNum = (string)$num['shopOrderNum'];
        // 到店订单总数
        $maiOrderNum = (string)$num['maiOrderNum'];
        return self::returnResponse(0, '成功', compact('unsendNum', 'afterSaleNum', 'shopOrderNum', 'maiOrderNum'));
    }

    /**
     *  我的任务 订单列表数据
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myTaskOrderData(){
        $params = self::$requestInstance->only(['type','is_all','page','limit']); // 3-代发货 4-已发货 6-已完成 7-售后 -1已取消
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        // 参数验证
        $rule = [
            'type'   => 'require|number|in:3,4,6,7,-1',
            'is_all' => 'number|in:0,1',
        ];
        $msg = [
            'type.require'   => '缺少必要参数', 'type.number' => '参数格式错误', 'type.in' => '参数不在接收范围内',
            'is_all.number' => '参数格式错误', 'is_all.in' => '参数不在接收范围内',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $is_all = isset($params['is_all']) ? $params['is_all'] : 0;
        if ($params['type'] == '-1'){ //已取消
            $where = ['o.store_id'=>self::$user_info['store_id'],'ob.buniess_id'=>self::$user_id];
            // 查询订单数据
            $list = ProductModel::getOrderListByWhereQuxiao($where,self::$user_id,$page,$limit);
        }elseif ($params['type'] == 3 || $params['type'] == 4 || $params['type'] == 6){
            $where = ['o.store_id'=>self::$user_info['store_id'],'o.order_status'=>$params['type'],'ob.buniess_id'=>self::$user_id];
            // 查询订单数据
            $list = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
        }else{
            // 售后订单单独处理
            if (!$is_all){
                $where['ps.refund_status'] = ['in', [1, 2, 3]];
            }else{
                $where['ps.refund_status'] = ['neq', 0];
            }
            $where['ps.store_id'] = self::$user_info['store_id'];
            $where['ps.business_id'] = self::$user_id;
            // 查询数据
            $list = ProductModel::getOrderListByWhereNewShou($where, self::$user_id,$page,$limit);
            // 商品规格json转数组
            /*foreach ($list as $k => $v){
                $list[$k]['product_specs'] = json_decode($v['product_specs'], true);
            }*/
        }
        return self::returnResponse(0, '成功',  $list);
    }

    /**
     *  商品列表数据接口
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreProductList(){
        $params = self::$requestInstance->only(['type','page','limit']);
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;

        if(isset($params['type']) && in_array($params['type'], [0,1,2])){
            $type = $params['type'];
        }else{
            $type = false;
        }
        // 获取全部商品数据列表
        $data = ProductSpecsModel::getStoreProductList(self::$user_info['store_id'],$type,$page,$limit);
        // 获取商品数量
        $productNum = ProductSpecsModel::getProductNumByStatus(self::$user_info['store_id']);
        //$total_page = ceil($productNum/$limit);
        return self::returnResponse(0, '成功', ['product_list' => $data, 'productNum' => $productNum]);
    }

    /**
     *  获取商品详情数据
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductDetails(){
        $params = self::$requestInstance->only(['product_id']);
        // 参数验证
        $rule = [
            'product_id'   => 'require|number',
        ];
        $msg = [
            'product_id.require'   => '缺少必要参数', 'product_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,10);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        // 商品详情数据
        $data = ProductSpecsModel::getProductDetails($params['product_id'], self::$user_info['store_id']);

        $status = '0';
        if($data['total_stocks'] == 0){
            $status = '1';
        }elseif($data['productData']['0']['status'] == 1 && $data['total_stocks'] > 0){
            $status = '2';
        }
        $productStatus = [
            'product_status' => $status,
            'shangjia_time'  => isset($data['productData']['0']['shangjia_time']) ? date( "Y-m-d H:i", $data['productData']['0']['shangjia_time']) : '0',
            'xiajia_time'    => isset($data['productData']['0']['xiajia_time']) ? date( "Y-m-d H:i", $data['productData']['0']['xiajia_time']) : '0',
        ];

        return self::returnResponse(0, '成功', [
            'product_details' => $data['productNewData'],
            'product_name' => $data['productName'],
            'product_cover' => $data['productImgUrl'],
            'productStatus'=>$productStatus]);
    }

    /**
     *  商品上架
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function productUpper(){
        $params = self::$requestInstance->only(['product_id']);
        // 参数验证
        $rule = [
            'product_id'   => 'require|number',
        ];
        $msg = [
            'product_id.require'   => '缺少必要参数', 'product_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,11);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);

        // 上下架逻辑处理
        $return = ProductGoodsModel::productUpperOrLower($params['product_id'],1, self::$user_info['store_id']);

        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, null);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', null);

        return self::returnResponse(0, '成功', null);
    }

    /**
     *  商品下架
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function productLower(){
        $params = self::$requestInstance->only(['product_id']);
        // 参数验证
        $rule = [
            'product_id'   => 'require|number',
        ];
        $msg = [
            'product_id.require'   => '缺少必要参数', 'product_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,12);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        // 上下架逻辑处理
        $return = ProductGoodsModel::productUpperOrLower($params['product_id'],2, self::$user_info['store_id']);

        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, null);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', null);

        return self::returnResponse(0, '成功', null);
    }

    /**
     * 商城订单数据接口
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getShopOrderByType(){
        $params = self::$requestInstance->only(['type','page','limit','is_all']); // 1-代付款 3-代发货 4-已发货 6-已完成 5-待评价 7-售后 -1-已取消
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        // 参数验证
        $rule = [
            'type'   => 'require|number|in:1,3,4,6,5,7,-1',
            'is_all' => 'number|in:0,1',
        ];
        $msg = [
            'type.require'   => '缺少必要参数', 'type.number' => '参数格式错误', 'type.in' => '参数不在接收范围内',
            'is_all.number' => '参数格式错误', 'is_all.in' => '参数不在接收范围内',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $is_all = isset($params['is_all']) ? $params['is_all'] : 0;

        //判断权限
        //$power = self::power(self::$user_info['is_main_user'],self::$user_id,14);
        switch ($params['type'])
        {
            case -1:
                $where = ['o.store_id' => self::$user_info['store_id']];
                $data = ProductModel::getOrderListByWhereQuxiao($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
                break;
            case 1:
                 $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>1];
                 $data = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
                 return self::returnResponse(0, '成功', $data);
            break;
            case 3:

                $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>3];
                    //$where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>3,'ob.buniess_id'=>[['=',self::$user_id],null,'or']];
                $data = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
            break;
            case 4:
                $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>4];
                $data = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
            break;
            case 5:
                $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>5];
                $data = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
            break;
            case 6:
                $where = ['o.store_id' => self::$user_info['store_id'],'o.order_status'=>6];
                $data = ProductModel::getOrderListByWhereNew($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
            break;
            case 7:
                $where1 = ['ps.store_id' => self::$user_info['store_id']];
                    //$where1 = ['ps.store_id' => self::$user_info['store_id'],'ps.business_id'=>[['=',self::$user_id],['=','0'],'or']];
                //$where['ps.business_id'] =  array(['=',self::$user_id],['=','0'],'or');
                if(!$is_all){
                    $where2 = ['ps.refund_status' => ['in', [1, 2, 3]]];
                }else{
                    $where2 = ['ps.refund_status' => ['neq', 0]];
                }
                $where = array_merge($where1,$where2);
                $data = ProductModel::getOrderListByWhereNewShou($where,self::$user_id,$page,$limit);
                return self::returnResponse(0, '成功', $data);
           break;
            default:
        }
    }

    /**
     *  获取订单详情数据
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderDetails(){
        $params = self::$requestInstance->only(['order_id','order_status','s_id','product_id']);

        // 参数验证
        $rule = [
            'order_id'   => 'require|number',
            'order_status' => 'require|number|in:1,3,4,6,5,7,-1',
            's_id'   => 'number',
            'product_id'   => 'number',
        ];
        $msg = [
            'order_id.require'   => '缺少必要参数', 'order_id.number' => '参数格式错误',
            'order_status.require' => '缺少必要参数', 'order_status.number' => '参数格式错误', 'order_status.in' => '参数不在接收范围内',
            's_id.number' => '参数格式错误',
            'product_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        // 查询订单数据
        if($params['order_status'] == '7'){
            $orderInfo = ProductModel::getAfterSaleOrderDetails($params['order_id'],self::$user_id,self::$user_info['store_id'],$params['s_id'],$params['product_id']);
        }elseif ($params['order_status'] == '-1'){
            $orderInfo = ProductModel::getOrderDetailsByIdQuxiao($params['order_id'], $params['order_status'],self::$user_id);
        }else{
            $orderInfo = ProductModel::getOrderDetailsById($params['order_id'], $params['order_status'],self::$user_id);
        }
        if (!$orderInfo) return self::returnResponse(1, '未检索到数据', null);

        return self::returnResponse(0, '成功', $orderInfo);

    }

    /**
     * 员工领取订单   --稍后发货
     * @return false|string
     */
    public function businessGetOrder(){
        $params = self::$requestInstance->only(['order_id']);
        // 参数验证
        $rule = [
            'order_id'   => 'require|number',
        ];
        $msg = [
            'order_id.require'   => '缺少必要参数', 'order_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $return = ProductModel::businessGetOrder($params['order_id'],self::$user_id);

        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);

    }

    /**
     *  员工订单发货
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function businessSendOrder(){
        $params = self::$requestInstance->only(['order_id','logistics_number', 'logistics_company']);

        // 参数验证
        $rule = [
            'order_id'   => 'require|number',
        ];
        $msg = [
            'order_id.require'   => '缺少必要参数', 'order_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        $params['logistics_number'] = isset($params['logistics_number']) ? $params['logistics_number'] : '';
        $params['logistics_company'] = isset($params['logistics_company']) ? $params['logistics_company'] : '';
        // 订单发货
        $return = ProductModel::businessSendOrder($params,self::$user_id);
        // 发货状态判断
        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);
    }


    /**
     * 员工取消订单
     * @return false|string
     */
    public function businessCancelOrder(){
        $params = self::$requestInstance->only(['order_id']);
        // 参数验证
        $rule = [
            'order_id'   => 'require|number',
        ];
        $msg = [
            'order_id.require' => '缺少必要参数','order_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        $return = ProductModel::businessCancelOrder($params['order_id'],self::$user_id,self::$user_info['store_id']);

        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '取消失败', null);
        return self::returnResponse(0, '取消成功', null);

    }


    /**
     * 售后 员工稍后处理
     */
    public function businessGetShou(){
        $params = self::$requestInstance->only(['s_id']);
        // 参数验证
        $rule = ['s_id'   => 'require|number'];
        $msg = ['s_id.require'   => '缺少必要参数', 's_id.number' => '参数格式错误'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }

        $return = ProductShouHouModel::businessGetShouOrder($params['s_id'],self::$user_id);

        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);
    }

    /**
     * 售后 拒绝申请
     */
    public function refuseApplication(){
        $params = self::$requestInstance->only(['s_id']);
        $rule = ['s_id'   => 'require|number'];
        $msg = ['s_id.require' => '缺少参数','s_id.number' => '参数格式错误'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        $return = ProductShouHouModel::refuseApplication($params['s_id'],self::$user_id);
        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);
    }

    /**
     * 售后拒绝退款
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function businessRefuseRefund(){
        $params = self::$requestInstance->only(['s_id', 'refuse_description']);

        // 参数验证
        $rule = ['s_id'   => 'require|number','refuse_description'   => 'require'];
        $msg = ['s_id.require' => '缺少参数','s_id.number' => '参数格式错误', 'refuse_description.require' => '请输入拒绝原因'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }

        $return = ProductShouHouModel::businessRefuseRefund($params['s_id'], $params['refuse_description'],self::$user_id);
        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);

    }

    /**
     * 售后 退货
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function salesReturn(){
        $params = self::$requestInstance->only(['s_id']);
        // 参数验证
        $rule = ['s_id'   => 'require|number'];
        $msg = ['s_id.require' => '缺少参数','s_id.number' => '参数格式错误'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }

        $return = ProductShouHouModel::salesReturn($params['s_id'],self::$user_info['store_id'],self::$user_id);
        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);
    }

    /**
     * 同意退款
     */
    public function moneyBack(){
        $params = self::$requestInstance->only(['s_id']);
        // 参数验证
        $rule = ['s_id'   => 'require|number'];
        $msg = ['s_id.require' => '缺少参数','s_id.number' => '参数格式错误'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), null);
        }
        $return = ProductShouHouModel::moneyBack($params['s_id'],self::$user_id,self::$user_info['store_id']);

        if (is_string($return)) return self::returnResponse(1, $return, null);
        if ($return === false) return self::returnResponse(1, '失败', null);
        return self::returnResponse(0, '成功', null);
    }

    /**
     *  收益数据统计  type 1-今日【默认】 2-本月 3-总计 kind  默认 买单 1  商城 2
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getShopMaiStatistics(){
        $params = self::$requestInstance->only(['start_time', 'end_time', 'type','kind','page','limit']);
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        // 参数验证
        $rule = [
            'start_time'   => 'number',
            'end_time'   => 'number',
            'type'   => 'number|in:1,2,3',
            'kind'   => 'number|in:1,2',
            'page'   => 'number',
            'limit'   => 'number',
        ];
        $msg = [
            'start_time.number' => '参数格式错误',
            'end_time.number' => '参数格式错误',
            'type.number' => '参数格式错误','type.in' => '参数不在接收范围内',
            'kind.number' => '参数格式错误','kind.in' => '参数不在接收范围内',
            'page.number' => '参数格式错误',
            'limit.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $type = isset($params['type']) ? $params['type'] : 1;
        $kind = isset($params['kind']) ? $params['kind'] : 1;
        $where['o.store_id'] = self::$user_info['store_id'];
        // 根据type组装where查询条件
        switch ($type){
            case 1:
                $where['o.pay_time'] = ['egt',strtotime(date("Y-m-d 00:00:00"))];
                break;
            case 2:
                $where['o.pay_time'] = ['between', [strtotime(date('Y-m-01 00:00:00')),strtotime(date('Y-m-d H:i:s'))]];
                break;
        }
        // 是否选择了时间查询范围
        if ($params['start_time'] > 0 && $params['end_time'] > 0){
            $where['o.pay_time'] = ['between', [$params['start_time'], $params['end_time']]];
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,20);
        if($power == 0){ //子账号
            if($kind == 1){
                $where['o.staff_id'] = self::$user_id;
            }else{
                $where['ob.buniess_id'] = self::$user_id;
            }
        }
        //收益统计
        $data = ProductModel::IncomeStatistics($where,$kind,$page,$limit,$kind);
        //$data = ProductModel::getOrderDetailsByWhere($where,$kind,$page,$limit,1,1);
        return self::returnResponse(0, '成功', $data);
    }
    /**
     *  订单统计 kind -1到店买单-  2商城订单  type1-今日【默认】 2-本月 3-总计
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderStatistics(){
        $params = self::$requestInstance->only(['start_time', 'end_time', 'type','kind','page','limit']);
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        // 参数验证
        $rule = [
            'start_time'   => 'number',
            'end_time'   => 'number',
            'type'   => 'number|in:1,2,3',
            'kind'   => 'number|in:1,2',
            'page'   => 'number',
            'limit'   => 'number',
        ];
        $msg = [
            'start_time.number' => '参数格式错误',
            'end_time.number' => '参数格式错误',
            'type.number' => '参数格式错误','type.in' => '参数不在接收范围内',
            'kind.number' => '参数格式错误','kind.in' => '参数不在接收范围内',
            'page.number' => '参数格式错误',
            'limit.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $type = isset($params['type']) ? $params['type'] : 1;
        $kind = isset($params['kind']) ? $params['kind'] : 1;
        $where['o.store_id'] = self::$user_info['store_id'];
        // 根据type组装where查询条件
        switch ($type){
            case 1:
                $where['o.pay_time'] = ['egt', strtotime(date("Y-m-d 00:00:00"))];
                break;
            case 2:
                $where['o.pay_time'] = ['between', [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-d H:i:s'))]];
                break;
        }
        // 是否选择了时间查询范围
        if ($params['start_time'] > 0 && $params['end_time'] > 0){
            $where['o.pay_time'] = ['between', [$params['start_time'], $params['end_time']]];
        }

        // 合并where条件
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,23);
        if($power == 0){ //子账号
            if($kind == 1){
                $where['o.staff_id'] = self::$user_id;
            }else{
                $where['ob.buniess_id'] = self::$user_id;
            }
        }
        $data = ProductModel::numStatistics($where,$kind,$page,$limit,$kind);
        //$data = ProductModel::getOrderDetailsByWhere($where,$kind,$page,$limit,2);

        return self::returnResponse(0, '成功', $data);
    }


    /**
     * 获取统计订单详情
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStatisticsDetail(){
        $params = self::$requestInstance->only(['order_id','order_status']);

        // 参数验证
        $rule = [
            'order_id'   => 'require|number',
            'order_status'   => 'require|number',
        ];
        $msg = [
            'order_id.require' => '缺少参数','order_id.number' => '参数格式错误',
            'order_status.require' => '缺少参数','order_status.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $orderInfo = ProductModel::getOrderDetailsById($params['order_id'], $params['order_status'],self::$user_id);

        return self::returnResponse(0, '成功', $orderInfo);
    }

    /**
     * 获取员工信息数据
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBusinessInfo(){
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,26);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        $info = BusinessModel::getBusinessInfoData(self::$user_id);
        return self::returnResponse(0, '成功', $info);
    }

    /**
     *  员工修改头像
     * @return false|string
     */
    public function businessEditAvatar(){
        $file = request()->file('avatar');
        if($file){
            $return = BusinessModel::businessEditAvatar($file,self::$user_id);
            // 返回修改结果【包括错误信息及成功信息提示，成功则返回头像地址】
            return self::returnResponse($return['code'], $return['msg'], [$return['data']]);
        }else{
            return self::returnResponse(1, '请选择头像', []);
        }
    }

    /**
     * 获取员工账号列表
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStaffAccount(){
        //判断权限
        /*$power = self::power(self::$user_info['is_main_user'],self::$user_id,29);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);*/
        // 查询数据
        $data = BusinessModel::getStaffAccount(self::$user_id,self::$user_info);

        return self::returnResponse(0, '成功', $data);
    }

    /**
     * 获取权限列表
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPowerList(){
        $params = self::$requestInstance->only(['business_id']);
        // 参数验证
        $rule = [
            'business_id'         => 'require|number',
        ];
        $msg = [
            'business_id.require' => '缺少必要参数','business_id.number' => '参数格式不正确'
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,29);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        $list = BusinessPowerModel::getBusinessAllPower($params['business_id']);
        return self::returnResponse(0, '成功', $list);
    }
    /**
     *  获取角色列表
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRoleData(){
        // 查询数据
        $list = BusinessRoleModel::getRoleData();

        return self::returnResponse(0, '成功', $list);
    }
    /**
     *  新建员工账号
     * @return false|string
     */
    public function addBusinessAccount(){
        $params = self::$requestInstance->only(['business_name','role_id','mobile','password','password_confirm']);

        // 参数验证
        $rule = [
            'business_name'   => 'require',
            'mobile'          => 'require|number|length:11|unique:business',
            'role_id'         => 'require|number',
            'password'        => 'require|confirm',
        ];
        $msg = [
            'mobile.require' => '缺少参数','mobile.number' => '参数格式错误','mobile.length' => '请输入正确手机号','mobile.unique'=>'手机号已注册',
            'business_name.require' => '缺少参数',
            'role_id.require' => '缺少参数','role_id.number' => '参数格式错误',
            'password.require' => '缺少参数','password.confirm' => '密码不一致',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,29);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        $password =  password_hash($params['password'], PASSWORD_DEFAULT);
        $new_token = makeUserToken(true);
        $insertData = [
            'business_name'     => addslashes($params['business_name']),
            'role_id'           => $params['role_id'],
            'mobile'            => $params['mobile'],
            'password'          => $password,
            'pid'               => self::$user_id,
            'store_id'          => self::$user_info['store_id'],
            'token'             => $new_token,
            'token_expire_time' => time()+7*24*60*60,
        ];
        // 添加数据
        //$add = BusinessModel::insert($insertData);
        $add = BusinessModel::insertGetId ($insertData);
        $store_id=self::$user_info['store_id'];
        $type = input('type',1,'intval')  ;

        //生成二维码
        Loader::import('phpqrcode.phpqrcode');
        $QRcode = new \QRcode;
        //$value = 'http://appwx.supersg.cn/app/download.html';
        $value = 'http://appwx.supersg.cn/app/download.html?store_id='.$store_id.'&type='.$type.'&business_id='.$add;
        $errorCorrectionLevel = 'L';//纠错级别：L、M、Q、H
        $matrixPointSize = 10;//二维码点的大小：1到10
        $path=ROOT_PATH . 'public' . DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS.$add.'.png';
        $QRcode::png ( $value, $path, $errorCorrectionLevel, $matrixPointSize, 2 );//不带Logo二维码的文件名
        $logo = ROOT_PATH . 'public' . DS .'logo.png';//需要显示在二维码中的Logo图像
        $QR =$path;
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring ( file_get_contents ( $QR ) );
            $logo = imagecreatefromstring ( file_get_contents ( $logo ) );
            $QR_width = imagesx ( $QR );
            $QR_height = imagesy ( $QR );
            $logo_width = imagesx ( $logo );
            $logo_height = imagesy ( $logo );
            $logo_qr_width = $QR_width / 6.2;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            imagecopyresampled ( $QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height );
        }
        imagepng ( $QR, $path );//带Logo二维码的文件名
        $qrcode=  DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS .$add.'.png';
        //将qrcode 写入到员工表
        BusinessModel::where(['id' => $add]) -> update(['qrcode' => $qrcode]);

        //先写死   权限 7-互动消息 8-系统消息 10-商品查看 15-员工处理商城订单 18-员工处理到店订单 21-员工处理订单收益 24-统计 员工 26-个人信息 27-我的钱包 28-新用户推广 29-员工账号管理
        $data_power = array(
            ['business_id'=>$add,'power_id'=>7],
            ['business_id'=>$add,'power_id'=>8],
            ['business_id'=>$add,'power_id'=>10],
            ['business_id'=>$add,'power_id'=>15],
            ['business_id'=>$add,'power_id'=>18],
            ['business_id'=>$add,'power_id'=>21],
            ['business_id'=>$add,'power_id'=>24],
            ['business_id'=>$add,'power_id'=>26],
            ['business_id'=>$add,'power_id'=>27],
            ['business_id'=>$add,'power_id'=>28],
            ['business_id'=>$add,'power_id'=>29]
        );
        $result   =  db('business_power_details')->insertAll($data_power);
        if (!$add) return self::returnResponse(1, '失败', []);
        if (!$result) return self::returnResponse(1, '权限添加失败', []);
        return self::returnResponse(0, '成功', []);
    }

    /**
     *  添加或删除员工权限
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editBusinessPower(){
        // type 1-添加权限 2-删除权限
        $params = self::$requestInstance->only(['business_id','power_id','status']);
        //判断权限
        /*$power = self::power(self::$user_info['is_main_user'],self::$user_id,29);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);*/
        if(self::$user_info['is_main_user'] == 0){
            return self::returnResponse(1, '无权修改', []);
        }

        // 参数验证
        $rule = [
            'business_id'         => 'require|number',
            'power_id'            => 'require|number',
            'status'                => 'require|number|in:0,1',
        ];
        $msg = [
            'business_id.require' => '缺少必要参数','business_id.number' => '参数格式不正确',
            'power_id.require'    => '缺少必要参数','power_id.number'    => '参数格式不正确',
            'status.require'        => '缺少必要参数','status.number'        => '参数格式不正确','status.in' => '参数不在接收范围内',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        //修改权限
        BusinessPowerDetailsModel::editBusinessPower($params['business_id'],$params['power_id'],$params['status']);
        //获取权限
        $list = BusinessPowerModel::getBusinessAllPower($params['business_id']);

        if (is_string($list)) return self::returnResponse(1, $list, []);
        if ($list === false) return self::returnResponse(1, '失败', []);
        return self::returnResponse(0, '成功', $list);

    }

    /**
     *  我的 员工相关订单
     *  Kind  1-到店买单 2-商城订单
     * type   1今日  2总订单数   3结算中
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBusinessOrder(){
        $params = self::$requestInstance->only(['business_id','start_time', 'end_time', 'type','kind','page','limit']);
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        // 参数验证
        $rule = [
            'business_id'         => 'require|number',
            'start_time'   => 'number',
            'end_time'   => 'number',
            'type'   => 'number|in:1,2,3',
            'kind'   => 'number|in:1,2',
            'page'   => 'number',
            'limit'   => 'number',
        ];
        $msg = [
            'start_time.number' => '参数格式错误',
            'business_id.number' => '参数格式错误','business_id.require'   => '缺少必要参数',
            'end_time.number' => '参数格式错误',
            'type.number' => '参数格式错误','type.in' => '参数不在接收范围内',
            'kind.number' => '参数格式错误','kind.in' => '参数不在接收范围内',
            'page.number' => '参数格式错误',
            'limit.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $type = isset($params['type']) ? $params['type'] : 1;
        $kind = isset($params['kind']) ? $params['kind'] : 1;
        $business_id = isset($params['business_id']) ? $params['business_id'] : '';
        $where['o.store_id'] = self::$user_info['store_id'];
        // 根据type组装where查询条件
        if($type == 1){
            $where['o.pay_time'] = ['egt', strtotime(date("Y-m-d 00:00:00"))];
        }
        /*switch ($type){
            case 1:
                $where['o.pay_time'] = ['egt', strtotime(date("Y-m-d 00:00:00"))];
                break;
            case 2:
                $where['o.pay_time'] = ['between', [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-d H:i:s'))]];
                break;
        }*/
        // 是否选择了时间查询范围
        if ($params['start_time'] > 0 && $params['end_time'] > 0){
            $where['o.pay_time'] = ['between', [$params['start_time'], $params['end_time']]];
        }

        // 合并where条件
        //$where['ob.buniess_id'] = $business_id;
        /*if($type == 3){//结算中   不等于8的
            $data = ProductModel::getOrderDetailsByWhere($where,$kind,$page,$limit,2,0,1);
        }else{
            $data = ProductModel::getOrderDetailsByWhere($where,$kind,$page,$limit,2,0);
        }*/
        $data = ProductModel::BusinessNumStatistics($where,$kind,$page,$limit,$type,$business_id);

        return self::returnResponse(0, '成功', $data);
    }

    /**
     *  获取员工钱包数据
     * @return false|string   1 收入  2支出
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMyWallet(){
        $params = self::$requestInstance->only(['type','page','limit']);
        // 参数验证
        $rule = [
            'type'          => 'number|in:1,2',
        ];
        $msg = [
            'type.number' => '参数格式不正确','type.in' => '参数不在接收范围内',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $type = isset($params['type']) ? $params['type'] : 1;
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,27);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        //查询
        $data = BusinessModel::getMyWallet(self::$user_id,$type,$page,$limit);
        return self::returnResponse(0, '成功', $data);
    }

    /**
     * 员工提现
     * @return false|string
     */
    public function businessAddWithdraw(){
        $params = self::$requestInstance->only(['withdraw_money', 'alipay_account']);
        // 参数验证
        $rule = [
            'withdraw_money'         => 'require|float',
            'alipay_account'         => 'require',
        ];
        $msg = [
            'withdraw_money.require' => '缺少必要参数','withdraw_money.float' => '参数格式不正确',
            'alipay_account.require' => '缺少必要参数',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        //判断权限
        $power = self::power(self::$user_info['is_main_user'],self::$user_id,27);
        if($power < 1) return self::returnResponse(1, '暂无权限', []);
        $return = BusinessTiXianModel::businessTiXian(self::$user_id,$params['withdraw_money'],self::$user_info['store_id'],$params['alipay_account']);

        if (is_string($return)) return self::returnResponse(1, $return, []);
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', []);
    }


    /**
     * 优惠券列表
     */
    public function couponList(){
        $params = self::$requestInstance->only(['coupon_name','kind','is_open','page','limit']); //kind 线下优惠券类型：1.实物礼品券；2.满减券；3.体验券；0.无   is_open 是否开启 1开启 0关闭  2全部

        // 参数验证
        $rule = [
            'kind'         => 'number|in:0,1,2,3',
            'is_open'         => 'number|in:0,1,2',
            'page'         => 'number',
            'limit'         => 'number',
        ];
        $msg = [
            'kind.number' => '参数格式不正确','kind.in' => '参数不在接收范围内',
            'is_open.number' => '参数格式不正确','is_open.in' => '参数不在接收范围内',
            'page.number' => '参数格式不正确',
            'limit.number' => '参数格式不正确',
        ];
        $coupon_name = isset($params['coupon_name']) ? $params['coupon_name'] : '';
        $kind = isset($params['kind']) ? $params['kind'] : 0;
        $is_open = isset($params['is_open']) ? $params['is_open'] : 2;
        $page= isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $return = CouponModel::couponList($coupon_name,$kind,$is_open,self::$user_id,self::$user_info['store_id'],self::$user_info['is_main_user'],$page,$limit);
        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, []);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', $return);
    }

    /**
     * 领取优惠券
     */
    public function getCoupon(){
        $params = self::$requestInstance->only(['coupon_id','page','limit']);
        // 参数验证
        $rule = [
            'coupon_id'         => 'require|number',
            'page'         => 'number',
            'limit'         => 'number',
        ];
        $msg = [
            'coupon_id.require' => '缺少必要参数','coupon_id.number' => '参数格式不正确',
            'page.number' => '参数格式不正确',
            'limit.number' => '参数格式不正确',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        if(self::$user_info['is_main_user'] == 0) return self::returnResponse(1, '员工不能查看', []);
        $page= isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $return = CouponModel::getCoupon($params['coupon_id'],self::$user_info['store_id'],$page,$limit);
        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, []);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', $return);
    }

    /**
     * 优惠券核销详情
     */
    public function validateDetail(){
        $params = self::$requestInstance->only(['coupon_id','validate_id','page','limit']);
        // 参数验证
        $rule = [
            'coupon_id'         => 'number',
            'validate_id'         => 'number',
            'page'         => 'number',
            'limit'         => 'number',
        ];
        $msg = [
            'coupon_id.number' => '参数格式不正确',
            'validate_id.number' => '参数格式不正确',
            'page.number' => '参数格式不正确',
            'limit.number' => '参数格式不正确',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $coupon_id= isset($params['coupon_id']) ? $params['coupon_id'] : 0;
        $validate_id= isset($params['validate_id']) ? $params['validate_id'] : 0;
        $page= isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;

        if(self::$user_info['is_main_user'] == 1) {//主账号可以查看核销列表     以及单个用户核销详情  （数据结构一样）
            if($validate_id > 0){//主账号点击单个核销详情
                $return = CouponModel::validateDetail(1,$validate_id,self::$user_info['store_id'],$page,$limit);
            }else{//点击核销详情
                $return = CouponModel::validateDetail(2,$coupon_id,self::$user_info['store_id'],$page,$limit);
            }
        }else{//子账号只可以查看自己核销的优惠券
            $return = CouponModel::validateDetail(3,$coupon_id,self::$user_info['store_id'],$page,$limit,self::$user_id);
        }
        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, []);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', $return);
    }

    /**
     * 核销券码
     */
    public function validateCoupon(){
        $params = self::$requestInstance->only(['validate_code','type']);
        $rule = ['validate_code'         => 'require',];
        $msg = ['validate_code.require' => '缺少必要参数'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $validate_code= $params['validate_code'];
        $type= isset($params['type']) ? $params['type'] : 1;//券码核销   2二维码核销
        $return = CouponModel::validateCoupon($validate_code,self::$user_id,self::$user_info['store_id'],$type);
        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, []);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '核销成功', []);
    }

    /**
     * 获取推广列表
     */
    public function getRecommendList(){
        // type 1-最近15天【默认】  2-总计
        $params = self::$requestInstance->only(['type','start_time','end_time','page','limit']);
        // 参数验证
        $rule = ['type'         => 'number|in:1,2', 'start_time'   => 'number', 'end_time'     => 'number',];
        $msg = ['type.number'       => '参数格式错误','type.in' => '参数不在接收范围内', 'start_time.number' => '参数格式错误', 'end_time.number'   => '参数格式错误',];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        // 默认查询最近15天
        $type = isset($params['type']) ? $params['type'] : 1;
        $start_time = isset($params['start_time']) ? $params['start_time'] : 0;
        $end_time = isset($params['end_time']) ? $params['end_time'] : 0;
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $where['staff_id'] = self::$user_id;
        $where['type'] = ['in',[3,4,5]];

        //计算收益
        $total = BussinessProfitModel::getBusinessTotal($where,$type,$start_time,$end_time);
        if(empty($total)) $total = '0';
        $return['total'] = $total;
            //计算未结转的
        $unpaid = BussinessProfitModel::getBusinessRecommendList($where,2,$page,$limit);
        $paid   = BussinessProfitModel::getBusinessRecommendList($where,3,$page,$limit);
        $return['list'] = array_merge($unpaid,$paid);
        // 返回错误信息
        if (is_string($return)) return self::returnResponse(1, $return, []);
        // 返回失败
        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', $return);
    }

    /**
     * 获取推广详情  1未结算   2已结算
     */
    public function getRecommendDetail(){
        $params = self::$requestInstance->only(['status','start_time','end_time']);
        // 参数验证
        $rule = ['status'=> 'require|number|in:1,2', 'start_time'   => 'require|number', 'end_time'     => 'require|number',];
        $msg = ['status.number' => '参数格式错误','status.in' => '参数不在接收范围内','status.require' => '缺少必要参数',
            '   start_time.require' => '缺少必要参数','start_time.number' => '参数格式错误',
                'end_time.require' => '缺少必要参数', 'end_time.number'   => '参数格式错误',];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        // 默认查看未支付
        $status = isset($params['status']) ? $params['status'] : 1;
        $status += 1;
        $start_time = isset($params['start_time']) ? $params['start_time'] : 0;
        $end_time = isset($params['end_time']) ? $params['end_time'] : 0;
        $where['staff_id'] = self::$user_id;
        $where['status'] = $status;
        $where['create_time'] = ['between', [$start_time, $end_time]];
        //计算首个用户奖励
        $one = BussinessProfitModel::getBusinessOne($where,4);
        //计算用户达到的平台推广阶梯奖励
        $jieti = BussinessProfitModel::getBusinessJieti($where,5);
        //计算单新用户推广奖励；
        $recommend = BussinessProfitModel::getBusinessRecommend($where,3,$status,$start_time,$end_time);
        $return = array_merge($one,$jieti,$recommend);

        return self::returnResponse(0, '成功', $return);

    }

    /**
     * 获取销售详情
     */
    public function getSalesDetail(){
        $params = self::$requestInstance->only(['profit_id']);
        // 参数验证
        $rule = ['profit_id'=> 'require|number'];
        $msg = ['profit_id.number' => '参数格式错误','profit_id.require' => '缺少必要参数'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $return= BussinessProfitModel::getSalesDetail($params['profit_id']);
        return self::returnResponse(0, '成功', $return);
    }

    /**
     * 员工收入详情  2.销售总额阶梯奖励；3.新用户推广奖励；4.首个用户额外奖励；5.新用户推广阶梯奖励
     */
    public function getIncomesDetail(){
        $params = self::$requestInstance->only(['profit_id']);
        // 参数验证
        $rule = ['profit_id'=> 'require|number'];
        $msg = ['profit_id.number' => '参数格式错误','profit_id.require' => '缺少必要参数'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $return= BussinessProfitModel::getIncomesDetail($params['profit_id']);
        return self::returnResponse(0, '成功', $return);
    }
    /**
     * 获取系统消息列表
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBusinessMsgData(){
        $data = MsgModel::getBusinessMsg(self::$user_id);
        return self::returnResponse(0, '成功', $data);
    }

    /**
     * 查看系统消息【并将未读数据设置为已读】
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lookSysMsgList(){
        $params = self::$requestInstance->only(['msg_id']);
        $rule = ['msg_id'         => 'require|number'];
        $msg = ['msg_id.require' => '缺少必要参数','msg_id.number' => '参数格式不正确'];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }
        $return = MsgModel::lookSysMsgList(self::$user_id,$params['msg_id']);

        if ($return === false) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', []);
    }




    /**
     * 员工删除系统消息
     * @return false|string
     */
    public function businessDelMsgData(){
        $params = self::$requestInstance->only(['sys_msg_id']);

        // 参数验证
        $rule = [
            'sys_msg_id'   => 'require|number',
        ];
        $msg = [
            'sys_msg_id.require' => '缺少参数','sys_msg_id.number' => '参数格式错误',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return self::returnResponse(1, $validate->getError(), []);
        }

        $del = MsgReadModel::delMsgData($params['sys_msg_id'], self::$user_id);

        if (!$del) return self::returnResponse(1, '失败', []);

        return self::returnResponse(0, '成功', []);
    }

    /**
     * 给员工创建二维码
     * @return false|string
     */
    public function businessQrcode(){
        $params = self::$requestInstance->only(['store_id','type','business_id']);
        $store_id = $params['store_id'];
        $add = $params['business_id'];
        $type = $params['type'];

        
        //生成二维码
        Loader::import('phpqrcode.phpqrcode');
        $QRcode = new \QRcode;
        //$value = 'http://appwx.supersg.cn/app/download.html';
        $value = 'http://appwx.supersg.cn/app/download.html?store_id='.$store_id.'&type='.$type.'&business_id='.$add;
        $errorCorrectionLevel = 'L';//纠错级别：L、M、Q、H
        $matrixPointSize = 10;//二维码点的大小：1到10
        $path=ROOT_PATH . 'public' . DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS.$add.'.png';
        $QRcode::png ( $value, $path, $errorCorrectionLevel, $matrixPointSize, 2 );//不带Logo二维码的文件名
        $logo = ROOT_PATH . 'public' . DS .'logo.png';//需要显示在二维码中的Logo图像
        $QR =$path;
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring ( file_get_contents ( $QR ) );
            $logo = imagecreatefromstring ( file_get_contents ( $logo ) );
            $QR_width = imagesx ( $QR );
            $QR_height = imagesy ( $QR );
            $logo_width = imagesx ( $logo );
            $logo_height = imagesy ( $logo );
            $logo_qr_width = $QR_width / 6.2;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            imagecopyresampled ( $QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height );
        }
        imagepng ( $QR, $path );//带Logo二维码的文件名
        $qrcode=  DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS .$add.'.png';
        //将qrcode 写入到员工表
        BusinessModel::where(['id' => $add]) -> update(['qrcode' => $qrcode]);
        return self::returnResponse(0, '成功', []);
    }

    public function test(){
        //return self::returnResponse(0, '成功', array(array('id'=>1,'age'=>2),array('id'=>3,'age'=>4)));
        $data = [
            'avatar'=>'http://wx.supersg.cn/uploads/store/cover/20191029/564ad55d002cacf4ad5325ed6e342fd3.png'
        ];
        $jig_id = 'user_15275_test';
        //JiG::editUserData($jig_id,$data);


        //$re2 = JiG::sendMsgToStaff("s1871",'fahou_system_msg');
        $data = [
            'avatar'=>'http://wx.supersg.cn/uploads/store/cover/20191029/564ad55d002cacf4ad5325ed6e342fd3.png'
        ];
        //$re2 = JiG::editServiceData('store_1871_test',$data);



        //$re2 = JiG::editServiceInfo("1871");
        //echo json_encode($re2);
    }
}