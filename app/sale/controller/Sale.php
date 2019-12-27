<?php

namespace app\sale\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use think\Db;
use think\response\Json;
use think\Session;

class Sale extends  Base
{

    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $mobile = input('post.mobile');   //获取验证码的手机号
        $mobile_type = input('post.mobile_type');  //是否已注册 1是 0否  需要验证

        if (!$mobile || !isset($mobile_type)) {
            return json(self::callBack(0,'参数错误'),400);
        }

        $count = Db::name('sale')->where('mobile','eq',$mobile)->count();

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


        $res = IhuyiSMS::getCode($mobile);

        if ($res !== true) {
            return json(self::callBack(0,$res));
        }

        return json(self::callBack(1,'',['mobile_code'=>Session::get('mobile_code')]));
    }

    /**
     * 登录
     */
    public function login(){
        try{

            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\sale\validate\Sale();
            if (!$validate->check($param,[],'login')) {
                throw new \Exception($validate->getError());
            }

            $model = new \app\sale\model\Sale();
            $saleInfo = $model->where('mobile',$param['mobile'])->find();

            if (!$saleInfo) {
                throw new \Exception('账号不存在');
            }

            if (!password_verify($param['password'], $saleInfo['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }


            if ($saleInfo['sale_status'] != 1) {
                throw new \Exception('账号已被禁用');
            }

            $token = \app\sale\common\Sale::setToken();

            $result = $model->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'login_time'=>time()
                ],['sale_id'=>$saleInfo['sale_id']]);

            if($result === false){
                return \json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,'',['sale_id'=>$saleInfo['sale_id'],'token'=>$model->getData('token')]));

        }catch (\Exception $e) {

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 忘记密码
     */
    public function forgetPassword(){
        try {
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\sale\validate\Sale();
            if (!$validate->check($param,[],'forgetPwd')) {
                throw new \Exception($validate->getError());
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['password'], PASSWORD_DEFAULT);

            $result = Db::name('sale')->where('mobile','eq',$param['mobile'])->setField('password',$password);

            if (!$result) {
                return \json(self::callback(0,'操作失败'),400);
            }

            Session::clear();

            return json(self::callback(1,''));


        } catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $saleInfo = \app\sale\common\Sale::checkToken();
            if ($saleInfo instanceof json){
                return $saleInfo;
            }
            $saleInfo['city_id'] = Db::name('shop_info')->where('id',$saleInfo['shop_id'])->value('city_id');

            $saleInfo['dsl_count'] = Db::name('house_entrust')->where('sale_id',$param['sale_id'])->where('status',0)->count();//待收录房源


            //查询出被当天被预定的房源id
            $today = date('Y-m-d');
            $id = Db::name('short_order')
                ->where('start_time','<=',$today)
                ->where('end_time','>',$today)
                ->where('status',2)
                ->where('sale_id',$param['sale_id'])
                ->column('short_id');

            $saleInfo['short_yd_count'] = Db::name('house_short')->where('id','in',$id)->count();//短租已定

            $saleInfo['long_yd_count'] = Db::name('house')->where('renting_status',2)->where('sale_id',$param['sale_id'])->count();//长租已定

            $saleInfo['dps_count'] = Db::name('goods_order')->where('order_status',3)->where('distribution_status',1)->where('sale_id',$param['sale_id'])->count(); //待配送

            $saleInfo['sl_count'] =  Db::name('house')->where('entrust_id',0)->where('add_sale_id',$param['sale_id'])->where('status',3)->count();//收录的房源

            $saleInfo['long_yj_count'] = Db::name('long_rent_record')
                ->join('long_order','long_order.id=long_rent_record.order_id','left')
                ->where('long_order.sale_id',$param['sale_id'])
                ->where('long_order.status',2)
                ->where('long_order.renting_status','>',0)
                ->count();  //长租业绩
           
            $saleInfo['short_yj_count'] = Db::name('short_order')->where('sale_id',$param['sale_id'])->where('status','>',1)->count(); //短租业绩

            $saleInfo['sh_yqs'] = Db::name('goods_order')->where('order_status','in','4,5')->where('sale_id',$param['sale_id'])->count();

            return json(self::callback(1,'',$saleInfo));

        }catch (\Exception $e) {

            return json(self::callback(0,$e->getMessage()));

        }


    }

    /**
     * 修改密码
     */
    public function modifyPassword(){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $saleInfo = \app\sale\common\Sale::checkToken();
            if ($saleInfo instanceof json){
                return $saleInfo;
            }

            $validate = new \app\sale\validate\Sale();
            if (!$validate->check($param,[],'modifyPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            if (!password_verify($param['password'], $saleInfo['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }

            $saleModel = new \app\sale\model\Sale();
            $result = $saleModel->allowField(true)->save(['password'=>password_hash($param['new_password'],PASSWORD_DEFAULT)],['sale_id'=>$saleInfo['sale_id']]);

            if ($result === false){
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 修改资料
     */
    public function modifyUserInfo(){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $saleInfo = \app\sale\common\Sale::checkToken();
            if ($saleInfo instanceof json){
                return $saleInfo;
            }

            if (!empty($param['nickname'])){
                $data['nickname'] = $param['nickname'];
            }

            $avatar_file = $this->request->file('avatar');
            if ($avatar_file) {
                //修改头像
                $info = $avatar_file->validate(['ext'=>'jpg,jpeg,png'])->move(ROOT_PATH.'public'.DS.'uploads'.DS.$this->request->module().DS.'sale_avatar');

                if ($info) {
                    $avatar = DS.'uploads'.DS.$this->request->module().DS.'sale_avatar'.DS.$info->getSaveName();
                    $data['avatar'] = str_replace(DS,"/",$avatar);

                }else{
                    throw new \Exception($avatar_file->getError());
                }

            }

            $saleModel = new \app\sale\model\Sale();
            $result = $saleModel->allowField(true)->save($data,['sale_id'=>$saleInfo['sale_id']]);

            if ($result === false) {
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }


    #loginpublic function

}