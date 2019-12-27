<?php

namespace app\property\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\user\controller\AliPay;
use app\user\model\HouseTag;
use app\user\validate\HouseEntrust;
use think\Db;
use think\Session;
use think\response\Json;
class Property extends  Base
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

        $count = Db::name('property')->where('mobile','eq',$mobile)->count();

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

            $validate = new \app\property\validate\Property();
            if (!$validate->check($param,[],'login')) {
                throw new \Exception($validate->getError());
            }

            $model = new \app\property\model\Property();
            $propertyInfo = $model->where('mobile',$param['mobile'])->find();

            if (!$propertyInfo) {
                throw new \Exception('账号不存在');
            }
            if (!password_verify($param['password'], $propertyInfo['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }

            if ($propertyInfo['property_status'] != 1) {
                throw new \Exception('账号已被禁用');
            }

            $token = \app\property\common\Property::setToken();

            $result = $model->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'login_time'=>time()
                ],['property_id'=>$propertyInfo['property_id']]);

            if($result === false){
                return \json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,'',['property_id'=>$propertyInfo['property_id'],'token'=>$model->getData('token')]));

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

            $validate = new \app\property\validate\Property();
            if (!$validate->check($param,[],'forgetPwd')) {
                throw new \Exception($validate->getError());
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['password'], PASSWORD_DEFAULT);

            $result = Db::name('property')->where('mobile','eq',$param['mobile'])->setField('password',$password);

            if (!$result) {
                return json(self::callback(0,'操作失败'),400);
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
                return json(self::callback(1,'参数错误'),400);
            }

            //token 验证
            $propertyInfo = \app\property\common\Property::checkToken();
            if ($propertyInfo instanceof Json){
                return $propertyInfo;
            }

            if (!$propertyInfo['pay_password']){
                $propertyInfo['pay_password'] = "";
            }

            $propertyInfo['valid_recommend_money'] = Db::name('property_money_record')->where('property_id',$propertyInfo['property_id'])->where('type',1)->sum('money');
            $propertyInfo['valid_recommend_count'] = Db::name('property_money_record')->where('property_id',$propertyInfo['property_id'])->where('type',1)->count();
            $propertyInfo['valid_seehouse_money'] = Db::name('property_money_record')->where('property_id',$propertyInfo['property_id'])->where('type',2)->sum('money');
            $propertyInfo['valid_seehouse_count'] = Db::name('property_money_record')->where('property_id',$propertyInfo['property_id'])->where('type',2)->count();
            $propertyInfo['city_id'] = Db::name('house_xiaoqu')->where('id',$propertyInfo['xiaoqu_id'])->value('city_id');

            return json(self::callback(1,'',$propertyInfo));

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
            $propertyInfo = \app\property\common\Property::checkToken();
            if ($propertyInfo instanceof json){
                return $propertyInfo;
            }

            $validate = new \app\property\validate\Property();
            if (!$validate->check($param,[],'modifyPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            if (!password_verify($param['password'], $propertyInfo['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }

            $propertyModel = new \app\property\model\Property();
            $result = $propertyModel->allowField(true)->save(['password'=>password_hash($param['new_password'],PASSWORD_DEFAULT)],['property_id'=>$propertyInfo['property_id']]);

            if ($result === false){
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 设置支付密码
     */
    public function setPayPassword(){
        try {
            $param = $this->request->post();

            if (!$param || !$param['mobile'] || !$param['code'] || !$param['pay_password']) {
                return json(self::callback(0,'参数错误'),400);
            }

            /*$validate = new \app\property\validate\Property();
            if (!$validate->check($param,[],'forgetPwd')) {
                throw new \Exception($validate->getError());
            }*/
            //token 验证
            $propertyInfo = \app\property\common\Property::checkToken();
            if ($propertyInfo instanceof json){
                return $propertyInfo;
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['pay_password'], PASSWORD_DEFAULT);

            $result = Db::name('property')->where('mobile','eq',$param['mobile'])->setField('pay_password',$password);

            if (!$result) {
                return json(self::callback(0,'操作失败'),400);
            }

            Session::clear();

            return json(self::callback(1,''));


        } catch (\Exception $e){

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
            $propertyInfo = \app\property\common\Property::checkToken();
            if ($propertyInfo instanceof json){
                return $propertyInfo;
            }

            if (!empty($param['nickname'])){
                $data['nickname'] = $param['nickname'];
            }


            $avatar_file = $this->request->file('avatar');
            if ($avatar_file) {
                //修改头像
                $info = $avatar_file->validate(['ext'=>'jpg,jpeg,png'])->move(ROOT_PATH.'public'.DS.'uploads'.DS.$this->request->module().DS.'property_avatar');

                if ($info) {
                    $avatar = DS.'uploads'.DS.$this->request->module().DS.'property_avatar'.DS.$info->getSaveName();
                    $data['avatar'] = str_replace(DS,"/",$avatar);

                }else{
                    throw new \Exception($avatar_file->getError());
                }

            }

            $propertyModel = new \app\property\model\Property();
            $result = $propertyModel->allowField(true)->save($data,['property_id'=>$propertyInfo['property_id']]);

            if ($result === false) {
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 房屋委托
     */
    public function houseEntrust(){
        try{
            $param = $this->request->post();

            if(!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new HouseEntrust();
            if (!$validate->check($param,[])) {
                throw new \Exception($validate->getError());
            }

            //token 验证
            $userInfo = \app\property\common\Property::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            //根据地址获取经纬度
            $latlng = addresstolatlng($param['province'].$param['city'].$param['area'].$param['address']);

            $param['lng'] = $latlng[0];
            $param['lat'] = $latlng[1];
            $param['type'] = 2;  //委托类型 1用户委托 2物业委托
            $param['param_id'] = $userInfo['property_id'];
            //todo 按距离分配给商家 否则随机分配
            $shop_id = $this->getShopId($param['lng'],$param['lat']);

            if (!$shop_id){
                $shop = Db::name('shop_info')->order('rand()')->find();
                $shop_id = $shop['id'];
            }

            $param['shop_id'] = $shop_id;

            $result = (new \app\user\model\HouseEntrust())->allowField(true)->save($param);

            if (!$result){
                return \json(self::callback(0,'操作失败'),400);
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 根据经纬度返回最近的商家id
     * @param $lng
     * @param $lat
     */
    public function getShopId($lng,$lat){
        $field = "ROUND(6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            $lat * PI() / 180 - lat * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS($lat * PI() / 180) * COS(lat * PI() / 180) * POW(
                    SIN(
                        (
                            $lng * PI() / 180 - lng * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS m";

        $shop = Db::name('shop_info')->field('id,'.$field)->order('m','asc')->limit(0,1)->select();


        return $shop[0]['id'];
    }

    /**
     * 有效推荐
     */
    public function validRecommend(){
        //token 验证
        $userInfo = \app\property\common\Property::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        $data = Db::view('property_money_record','money')
            ->view('house','id,title,description,rent,bedroom_number,parlour_number,toilet_number,acreage,tag_id,lines_id','house.id=property_money_record.house_id','left')
            ->where('property_money_record.type',1)
            ->where('property_money_record.property_id',$userInfo['property_id'])
            ->order('property_money_record.create_time','desc')
            ->select();

        foreach ($data as $k=>$v){
            $data[$k]['lines_name'] = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');

            if (!$v['lines_id']){
                $data[$k]['lines_name'] = '';
            }

            $data[$k]['house_img'] = Db::name('house_img')->field('id,img_url')->where('house_id',$v['id'])->select();
            $data[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);
        }

        return \json(self::callback(1,'',$data));
    }

    /**
     * 有效看房
     */
    public function validSeeHouse(){
        //token 验证
        $userInfo = \app\property\common\Property::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $data = Db::view('property_money_record','money')
            ->view('house','id,title,description,rent,bedroom_number,parlour_number,toilet_number,acreage,tag_id,lines_id','house.id=property_money_record.house_id','left')
            ->where('property_money_record.type',2)
            ->where('property_money_record.property_id',$userInfo['property_id'])
            ->order('property_money_record.create_time','desc')
            ->select();

        foreach ($data as $k=>$v){
            $data[$k]['lines_name'] = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');

            if (!$v['lines_id']){
                $data[$k]['lines_name'] = '';
            }

            $data[$k]['house_img'] = Db::name('house_img')->field('id,img_url')->where('house_id',$v['id'])->select();
            $data[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);
        }

        return \json(self::callback(1,'',$data));
    }


    /**
     * 支付宝账号验证
     * @param $alipay_account
     * @return bool
     */
    public function ALIAccountVerify($alipay_account){
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if(preg_match("/^1[34578]\d{9}$/", $alipay_account) || preg_match($pattern,$alipay_account)){
            return true;
        }
        return false;
    }

    /**
     * 支付密码验证
     */
    public function payPwdVerify(){
        //token 验证
        $userInfo = \app\property\common\Property::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        $pay_password = input('pay_password');  //支付密码

        if(!$pay_password){
            return json(self::callback( 0, "参数错误"), 400);
        }
        if (!password_verify($pay_password, $userInfo['pay_password'])) {
            // Pass
            return \json(self::callback(0,'密码错误'));
        }
        return json(self::callback( 1, ""));
    }

    /**
     * 提现
     */
    public function tixian(){
        try{
            //token 验证
            $userInfo = \app\property\common\Property::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $money = input('money');
            $alipay_account = input('alipay_account') ? trim(input('alipay_account')) : '';  //支付宝账号 手机号或者邮箱

            if (!$money || !$alipay_account ) {
                return json(self::callback(0,'参数错误'), 400);
            }

            if (!$this->ALIAccountVerify($alipay_account)) {
                throw new \Exception('支付宝账号无效');
            }

            if ($userInfo['money'] < $money) {
                throw new \Exception('余额不足');
            }

            Db::startTrans();

            $date = date('Y-m-d H:i:s');
            $id = Db::name('property_tixian_record')->insertGetId([
                'order_no'=> $order_no = build_order_no('T'),
                'money'=>$money,
                'alipay_account'=>$alipay_account,
                'property_id'=>$userInfo['property_id'],
                'create_at'=>$date
            ]);

            $aliPay = new AliPay();
            $data = $aliPay->transfer($order_no,$alipay_account,$money);

            $code = $data['code'];
            Db::name('property_tixian_record')->where('id',$id)->update(['code'=>$code,'order_id'=>$data['order_id']]);

            //提现成功
            if(!empty($code) && $code == 10000){

                Db::name('property')->where('property_id',$userInfo['property_id'])->setDec('money',$money);

            }else{
                Db::commit();
                throw new \Exception('提现失败');
            }
            Db::commit();

            return \json(self::callback(1,''));
        }catch (\Exception $e){

            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}