<?php

namespace app\user_v2\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\user_v2\common\Logic;
use app\user_v2\common\Login;
use app\user_v2\model\HouseEntrust;
use app\user_v2\validate\Login as LoginValidate;
use app\user_v2\common\Login as LoginFunc;
use app\user_v2\common\User as UserFunc;
use app\user_v2\validate\UserAddress;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use think\response\Json;
use think\Session;
use app\user_v2\model\User as UserModel;
use app\user_v2\validate\User as UserValidate;
use app\user_v2\validate\UserAddress as UserAddrValidate;
use app\user_v2\model\UserAddress as UserAddrModel;
use app\user_v2\model\House as HouseModel;
use app\user_v2\model\HouseShort as HouseShortModel;
use app\user_v2\model\Store as StoreModel;
use app\user_v2\common\UserLogic;

class User extends Base
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

        $count = Db::name('user')->where('mobile','eq',$mobile)->count();

        if ($mobile_type == 1){
            if(!$count){
                return json(self::callback(0, "该手机号未注册"));
            }
        }elseif ($mobile_type == 0){
            if($count){
                $pwd = Db::name('user')->where(['mobile'=>$mobile])->value('password');
                if($pwd)return json(self::callback(0, "该手机号已注册"));
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
     * 注册
     */
    public function register(UserValidate $validate, UserModel $UserModel){
        try {
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'));
            }

            $rule = [
                'mobile' => 'require|length:11|checkPhone'
            ];

            if (!$validate->rule($rule)->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()));
            }
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $code = input('post.code','','addslashes,strip_tags,trim');
            $password = input('post.password','','addslashes,strip_tags,trim');
            $user_code = input('post.user_code','','addslashes,strip_tags,trim,strtoupper');

            //$verify = IhuyiSMS::verifyCode($mobile, $code);
            //if (!$verify) {
           //     throw new \Exception('验证码不存在或已失效');
            //}

            ##判断手机号是否已绑定
            $userInfo = $UserModel->getUserInfoByMobile($mobile);
            if($userInfo && $userInfo['password'])return \json(self::callback(0,'当前手机号已注册,请直接登录'));

            ##生成token
            $tokenInfo = UserFunc::setToken();
            $data = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'token' => $tokenInfo['token'],
                'token_expire_time' => $tokenInfo['token_expire_time']
            ];

            Db::startTrans();
            if($userInfo){ ##通过小程序或者第三方已经注册了[但是没有密码]
                ##修改信息
                $data['login_time'] = time();

                $res = $UserModel->edit($userInfo['user_id'],$data);
                $user_id = $userInfo['id'];
            }else{ ##未注册
                $data['mobile'] = $mobile;
                $data['nickname'] = '新用户'.hide_phone($mobile);
                $data['create_time'] = $data['authorize_time'] = time();
                $invitation_user_id = decode($user_code);
                if($user_code)$data['invitation_user_id'] = $invitation_user_id;

                $res = $UserModel->addUser($data);
                $user_id = $res;
            }
//            echo $UserModel->getLastSql();

            if(!$res)throw new Exception('注册失败');

            ##新注册下发新人券
//            if(!$userInfo){
//                ###获取新人券信息
//                $couponInfo = Logic::newUserCouponInfo();
//                if($couponInfo){
//                    foreach($couponInfo as &$v){
//                        $v['create_time'] = time();
//                        $v['status'] = 1;
//                        $v['user_id'] = $user_id;
//                    }
//                    $res = UserLogic::userGetNewUserCoupon($couponInfo);
//                    ###领取失败 打印错误日志
//                    if($res === false)Log::error("\r\n 新人券领券失败 =》 " . json_encode($couponInfo));
//
//                    ###更新新人券数量
//                    foreach($couponInfo as $v){
//                        $res = logic::updateCouponNum($v['coupon_id']);
//                        if($res === false)Log::error("\r\n 新人券数量更新失败 =》 " . json_encode($v));
//                    }
//                }
//            }

            /*****************  0719注释start  ************/
//            $param['password'] = password_hash($param['password'], PASSWORD_DEFAULT);
//            $param['nickname'] = '新用户'.hide_phone($mobile);
//
//
//            $result = $UserModel->allowField(true)->save($param);
//
//            $user_id = $UserModel->user_id;
//
//            if (!$result) {
//                Db::rollback();
//                throw new \Exception('注册失败');
//            }
//
//            $tokenInfo = UserFunc::setToken();
//
//            if (!$tokenInfo) {
//                Db::rollback();
//                throw new \Exception('注册失败,请稍后重试');
//            }
//
//            //是否填写邀请码
//            if ($param['user_code']){
//                $invitation_user_id = decode($param['user_code']);
//                $UserModel->invitation_user_id = $invitation_user_id;
//            }
//
//            $UserModel->token = $tokenInfo['token'];
//            $UserModel->token_expire_time = $tokenInfo['token_expire_time'];
//            $UserModel->login_time = time();
//
//            $UserModel->allowField(true)->save();
            /*****************  0719注释start  ************/


            /*//赠送优惠券 满300-50 七天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 300, 'coupon_money' => 50, 'status' => 1, 'expiration_time' => time() + 24*3600*7, 'create_time' => time()]);

            //赠送优惠券 满100-25 五天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 100, 'coupon_money' => 25, 'status' => 1, 'expiration_time' => time() + 24*3600*5, 'create_time' => time()]);

            //赠送优惠券 满50-15 三天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 50, 'coupon_money' => 15, 'status' => 1, 'expiration_time' => time() + 24*3600*3, 'create_time' => time()]);

            //赠送优惠券 满20-10 两天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 20, 'coupon_money' => 10, 'status' => 1, 'expiration_time' => time() + 24*3600*2, 'create_time' => time()]);*/

            if(!$userInfo) {
                ##生成邀请码
                Db::name('user')->where(['user_id'=>$user_id])->setField('invitation_code', createCode($user_id));

                //赠送优惠券

                ##新人券
                $coupon_rule = Db::name('coupon_rule')->where(['coupon_type'=>1,'is_open'=>1])->where(['client_type'=>['IN',[0,2]]])->select();

                ##邀请券
                if(isset($invitation_user_id) && $invitation_user_id>0) {
                    $invitation_user_info = Db::name('user')->where(['user_id' => $invitation_user_id])->value('user_status');
                    if (!empty($invitation_user_info) && $invitation_user_info > 0) {
                        $coupon_rule2 = Logic::getInvitationCoupons(1);
                        if(!empty($coupon_rule2))$coupon_rule = array_merge($coupon_rule,$coupon_rule2);
                    }
                }

                $data = [];
                foreach($coupon_rule as $v){
                    for ($i = 0; $i < $v['zengsong_number']; $i++) {
                        $data[] = [
                            'coupon_id' => $v['id'],
                            'user_id' => $user_id,
                            'coupon_name' => $v['coupon_name'],
                            'satisfy_money' => $v['satisfy_money'],
                            'coupon_money' => $v['coupon_money'],
                            'status' => 1,
                            'expiration_time' => time() + 24 * 3600 * $v['days'],
                            'create_time' => time(),
                            'coupon_type' => $v['coupon_type']
                        ];
                    }

                }

                ##派发优惠券
                Db::name('coupon')->insertAll($data);

                ##更新优惠券剩余数量
                foreach($coupon_rule as $v)Logic::updateNoNumCouponNum($v['id'], $v['zengsong_number']);

                if(isset($coupon_rule2) && !empty($coupon_rule2)){
                    ##给邀请人发券
                    $invitation_coupons = Logic::getInvitationCoupons(2);

                    $data = [];
                    foreach($invitation_coupons as $v){
                        for ($i = 0; $i < $v['zengsong_number']; $i++) {
                            $data[] = [
                                'coupon_id' => $v['id'],
                                'user_id' => $invitation_user_id,
                                'coupon_name' => $v['coupon_name'],
                                'satisfy_money' => $v['satisfy_money'],
                                'coupon_money' => $v['coupon_money'],
                                'status' => 1,
                                'expiration_time' => time() + 24 * 3600 * $v['days'],
                                'create_time' => time(),
                                'coupon_type' => $v['coupon_type']
                            ];
                        }
                    }
                  //  Log::info(print_r($data,true));
                    ###发券
                    Db::name('coupon')->insertAll($data);
                }

            }
            Session::clear();
            Db::commit();

            return json(self::callback(1,'',['user_id'=>$user_id,'token'=>$tokenInfo['token']]));

        } catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }


    }


    /**
     * 登录
     */
    public  function login(UserValidate $validate, UserModel $UserModel){
        try{

            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            if (!$validate->check($param,[],'login')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $userInfo = $UserModel->where('mobile',$param['mobile'])->find();

            if (!$userInfo) {
                throw new \Exception('账号不存在');
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }

            if ($userInfo['user_status'] != 1 && $userInfo['user_status'] != 3) {
                throw new \Exception('账号已被禁用');
            }

            $token = UserFunc::setToken();

            $result = $UserModel->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'login_time'=>time()
                ],['user_id'=>$userInfo['user_id']]);

            if($result === false){
                return \json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,'',['user_id'=>$userInfo['user_id'],'token'=>$UserModel->getData('token')]));

        }catch (\Exception $e) {

            return json(self::callback(0,$e->getMessage()));

        }
    }


    /**
     * 忘记密码
     */
    public function forgetPassword(UserValidate $validate){
        try {

            $param = $this->request->param();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            if (!$validate->check($param,[],'forgetPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            ##检测手机号是否注册
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $check = UserLogic::checkRegister($mobile);
            if(!$check)throw new Exception('当前手机号未注册,请前往注册');

            $password = password_hash($param['password'], PASSWORD_DEFAULT);

            $result = Db::name('user')->where('mobile','eq',$param['mobile'])->setField('password',$password);

            if (!$result) {
                return \json(self::callback(0,'操作失败'));
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
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

//            $userInfo['entrust_number'] = Db::name('house_entrust')->where('type',1)->where('param_id',$userInfo['user_id'])->count();
//            $long_house = Db::name('house_collection')->where('user_id',$userInfo['user_id'])->count();
//            $short_house = Db::name('short_collection')->where('user_id',$userInfo['user_id'])->count();
//            $product = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->count();
//            $userInfo['collection_number'] = $long_house + $short_house + $product;
//            $userInfo['user_code'] = createCode($userInfo['user_id']);
//            $userInfo['card_number'] = Db::name('user_card')->where('user_id',$userInfo['user_id'])->count();

            $user_id = $userInfo['user_id'];
            $userInfo['collect_num'] = UserLogic::userCollectNum($user_id);
            $userInfo['follow_num'] = UserLogic::userFollowNum($user_id);
            $userInfo['coupon_num'] = UserLogic::userCouponCount($user_id,0);
            $userInfo['member_end_time'] = $userInfo['end_time'];

            return json(self::callback(1,'',$userInfo));

        }catch (\Exception $e) {

            return json(self::callback(0,$e->getMessage()));

        }


    }


    /**
     * 修改密码
     */
    public function modifyPassword(UserValidate $validate, UserModel $UserModel){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            if (!$validate->check($param,[],'modifyPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }

            $result = $UserModel->allowField(true)->save(['password'=>password_hash($param['new_password'],PASSWORD_DEFAULT)],['user_id'=>$userInfo['user_id']]);

            if ($result === false){
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 修改用户信息
     */
    public function modifyUserInfo(UserModel $UserModel){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            if (!empty($param['nickname'])){
                $data['nickname'] = $param['nickname'];
            }

            $avatar_file = $this->request->file('avatar');
            if ($avatar_file) {
                //修改头像
                $info = $avatar_file->validate(['ext'=>'jpg,jpeg,png'])->move(config('config_uploads.uploads_path') .'avatar');

                if ($info){
//                    Log::info("\r\n  =====================================" . config('config_uploads.img_path') .'avatar'. DS . $info->getSaveName() ."\r\n  =====================================");
                    $avatar = config('config_uploads.img_path') .'avatar'. DS . $info->getSaveName();
//                    $avatar = DS.'uploads'.DS.$this->request->module().DS.'avatar'.DS.$info->getSaveName();
                    $data['avatar'] = str_replace(DS,"/",$avatar);

                }else{
                    throw new \Exception($avatar_file->getError());
                }

            }

            $result = $UserModel->allowField(true)->save($data,['user_id'=>$userInfo['user_id']]);

            if ($result === false) {
                return json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }


    /**
     * 收货地址列表
     */
    public function addressList(){
        try {
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }
            $data = Db::name('user_address')->where('user_id',$userInfo['user_id'])->select();
            if ($data){
                return json(self::callback(1,'查询成功',$data));
            }
            $data=[];
            return json(self::callback(1,'没有可用的地址了',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }


    /**
     * 添加收货地址
     */
    public function addAddress(UserAddrValidate $validate, UserAddrModel $userAddressModel){
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        if (!$validate->check($param,[])) {
            return json(self::callback(0,$validate->getError()),400);
        }

        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];

        if ($param['is_default'] == 1){
            $userAddressModel->where('user_id',$userInfo['user_id'])->setField('is_default',0);
        }

        $result = $userAddressModel->allowField(true)->save($param);

        if (!$result){
            return json(self::callback(0,'操作失败'));
        }

        return json(self::callback(1,''));

    }

    /**
     * 修改收货地址
     */
    public function modifyAddress(UserAddrModel $userAddressModel){
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $result = $this->validate($param, ['address_id'  => 'require|number']);
        if(true !== $result){
            // 验证失败 输出错误信息
            return json(self::callback(0,$result),400);
        }

        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];

        if ($param['is_default'] == 1){
            $userAddressModel->where('user_id',$userInfo['user_id'])->setField('is_default',0);
        }

        $result = $userAddressModel->allowField(true)->save($param,['id'=>$param['address_id']]);

        if (!$result){
            return json(self::callback(0,'操作失败'));
        }

        return json(self::callback(1,''));
    }

    /**
     * 删除地址
     */
    public function deleteAddress(UserAddrModel $userAddressModel){
        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $address_id = input('address_id') ? intval(input('address_id')) : 0 ;
        if (!$address_id){
            return \json(self::callback(0,'参数错误'));
        }

        $result = $userAddressModel->where('id',$address_id)->delete();

        if (!$result){
            return \json(self::callback(0,'操作失败'));
        }

        return \json(self::callback(1,''));

    }

    /**
     * 意见反馈
     */
    public function feedback(){
        try{
        $param = $this->request->post();

        if (!$param || !$param['content']) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }
$param['content']=trim($param['content']);//删除空格
            $data = [
                'content' => $param['content'],
                'user_id' => $userInfo['user_id'],
                'img_url' => '', //默认为空 因为暂时没有设置上传图片
                'is_read' =>0 ,
                'create_time' => time()
            ];
            $feedback_id = Db::name('feedback')->insertGetId($data);

        $files = $this->request->file('img');
        if ($files){
            foreach ($files as $file){
                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(config('config_uploads.uploads_path') .'feedback_img');
                if($info){
                    $img_url= $avatar = config('config_uploads.img_path') .'feedback_img'. DS . $info->getSaveName().',,,';
                    $param['img_url'] = trim(str_replace(DS,"/",$img_url),',,,');

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }
        }

        if ($feedback_id===false){
            return json(self::callback(0,'操作失败'));
        }

        return json(self::callback(1,'反馈成功'));
        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 收藏列表
     */
    public function collectionList(HouseModel $HouseModel, HouseShortModel $model){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;
        $type = input('type') ? intval(input('type')) : 0 ;   //1长租 2短租

        if (!$type){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }


        switch ($type){
            case 1:

                $id = Db::name('house_collection')->where('user_id',$userInfo['user_id'])->column('house_id');

                if (!$id){
                    break;
                }

                $where['id'] = ['in',$id];
                $where['is_delete'] = ['eq',0];

                $total = $HouseModel->where($where)->count();

                $list = HouseModel::getHouseList($page,$size,$where);

                if ($list){
                    $list = $list->toArray();
                    foreach ($list as $k=>$v){
                        $lines_name = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');
                        $list[$k]['lines_name'] = !empty($lines_name) ? $lines_name : '';
                        $list[$k]['tag_info'] = Db::name('house_tag')->whereIn('id',$id)->where('type',1)->field('id,tag_name')->select();
                        unset($list[$k]['tag_id']);
                        unset($list[$k]['lines_id']);
                    }

                }
                break;
            case 2:

                $id = Db::name('short_collection')->where('user_id',$userInfo['user_id'])->column('short_id');

                if (!$id){
                    break;
                }

                $where['id'] = ['in',$id];

                $total = $model->where($where)->count();

                $list = HouseShortModel::getHouseShortList($page,$size,$where);

                if ($list){
                    $list = $list->toArray();
                    foreach ($list as $k=>$v){
                        $list[$k]['tag_info'] = $this->getTagInfo($v['tag_id']);

                        $list[$k]['traffic_tag_info'] = $this->getTrafficTagInfo($v['traffic_tag_id']);
                        $list[$k]['city_name'] = Db::name('city')->where('id',$v['city_id'])->value('city_name');
                        $score = Db::name('short_comment')->where('short_id',$v['id'])->avg('hygiene_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('service_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('position_score');
                        $score += Db::name('short_comment')->where('short_id',$v['id'])->avg('renovation_score');

                        $list[$k]['avg_score'] = round($score/4,1);
                        $list[$k]['total_comment'] = Db::name('short_comment')->where('short_id',$v['id'])->count();
                        unset($list[$k]['tag_id']);
                        unset($list[$k]['traffic_tag_id']);
                        unset($list[$k]['city_id']);
                    }

                }
                break;
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }


    /*
     * 获取标签信息
     * */
    protected function getTagInfo($id){
        $data = Db::name('house_tag')->whereIn('id',$id)->where('type',2)->field('id,tag_name')->select();
        return $data;
    }

    /*
     * 获取交通位置信息
     * */
    protected function getTrafficTagInfo($id){
        $data = Db::name('short_traffic_tag')->whereIn('id',$id)->field('id,name')->select();
        return $data;
    }



    /**
     * 委托列表
     */
    public function entrustList(){
        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $data = HouseEntrust::all(['type'=>1,'param_id'=>$userInfo['user_id']]);

        return \json(self::callback(1,'',$data));
    }


    /**
     * 优惠券列表
     */
    public function couponList(){

        try {
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $status = input('status') ? intval(input('status')) : 0 ;  //状态 1未使用 -1已过期

            switch ($status) {
                case 1:
                    $where['status'] = ['eq',1];
                    $where['expiration_time'] = ['gt',time()];
                    break;
                case -1:
                    $where['expiration_time'] = ['lt',time()];
                    break;
                default:
                    throw new \Exception('参数错误');
                    break;
            }

            $data = Db::name('coupon')->where($where)->where('user_id',$userInfo['user_id'])->select();


            foreach ($data as $k=>$v) {
                $data[$k]['create_time'] = date('Y-m-d H:i',$v['create_time']);
                $data[$k]['expiration_time'] = date('Y-m-d H:i',$v['expiration_time']);
                unset($data[$k]['use_time']);
                unset($data[$k]['status']);
            }

            return \json(self::callback(1,'',$data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 消息列表
     */
    public function msgList(){
        try{
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::view('user_msg_link')
                ->view('user_msg','title,content,type,create_time','user_msg.id = user_msg_link.msg_id','left')
                ->where('user_msg_link.user_id',$userInfo['user_id'])
                ->count();

            $list = Db::view('user_msg_link','id')
                ->view('user_msg','title,content,type,create_time','user_msg.id = user_msg_link.msg_id','left')
                ->where('user_msg_link.user_id',$userInfo['user_id'])
                ->order('user_msg.create_time','desc')
                ->page($page,$size)
                ->select();

//            foreach($list as&$v){
//                $v['create_time'] = date('Y-m-d',$v['create_time']);
//            }


            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 代购记录
     */
    public function dgRecord(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');
            switch ($status){
                case 1:
                    //已完成获利
                    $where['product_order.order_status'] = ['in','5,6'];
                    break;
                case 2:
                    //待完成获利
                    $where['product_order.order_status'] = ['in','3,4'];
                    break;
                default:
                    return \json(self::callback(0,'参数错误'),400);
                    break;
            }

            $total = Db::view('product_order_detail','specs_id,product_name,product_specs,huoli_money')
                ->view('product_order','order_no','product_order.id = product_order_detail.order_id','left')
                ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id')
                ->where('product_order_detail.type',2)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->where($where)->count();

            $list = Db::view('product_order_detail','specs_id,product_name,product_specs,huoli_money')
                ->view('product_order','order_no','product_order.id = product_order_detail.order_id','left')
                ->view('product_specs','cover','product_specs.id = product_order_detail.specs_id')
                ->where('product_order_detail.type',2)
                ->where('product_order.user_id',$userInfo['user_id'])
                ->where($where)
                ->order('product_order.create_time','desc')
                ->page($page,$size)
                ->select();

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

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
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $money = input('money',0,'floatval');
            $alipay_account = input('alipay_account','','addslashes,strip_tags,trim');  //支付宝账号 手机号或者邮箱

            if (!$money || !$alipay_account ) {
                return json(self::callback(0,'参数错误'));
            }

            if($money <= 0)return \json(self::callback(0,'提现金额错误'));

            if (!$this->ALIAccountVerify($alipay_account)) {
                throw new \Exception('支付宝账号无效');
            }

            if ($userInfo['money'] < $money) {
                throw new \Exception('余额不足');
            }

            Db::startTrans();

            $date = date('Y-m-d H:i:s');
            $id = Db::name('user_tixian_record')->insertGetId([
                'order_no'=> $order_no = build_order_no('T'),
                'money'=>$money,
                'alipay_account'=>$alipay_account,
                'user_id'=>$userInfo['user_id'],
                'create_at'=>$date
            ]);

            $aliPay = new AliPay();
            $data = $aliPay->transfer($order_no,$alipay_account,$money);

            $code = $data['code'];
            Db::name('user_tixian_record')->where('id',$id)->update(['code'=>$code,'order_id'=>$data['order_id']]);

            //提现成功
            if(!empty($code) && $code == 10000){

                Db::name('user')->where('user_id',$userInfo['user_id'])->setDec('money',$money);
                Db::name('user_money_detail')->insert([
                    'user_id' => $userInfo['user_id'],
                    'order_id' => $id,
                    'note' => '提现',
                    'money' => -$money,
                    'balance' => $userInfo['money'] - $money,
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
     * 支付密码验证
     */
    public function payPwdVerify(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
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
        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
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

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['pay_password'], PASSWORD_DEFAULT);

            $result = Db::name('user')->where('mobile','eq',$param['mobile'])->setField('pay_password',$password);

            if (!$result) {
                return json(self::callback(0,'操作失败'));
            }

            Session::clear();

            return json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 收支记录
     */
    public function userMoneyRecord2(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $month = input('month');

            $status = input('status');

            switch ($status){
                case 1:
                    $list = Db::name('user_money_detail')->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->join('product_order_detail p','p.id = d.order_detail_id','left')
                        ->field('d.note,d.money,d.create_time,o.order_no,p.product_name')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(d.`create_time`),'%Y%m') = $month")
                        #->where('d.note','eq','代购收入')
                        ->where('d.user_id',$userInfo['user_id'])
                        ->order('d.create_time','desc')
                        ->select();

                    break;
                case 2:
                    $list = Db::name('product_order_detail')
                        ->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->field('d.note,d.huoli_money as money,o.create_time,o.order_no,d.product_name')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(o.`create_time`),'%Y%m') = $month")
                        ->where('o.user_id',$userInfo['user_id'])
                        ->where('d.huoli_money','neq',0)
                        ->where('o.order_status','in','3,4')
                        ->select();

                    break;
                case 3:
                    $list = Db::name('user_money_detail')->alias('d')
                        ->join('product_order o','o.id = d.order_id','left')
                        ->join('product_order_detail p','p.id = d.order_detail_id','left')
                        ->field('d.note,d.money,d.create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(d.`create_time`),'%Y%m') = $month")
                        ->where('d.note','eq','提现')
                        ->where('d.user_id',$userInfo['user_id'])
                        ->order('d.create_time','desc')
                        ->select();
                    break;
                default:
                    return \json(self::callback(0,'参数错误'),400);
                    break;
            }

            #$data = Db::query($sql);

            $total_money = 0;
            foreach ($list as $k=>$v){
                $total_money += $v['money'];
                $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            }

            $data['total_money'] = $total_money;
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 收支记录
     */
    public function userMoneyRecord(){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $month = input('month',date('Ym',time()),'addslashes,strip_tags,trim');

            $status = input('status',0,'intval'); //0全部 1收入 2支出

            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $page = max(1,$page);
            $size = max(1,$size);

            $type = input('post.type',0,'intval');  //0.按照月来;1.不按月,分页加载

            $user_id = $userInfo['user_id'];
            ##获取各类金额
            $total_money = UserLogic::getUserMoneyTotal($user_id,$month);
            $dis_money = UserLogic::getUserMoneyDis($user_id,$month);
            $had_money = abs(UserLogic::getUserMoneyHad($user_id,$month));

            $where = [
                'user_id' => $user_id
            ];

            if($status > 0){
                $where['money'] = $status==1?['GT',0]:['LT',0];
            }

            if(!$type){
                $where["DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m')"] = $month;
            }

            $total = UserLogic::countUserMoneyLog($where);

            $list = UserLogic::getUserMoneyList($where,$page,$size);

//            switch ($status){
//                case 1:
//                    $total = Db::name('user_money_detail')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->where('money','>',0)
//                        ->count('id');
//                    $list = Db::name('user_money_detail')
//                        ->field('id,note,money,create_time,order_id')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->where('money','>',0)
//                        ->order('create_time','desc')
//                        ->limit(($page-1)*$size,$size)
//                        ->select();
//
//                    break;
//                case 2:
//                    $total = Db::name('user_money_detail')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->where('money','<',0)
//                        ->count('id');
//                    $list = Db::name('user_money_detail')
//                        ->field('id,note,money,create_time,order_id')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->where('money','<',0)
//                        ->order('create_time','desc')
//                        ->limit(($page-1)*$size,$size)
//                        ->select();
//
//                    break;
//                default:
//                    $total = Db::name('user_money_detail')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->count('id');
//                    $list = Db::name('user_money_detail')
//                        ->field('id,note,money,create_time,order_id')
//                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
//                        ->where('user_id',$userInfo['user_id'])
//                        ->order('create_time','desc')
//                        ->limit(($page-1)*$size,$size)
//                        ->select();
//
//                    break;
//            }

            foreach($list as &$v){
                $type = $v['money']>0?1:2;
                $v['order_no'] = UserLogic::getUserMoneyOrderNo($v['order_id'],$type);
            }

            $max_page = ceil($total/$size);

            return \json(self::callback(1,'',compact('max_page','total','list','total_money','dis_money','had_money')));

        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 增加活跃度
     */
    public function addActive(){
        try{

            $date = date('Y-m-d');

            if (Db::name('active_count')->where('active_date','eq',$date)->count()){
                Db::name('active_count')->where('active_date','eq',$date)->setInc('active_number');
            }else{
                Db::name('active_count')->insert(['active_number'=>1,'active_date'=>$date]);
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 我的卡券列表
     */
    public function cardList(){
        try{

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $status = input('status') ? intval(input('status')) : 0 ;  //状态 1未使用 2已使用 -1已过期
            $lng = input('lng');
            $lat = input('lat');

            switch ($status) {
                case 1:
                    $where['status'] = ['eq',1];
                    $where['end_time'] = ['gt',time()];
                    break;
                case 2:
                    $where['status'] = ['eq',2];
                    break;
                case -1:
                    $where['end_time'] = ['lt',time()];
                    break;
                default:
                    throw new \Exception('参数错误');
                    break;
            }

            $data = Db::name('user_card')->where('user_id',$userInfo['user_id'])->where($where)->select();

            foreach ($data as $k=>$v) {
                $data[$k]['start_time'] = date('Y-m-d H:i',$v['start_time']);
                $data[$k]['end_time'] = date('Y-m-d H:i',$v['end_time']);

                $store_list = Db::view('user_card_store','store_id')
                    ->view('store','cover,store_name,province,city,area,address,lng,lat','store.id = user_card_store.store_id','left')
                    ->where('user_card_store.card_id',$v['id'])
                    ->select();

                foreach ($store_list as $k2=>$v2){
                    $store_list[$k2]['address'] = $v2['province'].$v2['city'].$v2['area'];
                    if (!empty($lat) && !empty($lng)) {
                        $store_list[$k2]['distance'] = round(getDistance($lat,$lng,$v2['lat'],$v2['lng']),1);
                    }else{
                        $store_list[$k2]['distance'] = '' ;
                    }

                    unset($store_list[$k2]['province']);
                    unset($store_list[$k2]['city']);
                    unset($store_list[$k2]['area']);
                    #unset($store_list[$k2]['lng']);
                    #unset($store_list[$k2]['lat']);
                }

                $distance = array_column($store_list,'distance');
                array_multisort($distance,SORT_ASC,$store_list);
                $data[$k]['store_list'] = $store_list;
            }

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /*
     * 我的关注列表
     * */
    public function storeFollowList(){
        try{

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $total = Db::name('store_follow')
                ->where('user_id',$userInfo['user_id'])
                ->count();

            $list = Db::view('store_follow','store_id')
                ->view('store','store_name,cover,type','store.id = store_follow.store_id','left')
                ->where('store_follow.user_id',$userInfo['user_id'])
                ->page($page,$size)
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['fans_number'] = Db::name('store_follow')->where('store_id',$v['store_id'])->count();
                $list[$k]['chaoda_number'] = Db::name('chaoda')->where('store_id',$v['store_id'])->count();
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * app => 微信第三方登录
     * @param LoginValidate $LoginValidate
     * @param UserModel $UserModel
     * @return Json
     */
    public function thirdWxLogin(LoginValidate $LoginValidate, UserModel $UserModel){

        #验证请求方式
        if(!request()->isPost())return json(self::callback(0,'非法请求'),400);

        #验证参数
        $res = $LoginValidate->scene('wx')->check(input());
        if(!$res)return json(self::callback(0,$LoginValidate->getError()),400);

        #逻辑
        $code = input('post.code','','addslashes,strip_tags,trim');

        ##获取access_token等信息
        $info = LoginFunc::getWxAccessToken($code);
        if(isset($info['errcode']))return json(self::callback(0,$info['errmsg']));

        ##获取用户信息
        $open_id = $info['openid'];
        $access_token = $info['access_token'];
        $info = LoginFunc::thirdGetWxUserInfo($access_token,$open_id);
        if(!$info)return json(self::callback(0,'登录失败'));
        if(isset($info['errcode']) || !isset($info['unionid']))return json(self::callback(0,$info['errmsg']));

        ##查看用户是否已经注册
        $unionid = $info['unionid'];
        $user_id = $UserModel->getUserInfoByUnionid($unionid);

        if($user_id){ ##非第一次使用微信第三方登录
            #重新更新token
            $result = LoginFunc::updateToken($user_id);
            if($result === false){
                return \json(self::callback(0,'操作失败'));
            }

            return json(self::callback(1,'',['user_id'=>$user_id,'token'=>$UserModel->getData('token')]));
        }else{ ##第一次使用微信第三方登录
            $token = UserFunc::setToken();
            #注册临时账号
            $data = [
                'nickname' => $info['nickname'],
                'avatar' => $info['headimgurl'],
                'wx_unionid' => $info['unionid'],
                'token' => $token['token'],
                'token_expire_time' => $token['token_expire_time']
            ];
            $res = $UserModel->addUser($data);
            if($res === false)return json(self::callback(0,'登录失败'));

            #返回
            return json(self::callback(-1,'新注册用户',['user_id'=>$res,'token'=>$UserModel->getData('token')]));
        }

    }

    /**
     * app => QQ第三方登录
     * @param LoginValidate $LoginValidate
     * @param UserModel $UserModel
     * @return Json
     */
    public function thirdQQLogin(LoginValidate $LoginValidate, UserModel $UserModel){

        #验证请求方式
        if(!request()->isPost())return json(self::callback(0,'非法请求'),400);

        #验证参数
        $res = $LoginValidate->scene('qq')->check(input());
        if(!$res)return json(self::callback(0,$LoginValidate->getError()),400);

        #逻辑
        $qq_openid = input('post.qq_openid','','addslashes,strip_tags,trim');
        $avatar = input('post.qq_avatar','','strip_tags,trim');
        $nickname = input('post.nickname','','addslashes,strip_tags,trim');

        ##检查用户是否已注册
        $user_id = $UserModel->getUserInfoByQQOpenid($qq_openid);
        if($user_id){ ##不是第一次通过qq登录
            ##更新token
            $res = LoginFunc::updateToken($user_id);
            if($res === false)return json(self::callback(0,'登录失败'));

            ##返回
            return json(self::callback(1,'登录成功',['user_id'=>$user_id,'token'=>$UserModel->getData('token')]));
        }else{ ##第一次使用QQ登录
            ##注册临时用户
            $data = compact('qq_openid','avatar','nickname');
            ###更新token
            $token = UserFunc::setToken();
            $data['token'] = $token['token'];
            $data['token_expire_time'] = $token['token_expire_time'];
            ###添加执行
            $res = $UserModel->addUser($data);
            if($res === false)return json(self::callback(0,'登录失败'));

            #返回
            return json(self::callback(-1,'登录成功',['user_id'=>$res,'token'=>$UserModel->getData('token')]));
        }

    }

    /**
     * app => 微博第三方登录
     * @param LoginValidate $LoginValidate
     * @param UserModel $UserModel
     * @return Json
     */
    public function thirdSinaLogin(LoginValidate $LoginValidate, UserModel $UserModel){

        #验证请求方式
        if(!request()->isPost())return json(self::callback(0,'非法请求'),400);

        #验证参数
        $res = $LoginValidate->scene('sina')->check(input());
        if(!$res)return json(self::callback(0,$LoginValidate->getError()),400);

        #逻辑
        $access_token = input('post.access_token','','addslashes,strip_tags,trim');
        $uid = input('post.user_id','','addslashes,strip_tags,trim');

        ##判断用户是否注册
        $user_id = $UserModel->getUserInfoBySinaId($uid);

        if($user_id){ ##非第一次使用微博登录
            ##更新token
            $res = LoginFunc::updateToken($user_id);
            if($res === false)return json(self::callback(0,'登录失败'));

            ##返回
            return json(self::callback(1,'登录成功',['user_id'=>$user_id,'token'=>$UserModel->getData('token')]));
        }else{ ##第一次使用微博登录
            ##获取用户信息
            $info = LoginFunc::thirdGetSinaUserInfo($access_token,$uid);
            if(!isset($info['id']))return json(self::callback(0,'用户信息获取失败'));

            ##生成token
            $token = UserFunc::setToken();

            ##添加临时注册用户信息
            $data = [
                'nickname' => $info['screen_name'],
                'avatar' => $info['avatar_large'],
                'sina_id' => $uid,
                'token' => $token['token'],
                'token_expire_time' => $token['token_expire_time']
            ];

            ##添加执行
            $res = $UserModel->addUser($data);
            if($res === false)return json(self::callback(0,'登录失败'));

            #返回
            return json(self::callback(-1,'登录成功',['user_id'=>$res,'token'=>$UserModel->getData('token')]));
        }

    }

    /**
     * 绑定手机号
     * @param UserValidate $UserValidate
     * @param UserModel $UserModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function bindMobile(UserValidate $UserValidate, UserModel $UserModel){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));
        $res = $UserValidate->scene('bind_mobile')->check(input());
        if(!$res)return \json(self::callback(0,$UserValidate->getError()));

        #逻辑
        $user_id = input('post.user_id',0,'intval');
        $mobile = input('post.mobile','','addslashes,strip_tags,trim');
        $password = input('post.password','','addslashes,strip_tags,trim');
        $code = input('post.code','','addslashes,strip_tags,trim');
        $user_code = input('post.user_code','','addslashes,starip_tags,trim');

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        ##验证验证码
        $verify = IhuyiSMS::verifyCode($mobile, $code);
        if (!$verify) {
            return \json(self::callback(0,'验证码不存在或已失效'));
        }

        $password = password_hash($password,PASSWORD_DEFAULT);

        ##判断用户是否存在
        $userInfo = $UserModel->getUserInfoByMobile($mobile);
        if($userInfo){##手机号已注册
            $data = compact('password');
            ##获取未绑定手机号账号的信息
            $prevInfo = $UserModel->getUserBaseInfo($user_id);
            if($prevInfo['avatar'] != ''){
                $data['avatar'] = $prevInfo['avatar'];
            }
            ##更新token
//            $token = UserFunc::setToken();
//            $data['token'] = $token['token'];
//            $data['token_expire_time'] = $token['token_expire_time'];

        }else{##手机号未注册
            $data = compact('mobile','password');
            if($user_code)$data['invitation_user_id'] = $user_code;
        }

        ##修改用户信息
        $res = $UserModel->edit($user_id,$data);
        if($res === false)return \json(self::callback(0,'手机号绑定失败'));

        #返回
        if($userInfo){
            $info = $UserModel->getUserBaseInfo($user_id);
            return \json(self::callback(2,'绑定成功',$info));
        }else{
            return \json(self::callback(1,'绑定成功'));
        }
    }

    /**
     * 用户浏览店铺增加浏览量
     * @param UserValidate $UserValidate
     * @param StoreModel $StoreModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function viewStore(UserValidate $UserValidate, StoreModel $StoreModel){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));
        $res = $UserValidate->scene('view_store')->check(input());
        if(!$res)return \json(self::callback(0,$UserValidate->getError()));

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $store_id = input('post.store_id',0,'intval');

        ##添加浏览量
        $res = $StoreModel->addViewNum($store_id);
        if(!$res)return \json(self::callback(0,'浏览量增加失败'));

        #返回
        return json(self::callback(1,'success'));
    }

    /**
     * 个人页收藏，关注，卡券数
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function collectFollowCoupon(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $user_id = input('post.user_id',0,'intval');

        $collect = UserLogic::userCollectNum($user_id);
        $follow = UserLogic::userFollowNum($user_id);
        $coupon = UserLogic::userCouponNum($user_id);

        #返回
        return \json(self::callback(1,'',compact('collect','follow','coupon')));
    }

    /**
     * 收藏店铺列表
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function storeCollectList(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $user_id = input('post.user_id',0,'intval');
        $page = input('post.page',1,'intval');
        $size = input('post.size',10,'intval');
        $page = $page>0?$page:1;
        $size = $size>0?$size:10;

        $total = UserLogic::UserStoreCollectCount($user_id);
        $max_page = ceil($total/$size);

        ##获取用户店铺收藏列表
        $list = UserLogic::UserStoreCollectList($user_id,$page,$size);

        return \json(self::callback(1,'',compact('total','max_page','list')));
    }

    /**
     * 用户卡券列表
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userCouponList(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));
        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }
        $type = input('post.type',0,'intval');
        $user_id = input('post.user_id',0,'intval');
        $store_id = input('post.store_id',0,'intval');
//        echo $store_id;die;
        $page = input('post.page',0,'intval');
        $size = input('post.size',10,'intval');
        $page = $page<0?0:$page;
        $size = $size<=0?10:$size;
        $total = $store_id > 0?(UserLogic::userOrderCouponCount($user_id,$type,$store_id)):(UserLogic::userCouponCount($user_id,$type));
//        $total = UserLogic::userCouponCount($user_id,$type);
        $max_page = ceil($total/$size);
//        $list = $store_id > 0?(UserLogic::userOrderCouponLists($user_id,$store_id,$type,$page,$size)):(UserLogic::userCouponLists($user_id,$type,$page,$size));
        $list = UserLogic::getOptimizeCouponList($user_id,$page,$size,$type);
        return \json(self::callback(1,'',compact('total','max_page','list')));
    }

    /**
     * 提现详情
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tixianDetail(){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $id = input('post.id',0,'intval');
        $user_id = input('post.user_id',0,'intval');

        $info = UserLogic::userTixianDetail($id,$user_id);

        return \json(self::callback(1,'',compact('info')));
    }

    /**
     * 用户领取优惠券
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userGetCoupon(UserValidate $UserValidate){
        #验证
        if(!request()->isPost())return \json(self::callback(0,'非法请求'));

        $res = $UserValidate->scene('get_coupon')->check(input());
        if(!$res)return \json(self::callback(0,$UserValidate->getError()));

        ##验证token
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        #逻辑
        $user_id = input('post.user_id',0,'intval');
        $coupon_id = input('post.coupon_id',0,'intval');
        $store_id = input('post.store_id',0,'intval');

        ##获取优惠券信息
        $coupon_info = Logic::couponInfo($coupon_id);
        if(!$coupon_info)return \json(self::callback(0,'优惠券信息不存在'));
        if($store_id)if($coupon_info['store_id'] != $store_id)return \json(self::callback(0,'当前商户未发行该优惠券'));
        if($store_id)if($coupon_info['type'] != 2)return \json(self::callback(0,'当前商户未发行该优惠券'));
        if(!$store_id && $coupon_info['type'] != 1)return \json(self::callback(0,'优惠券领取失败'));
        if($coupon_info['surplus_number'] <= 0)return \json(self::callback(0,'优惠券已领取完'));
        if($coupon_info['is_open'] == -1)return \json(self::callback(0,'优惠券已下架'));

        ##检查用户是否已经领券

        $res = UserLogic::checkUserCoupon($user_id,$coupon_id);
        if($res)return \json(self::callback(0,'您已领取过该优惠券了'));

        Db::startTrans();
        try{
            $data = [
                'user_id' => $user_id,
                'coupon_id' => $coupon_id,
                'coupon_name' => $coupon_info['coupon_name'],
                'satisfy_money' => $coupon_info['satisfy_money'],
                'coupon_money' => $coupon_info['coupon_money'],
                'expiration_time' => $coupon_info['end_time'],
                'status' => 1,
                'create_time' => time(),
                'store_id' => $store_id,
                'coupon_type' => $coupon_info['coupon_type'],
            ];
            ##添加领券记录
            $res = UserLogic::userGetCoupon($data);
            if($res === false)throw new Exception('领取失败');

            ##修改优惠券剩余数和领取数
            $res = Logic::updateCouponNum($coupon_id);
            if($res === false)throw new Exception('领取失败');

            Db::commit();
            return \json(self::callback(1,'领取成功'));
        }catch(Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /*
 * 收藏店铺和取消收藏
 * */
    public function store_collection_and_cancel(){
        try{
            //token 验证
            $userInfo = \app\user_v2\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $param = $this->request->post();
            $store_id=$param['store_id'];
            $status=$param['status'];

            if(!$store_id || !$status){
                return \json(self::callback(0,'参数错误'),400);
            }
            if($status=='true'){
                $guan= Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();

                if ($guan==0){
                    //写入收藏
                    Db::name('store_collection')->insert([
                        'store_id' => $store_id,
                        'user_id' => $userInfo['user_id'],
                        'create_time' => time()
                    ]);
                    //统计最新收藏
                    $xie= Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if ($xie==0){

                        //写入失败
                        $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                        if($data==0){
                            $data=-1;

                        }else{

                        }
                        return \json(self::callback(0,'收藏失败',$data));
                    }else{

                        //写入成功
                        $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                        if($data==0){
                            $data=-1;
                        }else{
                        }
                        return \json(self::callback(1,'收藏成功',$data));
                    }



                }else{

                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;
                    }else{
                    }

                    return \json(self::callback(1,'已收藏',$data));
                }


            }else if($status=='false'){

                $de=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->delete();
                if($de==0){
                    //删除失败
                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;
                    }else{

                    }
                    return \json(self::callback(0,'取消收藏失败',$data));

                }else{
                    //删除成功

                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;

                    }else{

                    }
                    return \json(self::callback(1,'取消收藏成功',$data));
                }

            }else{
                return \json(self::callback(0,'操作错误'));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 兑换优惠券
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function exchangeCoupon(UserValidate $UserValidate){
        try{

            #验证
            if(!request()->isPost())throw new Exception('非法请求');

            $res = $UserValidate->scene('exchange_code')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $coupon_code = input('post.coupon_code','','addslashes,strip_tags,trim');
            $user_id = input('post.user_id',0,'intval');

            ##优惠券信息
            $coupon_info = Logic::couponCodeInfo($coupon_code);
            if(!$coupon_info)throw new Exception('无效兑换码');
            if($coupon_info['status'] == -1)throw new Exception('兑换码已冻结');
            if($coupon_info['is_open'] != 1)throw new Exception('优惠券已下架');
            if($coupon_info['end_time'] < time())throw new Exception('优惠券已下架');
            if($coupon_info['surplus_number'] <= 0)throw new Exception('来晚啦!优惠券已经被领光啦!');
            if($coupon_info['type'] == 1 && $coupon_info['status'] == 2)throw new Exception('兑换码已失效');

            ##获取总领取数
            $get_count = UserLogic::countExchangeRecord($coupon_info['css_coupon_id'],$user_id);
            if($get_count >= $coupon_info['zengsong_number'])throw new Exception('优惠券领用已达上限');

            if($coupon_info['type'] == 2){  //验证推广券
                ##获取推广人状态
                $extend_info = Logic::getExtendInfo($coupon_info['extend_id']);
                if(!$extend_info)throw new Exception('兑换码异常[2001]');
                if($extend_info['status'] != 1)throw new Exception('兑换码已下架');
            }

            ##下发优惠券
            Db::startTrans();
            ###下发优惠券
            $data_coupon = [
                'user_id' => $user_id,
                'coupon_id' => $coupon_info['css_coupon_id'],
                'coupon_name' => $coupon_info['coupon_name'],
                'store_id' => (int)$coupon_info['store_id'],
                'satisfy_money' => $coupon_info['satisfy_money'],
                'coupon_money' => $coupon_info['coupon_money'],
                'expiration_time' => $coupon_info['end_time'],
                'create_time' => time(),
                'css_coupon_table_num' => 1,
                'coupon_type' => $coupon_info['coupon_type']
            ];
            $coupon_id = UserLogic::userGetCouponRtnId($data_coupon);
            if($coupon_id === false)throw new Exception('优惠券兑换失败[2002]');
            ###修改优惠券信息
            $res = Logic::updateCouponNum($coupon_info['css_coupon_id']);
            if($res === false)throw new Exception('优惠券兑换失败[2003]');
            ###增加兑换记录
            $data_coupon_record = [
                'code_id' => $coupon_info['id'],
                'extend_id' => $coupon_info['extend_id'],
                'user_id' => $user_id,
                'coupon_id' => $coupon_id,
                'coupon_rule_id' => $coupon_info['css_coupon_id'],
            ];
            $res = UserLogic::addExchangeCouponLog($data_coupon_record);
            if($res === false)throw new Exception('优惠券兑换失败[2004]');
            ###修改兑换码状态(合作券兑换码)
            if($coupon_info['type'] == 1){
                $data_coupon_code = [
                    'exchange_time' => time(),
                    'status' => 2
                ];
                $res = Logic::cancelCouponCode($coupon_info['id'], $data_coupon_code);
                if($res === false)throw new Exception('优惠券兑换失败[2005]');
            }

            #返回
            Db::commit();
            return \json(self::callback(1,'优惠券兑换成功'));
        }catch(Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 获取订单可用优惠券列表
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function orderCouponList(UserValidate $UserValidate){
        try{
            #验证
            $res = $UserValidate->scene('order_coupon_list')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $user_id = input('post.user_id',0,'intval');
            $coupon_type = input('post.type',1,'intval');  //1.平台券;2.店铺券
            $coupon_id = $coupon_type == 2?input('post.coupon_id',0,'intval'):input('post.coupon_id','','addslashes,strip_tags,trim');
            if($coupon_type == 1 && $coupon_id)$coupon_id = explode(',',$coupon_id);
            $store_id = input('post.store_id',0,'intval');
            $page = input('post.page',0,'intval');
            $size = input('post.size',10,'intval');

            if($coupon_type == 2 && !$store_id)throw new Exception('参数错误');

            $data = UserLogic::userOrderCouponLists0812($coupon_type, $coupon_id,$user_id, $store_id, $page, $size);

            return \json(self::callback(1,'',$data));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除用户通知信息
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function deleteMsgLink(UserValidate $UserValidate){
        try{

            #验证
            $res = $UserValidate->scene('delete_msg')->check(input());
            if(!$res)throw new Exception($UserValidate->getError());

            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $user_id = input('post.user_id',0,'intval');
            $msg_id = input('post.msg_id',0,'intval');

            ##删除消息通知
            $res = UserLogic::deleteMsg($user_id,$msg_id);
            if($res === false)throw new Exception('删除失败');

            return \json(self::callback(1,'删除成功'));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    public function test(){
        phpinfo();
    }

}