<?php

namespace app\user_v4\controller;

use app\common\controller\Base;
use app\common\controller\IhuyiSMS;
use app\user_v4\model\HouseEntrust;
use app\user_v4\validate\UserAddress;
use think\Db;
use think\Request;
use think\response\Json;
use think\Session;

class User2 extends Base
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
     * 注册
     */
    public function register(){
        try {
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'register')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

            $param['password'] = password_hash($param['password'], PASSWORD_DEFAULT);
            $param['nickname'] = '新用户'.hide_phone($param['mobile']);

            Db::startTrans();
            $UserModel = new \app\user\model\User();
            $result = $UserModel->allowField(true)->save($param);

            $user_id = $UserModel->user_id;

            if (!$result) {
                Db::rollback();
                throw new \Exception('注册失败');
            }

            $tokenInfo = \app\user\common\User::setToken();

            if (!$tokenInfo) {
                Db::rollback();
                throw new \Exception('注册失败,请稍后重试');
            }

            //是否填写邀请码
            if ($param['user_code']){
                $invitation_user_id = decode($param['user_code']);
                $UserModel->invitation_user_id = $invitation_user_id;
            }

            $UserModel->token = $tokenInfo['token'];
            $UserModel->token_expire_time = $tokenInfo['token_expire_time'];
            $UserModel->login_time = time();

            $UserModel->allowField(true)->save();


            /*//赠送优惠券 满300-50 七天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 300, 'coupon_money' => 50, 'status' => 1, 'expiration_time' => time() + 24*3600*7, 'create_time' => time()]);

            //赠送优惠券 满100-25 五天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 100, 'coupon_money' => 25, 'status' => 1, 'expiration_time' => time() + 24*3600*5, 'create_time' => time()]);

            //赠送优惠券 满50-15 三天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 50, 'coupon_money' => 15, 'status' => 1, 'expiration_time' => time() + 24*3600*3, 'create_time' => time()]);

            //赠送优惠券 满20-10 两天
            Db::name('coupon')->insert(['user_id' => $user_id, 'coupon_name' => "新人优惠满减券", 'satisfy_money' => 20, 'coupon_money' => 10, 'status' => 1, 'expiration_time' => time() + 24*3600*2, 'create_time' => time()]);*/

            /*//赠送优惠券
            $coupon_rule = Db::name('coupon_rule')->where('id',1)->find();

            if ($coupon_rule['is_open'] == 1){
                for ($i=0;$i<$coupon_rule['zengsong_number'];$i++) {
                    Db::name('coupon')->insert([
                        'user_id' => $user_id,
                        'coupon_name' => $coupon_rule['coupon_name'],
                        'satisfy_money' => $coupon_rule['satisfy_money'],
                        'coupon_money' => $coupon_rule['coupon_money'],
                        'status' => 1,
                        'expiration_time' => time() + 24*3600*$coupon_rule['days'],
                        'create_time' => time()
                    ]);
                }
            }*/

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
    public  function login(){
        try{

            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'login')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $UserModel = new \app\user\model\User();
            $userInfo = $UserModel->where('mobile',$param['mobile'])->find();

            if (!$userInfo) {
                throw new \Exception('账号不存在');
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('密码错误');
            }

            if ($userInfo['user_status'] != 1) {
                throw new \Exception('账号已被禁用');
            }

            $token = \app\user\common\User::setToken();

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
    public function forgetPassword(){
        try {

            $param = $this->request->param();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'forgetPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            $verify = IhuyiSMS::verifyCode($param['mobile'],$param['code']);
            if (!$verify) {
                throw new \Exception('验证码不存在或已失效');
            }

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
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $userInfo['entrust_number'] = Db::name('house_entrust')->where('type',1)->where('param_id',$userInfo['user_id'])->count();
            $long_house = Db::name('house_collection')->where('user_id',$userInfo['user_id'])->count();
            $short_house = Db::name('short_collection')->where('user_id',$userInfo['user_id'])->count();
            $product = Db::name('product_collection')->where('user_id',$userInfo['user_id'])->count();
            $userInfo['collection_number'] = $long_house + $short_house + $product;
            $userInfo['user_code'] = createCode($userInfo['user_id']);
            $userInfo['card_number'] = Db::name('user_card')->where('user_id',$userInfo['user_id'])->count();

            return json(self::callback(1,'',$userInfo));

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
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof json){
                return $userInfo;
            }

            $validate = new \app\user\validate\User();
            if (!$validate->check($param,[],'modifyPwd')) {
                return json(self::callback(0,$validate->getError()),400);
            }

            if (!password_verify($param['password'], $userInfo['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }

            $UserModel = new \app\user\model\User();
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
    public function modifyUserInfo(){
        try{
            $param = $this->request->post();

            if (!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            if (!empty($param['nickname'])){
                $data['nickname'] = $param['nickname'];
            }


            $avatar_file = $this->request->file('avatar');
            if ($avatar_file) {
                //修改头像
                $info = $avatar_file->validate(['ext'=>'jpg,jpeg,png'])->move(ROOT_PATH.'public'.DS.'uploads'.DS.$this->request->module().DS.'avatar');

                if ($info) {
                    $avatar = DS.'uploads'.DS.$this->request->module().DS.'avatar'.DS.$info->getSaveName();
                    $data['avatar'] = str_replace(DS,"/",$avatar);

                }else{
                    throw new \Exception($avatar_file->getError());
                }

            }

            $UserModel = new \app\user\model\User();
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
        try{
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $data = Db::name('user_address')->where('user_id',$userInfo['user_id'])->select();

        if (empty($data)){
            $data = new \stdClass();
        }

        return json(self::callback(1,'',$data));
        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }


    /**
     * 添加收货地址
     */
    public function addAddress(){
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $validate = new UserAddress();
        if (!$validate->check($param,[])) {
            return json(self::callback(0,$validate->getError()),400);
        }

        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];

        $userAddressModel = new \app\user\model\UserAddress();


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
    public function modifyAddress(){
        $param = $this->request->post();

        if (!$param) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $result = $this->validate($param, ['address_id'  => 'require|number']);
        if(true !== $result){
            // 验证失败 输出错误信息
            return json(self::callback(0,$result),400);
        }

        $param['is_default'] = !isset($param['is_default']) ? 0 : $param['is_default'];

        $userAddressModel = new \app\user\model\UserAddress();


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
    public function deleteAddress(){
        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $address_id = input('address_id') ? intval(input('address_id')) : 0 ;
        if (!$address_id){
            return \json(self::callback(0,'参数错误'));
        }

        $userAddressModel = new \app\user\model\UserAddress();
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

        $param = $this->request->post();

        if (!$param || !$param['content']) {
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof json){
            return $userInfo;
        }

        $param['create_time'] = time();

        $files = $this->request->file('img');
        if ($files){
            foreach ($files as $file){
                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'feedback_img');
                if($info){
                    $img_url= $avatar = DS.'uploads'.DS.$this->request->module().DS.'feedback_img'.DS.$info->getSaveName().',,,';
                    $param['img_url'] = trim(str_replace(DS,"/",$img_url),',,,');

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }
        }

        $result = Db::name('feedback')->strict(false)->insert($param);
        if (!$result){
            return json(self::callback(0,'操作失败'));
        }

        return json(self::callback(1,''));

    }

    /**
     * 收藏列表
     */
    public function collectionList(){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;
        $type = input('type') ? intval(input('type')) : 0 ;   //1长租 2短租

        if (!$type){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\user\common\User::checkToken();
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

                $HouseModel = new \app\user\model\House();

                $total = $HouseModel->where($where)->count();

                $list = \app\user\model\House::getHouseList($page,$size,$where);

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

                $model = new \app\user\model\HouseShort();

                $total = $model->where($where)->count();

                $list = \app\user\model\HouseShort::getHouseShortList($page,$size,$where);

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
        $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $month = input('month');

            $status = input('status'); //0全部 1收入 2支出

            switch ($status){
                case 1:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->where('money','>',0)
                        ->order('create_time','desc')
                        ->select();

                    break;
                case 2:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->where('money','<',0)
                        ->order('create_time','desc')
                        ->select();

                    break;
                default:
                    $list = Db::name('user_money_detail')
                        ->field('id,note,money,create_time')
                        ->where("DATE_FORMAT(FROM_UNIXTIME(`create_time`),'%Y%m') = $month")
                        ->where('user_id',$userInfo['user_id'])
                        ->order('create_time','desc')
                        ->select();

                    break;
            }


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
            $userInfo = \app\user\common\User::checkToken();
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
            $userInfo = \app\user\common\User::checkToken();
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

}