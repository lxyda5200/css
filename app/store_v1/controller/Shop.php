<?php


namespace app\store_v1\controller;


use app\common\controller\IhuyiSMS;
use app\store_v1\model\Business;
use think\captcha\Captcha;
use think\Db;
use think\Request;
use think\Session;

class Shop extends Base
{
    protected $noNeedLogin = '*';  //不需要登录

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }


    /***
     * 官网端登录
     */
    public function login(){
        try{
            $mobile = trim(input('mobile'));
            $password = trim(input('password'));
            $code = trim(input('code'));
            if (!$mobile || !$password || !$code) {
                return \json(self::callback(0,'参数错误'));
            }
            //验证码验证
            if(!$this->check_verify($code)){
                return \json(self::callback(0,'验证码错误'));
            }
            $store_info = Db::name('business')
                ->where('mobile',$mobile)
                ->whereOr('user_name','eq',$mobile)
                ->whereOr('email','eq',$mobile)
                ->find();
            if (!$store_info) {
                throw new \Exception('账号不存在');
            }
            if (!password_verify($password,$store_info['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }
            $token = Base::setToken();
            $data = [
                'id'=>$store_info['id'],//用户ID
                'is_type'=>$store_info['pid'], //是否主账号 0 是
                'main_id'=>$store_info['main_id'], //主店铺id
                'business_name'=>$store_info['business_name'],
                'token'=>$token['token'],
            ];
            $model = new Business();
            $model->allowField(true)->save(
                [
                    'token'=>$token['token'],
                    'token_expire_time'=>$token['token_expire_time'],
                    'last_login_time'=>time()
                ],['id'=>$store_info['id']]);
            //查询店铺信息
            if(!empty($store_info['store_id']) && $store_info['store_id']>0){
                $store = Db::name('store')->where('id',$store_info['store_id'])->find();
                $data['store_id']=$store['id'];//关联店铺ID
                $data['type']=$store['type'];
                $data['cover']=$store['cover'];
                $data['money']=$store['money'];
                $data['telephone']=$store['telephone'];
                $data['brand_name']=$store['brand_name'];
                $data['description']=$store['description'];
                $data['is_ziqu']=$store['is_ziqu'];
                $data['address']=$store['address'];
                $data['store_type']=$store['store_type'];
                $data['store_name']=$store['store_name'];
                if ($store['sh_status'] == 0 && $store['sh_type'] == 1) {
                    return \json(self::callback(102,'正在审核中,请耐心等待',$data));
                }
                if ($store['sh_status'] != 1 && $store['sh_type'] == 1 ) {
                    return \json(self::callback(103,'审核未通过',$data));
                }
                if ($store['store_status'] != 1) {
                    return \json(self::callback(104,'店铺已被下架',$data));
                }
                return \json(self::callback(1,'登录成功',$data));
            }else{
                return \json(self::callback(101,'账号未完成店铺申请',$data));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    // 检测输入的验证码是否正确，$code为用户输入的验证码字符串，$id多个验证码标识
    protected function check_verify($code, $id = ''){
        $captcha = new Captcha();
        return $captcha->check($code, $id);
    }

    /**
     * 注册
     */
    public function register(){
        try{
            $param = $this->request->post();
            if (!$param) {
                return json(self::callback(0,'参数错误'));
            }
            $validate = new \app\store_v1\validate\User();
            if (!$validate->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()));
            }
             $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
             if (!$verify) {
                 throw new \Exception('验证码不存在或已失效');
             }

            //邮箱验证码验证
            if(!$this->chen_email(trim($param['email']),trim($param['emailcode']))){
                return \json(self::callback(0,'邮箱验证码错误或已过期!',false));
            }

            //判断手机号是否注册
            if(isset($param['mobile'])){
                $data=Db::table('business')->field('id,mobile')->where('mobile',trim($param['mobile']))->find();
                if($data){
                    return \json(self::callback(0,'不能重复注册!',false));
                }
            }
            $param['business_name'] = trim($param['mobile']);
            $param['password'] = password_hash(trim($param['password']), PASSWORD_DEFAULT);
            $StoreUser = new Business();
            $result = $StoreUser->allowField(true)->save($param);
            if (!$result){throw new \Exception('注册失败');}
            $store_id = $StoreUser->id;
            $tokenInfo = Base::setToken();
            $StoreUser->allowField(true)
                ->save([
                    'token' => $tokenInfo['token'],
                    'token_expire_time' => $tokenInfo['token_expire_time'],
                    'last_login_time' => time()
                ], ['id'=>$store_id]);
            Session::clear();
            unset($StoreUser->password);
            unset($StoreUser->code);
            unset($StoreUser->emailcode);
            unset($StoreUser->email);
            return \json(self::callback(1,'',$StoreUser));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }



    /**
     * 邮件/短信验证码验证
     * @param $email
     * @param $code
     * @return bool
     */
    public function chen_code(){
//        return json(self::callback(1,'验证成功'));
        $username = trim(input('mobile'));
        $code = trim(input('code'));
        $type = trim(input('type')); // 验证类型 1短信 2邮箱
        if(!$code || !in_array($type,[1,2])){
            return json(self::callback(0,'请求信息错误'));
        }
        if($type ==1){
            $verify = IhuyiSMS::verifyCode($username,$code);
            if (!$verify) {
                return json(self::callback(0,'短信验证码错误或已失效'));
            }else{
                return json(self::callback(1,'短信验证成功'));
            }
        }else{
            if(!$this->chen_email($username,$code)){
                return json(self::callback(0,'邮箱验证码错误或已失效'));
            }else{
                return json(self::callback(1,'邮箱验证成功'));
            }
        }
    }
}