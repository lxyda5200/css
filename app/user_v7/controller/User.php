<?php

namespace app\user_v7\controller;

use aes\Aes;
use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\user_v7\common\Logic;
use app\user_v7\common\Login;
use app\user_v7\model\Coupon;
use app\user_v7\model\DrawLottery;
use app\user_v7\model\DrawLotteryUserNum;
use app\user_v7\model\HouseEntrust;
use app\user_v7\validate\Login as LoginValidate;
use app\user_v7\common\Login as LoginFunc;
use app\user_v7\common\User as UserFunc;
use app\user_v7\validate\UserAddress;
use jiguang\JiG;
use my_redis\MRedis;
use system_message\Msg;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use think\response\Json;
use think\Session;
use app\user_v7\model\User as UserModel;
use app\user_v7\validate\User as UserValidate;
use app\user_v7\validate\UserAddress as UserAddrValidate;
use app\user_v7\model\UserAddress as UserAddrModel;
use app\user_v7\model\House as HouseModel;
use app\user_v7\model\HouseShort as HouseShortModel;
use app\user_v7\model\Store as StoreModel;
use app\user_v7\common\UserLogic;
use app\user_v7\controller\Task as TaskCon;

class User extends Base
{
    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $mobile =input('post.mobile','','addslashes,strip_tags,trim');  //获取验证码的手机号
        $mobile_type = input('mobile_type');  //是否已注册 1是 0否  需要验证

//---------start
        $test = input('test','0','addslashes,strip_tags,trim');  //用于测试
        if($test==1){
            $mobile_code ='1111';
            Session::set($mobile.'mobile',$mobile);
            Session::set($mobile.'mobile_code',$mobile_code);
            Session::set($mobile.'expire_time',$_SERVER['REQUEST_TIME'] + 60*3);
            return json(self::callBack(1,'验证码发送成功!',Session::get($mobile.'mobile_code')));
        }
//---------end

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

        $res = IhuyiSMS::getCodeNew($mobile);

        if ($res !== true) {
            return json(self::callBack(0,$res));
        }

        return json(self::callBack(1,''));
    }


    /**
     * 新版获取验证码
     */
    public function getVerifyCodeNew() {
        $mobile = input('post.mobile','','addslashes,strip_tags,trim');   //获取验证码的手机号

//---------start
        $test = input('test','0','addslashes,strip_tags,trim');  //用于测试
        if($test==1){
            $mobile_code = '1111';
            Session::set($mobile.'mobile',$mobile);
            Session::set($mobile.'mobile_code',$mobile_code);
            Session::set($mobile.'expire_time',$_SERVER['REQUEST_TIME'] + 60*3);
            return json(self::callBack(1,'验证码发送成功!',Session::get($mobile.'mobile_code')));
        }
//---------end

        if(!$mobile){return json(self::callBack(0,'参数错误'),400);}
        $res = IhuyiSMS::getCodeNew($mobile);
        if($res !== true) {return json(self::callBack(0,$res));}
        return json(self::callBack(1,'验证码发送成功!'));
    }

    /**
     * 验证码登录
     */
    public function verifyCodeLogin(UserValidate $validate, UserModel $UserModel) {
        try {
            $param['mobile'] = input('post.mobile','','addslashes,strip_tags,trim');
            $param['code'] =input('post.code','','addslashes,strip_tags,trim');//验证码
            $invitation_code = input('invitation_code');  //邀请码
            $invitation_user_id = intval(input('invitation_user_id'));  //用户id
            $type= input('type');  //用户id
            if(!$param['mobile'] || !$param['code']) {return json(self::callBack(0,'参数错误'),400);}
            if($invitation_code && $invitation_user_id) {return json(self::callBack(0,'邀请人不能同时存在'),400);}
            if (!$validate->check($param,[],'verifyCodeLogin')){return json(self::callback(0,$validate->getError()),400);}
            $verify = IhuyiSMS::verifyCodeNew($param['mobile'],$param['code']);
            if (!$verify) {return json(self::callBack(0,'验证码不存在或已失效'),400);}
            ##检测手机号是否注册
            $userInfo = UserLogic::findUser($param['mobile']);
            ##生成token
            $tokenInfo = UserFunc::setToken();
            $data = [
                'token' => $tokenInfo['token'],
                'token_expire_time' => $tokenInfo['token_expire_time']
            ];
            Db::startTrans();
            if($userInfo){
                //已注册
                $data['login_time'] = time();
                $res = $UserModel->edit($userInfo['user_id'],$data);
                if($res===false){throw new Exception('更新用户信息失败!');}
                $user_id=$userInfo['user_id'];
            }else{
                //未注册
                $data['mobile']=$param['mobile'];
                $data['nickname'] = '新用户'.hide_phone($param['mobile']);
                $data['authorize_time'] = time();
                $data['avatar']='/default/user_logo.png';

                if($type && $type==1){
                if($invitation_user_id){
                $have_user=Db::name('user')->where('user_id',$invitation_user_id)->find();
                    if($have_user){
                        $data['invitation_user_id'] = $invitation_user_id;
                        $invitation_user_id=$data['invitation_user_id'];
                    }
                }
                }else{
                    if($invitation_code){
                        $invitation = Logic::getInvitation($invitation_code);
                        if($invitation){
                            $data['invitation_user_id'] = decode($invitation_code);
                            $invitation_user_id=$data['invitation_user_id'];
                        }
                    }
                }
                $user_id = $UserModel->addUser($data);
                if($user_id===false)throw new Exception('注册失败');
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
                ##注册极光IM
                JiG::registerUser($user_id);
            }
            Db::commit();
            Session($param['mobile'].'mobile',null);
            Session($param['mobile'].'mobile_code',null);
            Session($param['mobile'].'expire_time',null);
            return json(self::callback(1,'登录成功!',['user_id'=>$user_id,'token'=>$tokenInfo['token']]));
        } catch (\Exception $e){
            Db::rollback();
        return json(self::callback(0,$e->getMessage()));
        }
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

            $verify = IhuyiSMS::verifyCodeNew($mobile, $code);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

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
                $user_id = $userInfo['user_id'];
            }else{ ##未注册
                $data['mobile'] = $mobile;
                $data['nickname'] = '新用户'.hide_phone($mobile);
                $data['create_time'] = $data['authorize_time'] = time();
                if($user_code){
                    $invitation = Logic::getInvitation($user_code);
                    if($invitation){
                        $invitation_user_id = decode($user_code);
                        $data['invitation_user_id'] = $invitation_user_id;
                    }
                }
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
            Session($param['mobile'].'mobile',null);
            Session($param['mobile'].'mobile_code',null);
            Session($param['mobile'].'expire_time',null);
            Db::commit();

            ##注册极光IM
            JiG::registerUser($user_id);

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

            if(!$userInfo['jig_id']){
                $jig_info = JiG::registerUser($userInfo['user_id']);
                $userInfo['jig_id'] = $jig_info['jig_id'];
                $userInfo['jig_pwd'] = $jig_info['jig_pwd'];
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

            $verify = IhuyiSMS::verifyCodeNew($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }
            ##检测手机号是否注册
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $check = UserLogic::findUser($mobile);
            if(!$check)throw new Exception('当前手机号未注册,请前往注册');

            if (password_verify($param['password'], $check['password'])) {
                // Pass
                throw new \Exception('新密码不能与原密码一样');
            }
            $password = password_hash($param['password'], PASSWORD_DEFAULT);

            $result = Db::name('user')->where('mobile',$param['mobile'])->setField('password',$password);

            if (!$result) {
                return \json(self::callback(0,'操作失败'));
            }

           // Session::clear();

            Session($param['mobile'].'mobile',null);
            Session($param['mobile'].'mobile_code',null);
            Session($param['mobile'].'expire_time',null);
            return json(self::callback(1,'修改成功!',true));

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
            $data = Db::name('user')->field('user_id,token,mobile,nickname,avatar,user_status,type,money,start_time,end_time,source,authorize_time,login_out,invitation_code,description,create_time,login_time,gender,use_version,invitation_user_id,age,user_scene,age_range_id,jig_id,jig_pwd')->where('user_id',$userInfo['user_id'])->find();
            $member = Db::name('member_card')->field('id,price')->where('id',1)->find();
            if($member){
                $data['member_card_id'] = $member['member_card_id'];
                $data['member_price'] = $member['price'];
            }
            if($member['avatar']==''){$member['avatar']='/default/user_logo.png';}
            $user_id = $userInfo['user_id'];
            $data['collect_num'] = UserLogic::userCollectNum($user_id);
            $data['follow_num'] = UserLogic::userFollowNum($user_id);
            $data['coupon_num'] = UserLogic::userCouponCount($user_id,0);
            $data['member_end_time'] = $userInfo['end_time'];
            if($userInfo['end_time'] <= time())$userInfo['type'] = 1;
            UserLogic::executeTask();
            return json(self::callback(1,'',$data));
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
                $jig_id = Db::name('user')->where(['user_id'=>$userInfo['user_id']])->value('jig_id');
                if($jig_id){
                    JiG::editUserData($jig_id,$data);
                }
            }

            $avatar_file = $this->request->file('avatar');
            if ($avatar_file) {
                //修改头像
                $info = $avatar_file->validate(['ext'=>'jpg,jpeg,png'])->move(config('config_uploads.uploads_path') .'avatar');

                if ($info){
//                    Log::info("\r\n  =====================================" . config('config_uploads.img_path') .'avatar'. DS . $info->getSaveName() ."\r\n  =====================================");
                    $avatar = config('config_uploads.img_path') .'avatar'. DS . $info->getSaveName();
//                    $avatar = DS.'uploads'.DS.$this->request->module().DS.'avatar'.DS.$info->getSaveName();
                    $imgurl = ROOT_PATH .'public'.$avatar;//原大图路径
                    $image = \think\Image::open($imgurl);
                    $image->thumb(200, 200,1)->save($imgurl);//生成缩略图、删除原图

                    $data['avatar'] = str_replace(DS,"/",$avatar);
                    $path = __FILE__;
                    $host = strstr($path,'csswx')?"wx.supersg.cn":"appwx.supersg.cn";
                    JiG::editUserInfo($userInfo['user_id'],1,"http://" . $host . $avatar);

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
            foreach($list as &$v){
                Db::name('user_msg_link')->where('id',$v['id'])->setField('is_read',1);
            }
            UserModel::updateUserScanSystemTime($userInfo['user_id']);
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

            $verify = IhuyiSMS::verifyCodeNew($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $password = password_hash($param['pay_password'], PASSWORD_DEFAULT);
            $result = Db::name('user')->where('user_id','eq',$userInfo['user_id'])->setField('pay_password',$password);

            //$result = Db::name('user')->where('mobile','eq',$param['mobile'])->setField('pay_password',$password);

            if (!$result) {
                return json(self::callback(0,'操作失败'));
            }

            //Session::clear();
            Session($param['mobile'].'mobile',null);
            Session($param['mobile'].'mobile_code',null);
            Session($param['mobile'].'expire_time',null);
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

            $total = Db::name('store_follow')->alias('sf')
                ->join('store s','s.id = sf.store_id')
                ->where('sf.user_id',$userInfo['user_id'])
                ->where('sf.store_id','GT',0) //先只查询关注的店铺等后期APP加入个人主页再开放
                ->where('s.store_status',1)
                ->count();

            $list = Db::view('store_follow','store_id')
                ->view('store','store_name,cover,type,store_status','store.id = store_follow.store_id','left')
                ->where('store_follow.user_id',$userInfo['user_id'])
                ->where('store_follow.store_id','GT',0) //先只查询关注的店铺等后期APP加入个人主页再开放
                ->where('store.store_status',1)
                ->page($page,$size)
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['fans_number'] = Db::name('store_follow')->where('store_id',$v['store_id'])->count();
                if($v['store_id']==1516){
                    $list[$k]['fans_number'] =147+$list[$k]['fans_number'];
                }
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
     * app => 微信授权登录
     * @param LoginValidate $LoginValidate
     * @param UserModel $UserModel
     * @return Json
     */
    public function thirdWxLogin(LoginValidate $LoginValidate, UserModel $UserModel){
        try{
        #验证请求方式
        if(!request()->isPost())return json(self::callback(0,'非法请求'),400);
        #验证参数
        $res = $LoginValidate->scene('wx')->check(input());
        if(!$res)return json(self::callback(0,$LoginValidate->getError()),400);
        #逻辑
        $code = input('code','','addslashes,strip_tags,trim');
        if(!$code){return json(self::callback(0,'参数错误'),400);}
        ##获取access_token等信息
        $info1 = LoginFunc::getWxAccessToken($code);
        if(isset($info1['errcode']))return json(self::callback(0,$info1['errmsg']));
        ##获取用户信息
        $open_id = $info1['openid'];
        $access_token = $info1['access_token'];
        $info = LoginFunc::thirdGetWxUserInfo($access_token,$open_id);
        if(!$info)return json(self::callback(0,'登录失败'));
        if(isset($info['errcode']) || !isset($info['unionid']))return json(self::callback(0,$info['errmsg']));
        ##查看用户是否已经注册
        $unionid = $info['unionid'];
        $userinfo = $UserModel->getUserInfoMobile($unionid);
            Db::startTrans();
        //默认没有绑定手机号
        $bind_mobile=0;
        if($userinfo){ ##非第一次使用微信第三方登录
            #重新更新token
            $result = LoginFunc::updateToken($userinfo['user_id']);
            if($result === false){throw new Exception('操作失败!');}
            $token = Db::name('user')->where('user_id',$userinfo['user_id'])->value('token');
            if($userinfo['mobile'] && $userinfo['mobile']>0){$bind_mobile=1;}

        }else{ ##第一次使用微信第三方登录
            $token = UserFunc::setToken();
            #注册临时账号
            $data = [
                'nickname' => filterEmoji($info['nickname']),
                'avatar' => $info['headimgurl']?:"/default/user_logo.png",
                'openid' => $info['openid'],
                'wx_unionid' => $info['unionid'],
                'token' => $token['token'],
                'authorize_time' => time(),
                'token_expire_time' => $token['token_expire_time']
            ];
            $userinfo['user_id'] = $UserModel->addUser($data);
            if($userinfo['user_id'] === false){throw new Exception('登录失败!');}
            $token = Db::name('user')->where('user_id',$userinfo['user_id'])->value('token');
            //生成邀请码
            Db::name('user')->where(['user_id'=>$userinfo['user_id']])->setField('invitation_code', createCode($userinfo['user_id']));

                //赠送优惠券
                ##新人券
                $coupon_rule = Db::name('coupon_rule')->where(['coupon_type'=>1,'is_open'=>1])->where(['client_type'=>['IN',[0,2]]])->select();

                $data = [];
                foreach($coupon_rule as $v){
                    for ($i = 0; $i < $v['zengsong_number']; $i++) {
                        $data[] = [
                            'coupon_id' => $v['id'],
                            'user_id' => $userinfo['user_id'],
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
               $rst= Db::name('coupon')->insertAll($data);
              if($rst === false){throw new Exception('派发优惠券失败!');}
                ##更新优惠券剩余数量
                foreach($coupon_rule as $v)Logic::updateNoNumCouponNum($v['id'], $v['zengsong_number']);


        }
            Db::commit();
            return json(self::callback(1,'授权登录成功!',['user_id'=>$userinfo['user_id'],'token'=>$token,'bind_mobile'=>$bind_mobile]));

            } catch (\Exception $e){
        Db::rollback();
        return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * app => 微信绑定手机号登录
     * @param LoginValidate $LoginValidate
     * @param UserModel $UserModel
     * @return Json
     */
    public function thirdWxLoginBindMobile(UserValidate $validate, UserModel $UserModel){
        try{
        #验证请求参数
        $param = $this->request->post();
        if (!$param) {return json(self::callback(0,'参数错误'),400);}
        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }
        $param['user_id'] = input('post.user_id','','addslashes,strip_tags,trim');//用户id
        $param['mobile'] = input('post.mobile','','addslashes,strip_tags,trim');//手机号
        $param['code'] =input('post.code','','addslashes,strip_tags,trim');//验证码
        $invitation_code = input('invitation_code');  //邀请码
        if(!$param['mobile'] || !$param['code']) {return json(self::callBack(0,'参数错误'),400);}
        if (!$validate->check($param,[],'verifyCodeLogin')){return json(self::callback(0,$validate->getError()),400);}
        $verify = IhuyiSMS::verifyCodeNew($param['mobile'],$param['code']);
        if (!$verify) {return json(self::callBack(0,'验证码不存在或已失效'),400);}
        ##检测手机号是否已注册
        $userinfomation = UserLogic::findUser($param['mobile']);
        ##生成token
        $tokenInfo = UserFunc::setToken();
        $data = [
            'token' => $tokenInfo['token'],
            'token_expire_time' => $tokenInfo['token_expire_time']
        ];
        Db::startTrans();
            //是否有邀请人
            if($invitation_code){
                $invitation = Logic::getInvitation($invitation_code);
                if($invitation){
                    $data['invitation_code'] = $invitation_code;
                    $data['invitation_user_id'] = decode($invitation_code);
                    $invitation_user_id=$data['invitation_user_id'];
                }

                ##邀请券
                if(isset($invitation_user_id) && $invitation_user_id>0) {
                    $invitation_user_info = Db::name('user')->where(['user_id' => $invitation_user_id])->value('user_status');
                    if (!empty($invitation_user_info) && $invitation_user_info > 0) {
                        $coupon_rule2= Logic::getInvitationCoupons(1);
                    }
                }
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

        if($userinfomation){

            //已注册 绑定到原来手机号
            $data['login_time'] = time();
            $data['wx_unionid']=$userInfo['wx_unionid'];
            if($userinfomation['user_id']!=$userInfo['user_id']){
                Db::name('user')->where('user_id', $userInfo['user_id'])->setField('user_status',-2);
            }
            $result = Db::name('user')->where('user_id', $userinfomation['user_id'])->update($data);
            $user_id=$userinfomation['user_id'];
        }else {
            //未注册 绑定手机号
            $data['login_time'] = time();
            $data['mobile'] = $param['mobile'];
            $result = Db::name('user')->where('user_id', $userInfo['user_id'])->update($data);
            $user_id=$userInfo['user_id'];
        }
            if($result===false){throw new Exception('更新用户信息失败!');}
        Db::commit();
        Session($param['mobile'].'mobile',null);
        Session($param['mobile'].'mobile_code',null);
        Session($param['mobile'].'expire_time',null);
        return json(self::callback(1,'sucess!',['user_id'=>$user_id,'token'=>$tokenInfo['token']]));
        } catch (\Exception $e){
        Db::rollback();
        return json(self::callback(0,$e->getMessage()));
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
        if($store_id)if($coupon_info['type'] == 1)return \json(self::callback(0,'当前商户未发行该优惠券'));
        if(!$store_id && ($coupon_info['type'] == 2 || $coupon_info['type'] == 1) && $coupon_info['coupon_type'] != 10)return \json(self::callback(0,'优惠券领取失败'));
        if($coupon_info['surplus_number'] <= 0 && $coupon_info['coupon_type'] !=1 )return \json(self::callback(0,'优惠券已领取完'));
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
            if($coupon_info['coupon_type'] == 1){  //新人优惠券
                $data['expiration_time'] = time() + $coupon_info['days'] * 24 * 60 * 60;
            }
            if($coupon_info['coupon_type'] == 10){  //线下优惠券
//                $data['validate_code'] = createCouponValidateCode();
                if($coupon_info['days']>0){
                    $data['expiration_time'] = time() + $coupon_info['days'] * 24 * 60 * 60;
                }
            }
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
    /**
     * 领券中心
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function couponCenter(UserValidate $UserValidate){
        try{
        ##验证token
//        $userInfo = UserFunc::checkToken();
//        if ($userInfo instanceof Json){
//            return $userInfo;
//        }
            $user_id = input('post.user_id',0,'intval');
            $token = input('post.token');
            $where = [
                'cr.is_open' =>1,
                'cr.use_type' => ['in','0,1,3'],
//                'cr.fb_user' => ['eq','平台'],
//                'cr.end_time' => ['EGT',time()],
                'cr.coupon_type' => ['IN',[8,10]],
                'cr.client_type' => ['in','0,2']
            ];
            $where1['cr.surplus_number'] = ['GT',0];
            $where2['cr.surplus_number'] = ['eq',0];
            $where3['cr.days'] = ['GT',0];
            $where4['cr.end_time'] = ['EGT',time()];
            //优惠券
            $list = Db::name('coupon_rule')->alias('cr')
                ->join('store s','cr.store_id = s.id','LEFT')
                ->where(function($query) use ($where, $where1, $where3){
                    $query->where($where)->where($where1)->where($where3);
                })
                ->whereOr(function($query) use($where, $where1, $where4){
                    $query->where($where)->where($where1)->where($where4);
                })
                ->field('cr.id,cr.coupon_money,cr.satisfy_money,cr.type,cr.coupon_type,cr.kind,cr.fb_user,cr.is_solo,cr.zengsong_number,s.cover,s.store_name,cr.is_superposition,cr.product_ids,cr.store_id,cr.coupon_name,cr.end_time,cr.is_show_coupon_center')
                ->select();
            //如果领取过
            if($user_id){
                $list2 = Db::name('coupon_rule')->alias('cr')
                    ->join('coupon c','cr.id = c.coupon_id','LEFT')
                    ->join('store s','cr.store_id = s.id','LEFT')
                    ->where(function($query) use ($where, $where2, $where3, $user_id){
                        $query->where($where)->where($where2)->where($where3)->where('user_id','eq',$user_id);
                    })
                    ->whereOr(function($query) use ($where, $where2, $where4, $user_id){
                        $query->where($where)->where($where2)->where($where4)->where('user_id','eq',$user_id);
                    })

                    ->field('cr.id,cr.coupon_money,cr.satisfy_money,cr.type,cr.coupon_type,cr.kind,cr.fb_user,cr.is_solo,cr.zengsong_number,s.cover,s.store_name,cr.is_superposition,cr.product_ids,cr.store_id,cr.coupon_name,cr.end_time,cr.is_show_coupon_center')
                    ->select();
                $list=array_merge($list,$list2);
            }
            foreach ($list as $k=>&$v){
                if($v['coupon_type']==11){$v['coupon_type']=10;} //抽奖线下优惠券转为线下优惠券

                if($v['coupon_type'] == 10 && !$v['is_show_coupon_center']){
                    continue;
                }
                $v['is_get']=1;
                $v['coupon_id']=0;//默认为0
               // $v['go_use']=0;//线下优惠券可领取为0 去使用为1
                if($user_id){
                    $num = Db::name('coupon')
                        ->where('user_id','eq',$user_id)
                        ->where('coupon_id','eq',$v['id'])
                        ->field('id')
                        ->count();
                    if($v['zengsong_number']<=$num){
                        //不可领取
                        $v['is_get']=-1;
                    }
                    if($v['coupon_type']==10){
                        $num2 = Db::name('coupon')
                            ->where('user_id','EQ',$user_id)
                            ->where('coupon_id','EQ',$v['id'])
                            ->field('id,coupon_id')
                            ->find();
                        if($num2){
                            //已领取去使用
                           // $v['go_use']=1;
                            $v['coupon_id']=$num2['id'];
                        }
                    }
                    $use_num = Db::name('coupon')->where(['user_id'=>$user_id,'coupon_id'=>$v['id'],'status'=>2])->count('id');
                    if($use_num >= $v['zengsong_number']){unset($list[$k]);continue;};
                }
                if($v['type']==1 ){
                //平台券
                    $v['desc']='全平台可用';
                $data['pt_coupon'][]=$v;
                }elseif($v['type']==2){
                //店铺券
                    $v['desc']=$v['store_name'].'可用';
                    $data['store_coupon'][]=$v;
                }elseif($v['type']==3){
                //商品券
                    $v['desc']='';
                    if($v['is_solo']==0){
//                        unset($v['coupon_name']);
//                        $v['coupon_name']='部分商品可用';
                    }
                    $data['product_coupon'][]=$v;
                }
            }
            return \json(self::callback(1,'查询成功!',$data));
        }catch(Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }

    }
    /**
     * 领券中心领取优惠券
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function GetCoupon(UserValidate $UserValidate){
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
//        $store_id = input('post.store_id',0,'intval');

        ##获取优惠券信息
        $coupon_info = Logic::couponInfo($coupon_id);
        if(!$coupon_info)return \json(self::callback(0,'优惠券信息不存在'));
        if($coupon_info['surplus_number'] <= 0)return \json(self::callback(0,'优惠券已领取完'));
        if($coupon_info['is_open'] == -1)return \json(self::callback(0,'优惠券已下架'));

//        if($store_id)if($coupon_info['store_id'] != $store_id)return \json(self::callback(0,'当前商户未发行该优惠券'));
//        if($store_id)if($coupon_info['type'] != 2)return \json(self::callback(0,'当前商户未发行该优惠券'));
//        if(!$store_id && $coupon_info['type'] == 2)return \json(self::callback(0,'优惠券领取失败'));

        ##检查用户是否已经领券
        $num = UserLogic::checkUserCoupon($user_id,$coupon_id);
        if($num>=$coupon_info['zengsong_number'])return \json(self::callback(0,'您已领取过该优惠券了'));

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
                'store_id' => $coupon_info['store_id'],
                'coupon_type' => $coupon_info['coupon_type'],
            ];

            if($coupon_info['coupon_type'] == 1){  //新人优惠券
                $data['expiration_time'] = time() + $coupon_info['days'] * 24 * 60 * 60;
            }

            if($coupon_info['coupon_type'] == 10){  //线下优惠券
//                $data['validate_code'] = createCouponValidateCode();
                if($coupon_info['days']>0){
                    $data['expiration_time'] = time() + $coupon_info['days'] * 24 * 60 * 60;
                }
            }

            ##添加领券记录
            $res = UserLogic::userGetCoupon($data);
            if($res === false)throw new Exception('领取失败');
            $data=$res;
            ##修改优惠券剩余数和领取数
            $res1 = Logic::updateCouponNum($coupon_id);
            if($res1 === false)throw new Exception('领取失败');

            Db::commit();
            return \json(self::callback(1,'领取成功',$data));
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
            $userInfo = \app\user_v7\common\User::checkToken();
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
                        }
                        return \json(self::callback(0,'收藏失败',$data));
                    }else{
                        //写入成功
                        $data2=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                        if($data2==0){
                            $data2=-1;
                        }
                        Db::name('store')->where('id',$store_id)->setInc('collect_number');
                        $data=Db::name('store')->where('id',$store_id)->value('collect_number');
                        return \json(self::callback(1,'收藏成功',$data));
                    }
                }else{
                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;
                    }
                    $data=Db::name('store')->where('id',$store_id)->value('collect_number');
                    return \json(self::callback(1,'已收藏',$data));
                }
            }else if($status=='false'){
                $de=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->delete();
                if($de==0){
                    //删除失败
                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;
                    }
                    return \json(self::callback(0,'取消收藏失败',$data));
                }else{
                    //删除成功

                    $data=Db::name('store_collection')->where('store_id',$store_id)->where('user_id',$userInfo['user_id'])->count();
                    if($data==0){
                        $data=-1;
                    }
                    Db::name('store')->where('id',$store_id)->setDec('collect_number');
                    $data=Db::name('store')->where('id',$store_id)->value('collect_number');
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
    public function orderCouponListMore(UserValidate $UserValidate){
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
            $coupon_type = input('post.type',1,'intval');  //1.平台券;2.店铺券;3.商品券
            $coupon_id = $coupon_type == 2?input('post.coupon_id',0,'intval'):input('post.coupon_id','','addslashes,strip_tags,trim'); //已选中卡券id
            if($coupon_type == 1 && $coupon_id)$coupon_id = explode(',',$coupon_id);
            $store_id = input('post.store_id',0,'intval');
            $page = input('post.page',0,'intval');
            $size = input('post.size',10,'intval');
            $product_ids = input('post.product_id','','addslashes,strip_tags,trim');
            $product_ids = explode(',',$product_ids);
            if($coupon_type == 3 && !$product_ids)throw new Exception('参数错误');
            if($coupon_type == 2 && !$store_id)throw new Exception('参数错误');

            $data = UserLogic::userOrderCouponLists0906($coupon_type, $coupon_id,$user_id, $store_id, $product_ids, $page, $size);

            return \json(self::callback(1,'',$data));

        }catch(Exception $e){
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
     * 获取买单叠加可用优惠券列表
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function maidanCouponList(UserValidate $UserValidate){
        try{
            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $type = input('post.type',1,'intval');  //1.平台券;2.店铺券
            $money = input('post.money',0);
            $store_id = input('post.store_id',0,'intval');
            $page = input('post.page',0,'intval');
            $size = input('post.size',10,'intval');
            if(!$type)throw new Exception('参数错误,缺少优惠券类型');
            if($type==2 && !$store_id)throw new Exception('参数错误,缺少店铺id');
            $where = [
                'c.user_id' => $userInfo['user_id'],
                'cr.type' => $type,
                'c.status' => 1,
                'cr.can_stacked' => 1,
                'c.expiration_time' => ['GT',time()],
                'cr.coupon_type' => ['NOT IN', [10, 11]]
            ];
            if($type == 2)$where['cr.store_id'] = $store_id;
            $total = Db::name('coupon')->alias('c')
                ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                ->where($where)
                ->where(['cr.client_type'=>['IN',[0,2]],'cr.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
                ->count('c.id');

            $list = Db::name('coupon')->alias('c')
                ->join('coupon_rule cr','cr.id = c.coupon_id','LEFT')
                ->join('store s','cr.store_id = s.id','LEFT')
                ->join('product p','p.id = cr.product_id','LEFT')
                ->where($where)
                ->where(['cr.client_type'=>['IN',[0,2]],'cr.use_type'=>['IN',[0,1,3]]])  //卡券的适用平台限制
                ->field('c.id,cr.coupon_name,cr.satisfy_money,cr.can_stacked,cr.coupon_money,c.expiration_time,cr.coupon_type,cr.kind,cr.type,s.store_name,p.product_name,cr.is_superposition,cr.store_id,cr.coupon_name,c.expiration_time as end_time,c.expiration_time as user_end_time,cr.days,c.create_time as user_start_time,cr.start_time,cr.rule_model_id,c.status')
                ->limit($page*$size,$size)
                ->order('c.expiration_time','asc')
                ->order('cr.coupon_money','desc')
                ->select();
            foreach ($list as $k=>$v){
                if($v['can_stacked']==-1){
                    $can_use = 2;
                }else{
                    $can_use = 1;
                }
                if(($v['satisfy_money']==0 && $v['coupon_money']>=$money) || ($v['satisfy_money']>0 && $v['satisfy_money']>$money)){
                    $can_use = 2;
                }
                $list[$k]['can_use'] = $can_use;
                $rule_models = explode(',',$v['rule_model_id']);
                $list[$k]['rule'] = Logic::getCouponRules($rule_models);
            }
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$list;
            return \json(self::callback(1,'返回成功',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 买单列表
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function maidanList(UserValidate $UserValidate){
        try{
            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $page = input('post.page',0,'intval');
            $page = max(0,$page);
            $size = input('post.size',15,'intval');
            $total = Db::name('maidan_order')->alias('m')
                ->join('store s','m.store_id = s.id','LEFT')
                ->where('m.user_id',$userInfo['user_id'])
                ->where('m.pay_time','gt',0)
                ->field('m.id')
                ->count('m.id');
            $list = Db::name('maidan_order')->alias('m')
                ->join('store s','m.store_id = s.id','LEFT')
                ->where('m.user_id',$userInfo['user_id'])
                ->where('m.pay_time','gt',0)
                ->field('m.id,m.order_sn,s.store_name,s.cover,m.store_id,m.price_maidan,m.price_yj,m.discount,m.coupon_money,m.pay_time,m.member_order_id,m.discount_platform')
                ->limit($page*$size,$size)
                ->order('m.id','desc')
                ->select();
            foreach ($list as $k=>&$v){
                if($v['member_order_id']>0){
                    $pay_money=  Db::name('member_order')->where('order_id',$v['member_order_id'])->value('pay_money');
                    $v['price_maidan']=$v['price_maidan']+$pay_money;
                }
            }
            $data['page']=$page;
            $data['total']=$total;
            $data['max_page'] = ceil($total/$size);
            $data['data']=$list;
            return \json(self::callback(1,'返回成功',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 买单详情
     * @param UserValidate $UserValidate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function maidanDetail(UserValidate $UserValidate){
        try{
            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #逻辑
            $id = input('post.id',0,'intval');
            if(!$id)throw new Exception('参数错误,缺少买单id');
            $data = Db::name('maidan_order')->alias('m')
                ->join('store s','m.store_id = s.id','LEFT')
                ->join('member_order mo','m.member_order_id = mo.order_id','LEFT')
                ->join('coupon c','m.coupon_id = c.id','LEFT')
                ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
                ->where('m.user_id',$userInfo['user_id'])
                ->where('m.pay_time','gt',0)
                ->where('m.id',$id)
                ->field('m.id,m.order_sn,s.store_name,s.cover,m.store_id,m.price_maidan,m.price_yj,m.discount,m.coupon_money,m.pay_time,cr.type,mo.pay_money,m.discount_platform')
                ->order('m.id','desc')
                ->find();

            if($data['pay_money']>0){
                $data['price_maidan']=$data['price_maidan']+$data['pay_money'];
            }
               if($data['type']==1){
                   $data['coupon_name']='平台优惠券';
               }elseif($data['type']==2){
                   $data['coupon_name']='店铺优惠券';
               }elseif($data['type']==3){
                   $data['coupon_name']='商品优惠券';
               }
            return \json(self::callback(1,'返回成功',$data));
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

    /**
     * 返回用户分享信息
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function userShareInfo(){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $code_img = "http://appwx.supersg.cn/app/download.png";
            $info = Logic::getShareInfo();
            $info['code_img'] = $code_img;
            $info['avatar'] = $userInfo['avatar'];
            $info['nickname'] = $userInfo['nickname'];
            $info['invitation_code'] = $userInfo['invitation_code'];

            #返回
            return \json(self::callback(1,'',$info));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户推广统计
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function userInviteCount(){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            $user_id = $userInfo['user_id'];
            $day_count = UserLogic::getUserInviteCount($user_id,1);
            $week_count = UserLogic::getUserInviteCount($user_id,2);
            $total_count = UserLogic::getUserInviteCount($user_id,0);

            #返回
            return \json(self::callback(1,'',compact('day_count','week_count','total_count')));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户首页弹窗新优惠券列表
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function showCouponToast(){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            #逻辑
            ##获取上次登录后的新增优惠券
            $list = UserLogic::getUserCouponToastList($userInfo['user_id'],$userInfo['login_time']);

            ##更新登录时间
            $res = UserLogic::updateUserLoginTime($userInfo['user_id']);
            if($res === false)throw new Exception('更新用户登录时间失败');

            #返回
            return \json(self::callback(1,'',$list));

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 核销码
     * @param UserValidate $user
     * @param Coupon $coupon
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function couponValidateCode(UserValidate $user, Coupon $coupon){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            ##验证参数
            $res = $user->scene('coupon_validate_code')->check(input());
            if(!$res)throw new Exception($user->getError());

            #逻辑
            $data = $coupon->getValidateCode();

            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 绑定手机获取验证码
     * @param UserValidate $user
     * @param UserModel $userModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function bindMobileGetCode(UserValidate $user, UserModel $userModel){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            ##验证参数
            $rule = [
                'mobile'  => 'require|length:11|checkPhone'
            ];
            $res = $user->scene('user_bind_mobile_get_code')->rule($rule)->check(input());
            if(!$res)throw new Exception($user->getError());
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $userInfo = $userModel->getUserInfoByMobile($mobile);
            if($userInfo && $userInfo['mobile'])return \json(self::callback(0,'当前手机号已注册,请直接登录'));
            #逻辑
            $res = IhuyiSMS::getCode($mobile);
            if ($res !== true) throw new Exception($res);

            return json(self::callBack(1,''));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 绑定手机号
     * @param UserValidate $user
     * @param UserModel $userModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function bindMobileVerifyCode(UserValidate $user, UserModel $userModel){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            ##验证参数
            $rule = [
                'mobile'  => 'require|length:11|checkPhone',
                'code' => 'require|length:4'
            ];
            $res = $user->scene('user_bind_mobile')->rule($rule)->check(input());
            if(!$res)throw new Exception($user->getError());
            ##验证验证码
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $code = input('post.code','','addslashes,strip_tags,trim');
            $check = IhuyiSMS::verifyCode($mobile, $code);
            if(!$check)throw new Exception('验证码错误');
            #绑定手机号
            $user_id = input('post.user_id',0,'intval');
            $res = $userModel->bindMobile($user_id, $mobile);
            if($res === false)throw new Exception('绑定手机号失败');
            #返回
            return json(self::callback(1,'绑定成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    public function test(){
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();die;
//        $res = Msg::addActivityWillStartSysMsg(26);
//        print_r($res);

//        $user_ids = Db::name('user')->where(['mobile'=>['IN',[13548273597,13350297411,18782832451,13548293835,18780341146,15882402377,13438134663,15928573486,15320262975,15680162956,13158703526,18581823235,15282752812,15881444431,18780823738,15808188819,18123113196,15281137320,18781849247,18380155218,13438111521,17302895692,15692811303,15883883073,15760373972,15884513916,15202882716,18084018860,15520202066,18583991127,18588718679,18255927988,13880447107,17855842584,18148086739,18782478333,18381359777,13551883707,13980532429,18780329927,15528398135,18784174611,18829581392,18384131161,17608325560,18215503301,15108129606,15908202612,17765364454,19983550476,18428315167,13568084667]]])->column('user_id');
//        $user_ids = Db::name('product_order')->where(['order_status'=>['EGT', 3],'user_id'=>['NOT IN', $user_ids]])->group('user_id')->column('user_id');
//
//        print_r($user_ids);
//        echo count($user_ids);

//        $str = [13548273597,13350297411,18782832451,13548293835,18780341146,15882402377,13438134663,15928573486,15320262975,15680162956,13158703526,18581823235,15282752812,15881444431,18780823738,15808188819,18123113196,15281137320,18781849247,18380155218,13438111521,17302895692,15692811303,15883883073,15760373972,15884513916,15202882716,18084018860,15520202066,18583991127,18588718679,18255927988,13880447107,17855842584,18148086739,18782478333,18381359777,13551883707,13980532429,18780329927,15528398135,18784174611,18829581392,18384131161,17608325560,18215503301,15108129606,15908202612,17765364454,19983550476,18428315167,13568084667,18381687787,15884409532,15281046867,18882025484,15182699054,18113383645,18180889079,13808131261,15908202612,15108129606,18780329927,13980532429,18217602172,17765364454,15528398135,19983550476,13568084667,17608325560,15775107616,18428315167,18215503301,18381359777,13102309038,15183820763,18782478333,13551883707,18848216640,18255927988,17855842584,13880447107,18148086739,15520202066,15884513916,18084018860,15202882716,13438111521,13628116261,13982245732,15692811303,17323117562,17726497386,18383486327,18782967728,15756321683,15983587793,18244410626,18382168294,17628285584,13689038820,13990120029,13730639424,15680861906,15283911966,18200376227,18382408715,17793732861,18095562062,13118221127,18512854340,18000521884,17634636854,15008205645,13056662886,13778172769,18380346261,13730820586,17738899146,17748765260,13540655418,18780057608,13547822750,15682375957,18380346261,15989332232,18589030129,15282223476,15982145286,18582498976,18782978101,17302895692,17608227397,17628282442,19950176862,18382168294,18080438302,15908157937,17780065634,18884050176,15756321683,17780060834];
//
//        $user_id1 = Db::name('product_order')->where(['order_status'=>['EGT', 3]])->group('user_id')->column('user_id');
//
//        $user_id = Db::name('user')->where(['user_id'=>['NOT IN', $user_id1], 'mobile'=>['NOT IN', $str]])->column('user_id');
//
//        print_r($user_id);
//
//        echo count($user_id);

//        $redis = new \Redis();
//        $redis->connect('47.110.92.34', 6379);
//        $redis->auth('one_4259');
//        $arr = ['user_id'=>time(),'token'=>rand(0,100000000)];
////        $redis->rPush('css_draw_1',json_encode($arr));
//        $len = $redis->lLen('css_draw_1');
//        echo $len;
//        $data = $redis->lPop('css_draw_1');
////        $data = $redis->lrange('css_draw_1',0,-1);
//        print_r($data);
//
//        $data = $redis->get('css_draw_1');
//        print_r($data);
//        $redis ->set( "test" , "Hello World");
//        echo $redis ->get( "test");
//        $res = JiG::sendMsgToStaff("s1893",'order_wait_handle_store',[]);
//        $res = JiG::sendMsgToUser('u15250','dynamic_add',['dynamic_id'=>55]);
//        var_dump($res);


//        JiG::editServiceData('store_62_formal',['nickname'=>'超神宿客服', 'avatar'=>'http://www.supersg.cn/themes/simpleboot_1028/public/new_assets/image/logo.png']);

//        $list = DrawLotteryUserNum::addUserDrawLotteryNum(12811,20);die;



//        $data = DrawLottery::addDrawRedisData(35);
//        print_r($data);die;
//
//        $data = range(0,100,1);
//        print_r($data);die;

//        $fp = fopen(__DIR__."/task_lock.txt", "w+");
//        if(!flock($fp,LOCK_EX | LOCK_NB)){
//            return \json(self::callback(0,'系统繁忙，请稍后再试'));
//        }
//        echo 1111;
//        sleep(10);
//        flock($fp,LOCK_UN);//释放锁
//        fclose($fp);
//        return json(self::callback(1,'哈哈哈'));
//        die;

//        $data = DrawLotteryUserNum::addUserDrawLotteryNum(12811,1);die;
        UserLogic::executeDrawTask();die;
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();
//        $data = $redis->lRange('test1',0,20);
//        $data = $redis->hSet('test_hash','name','kiki');
        $data = $redis->hGet('test_hash','name2');
        print_r($data);
        $redis->rPush('test_list',2321321312,3213213213,21321312,21321312,23123213,213213123,23213);
        die;
        $res = $redis->keys("test_css_main_draw1");
        print_r($res);die;

//        $data = $redis->get(getMainDrawKey());
//        $data =json_decode($data,true);
//        $imgs = createFakeRewardRecord(10,$data['reward'],$data['start_time']);
//        print_r($imgs);die;
//        print_r($data['reward'][32]);die;

//        $res = $redis->lPush('test_list',time());
//        $res = $redis->lRange('test_list',0,0);  //下标从0开始
//        $res = $redis->lSet('test_list',3,0);
//        var_dump($res);die;



//        $record_data = [
//            [
//                'avatar' => '/uploads/user/avatar/20191128/b15073d98fdc1f0d210f43b019fde0dc.png',
//                'user_name' => '159****5986',
//                'draw_time' => 1575872252,
//                'satisfy_money' => "10",
//                'coupon_money' => "4"
//            ],
//            [
//                'avatar' => '/uploads/user/avatar/20191128/b15073d98fdc1f0d210f43b019fde0dc.png',
//                'user_name' => '159****5986',
//                'draw_time' => 1575872252,
//                'satisfy_money' => "10",
//                'coupon_money' => "4"
//            ],
//            [
//                'avatar' => '/uploads/user/avatar/20191128/b15073d98fdc1f0d210f43b019fde0dc.png',
//                'user_name' => '159****5986',
//                'draw_time' => 1575872252,
//                'satisfy_money' => "10",
//                'coupon_money' => "4"
//            ],
//            [
//                'avatar' => '/uploads/user/avatar/20191128/b15073d98fdc1f0d210f43b019fde0dc.png',
//                'user_name' => '159****5986',
//                'draw_time' => 1575872252,
//                'satisfy_money' => "10",
//                'coupon_money' => "4"
//            ],
//            [
//                'avatar' => '/uploads/user/avatar/20191128/b15073d98fdc1f0d210f43b019fde0dc.png',
//                'user_name' => '159****5986',
//                'draw_time' => 1575872252,
//                'satisfy_money' => "10",
//                'coupon_money' => "4"
//            ],
//        ];
//        $key = getDrawWithFakeRecordKey(1);
//        foreach($record_data as $v){
//            $redis->rPush($key, json_encode($v));
//        }
//        die;

        $rule = "dasdasdasddsad|dsadasdsadsadsad|dsadsadsadsadsadsad";
        $rule = explode('|',$rule);
        $draw_info = [
            'id' => 1,
            'start_time' => time(),
            'end_time' => 1576635263,
            'status' => 1,
            'type' => 1, // 1.随机模式;2.概率模式
            'bg_img' => '',
            'rule' => $rule,
            'icon' => '',
            'per_user_max_number' => 5,
            'reward_num' => 100,  //奖品数
            'draw_num' => 1000, //可抽奖次数
            'reward' => [
                12 => [
                    'id' => 12,
                    'type' => 1,
                    'gift_desc' => '优惠券',
                    'satisfy_money' => '0',
                    'coupon_money' => '10',
                    'icon' => ''
                ],
                90 => [
                    'id' => 90,
                    'type' => 2,
                    'gift_desc' => '谢谢参与',
                    'satisfy_money' => '0',
                    'coupon_money' => '0',
                    'icon' => ''
                ],
                32 => [
                    'id' => 32,
                    'type' => 2,
                    'gift_desc' => '谢谢参与咯',
                    'satisfy_money' => '0',
                    'coupon_money' => '0',
                    'icon' => ''
                ],
                33 => [
                    'id' => 33,
                    'type' => 2,
                    'gift_desc' => '谢谢参与咯',
                    'satisfy_money' => '0',
                    'coupon_money' => '0',
                    'icon' => ''
                ],
                1 => [
                    'id' => 1,
                    'type' => 1,
                    'gift_desc' => '优惠券',
                    'satisfy_money' => '0',
                    'coupon_money' => '12',
                    'icon' => ''
                ],
                34 => [
                    'id' => 34,
                    'type' => 2,
                    'gift_desc' => '谢谢参与咯',
                    'satisfy_money' => '0',
                    'coupon_money' => '0',
                    'icon' => ''
                ],
                35 => [
                    'id' => 35,
                    'type' => 2,
                    'gift_desc' => '谢谢参与咯',
                    'satisfy_money' => '0',
                    'coupon_money' => '0',
                    'icon' => ''
                ],
                36 => [
                    'id' => 36,
                    'type' => 1,
                    'gift_desc' => '优惠券',
                    'satisfy_money' => '10',
                    'coupon_money' => '3',
                    'icon' => ''
                ]
            ]
        ];
        $draw_info = json_encode($draw_info);
        $key = getDrawKey(1);
        $res = $redis->set($key,$draw_info,72 * 60 *60);

        print_r($res);die;

        ##随机模式=>有序列表
        ##概率模式=>[]

        $data = [];
        for($i = 1000; $i>=0; $i--){
            $data[] = $i;
        }
        $redis->set('test',json_encode($data));
//        $data = $redis->get('test');
        print_r($data);
        die;
        do{
            $key = 'draw_01';
            $rand = rand(10000,99999);
            $isLock = $redis->set($key, $rand, ['nx', 'ex'=>5]);
            if($isLock){
                if($redis->get($key) == $rand){
                    $redis->del($key);
                    continue;
                }
            }else{
                usleep(5000);  //休息5毫秒
            }
        }while(!$isLock);

    }


    public function addDrawUserTimes(){
        $user_id = input('post.user_id',0,'intval');
        $draw_id = input('post.draw_id',0,'intval');
        $number = input('post.number',10,'intval');
        if(!$draw_id || !$user_id)return json(self::callback(0,'参数错误'));
        $key = getUserDrawNumKey($user_id,$draw_id);
//        echo $key;die;
        $redis = new MRedis();
        $redis = $redis->getRedis();
        $number += (int)$redis->get($key);
        $data = $redis->set($key,$number);
        return \json(self::callback(1,'',$data));
    }

    public function updateDraw(){
        $draw_id = input('post.draw_id',0,'intval');
        DrawLottery::addDrawRedisData($draw_id);
    }

}