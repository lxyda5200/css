<?php

namespace app\mainstore\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\mainstore\model\Store;
use app\user\controller\AliPay;
use think\Db;
use think\Log;
use think\Loader;
use think\response\Json;
use think\Session;


class Index extends Base
{
    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $mobile = trim(input('mobile'));   //获取验证码的手机号
        $mobile_type = trim(input('mobile_type'));  //是否已注册 1是 0否  需要验证

        if (!$mobile || !isset($mobile_type)) {
            return json(self::callBack(0,'参数错误'),400);
        }
        $count = Db::name('store')->where('mobile','eq',$mobile)->count();

        if ($mobile_type == 1){
            if(!$count){
                return json(self::callback(0, "该手机号未绑定",false));
            }
        }elseif ($mobile_type == 0){
            if($count){
                return json(self::callback(0, "该手机号已绑定",false));
            }
        }else{
            return json(self::callBack(0,'参数错误'),400);
        }
        $res = IhuyiSMS::tixian_code($mobile);

        if ($res !== true) {
            return json(self::callBack(0,$res));
        }

        // return json(self::callBack(1,'验证码发送成功!',['mobile_code'=>Session::get('mobile_code')]));
        return json(self::callBack(1,'验证码发送成功!',true));
    }

    /**
     * 登录
     */
    public function login(){
        try{

            $mobile = trim(input('mobile'));
                $password = trim(input('password'));
                if (!$mobile || !$password) {
                   return '参数错误';
                }
                $store_info = Db::name('store')->where('mobile',$mobile)->find();
                if (!$store_info) {
                    throw new \Exception('账号不存在');
                }
                //判断是否是总店
                if ($store_info['store_type']==0) {
                    throw new \Exception('你还不是总店,不能登陆!');
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

                $token = \app\mainstore\common\Store::setToken();

                $model = new Store();

                $model = $model->field('id,token,nickname,user_name,store_name,mobile,cover,money,telephone,brand_name,description,is_ziqu,address,store_type')->where('id',$store_info['id'])->find();

                $model->allowField(true)->save(
                    [
                        'token'=>$token['token'],
                        'token_expire_time'=>$token['token_expire_time'],
                        'last_login_time'=>time()
                    ],['id'=>$store_info['id']]);
                if($model['cover']==''){
                    $model['cover']='/default/user_logo.png';
                }
                $model->store_img = Db::name('store_img')->field('id,img_url')->where('store_id',$store_info['id'])->select();
            $model['service_mobile'] = Db::name('about_us')->where('id',1)->value('service_mobile');
                return \json(self::callback(1,'',$model));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * （删除）注销店铺
     */
    public function deleteStore(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $ids = input('ids/a');
            if (!$ids) {
                throw new \Exception('参数错误');
            }
            Db::startTrans();
            foreach ($ids as $k=>$v){
                $store[$k] = Db::name('store')->where('id',$v)->find();
                if (!$store[$k]) {
                    Db::rollback();
                    throw new \Exception('店铺不存在');
                }
                if ($store[$k]['sh_status']!= -1){
                    Db::rollback();
                    throw new \Exception('店铺状态不支持被删');
                }
                if ($store_info['id']==$v){
                    Db::rollback();
                    throw new \Exception('总店不允许被删');
                }
                $res[$k] = Db::table('store')->where('id', $v)->update([
                    'sh_status' => -2,
                    'store_status' => -1
                ]);
                if (!$res[$k]){
                    Db::rollback();
                    throw new \Exception('操作失败');
                }
            }
            Db::commit();
//            $res = Db::name('store')->where('id',$store_id)->delete();

            return \json(self::callback(1,'操作成功',true));

        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺信息
     */
    public function getStoreInfo(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $store_info['store_img'] = Db::name('store_img')->field('id,img_url')->where('store_id',$store_info['id'])->order('paixu','asc')->select();

            $store_info['total_freight'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('total_freight');

            $store_info['platform_profit'] = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('platform_profit');

            $total_freight = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->sum('total_freight');

            $order_id = Db::name('product_order')->where('store_id',$store_info['id'])->where('order_status','>',2)->column('id');

            $order_detail = Db::name('product_order_detail')->where('order_id','in',$order_id)->select();

            $product_price = 0;
            foreach ($order_detail as $k=>$v){
                $product_price += $v['number'] * $v['price'];
            }
            $store_info['total_order_money'] = $total_freight + $product_price;
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
     * 新增新店-注册
     */
    public function register(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误',false));
            }
            $validate = new \app\mainstore\validate\Store();
            if (!$validate->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()));
            }
           $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }
            //判断手机号是否注册
            if(isset($param['mobile'])){
                $data=Db::table('store')->field('id,mobile')->where('mobile',$param['mobile'])->find();
                if($data){
                    return \json(self::callback(0,'不能重复注册!',false));
                }
            }
            $param['password'] = password_hash(88888888, PASSWORD_DEFAULT);
            $param['p_id'] =$store_info['id'];//总店id
            $storeModel = new Store();
            $result = $storeModel->allowField(true)->save($param);

            if (!$result){
                throw new \Exception('注册失败');
            }

            $store_id = $storeModel->id;

            $tokenInfo = \app\mainstore\common\Store::setToken();

            $storeModel
                ->allowField(true)
                ->save([
                    'token' => $tokenInfo['token'],
                    'token_expire_time' => $tokenInfo['token_expire_time'],
                    'last_login_time' => time()
                ], ['id'=>$store_id]);

            Session::clear();

            return \json(self::callback(1,'操作成功!',$storeModel));

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
            #dump($param);
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
            $data = Db::name('store_category')->field('id,category_name')->where('is_show',1)->select();
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

       $info = $file->validate(['size'=>500*1024*1024,'ext'=>'jpg,png,gif,mp4,zip,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);

        if($info) {
           $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();

            return json(self::callback(1,'上传成功',$res));
        } else {
            // 上传失败获取错误信息
            return \json(self::callback(0,'上传失败：'.$file->getError()));
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
     * 修改基本信息-昵称
     */
    public function modifyNickname(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $nickname = trim(input('nickname'));
            if(!$nickname){
                return \json(self::callback(0,'新昵称不能为空'));
            }else{
                $data['nickname'] = $nickname;
                $rst=Db::name('store')->where('id',$store_info['id'])->update($data);
                if($rst===false){
                    return \json(self::callback(0,'修改失败!'));
                }else{
                    return \json(self::callback(1,'修改成功!',true));
                }
            }
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
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $old_password = trim(input('old_password'));
            $new_password = trim(input('new_password'));
            if (!$old_password || !$new_password){
                return \json(self::callback(0,'参数错误'),400);
            }
            if (!password_verify($old_password, $store_info['password'])) {
                // Pass
                return \json(self::callback(0,'原密码错误',false,true));
            }
            if (password_verify($new_password, $store_info['password'])) {
                // Pass
                return \json(self::callback(0,'新密码不能和原密码相同',false,true));
            }
            $data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            $rst=Db::name('store')->where('id',$store_info['id'])->update($data);
            if($rst===false){
                return \json(self::callback(0,'修改失败!',false,true));
            }else{
                return \json(self::callback(1,'修改成功!',true));
            }

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改绑定手机号
     */
    public function modifyMobile(){

        try{
            session_start();
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
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
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $money = input('money');
            $money= round($money,2);
            $store_ids = input('store_ids/a'); //ids
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
            if($money<=0){
                throw new \Exception('提现金额错误');
            }

            $ti_moneys=0;
            foreach ($store_ids as $k=>$v){
                if($v<10){
                    throw new \Exception('每个店铺提现金额不能小于10元');
                }
                $moneys[$k] = Db::name('store')
                    ->where('p_id',$store_info['id'])
                    ->where('id',$k)
                    ->where('is_tixian','eq',-1)
                    ->value('money');
                if($moneys[$k] <$v){
                    throw new \Exception($k.'金额不足');
                }
                $ti_moneys+=$v;
            }
//            $moneys = Db::name('store')
//                ->where('p_id',$store_info['id'])
//                ->where('id','in',$ids)
//                ->where('is_tixian','eq',-1)
//                ->sum('money');
            $ti_moneys=round($ti_moneys,2);
            if ($ti_moneys != $money) {
                throw new \Exception('金额错误'.$ti_moneys);
            }
            if ($ti_moneys < $money) {
                throw new \Exception('余额不足');
            }
            if ($ti_moneys <10 || $money<10) {
                throw new \Exception('提现金额不能小于10元');
            }
            Db::startTrans();
            $date = date('Y-m-d H:i:s');
            $id = Db::name('store_tixian_record')->insertGetId([
                'order_no'=> $order_no = build_order_no('T'),
                'money'=>$ti_moneys,
                'alipay_account'=>$alipay_account,
                'store_id'=>$store_info['id'],
                'create_at'=>$date
            ]);

            $aliPay = new AliPay();
            $data = $aliPay->transfer($order_no,$alipay_account,$ti_moneys);

            $code = $data['code'];
            Db::name('store_tixian_record')->where('id',$id)->update(['code'=>$code,'order_id'=>$data['order_id']]);

            //提现成功
            if(!empty($code) && $code == 10000){
                foreach ($store_ids as $k=>$v){
                    $moneys[$k] = Db::name('store')
                        ->where('p_id',$store_info['id'])
                        ->where('id',$k)
                        ->where('is_tixian','eq',-1)
                        ->value('money');
                    Db::name('store')->where('id',$k)->setDec('money',$v);
                    Db::name('store_money_detail')->insert([
                        'store_id' => $k,
                        'note' => '提现',
                        'main_store_id' => $store_info['id'],
                        'money' => -$v,
                        'tixian_record_id'=>$id,
                        'balance' => $moneys[$k] - $v,
                        'create_time' => time()
                    ]);
                }

//                Db::name('store')->where('id',$store_info['id'])->setDec('money',$money);
//                Db::name('store_money_detail')->insert([
//                    'store_id' => $store_info['id'],
//                    'note' => '提现',
//                    'money' => -$money,
//                    'balance' => $store_info['money'] - $money,
//                    'create_time' => time()
//                ]);
            }else{
                Db::rollback();
                throw new \Exception('提现失败：'.$data['sub_msg']);
            }
            Db::commit();
            return \json(self::callback(1,'操作成功!',true));
        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}