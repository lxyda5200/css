<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\mainstore\controller;

use app\common\controller\Base;
use think\Db;
use think\Loader;
use think\response\Json;
use think\Session;
use app\mainstore\model\Store as storeModel;
class Store extends Base
{
    /**
     * 店铺列表
     */
    public function StoreList(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $status = $this->request->post('status');

            if(isset($status)){
                if($status==1){
                    //上架
                    $where['store_status'] = ['eq', 1];
                    $where['sh_status'] = ['eq', 1];
                }elseif($status==0){
                    //下架
                    $where['store_status'] = ['eq', 0];
                    $where['sh_status'] = ['eq', 1];
                }elseif($status==2){
                    //待审核
                    $where['sh_status'] = ['eq', 0];
                }elseif($status==-1){
                    //审核失败
                    $where['sh_status'] = ['eq', -1];
                }else{
                    //报错
                    return \json(self::callback(0,'状态错误!',false));
                }
            }else{
                $where['sh_status'] = ['neq', -2];
            }
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            if (!empty($keywords)) {
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where['id|store_name|address'] = ['like', "%$keywords%"];
            }
            $total = Db::name('store')
                ->field('id')
                ->where('p_id',$store_info['id'])
                ->where($where)
                ->count();
            $storelist = Db::name('store')
                ->field('id,store_name,address,create_time,store_status,sh_status')
                ->where('p_id',$store_info['id'])
                ->where($where)
                ->page($page,$size)
                ->order('id','desc')
                ->select();
            if($storelist){
                foreach ( $storelist as $k=>$v){
                    $storelist[$k]['product_number'] = Db::name('product')->where('store_id',$v['id'])->count();
                }
            }
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$storelist;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 销售管理
     */
    public function SalesManagement(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
//            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $status = input('status') ? intval(input('status')) : '0';
            $id = input('id') ? intval(input('id')) : '0';
            $size = input('size') ? intval(input('size')) : 15;
            $page = input('page') ? intval(input('page')) : 1;
//            if (!empty($keywords)) {
//                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
//                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
//                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
//                $where['id|store_name'] = ['like', "%{$keywords}%"];
//            }
            if(isset($id) && $id>0){
                $where['id'] = ['eq', $id];
            }
            if($status==1){
                $where['is_tixian'] = ['eq', 1];
            }elseif($status==-1){
                $where['is_tixian'] = ['eq', -1];
            }

            $total = Db::name('store')
                ->field('id')
                ->where('p_id',$store_info['id'])
                ->where($where)
                ->count();
            $storelist = Db::name('store')
                ->field('id,store_name,address,create_time,store_status,is_tixian,money as allow_income')
                ->where('p_id',$store_info['id'])
                ->where($where)
                ->page($page,$size)
                ->order('id','desc')
                ->select();
            if($storelist){
                foreach ( $storelist as $k=>$v){
                    //今日收益
                    $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
                    $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
                    $where1['pay_time'] = ['between',[$beginToday,$endToday]];
                    $where1['order_status'] = ['eq',3];
                    $storelist[$k]['today_income'] = Db::name('product_order')->where('store_id',$v['id'])->where($where1)->sum('pay_money');
                    //总收益
                    $storelist[$k]['total_income'] = Db::name('product_order')->where('store_id',$v['id'])->where('order_status',3)->sum('pay_money');
                    //待确认收益
                    $storelist[$k]['wait_income'] = Db::name('product_order')->where('store_id',$v['id'])->where('order_status','in','3,4')->sum('pay_money');
                    //可提现收益
//                    $storelist[$k]['allow_income'] = $v['money'];
                }
            }
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$storelist;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 分店销售管理
     */
    public function StoreManagement(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id') ? intval(input('id')) : '0';
            if(!$id){return \json(self::callback(0,'参数错误'));}
            $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
            $where1['create_time'] = ['between',[$beginToday,$endToday]];
            $where2['pay_time'] = ['between',[$beginToday,$endToday]];
            $where2['order_status'] = ['gt',2];
            $where3['order_status'] = ['gt',2];
            //--订单数
            //今日订单
            $storelist['today_orders'] = Db::name('product_order')->where('store_id',$id)->where($where1)->count();
            //总订单
            $storelist['total_orders'] = Db::name('product_order')->where('store_id',$id)->count();
            //可提现收益
            $storelist['allow_tixian_income'] = Db::name('store')->where('id',$id)->value('money');
            //--今日收益
            //平台补贴
            $storelist['today_subsidy'] = Db::name('product_order')->where('store_id',$id)->where($where2)->sum('coupon_money');
            //实收款
            $storelist['today_real_income'] = Db::name('product_order')->where('store_id',$id)->where($where2)->sum('pay_money');
            $store_coupon = Db::name('product_order')->where('store_id',$id)->where($where2)->sum('store_coupon_money');
            $product_coupon= Db::name('product_order')->where('store_id',$id)->where($where2)->sum('product_coupon_money');
            //销售额
            $storelist['today_sales_money'] =$storelist['today_subsidy']+$store_coupon+$product_coupon+$storelist['today_real_income'];
            //--总收益
            //平台补贴
            $storelist['total_subsidy'] = Db::name('product_order')->where('store_id',$id)->where($where3)->sum('coupon_money');
            //实收款
            $storelist['total_real_income'] = Db::name('product_order')->where('store_id',$id)->where($where3)->sum('pay_money');
            $total_store_coupon = Db::name('product_order')->where('store_id',$id)->where($where3)->sum('store_coupon_money');
            $total_product_coupon= Db::name('product_order')->where('store_id',$id)->where($where3)->sum('product_coupon_money');
            //销售额
            $storelist['total_sales_money'] =$storelist['total_subsidy']+$total_store_coupon+$total_product_coupon+$storelist['total_real_income'];
            $data=$storelist;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 设置可否提现
     */
    public function tixianSet(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $type=input('type') ? intval(input('type')) : 0;// 1单个 2为多个
            $status = input('status') ? intval(input('status')) : 0; //1可提现 -1不可提现
            $id=input('id') ? intval(input('id')) : 0;// 店铺id
            $ids = $this->request->post('ids/a');
            if(!$type || !$status){return \json(self::callback(0,'参数错误'));}
            if($type==1){
                if(!$id){return \json(self::callback(0,'店铺id不能为空'));}
                //单个
                $rst=Db::name('store')->where('id','eq',$id)->setField('is_tixian',$status);
            }elseif ($type==2){
                //多个
                if(!$ids){return \json(self::callback(0,'店铺id不能为空'));}

                $rst=Db::name('store')->where('id','in',$ids)->setField('is_tixian',$status);
            }else{
                return \json(self::callback(0,'参数类型错误'));
            }
            if($rst===false){return \json(self::callback(0,'设置失败!'));}
            return \json(self::callback(1,'设置成功!',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 启用禁用店铺
     */
    public function storestatusSet(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $type=input('type') ? intval(input('type')) : 0;// 1单个 2为多个
            $status = input('status') ? intval(input('status')) : 0; //1启用 -1禁用
            $id=input('id') ? intval(input('id')) : 0;// 店铺id
            $ids = $this->request->post('ids/a');
            if(!$type || !$status){return \json(self::callback(0,'参数错误'));}
            if($status==-1){
                $status=0;
            }elseif($status==1){
                $status=1;
            }else{return \json(self::callback(0,'状态错误'));}
            if($type==1){
                if(!$id){return \json(self::callback(0,'店铺id不能为空'));}
                //单个
                $rst=Db::name('store')->where('id','eq',$id)->setField('store_status',$status);
            }elseif ($type==2){
                //多个
                if(!$ids){return \json(self::callback(0,'店铺id不能为空'));}
                $ids=str_replace('"','',str_replace('"','',$ids));

                $rst=Db::name('store')->where('id','in',$ids)->setField('store_status',$status);
            }else{
                return \json(self::callback(0,'参数类型错误'));
            }
            if($rst===false){return \json(self::callback(0,'设置失败!'));}
            return \json(self::callback(1,'设置成功!',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 店铺详情
     */
    public function storeDetail(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
            if (!$id){
                throw new \Exception('参数错误');
            }
            $id=intval($id);
            $data=Db::name('store')->where('id',$id)->find();
if($data){
    $data['store_img'] = Db::name('store_img')->field('id,img_url')->where('store_id',$id)->order('paixu','asc')->select();
    $data['total_freight'] = Db::name('product_order')->where('store_id',$id)->where('order_status','>',2)->sum('total_freight');
    $data['platform_profit'] = Db::name('product_order')->where('store_id',$id)->where('order_status','>',2)->sum('platform_profit');
    $total_freight = Db::name('product_order')->where('store_id',$id)->where('order_status','>',2)->sum('total_freight');
    $order_id = Db::name('product_order')->where('store_id',$id)->where('order_status','>',2)->column('id');
    $order_detail = Db::name('product_order_detail')->where('order_id','in',$order_id)->select();
    $product_price = 0;
    foreach ($order_detail as $k=>$v){
        $product_price += $v['number'] * $v['price'];
    }
    $data['total_order_money'] = $total_freight + $product_price;
    if($data['qrcode']==''){
        //生成二维码
        Loader::import('phpqrcode.phpqrcode');
        $QRcode = new \QRcode;
        $store_id=$data['id'];
        $type = input('type') ? intval(input('type')) : 1 ;
        //$value = 'http://web.supersg.cn?store_id='.$store_id.'&type='.$type;//二维码内容
        $value = 'http://appwx.supersg.cn/app/download.html?store_id='.$store_id.'&type='.$type;
        $errorCorrectionLevel = 'L';//纠错级别：L、M、Q、H
        $matrixPointSize = 10;//二维码点的大小：1到10
        $path=ROOT_PATH . 'public' . DS . 'uploads'. DS .'store'. DS .'qrcode_img'.DS .$store_id.'.png';
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
        $data['qrcode']=  DS . 'uploads'. DS .'store'. DS .'qrcode_img'.DS .$store_id.'.png';
        Db::name('store')->where('id',$data['id'])->setField('qrcode',$data['qrcode']);
            }
            return \json(self::callback(1,'',$data));
        }else{
            throw new \Exception('没有找到该店铺!');
        }

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
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

            if ($store_info['type'] != 1){
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
}