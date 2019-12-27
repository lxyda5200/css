<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\store_v1\controller;

use app\store_v1\model\BrandStore;
use app\store_v1\model\NewArea;
use app\store_v1\repository\implementses\REnter;
use think\Db;
use think\Exception;
use think\Request;
use think\response\Json;
use think\Session;
use app\store_v1\model\Store as storeModel;
header("content-type:text/html;charset=utf-8");
class Store extends Base
{
    protected $model = [];
    protected $noNeedRight ='*';

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->model = new storeModel();
    }

    /**
     * 修改店铺信息
     */
    public function modifyStoreInfo(){
        try{
            $storeModel = new storeModel();
            $post = $this->request->post();
            if(empty($post['ids'])){
                throw new \Exception('店铺信息错误');
            }
            $res = $storeModel->where(['id'=>intval($post['ids'])])->find();
            if(empty($res)){
                throw new \Exception('店铺信息不存在');
            }
            $post['store_id'] =$res['id'];
            $store= [];
            if(!empty($post['store_name'])){
                $store['store_name'] = htmlspecialchars_decode($post['store_name']);
            }
            if(!empty($post['business_img'])){
                $store['business_img'] = trim($post['business_img']);
            }
            if(!empty($post['cover'])){
                $store['cover'] = trim($post['cover']);
            }
            if(!empty($post['description'])){
                $store['description'] = htmlspecialchars_decode($post['description']);
            }

            if(!empty($post['nickname'])){
                $store['nickname'] = trim($post['nickname']);
            }
            if(!empty($post['mobile'])){
                $store['mobile'] = trim($post['mobile']);
            }
            if(!empty($post['telephone'])){
                $store['telephone'] = trim($post['telephone']);
            }
            Db::startTrans();
            //更新品牌信息
            if(!empty($post['brand_id'])){
                $Brand = $storeModel->getBrand($post);
                if(empty($Brand)){
                    Db::rollback();
                    throw new Exception('品牌不存在或已禁用');
                }
                $store['brand_type'] = $Brand['type'];
                $store['brand_name'] = $Brand['brand_name'];
                $store['brand_img'] = $Brand['logo'];
                $store['brand_id'] = intval($Brand['brand_id']);
                $Brand['store_id'] = $res['id'];
                $Brand['brand_id'] = $post['brand_id'];
                $Brand['main_id'] = $res['p_id'];
                //删除品牌关联
                $Brands = new BrandStore();
                $Brands->where(['store_id'=>$res['id']])->delete();
                $res_brand = $storeModel->addBrandStoreRelation($Brand);
                if(!$res_brand){
                    Db::rollback();
                    throw new Exception('品牌更新失败');
                }
            }
            #更新普通店铺数据
            if($res['type'] ==1){
                //更新地址信息
                if(!empty($post['province']) && !empty($post['city']) && !empty($post['area']) && !empty($post['address'])){
                    $store['province'] = intval($post['province']);
                    $store['city'] = intval($post['city']);
                    $store['area'] = intval($post['area']);
//                    $store['province'] = NewArea::get_name(intval($post['province']));
//                    $store['city'] = NewArea::get_name(intval($post['city']));
//                    $store['area'] = NewArea::get_name(intval($post['area']));
                    $store['address'] = trim($post['address']);
                }
                //更新商圈
                if(!empty($post['circle_id'])){
                    //删除原来的商圈
                    Db::table('business_circle_store')->where(['store_id'=>$res['id']])->delete();
                    $resn = $storeModel->Store_circle($post);
                    if(!$resn){
                        Db::rollback();
                        return \json(self::callback(0,'关联商圈失败'));
                    }
                }
                //更新线下店铺图片信息
                if(!empty($post['img_uels'])){
                    $add_Store_imgs = img_array($res['id'],$post['img_uels']);
                    if(!empty($add_Store_imgs)) {
                        //删除原来的线下店铺图片
                        Db::table('store_imgs')->where(['store_id'=>$res['id']])->delete();
                        $res_imgs = $storeModel->add_Store_imgs($add_Store_imgs);
                        if (!$res_imgs){
                            Db::rollback();
                            throw new Exception('更新线下店铺图片失败');
                        }

                    }
                }
                if(!empty($post['is_ziqu'])){
                    if($post['is_ziqu'] != 1){
                        $store['is_ziqu'] =0;
                    }else{
                        $store['is_ziqu'] =1;
                    }
                }
            }
            # 更新店铺图片
            if(!empty($post['img_uel'])){
                $add_Store_img = img_array($res['id'],$post['img_uel']);
                if(!empty($add_Store_img)) {
                    //删除店铺图片
                    Db::table('store_img')->where(['store_id'=>$res['id']])->delete();
                    $res_img = $storeModel->add_Store_img($add_Store_img);
                    if (!$res_img)
                        throw new Exception('更新店铺图片失败');
                }
            }
            # 添加店铺主营分类关系
            if(!empty($post['cate_id'])){
                //删除原来的主营分类关系
                Db::table('store_cate_store')->where(['store_id'=>$res['id']])->delete();
                $res_cate = $storeModel->addStoreCateRelation($post);
                if(!$res_cate)
                    throw new Exception('更新店铺分类失败');
            }
            # 添加店铺主营风格
            if(!empty($post['style_id'])){
                //删除原来的店铺主营风格
                Db::table('store_style_store')->where(['store_id'=>$res['id']])->delete();
                $res_style = $storeModel->addStoreStyle($post);
                if(!$res_style){
                    Db::rollback();
                    throw new Exception('更新店铺分类失败');
                }
            }
            # 添加店铺状态记录
            $res_info = $storeModel->writeStoreStatusLog($res['id'], 1);
            if(!$res_info){
                Db::rollback();
                throw new Exception('日志记录失败');
            }
            if(!empty($post['platform_ticheng'])){
                $store['platform_ticheng'] = trim($post['platform_ticheng']);
            }
            if(!empty($post['refund_address'])){
                $store['refund_address'] = htmlspecialchars_decode($post['refund_address']);
            }
            if(!empty($post['refund_mobile'])){
                $store['refund_mobile'] = htmlspecialchars_decode($post['refund_mobile']);
            }
            if(empty($store)){
                Db::rollback();
                throw new Exception('更新数据为空');
            }
            $store['sh_type'] = 2;
            $store['sh_status'] = 0;
            $store['update_time'] = time();
            $storeModel->allowField(true)->save($store,['id'=>$res['id']]);
            Db::commit();
            return \json(self::callback(1,'更新成功'));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }



    /**
     * 店铺分类
     */
    public function StoreCate(){
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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;
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

            Db::startTrans();
            $storeModel = new storeModel();
            $post['sh_type'] = 2;
            $post['sh_status'] = 0;
            $storeModel->allowField(true)->save($post,['id'=>$store_info['id']]);

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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;
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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;

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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;

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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;

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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;
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
//            $store_info = \app\store_v1\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
            $store_info = $this->store_info;

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


    /***************************************************************************************************************************
     * 分界线   20191125
     */

    /**
     * 店铺列表
     */
    public function storelist(){
        try{
            $type = $this->request->request('type');
            $store_name = $this->request->request('store_name');
            //$type   查询条件 1只查正常 已审核的 2查全部
            $data = storeModel::get_list($this->store_info,$type,$store_name);
            return \json(self::callback(1,'返回成功',$data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }

    /**
     * 店铺详情
     */
    public function storedetails(){
        try{
            $store_id = intval(input('store_id')); //店铺ID
            if(empty($store_id)){
                //如果未传输店铺ID默认查询当前登录账号绑定店铺信息
                $store_id = $this->store_info['store_id'];
            }
            $data = storeModel::get_details($store_id);
            return \json(self::callback(1,'返回成功',$data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }
    /**
     * 店铺新增
     */
    public function storeadd(){
        $params = input('post.');
        Db::startTrans();
        try{
//            if(empty($params['type']) || !in_array($params['type'],[1,2])){
//                return \json(self::callback(0,'请选择店铺类型'));
//            }
            if(empty($params['opening_type']) || !in_array($params['opening_type'],[1,2])){
                return \json(self::callback(0,'请选择开通时长'));
            }
            if(empty($params['business_img'])){
                return \json(self::callback(0,'请上传店铺营业执照等资质'));
            }
            $validate = new \app\store_v1\validate\Store();
            if(!$validate->scene('add')->check($params)){
                return \json(self::callback(0, $validate->getError()));
            }
            $store = new \app\store_v1\model\Store();
            $Brand = $store->getBrand($params);
            if(empty($Brand)){
                throw new Exception('品牌不存在或已禁用');
            }
            # 添加品牌信息，获取到store_id用于之后的操作
            if(empty($this->store_info['main_id'])){
                throw new Exception('店铺主店不存在');
            }
            $params['p_id'] = $this->store_info['main_id'];
            $store_msg = $this->addBrandInfo($params,$Brand);
            if(empty($store_msg) || $store_msg['code'] !=1 || empty($store_msg['data'])){
                Db::rollback();
                if(!empty($store_msg['msg'])){
                    throw new Exception($store_msg['msg']);
                }else{
                    throw new Exception('提交失败');
                }
            }
            $store_id= $store_msg['data'];
            $params['store_id'] = $store_id;
            $params['main_id'] = $this->store_info['main_id'];
            # 添加店铺图片
            if(empty($params['img_uel'])){
                Db::rollback();
                throw new Exception('请上传店铺图片');
            }
            $add_Store_img = img_array($store_id,$params['img_uel']);
            if(empty($add_Store_img)){
                Db::rollback();
                throw new Exception('请上传店铺图片');
            }

            $res = $store->add_Store_img($add_Store_img);
            if(!$res)
                throw new Exception('提交失败2');

            # 添加店铺线下图片
            if($this->store_info['type'] ==1){
                if(empty($params['img_uels'])){
                    Db::rollback();
                    throw new Exception('请上传店铺线下图片');
                }
                $add_Store_imgs = img_array($store_id,$params['img_uels']);
                if(empty($add_Store_imgs)){
                    Db::rollback();
                    throw new Exception('请上传店铺线下图片');
                }
                $res = $store->add_Store_imgs($add_Store_imgs);
                if(!$res)
                    throw new Exception('提交失败3');
            }

            # 添加店铺主营分类关系
            $res = $store->addStoreCateRelation($params);
            if(!$res)
                throw new Exception('提交失败5');
            # 添加店铺主营风格
            $res = $store->addStoreStyle($params);
            if(!$res)
                throw new Exception('提交失败6');

            # 添加店铺状态记录
            $res = $store->writeStoreStatusLog($store_id, 1);
            if(!$res)
                throw new Exception('日志记录失败');
            Db::commit();
            return \json(self::callback(1,'提交成功'));
        }catch (Exception $exception) {
            Db::rollback();
            return \json(self::callback(0,$exception->getMessage()));
        }
    }

    /**
     * 店铺状态修改
     */
    public function store_edit(){
        try{
            $store_id = intval(input('store_id')); //店铺ID
            $status = intval(input('status')); //店铺ID
            if(empty($store_id) || !in_array($status,[1,2])){
                return \json(self::callback(0,'店铺信息未传输'));
            }
            $data = storeModel::edit_status($store_id,$status);
            if($data){
                return \json(self::callback(1,'操作成功'));
            }
            return \json(self::callback(0, '操作失败'));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }

    /**
     * 获取店铺品牌列表
     */
    public function brandstore(){
        try{
            $data = $this->model->get_brand($this->store_info['main_id']);
            return \json(self::callback(1,'返回成功',$data));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }

    }


    /**
     * 店铺品牌添加
     */
    public function brandstoreadd(){
        try{
            $params = input('post.');
            $validate = new \app\store_v1\validate\Store();
            if(!$validate->scene('brand')->check($params)){
                return \json(self::callback(0, $validate->getError()));
            }
            if(!in_array($params['is_brand'],[1,2])){
                return \json(self::callback(0, '品牌商状态不正确'));
            }
            if($params['is_brand'] ==2){
                if(empty($params['brand_img'])){
                    return \json(self::callback(0, '非品牌商需要上传品牌授权书'));
                }
            }
            if(empty($params['notion'])){
                return \json(self::callback(0, '请填写品牌理念信息'));
            }
            if(!in_array($params['status'],[1,2])){
                return \json(self::callback(0, '品牌生效状态不正确'));
            }
            if(empty($params['brand_time_start'])){
                return \json(self::callback(0, '请选择品牌有效时间段'));
            }else{
                if($params['brand_time_start'] ==1){
                    $params['brand_time_end'] =1;
                }else{
                    $params['brand_time_start'] = strtotime($params['brand_time_start']);
                    $params['brand_time_end'] = strtotime($params['brand_time_end']);
                    if($params['brand_time_end']<=$params['brand_time_start'] || empty($params['brand_time_end']) || $params['brand_time_end']<time()){
                        return \json(self::callback(0, '请选择正确的品牌有效时间段'));
                    }
                }
            }
            $brand = new \app\store_v1\model\Brand();
            //查询品牌名称是否存在
            $re = $brand->where(['brand_name'=>$params['brand_name']])->field('id')->find();
            if($re){
                return \json(self::callback(0, '品牌名已存在'));
            }
            Db::startTrans();
            $time = time();
            //添加品牌信息
            $brand_data = [
                'brand_name'=>trimStr($params['brand_name']),
                'cate_id'=>$params['cate_id'],
                'logo'=>trimStr($params['logo']),
                'type'=>2,
                'status'=>0,
                'create_time'=>$time,
            ];
            $brand_id = $brand->allowField(true)->insertGetId($brand_data);
            if(!$brand_id){
                Db::rollback();
                return \json(self::callback(0, '品牌创建失败'));
            }
            $res = Db::table('brand_story')->insertGetId([
                'brand_id'=>$brand_id,
                'notion'=>trimStr($params['notion']),
                'create_time'=>time(),
            ]);
            if(empty($res)){
                Db::rollback();
                return \json(self::callback(0, '品牌创建失败'));
            }
            $params['create_time'] = $time;
            $params['type'] =2;
            $params['company_id'] =$this->store_info['main_id'];
            //总店关联品牌信息
            $BrandCompany = new \app\store_v1\model\BrandCompany();
            $brand_datas = [
                'brand_id'=>$brand_id,
                'company_id'=>$this->store_info['main_id'],
                'brand_time_start'=>$params['brand_time_start'],
                'brand_time_end'=>$params['brand_time_end'],
                'certs'=>trimStr($params['certs']),
                'brand_img'=>trimStr($params['brand_img']),
                'is_brand'=>intval($params['is_brand']),
                'status'=>$params['status'],
                'type'=>2,
                'create_time'=>$time,
            ];
            if($params['is_brand'] ==2 && !empty($params['brand_url'])){
                $brand_datas['brand_url'] = trim($params['brand_url']);
            }
            $brandCompany_id = $BrandCompany->allowField(true)->insertGetId($brand_datas);
            if(empty($brandCompany_id)){
                Db::rollback();
                return \json(self::callback(0, '品牌创建失败'));
            }
            //店铺关联信息
            $StoreRela_data = [
                'brand_id'=>$brand_id,
                'store_id'=>$this->store_info['store_id'],
                'main_id'=>$this->store_info['main_id'],
            ];
            $res = $this->model->addBrandStoreRelation($StoreRela_data);
            if(empty($res)){
                Db::rollback();
                return \json(self::callback(0, '品牌创建失败'));
            }
            //添加审核记录
            $res = Db::table('brand_review')->insertGetId(
                [
                    'store_id'=>$this->store_info['main_id'],
                    'brand_id'=>$brand_id,
                    'create_time'=>time(),
                ]
            );
            if(empty($res)){
                Db::rollback();
                return \json(self::callback(0, '品牌创建失败'));
            }
            Db::commit();
            return \json(self::callback(1,'品牌创建成功',['brand_id'=>$brand_id,'brand_name'=>$params['brand_name'],'logo'=>$params['logo']]));
        }catch (Exception $exception) {
            return \json(self::callback(0, $exception->getMessage()));
        }
    }


    /**
     * 添加店铺信息
     */
    protected function addBrandInfo($data,$brand=[]){
        $store = new \app\store_v1\model\Store();
        $info_data = [
            'opening_type'=>intval($data['opening_type']),
            'store_name'=>trimStr($data['store_name']), //店铺名字
            'cover'=>trimStr($data['cover']), //logo
            'p_id'=>$this->store_info['main_id'],//主店铺ID
            'nickname'=>trimStr($data['nickname']),
            'mobile'=>$data['mobile'],
            'business_img'=>trimStr($data['business_img']),
            'sh_type'=>2,
            'type'=>$this->store_info['type'],
            'create_time'=>time(),
        ];
        if(!empty($brand['id'])){
            $info_data['brand_id'] = intval($brand['id']);
            $info_data['brand_name'] = trimStr($brand['brand_name']);
            $info_data['brand_img'] = trimStr($brand['logo']);
        }
        if($this->store_info['type'] ==1){
            if(empty($data['province']) || empty($data['city']) || empty($data['area']) || empty($data['address'])){
                return ['code'=>0,'msg'=>'店铺地址信息未补充完整'];
            }
            $info_data['province'] = intval($data['province']);
            $info_data['city'] = intval($data['city']);
            $info_data['area'] =intval($data['area']);
//            $info_data['province'] = NewArea::get_name(intval($data['province']));
//            $info_data['city'] = NewArea::get_name(intval($data['city']));
//            $info_data['area'] = NewArea::get_name(intval($data['area']));
            $info_data['address'] = $data['address'];
            if(isset($data['is_ziqu']) && in_array($data['is_ziqu'],[1,2])){
                $is_ziqu = intval($data['is_ziqu']);
                if($is_ziqu != 1){
                    $info_data['is_ziqu'] =0;
                }else{
                    $info_data['is_ziqu'] =1;
                }
            }
        }else{
            if(isset($data['is_ziqu'])){
                unset($data['is_ziqu']);
            }
        }
        if(!empty($data['description'])){
            $info_data['description'] = trimStr($data['description']);
        }
        if(!empty($data['telephone'])){
            $info_data['telephone'] = trim($data['telephone']);
        }
        $store_id = $store->insertGetId($info_data);
        if(!$store_id){
            return ['code'=>0,'msg'=>'添加店铺失败'];
        }else{
            $data['store_id'] = $store_id;
            if($this->store_info['type'] ==1){
                if(!empty($data['circle_id'])){
                    //删除原商圈
                    Db::table('business_circle_store')->where(['store_id'=>$store_id])->delete();
                    $res = $store->Store_circle($data);
                    if(!$res){
                        return ['code'=>0,'msg'=>'关联商圈失败'];
                    }
                }
            }
            # 添加店铺品牌关系
            $res = $store->addBrandStoreRelation($data);
            if(!$res){
                return ['code'=>0,'msg'=>'关联品牌失败'];
            }
            return ['code'=>1,'data'=>$store_id];
        }
    }

}