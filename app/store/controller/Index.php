<?php

namespace app\store\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\store\model\Store;
use app\user\controller\AliPay;
use sourceUpload\UploadVideo;
use think\Db;
use think\Exception;
use think\Loader;
use think\response\Json;
use app\store\common\Logic;
use think\Session;
use app\store\model\Brand as BrandModel;
class Index extends Base
{
    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $mobile = input('mobile');   //获取验证码的手机号
        $mobile_type = input('mobile_type');  //是否已注册 1是 0否  需要验证
        if (!$mobile || !isset($mobile_type)) {
            return json(self::callBack(0,'参数错误'),400);
        }
        $count = Db::name('store')->where('mobile','eq',$mobile)->count();

        if ($mobile_type == 1){
            if(!$count){
                return json(self::callback(0, "该手机号未注册"));
            }
        }elseif ($mobile_type == 0){
            if($count){
                return json(self::callback(0, "该手机号已注册"));
            }
        }else{
            return json(self::callBack(0,'参数错误'),400);
        }
        $res = IhuyiSMS::tixian_code($mobile);
        if ($res !== true) {
            return json(self::callBack(0,$res));
        }
       // return json(self::callBack(1,'返回成功',['mobile_code'=>Session::get('mobile_code')]));
        return json(self::callBack(1,'返回成功',true));
    }

    /**
     * 登录
     */
    public function login(){
        try{

            $mobile = input('mobile');
            $password = input('password');

            if (!$mobile || !$password) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $store_info = Db::name('store')->where('mobile',$mobile)->find();
            if (!$store_info) {
                throw new \Exception('账号不存在');
            }

            if (!password_verify($password, $store_info['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }

            if ($store_info['sh_status'] == 0 && $store_info['sh_type'] == 1) {
                throw new \Exception('正在审核中,请耐心等待');
            }

            if ($store_info['sh_status'] != 1 && $store_info['sh_type'] == 1 ) {
                return \json(self::callback(-1,'审核未通过',$store_info));
            }

            if ($store_info['store_status'] != 1) {
                throw new \Exception('店铺已被下架');
            }

            $token = \app\user\common\User::setToken();

            $model = new Store();

            $model = $model->field('id,token,nickname,user_name,store_name,type,mobile,cover,money,telephone,brand_name,description,is_ziqu,address,store_type')->where('id',$store_info['id'])->find();

            $model->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'last_login_time'=>time()
                ],['id'=>$store_info['id']]);
            $model->store_img = Db::name('store_img')->field('id,img_url')->where('store_id',$store_info['id'])->select();
            $model['service_mobile'] = Db::name('about_us')->where('id',1)->value('service_mobile');
            return \json(self::callback(1,'',$model));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 注销店铺
     */
    public function cancelStore(){
        try{
            $store_id = input('id');

            $store_info = Db::name('store')->where('id',$store_id)->find();
            if (!$store_info) {
                throw new \Exception('账号不存在');
            }

            if ($store_info['sh_type'] != 1){
                throw new \Exception('错误操作');
            }

            $res = Db::name('store')->where('id',$store_id)->delete();

            if (!$res){
                throw new \Exception('操作失败');
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺信息
     */
    public function getStoreInfo(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            //执行定时任务结转线下优惠券平台补贴
            Logic::executeTask();
            $store_info['store_img'] = Db::name('store_img')->field('id,img_url')->where('store_id',$store_info['id'])->order('paixu','asc')->select();

            $store_info['total_freight'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('total_freight');

            $store_info['platform_profit'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('platform_profit');

            $total_freight = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('total_freight');

            $order_id = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->column('id');

            $order_detail = Db::name('product_order_detail')->where('order_id','in',$order_id)->select();


            //结转
            $store_info2['total_freight'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','EQ',8)->sum('total_freight');

            $store_info2['platform_profit'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','EQ',8)->sum('platform_profit');

            $total_freight2 = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','EQ',8)->sum('total_freight');

            $order_id2 = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','EQ',8)->column('id');

            $order_detail2 = Db::name('product_order_detail')->where('order_id','in',$order_id2)->select();
            $product_price = 0;
            foreach ($order_detail as $k1=>$v1){
                $product_price += $v1['number'] * $v1['price'];
            }
            //结转
            $product_price2 = 0;
            foreach ($order_detail2 as $k=>$v){
                if($v['is_shouhou']==0 and $v['status']==0){
                    $product_price2 += $v['realpay_money'];
                }
            }
            //店铺主营分类
            $store_info['store_category'] = Db::view('store_cate_store')->alias('scs')
                ->join('cate_store cs','scs.cate_store_id = cs.id','LEFT')
                ->field('cs.id,cs.title')
                ->where('scs.store_id',$store_info['id'])
                ->select();

            //店铺主营风格
            $store_info['store_style'] = Db::view('store_style_store')->alias('sss')
                ->join('style_store ss','sss.style_store_id = ss.id','LEFT')
                ->field('ss.id,ss.title')
                ->where('sss.store_id',$store_info['id'])
                ->select();

            //商城收益
            $store_info['total_order_money'] = $total_freight + $product_price;
            //商城结转收益
            $store_info['total_carry_order_money'] = $total_freight2 + $product_price2;
            //到店买单收益
            $store_info['total_maidan_money'] = Db::name('maidan_order')->where('store_id',$store_info['id'])->where('pay_time','GT',0)->where('is_finish',1)->sum('price_store');
            //线下核销收益
            $store_info['total_validate_money'] = Db::name('coupon_validate')->where('store_id',$store_info['id'])->where('status',2)->sum('platform_price');

            if($store_info['qrcode']==''){
                //生成二维码
                Loader::import('phpqrcode.phpqrcode');
                $QRcode = new \QRcode;
                $store_id=$store_info['id'];
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
                $store_info['qrcode']=  DS . 'uploads'. DS .'store'. DS .'qrcode_img'.DS .$store_id.'.png';
                Db::name('store')->where('id',$store_info['id'])->setField('qrcode',$store_info['qrcode']);
            }
            return \json(self::callback(1,'',$store_info));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 注册
     */
    public function register(){
        try{
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }
            $validate = new \app\store\validate\Store();
            if (!$validate->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()),400);
            }
           /* $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }*/
            //判断手机号是否注册
           if(isset($param['mobile'])){
               $data=Db::table('store')->field('id,mobile')->where('mobile',$param['mobile'])->find();
               if($data){
                   return \json(self::callback(0,'不能重复注册!',false));
               }
           }
            $param['password'] = password_hash(88888888, PASSWORD_DEFAULT);
            $storeModel = new Store();
            $result = $storeModel->allowField(true)->save($param);
            if (!$result){throw new \Exception('注册失败');}
            $store_id = $storeModel->id;
            $tokenInfo = \app\store\common\Store::setToken();
            $storeModel
                ->allowField(true)
                ->save([
                    'token' => $tokenInfo['token'],
                    'token_expire_time' => $tokenInfo['token_expire_time'],
                    'last_login_time' => time()
                ], ['id'=>$store_id]);
            //添加中间表
            $user_and_store = ['store_id' => $store_id, 'create_time' => time()];
            Db::table('user_and_store')->insert($user_and_store);
            //商家主营分类和主营风格
            $store_category = trim($param['store_category']);
            $store_style = trim($param['store_style']);
            //写入分类
            if($store_category){
                $category = StoreCategory($store_category,$store_id);
                $rst=Db::name('store_cate_store')->strict(false)->insertAll($category);
                if($rst===false){throw new \Exception('添加店铺分类错误!');}
            }
            //写入风格
            if($store_style){
                $type = StoreType($store_style,$store_id);
                $rst=Db::name('store_style_store')->strict(false)->insertAll($type);
                if($rst===false){throw new \Exception('添加店铺风格错误!');}
            }

            Session::clear();
            return \json(self::callback(1,'',$storeModel));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改店铺信息
     */
    public function modifyStore(){
        try{
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }
            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }
            $param['sh_type'] = 1;
            $param['sh_status'] = 0;
            $storeModel = new Store();
            $result = $storeModel->allowField(true)->save($param,['id'=>$param['id']]);
            if (!$result){
                throw new \Exception('修改失败');
            }
            Session::clear();
            return \json(self::callback(1,'',$storeModel));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺分类
     */
    public function getStoreCategory(){
        try{
            $data = Db::name('store_category')->field('id,category_name,client_type')->where('is_show',1)->select();
            foreach ($data as &$v){
            if($v['client_type']==2){$v['category_name'].='(APP)';} elseif($v['client_type']==1){$v['category_name'].='(小程序)';}
            unset($v['client_type']);
            }
            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取会员店铺分类
     */
    public function getMemberStoreCategory(){
        try{
            $data = Db::name('member_store_category')->field('id,category_name')->where('is_show',1)->select();
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取商品分类
     */
    public function getProductCategory(){
        try{
            $data = Db::name('product_category')->field('id,category_name')->where('is_show',1)->select();
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 图片上传方法 返回id
     * @return [type] [description]
     */
    public function uploadFile()
    {
        $module = 'store';
        $use = input('use');
        if (!$use) $use = 'cover';
        if($this->request->file('file')){
            $file = $this->request->file('file');
        }else{
            return json(self::callback(0,'没有上传文件'));
        }
        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
       $info = $file->validate(['size'=>500*1024*1024,'ext'=>'jpg,png,gif,mp4,zip,webp,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
        if($info) {
           $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();

            return json(self::callback(1,'上传成功',$res));
        } else {
            // 上传失败获取错误信息
            return \json(self::callback(0,'上传失败：'.$file->getError()));
        }
    }

    /**
     * 上传视频
     * @return Json
     */
    public function uploadVideo(){
        try{
            ##验证
            $store_id = input('store_id','0','intval');
            if(!$store_id)throw new Exception('参数缺失');
            if(!request()->has('video','file'))throw new Exception('上传文件缺失');

            $file = request()->file('video');
            if($file){
                $path = $file->getRealPath();
                $size = $file->getSize();
                $ext = explode('/',$file->getInfo('type'))[1];
                ##判断文件格式
                $right_ext = config('config_uploads.video_type');
                if(!in_array(strtolower($ext),$right_ext))throw new Exception('文件格式不支持');
                ##判断文件大小
                if($size > 40 * 1024 *1024)throw new Exception('文件大小不能超过40M');
                ##保存临时本地文件
                $file_name = "store_" . $store_id . time() . rand(10000,99999) . '.' . $ext;
                $data = file_get_contents($path);
                $path2 = "uploads/video_temp/{$file_name}";
                file_put_contents($path2,$data);
                if(file_exists($path2)){
                    $res = UploadVideo::uploadLocalVideo($path2,$file_name,2);
                    if(!$res)throw new Exception('上传失败');
                    $data = [
                        'video_url' => $res['path'],
                        'cover_img' => $res['path'] . "?x-oss-process=video/snapshot,t_1000,m_fast",
                        'video_id'  => $res['media_id']
                    ];
                    @unlink($path2);  //删除临时文件
                    return json(self::callback(1,'',$data));
                }
                throw new Exception('临时本地文件生成失败');
            }
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除文件
     */
    public function deleteFile(){
        try{
            $file = input('file');

            $result = unlink(trim($file,'/\\'));   //删除文件

            if (!$result){
                throw new \Exception('删除失败');
            }

            return \json(self::callback(1,'删除成功'));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改账户基本信息
     */
    public function modifyInfo(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $nickname = input('nickname');
            if ($nickname) {$data['nickname'] = $nickname;}
            Db::name('store')->where('id',$store_info['id'])->update($data);
            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改密码
     */
    public function modifyPassword(){
        try{

            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $old_password = input('old_password');
            $new_password = input('new_password');
            if (!$old_password || !$new_password){
                return \json(self::callback(0,'参数错误'),400);
            }
            if (!password_verify($old_password, $store_info['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }
            if (password_verify($new_password, $store_info['password'])) {
                // Pass
                throw new \Exception('新密码不能和原密码相同');
            }
            $data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            Db::name('store')->where('id',$store_info['id'])->update($data);
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改绑定手机号
     */
    public function modifyMobile(){

        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            //原手机号
            $mobile_old = $store_info['mobile'];  //原手机号
            $code_old = trim(input('code_old')); //验证码
            //新手机号
            $mobile = trim(input('mobile'));  //修改手机号
            $code = trim(input('code_new')); //验证码

            if(!$code_old || !$code || !$mobile){
                return \json(self::callback(0,'参数错误',false));
            }
            if ($mobile_old==$mobile) {
                return \json(self::callback(0,'原手机号和新手机号不能相同',false));
            }
            $verimobile = IhuyiSMS::verifymobileCode($mobile_old,$code_old);
            if (!$verimobile) {
                throw new \Exception('原验证码不存在或已失效');
            }
            $verify = IhuyiSMS::verifymobileCode($mobile,$code);
            if (!$verify) {
                throw new \Exception('新验证码不存在或已失效');
            }
            $rst= Db::name('store')->where('id',$store_info['id'])->setField('mobile',$mobile);
            if($rst===false){
                throw new \Exception('修改失败!');
            }else{
                return \json(self::callback(1,'修改成功!',true));
            }

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 提现
     */
    public function tixian(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $money = input('money');
            $alipay_account = input('alipay_account') ? trim(input('alipay_account')) : '';  //支付宝账号 手机号或者邮箱
            $code = input('code'); //验证码
            if (!$money || !$alipay_account || !$code) {
                return json(self::callback(0,'参数错误'), 400);
            }
            $verify = IhuyiSMS::verifyCode($store_info['mobile'],$code);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }
            if (!$this->ALIAccountVerify($alipay_account)) {
                throw new \Exception('支付宝账号无效');
            }

            if ($store_info['money'] < $money) {
                throw new \Exception('余额不足');
            }
            if ($store_info['is_tixian'] == -1) {
                throw new \Exception('总店设置了不允许提现');
            }
            Db::startTrans();
            $date = date('Y-m-d H:i:s');
            $id = Db::name('store_tixian_record')->insertGetId([
                'order_no'=> $order_no = build_order_no('T'),
                'money'=>$money,
                'alipay_account'=>$alipay_account,
                'store_id'=>$store_info['id'],
                'create_at'=>$date
            ]);

            $aliPay = new AliPay();
            $data = $aliPay->transfer($order_no,$alipay_account,$money);

            $code = $data['code'];
            Db::name('store_tixian_record')->where('id',$id)->update(['code'=>$code,'order_id'=>$data['order_id']]);

            //提现成功
            if(!empty($code) && $code == 10000){
                Db::name('store')->where('id',$store_info['id'])->setDec('money',$money);
                Db::name('store_money_detail')->insert([
                    'store_id' => $store_info['id'],
                    'note' => '提现',
                    'money' => -$money,
                    'balance' => $store_info['money'] - $money,
                    'create_time' => time()
                ]);
            }else{
                Db::commit();
                throw new \Exception('提现失败：'.$data['sub_msg']);
            }
            Db::commit();
            return \json(self::callback(1,''));
        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 测试结转
     */
    public function TestCarry(){
        try{
            //执行定时任务结转线下优惠券平台补贴
            Logic::executeTask();
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

}