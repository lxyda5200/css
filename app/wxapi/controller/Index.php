<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/7/19
 * Time: 17:43
 */

namespace app\wxapi\controller;

use app\common\controller\Base;
use app\common\controller\Character;
use app\common\controller\IhuyiSMS;
use app\wxapi\common\UserLogic;
use app\wxapi\common\Weixin;
use app\wxapi\model\ProductOrderDetail ;
use think\Exception;
use think\Loader;
use think\Log;
use think\response\Json;
use think\Db;
use think\Request;
use app\wxapi\common\User;


class Index extends Base
{

    /**
     * 获取版本号
     */
    public function getVersion()
    { try {
        $data = Db::name('version')->select();
        return json(self::callback(1, '', $data));
    } catch (\Exception $e) {
        return json(self::callback(0, $e->getMessage()));
    }
    }
    /**
     * banner列表
     */
    public function Banner(){
        try {
        $data = Db::name('store_category')
            ->field('id,category_name')
            ->where('is_show', 1)
            ->where('client_type', 1)
            ->order('paixu asc')
            ->select();
        return json(self::callback(1, '查询成功', $data));
    } catch (\Exception $e) {
        return json(self::callback(0, $e->getMessage()));
    }
    }
    /**
     * 获取用户openid
     * */
    public function getopenid()
    {
        try {
            $code = Request::instance()->post('code');
            if (!$code) {
                return json(self::callback(0, '参数错误'), 400);
            }
            $code=trim($code);
            $userInfo = \app\wxapi\common\Weixin::getOpenid($code);
         Log::info(print_r($userInfo,true));
            return $userInfo;
        } catch (\Exception $e) {
            return json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     **用户授权登录
     **/
    public function wxlogin()
    {
        try {
            $param = $this->request->post();
            $user_id=$param['user_id'];
            $encryptedData=$param['encryptedData'];
            $iv=$param['iv'];
            if (empty($param['iv']) || empty($param['encryptedData']) || empty($param['user_id'])) {
                return json(self::callback(0, '参数错误'), 400);
            }
            $user_id=intval($user_id);
            $user_info = Db::table('user')->where('user_id',$user_id)->find();
            if(!$user_info) {
                return json(self::callback(0, '用户不存在', $user_info));
            }
            $phone_info = \app\wxapi\common\Weixin::getUserPhone($user_info['wx_session_key'],$iv,$encryptedData);
//            addErrLog($phone_info);
            if(!$phone_info) return json(self::callback(0, '解密手机号失败', []));
            $phone_info = json_decode($phone_info,true);
            //获取手机号成功
            $mobile= $phone_info['phoneNumber'];
            //查询手机号是否有注册 
            $ismobile = Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,login_time,money,wx_openid,leiji_money,authorize_time,invitation_code,login_out,description')->where('mobile',$mobile)->find();

            if($ismobile){
                //修改为已授权登录
                if($ismobile['login_out']==-1){
                    Db::name('user')->where('mobile',$mobile)->setField('login_out',1);
                }
                if($user_id==$ismobile['user_id']){
                    $old = Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,login_time,money,leiji_money,authorize_time,invitation_code,description')->where('user_id',$user_id)->find();
                    if($old['authorize_time']==0){
                        //下发兑换码
                        IhuyiSMS::get_coupon_code($user_id,$mobile);
                        Db::table('user')->where('user_id', $user_info['user_id'])->update([
                            'login_time' => time(),
                            'authorize_time' => time()
                        ]);
                        //下发新人优惠券
                        autocoupon($user_id);
                        //查询是否有优惠券
                        $coupon=getcoupon($old['user_id'],$old['login_time']);
                        if(empty($coupon)){$coupon=[];}
                    }
                    $share=share_set();
                    $ismobile= array_merge($ismobile,$share);
                    $coupon_number=count($coupon);
                    if($coupon_number>1){
                        $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/2x.png';
                        $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                        $data['coupon_info']['list']=$coupon;
                    }elseif($coupon_number==1){
                        $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/1x.png';
                        $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                        $data['coupon_info']['list']=$coupon;
                    }
                    $data['user_info']=$ismobile;
                    return \json(self::callback(1, '用户已存在!', $data));
                }else{
                    $appold = Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,login_time,type,money,login_time,leiji_money,authorize_time,invitation_code,description')->where('mobile',$mobile)->find();
                    if($appold['authorize_time']==0){
                        //下发兑换码
                        IhuyiSMS::get_coupon_code($user_id,$mobile);
                        //下发优惠券
                        autocoupon($user_id);
                        //查询是否有优惠券
                        $coupon=getcoupon($appold['user_id'],$appold['login_time']);
                        if(empty($coupon)){$coupon=[];}
                    }
                    //如果wx_openid为空则为app注册
                    if($ismobile['wx_openid']==''){
                        //判断是否有头像 没有则给一个默认头像
                        if(empty($ismobile['avatar'])){
                            //手机号已注册则补全信息
                            $genxinuser = Db::table('user')->where('mobile', $mobile)->update([
                                'wx_openid' => $user_info['wx_openid'],
                                'avatar' => '/default/user_logo.png',
                                'login_time' => time(),
                                'authorize_time' => time(),
                                'wx_session_key' => $user_info['wx_session_key'],
                                'wx_unionid' => $user_info['wx_unionid'],
                                'wx_token' => $user_info['wx_token'],
                                'wx_token_expire_time' => $user_info['wx_token_expire_time']
                            ]);
                        }else{
                            //手机号已注册则补全信息
                            $genxinuser = Db::table('user')->where('mobile', $mobile)->update([
                                'wx_openid' => $user_info['wx_openid'],
                                'wx_session_key' => $user_info['wx_session_key'],
                                'wx_unionid' => $user_info['wx_unionid'],
                                'login_time' => time(),
                                'authorize_time' => time(),
                                'wx_token' => $user_info['wx_token'],
                                'wx_token_expire_time' => $user_info['wx_token_expire_time']
                            ]);
                        }
                        if($genxinuser===false){
                            return \json(self::callback(0, '更新原用户失败!'));
                        }else{
                            //删除临时用户信息
                            Db::table('user')->where('user_id',$user_info['user_id'])->update([
                                'user_status' => 1,
                                'authorize_time' => time()
                            ]);
//                            Db::table('user')->where('user_id',$user_info['user_id'])->setField('user_status', 3);//做假删除
//                            $del=Db::table('user')->where('user_id',$user_info['user_id'])->delete();
                            $data=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,invitation_code,description')->where('mobile',$mobile)->find();
                            $share=share_set();
                            $data= array_merge($data,$share);
                            $coupon_number=count($coupon);
                            if($coupon_number>1){
                                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/2x.png';
                                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                                $data['coupon_info']['list']=$coupon;
                            }elseif($coupon_number==1){
                                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/1x.png';
                                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                                $data['coupon_info']['list']=$coupon;
                            }
                            $data['user_info']=$data;
                            return \json(self::callback(1, '更新老用户信息成功', $data));
                        }
                    }else{
                        if($appold['authorize_time']==0){
                            //下发兑换码
                            IhuyiSMS::get_coupon_code($user_id,$mobile);
                            //下发优惠券
                            autocoupon($user_id);
                            //查询是否有优惠券
                            $coupon=getcoupon($appold['user_id'],$appold['login_time']);
                            if(empty($coupon)){$coupon=[];}
                        }
                        //判断是否有头像 没有则给一个默认头像
                        if(empty($ismobile['avatar'])){
                            //手机号已注册则补全信息
                            $genxinuser = Db::table('user')->where('mobile', $mobile)->update([
                                'wx_openid' => $user_info['wx_openid'],
                                'avatar' => '/default/user_logo.png',
                                'login_time' => time(),
                                'authorize_time' => time(),
                                'wx_session_key' => $user_info['wx_session_key'],
                                'wx_unionid' => $user_info['wx_unionid'],
                                'wx_token' => $user_info['wx_token'],
                                'wx_token_expire_time' => $user_info['wx_token_expire_time']
                            ]);
                        }else{
                            //手机号已注册则补全信息
                            $genxinuser = Db::table('user')->where('mobile', $mobile)->update([
                                'wx_openid' => $user_info['wx_openid'],
                                'wx_session_key' => $user_info['wx_session_key'],
                                'wx_unionid' => $user_info['wx_unionid'],
                                'login_time' => time(),
                                'authorize_time' => time(),
                                'wx_token' => $user_info['wx_token'],
                                'wx_token_expire_time' => $user_info['wx_token_expire_time']
                            ]);
                        }
                        if($genxinuser===false){
                            return \json(self::callback(0, '更新原用户失败!'));
                        }else{
                            //删除临时用户信息
                            Db::table('user')->where('user_id',$user_info['user_id'])->update([
                                'user_status' => 1,
                                'authorize_time' => time()
                            ]);
//                            Db::table('user')->where('user_id',$user_info['user_id'])->setField('user_status', 3);//做假删除
//                            $del=Db::table('user')->where('user_id',$user_info['user_id'])->delete();
                            $data=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,invitation_code,description')->where('mobile',$mobile)->find();
                            $share=share_set();
                            $data= array_merge($data,$share);
                            $coupon_number=count($coupon);
                            if($coupon_number>1){
                                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/2x.png';
                                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                                $data['coupon_info']['list']=$coupon;
                            }elseif($coupon_number==1){
                                $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/1x.png';
                                $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                                $data['coupon_info']['list']=$coupon;
                            }
                            $data['user_info']=$data;
                            return \json(self::callback(1, '更新老用户信息成功', $data));
                        }
                    }
                }
            }else{
//return $user_info['user_id'];
               $new= Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,login_time,money,leiji_money,authorize_time,invitation_code,login_out,description')->where('user_id',$user_info['user_id'])->find();
               if($new['login_out']==-1){ //修改为已授权登录
                   Db::name('user')->where('user_id',$user_info['user_id'])->setField('login_out',1);}
                if($new['authorize_time']==0){

                    //未授权下发兑换码
                    IhuyiSMS::get_coupon_code($user_info['user_id'],$mobile);
                    //下发优惠券
                    autocoupon($user_info['user_id']);
                    //查询是否有优惠券
                    $coupon=getcoupon($new['user_id'],$new['login_time']);
                    if(empty($coupon)){$coupon=[];}
                }
                if($new['invitation_code']==''){
                    $invitation_code = createCode($user_info['user_id']);
                    //没有该手机号 补全新用户信息
                    $setmobile = Db::table('user')->where('user_id',$user_info['user_id'])->update([
                        'mobile' => "15756326834",
                        'login_time' => time(),
                        'authorize_time' => time(),
                        'invitation_code' => $invitation_code,
                        'nickname' => $mobile
                    ]);

                }else{
                    //没有该手机号 补全新用户信息
                    $setmobile = Db::table('user')->where('user_id',$user_info['user_id'])->update([
                        'mobile' => $mobile,
                        'login_time' => time(),
                        'authorize_time' => time(),
                        'nickname' => $mobile
                    ]);
                }

                if($setmobile===false){
                    return \json(self::callback(0, '更新手机号失败!'));
                }else{
                    $data=Db::table('user')->field('user_id,wx_token,mobile,nickname,avatar,type,money,leiji_money,invitation_code,description')->where('user_id',$user_info['user_id'])->find();
//                    addErrLog($data);
                    $share=share_set();
                    $data= array_merge($data,$share);
                    $coupon_number=count($coupon);
                    if($coupon_number>1){
                        $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/2x.png';
                        $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                        $data['coupon_info']['list']=$coupon;
                    }elseif($coupon_number==1){
                        $data['coupon_info']['c_bg']='/uploads/coupon/wxapi/1x.png';
                        $data['coupon_info']['b_bg']='/uploads/coupon/wxapi/quan.png';
                        $data['coupon_info']['list']=$coupon;
                    }
                    $data['user_info']=$data;
                    return \json(self::callback(1, '用户更新成功!', $data));
                }
            }
            return \json(self::callback(0, '未知错误!'));
        } catch (\Exception $e) {
            return json(self::callback(0, $e->getMessage()));
        }
    }
    /**
     * 获取分享人用户
     * */
    public function sharer()
    {
        try {
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $share_user_id = input('share_user_id') ? intval(input('share_user_id')) : 0;
           // Log::error(print_r($share_user_id,true));

            if (!$share_user_id) {
                return json(self::callback(1, '参数错误'), 400);
            }
            $data=Db::table('user')->where('user_id',$share_user_id)->find();
            if($data){
            //增加推广人
                Db::name('user')->where('wx_openid',$userInfo['wx_openid'])->where('source',1)->setField('invitation_user_id',$share_user_id);
            //查询是否有被邀请人优惠券
                $coupons=Db::table('coupon_rule')
                    ->where('type',1)
                    ->where('coupon_type',7)
                    ->where('is_open',1)
                    ->where('grant_object','in',[1,3])
                    ->select();


                if($coupons){
                    foreach( $coupons as $k=>$v){
                        for ($i = 0; $i < $v['zengsong_number']; $i++) {
                            $coupon[] = [
                                'coupon_id' => $v['id'],
                                'user_id' => $userInfo['user_id'],
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
                   $rst= Db::name('coupon')->insertAll($coupon);

                }
//查询是否有邀请人优惠券
                $share=Db::table('coupon_rule')
                    ->where('type',1)
                    ->where('coupon_type',7)
                    ->where('is_open',1)
                    ->where('grant_object','in',[2,3])
                    ->select();
                if($share){
                    foreach( $share as $k1=>$v1){
                        for ($i = 0; $i < $v1['zengsong_number']; $i++) {
                            $shares[] = [
                                'coupon_id' => $v1['id'],
                                'user_id' => $share_user_id,
                                'coupon_name' => $v1['coupon_name'],
                                'satisfy_money' => $v1['satisfy_money'],
                                'coupon_money' => $v1['coupon_money'],
                                'status' => 1,
                                'expiration_time' => time() + 24 * 3600 * $v1['days'],
                                'create_time' => time(),
                                'coupon_type' => $v1['coupon_type']
                            ];
                        }
                    }
                    $rst2= Db::name('coupon')->insertAll($shares);

                }
                if($rst && $rst2){
                    return json(self::callback(1, '优惠券发送成功'), 400);
                }
            }else{
                return json(self::callback(1, '没有该用户'), 400);
            }
        } catch (\Exception $e) {
            Db::rollback();
            return json(self::callback(0, $e->getMessage()));
        }
    }
    /**
     * h5分享
     * @param $user_id用户id
     */
    function share(){
        try{
            $user_id = input('post.user_id',0,'intval');
            if(!$user_id){
                return json(self::callback(0, '参数错误'), 400);
            }
            $data=Db::table('user')->field('user_id,nickname,avatar,invitation_code')->where('user_id',$user_id)->find();
            if($data){
                $share=share_set2();
                $data= array_merge($data,$share);
                return \json(self::callback(1, '返回成功!', $data));
            }else{
                return json(self::callback(0, '没有该用户!'), 400);
            }
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
    /**
     **设置-退出登录
     **/
    public function loginOut(){
        try{
            //token 验证
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            Db::name('user')->where('user_id',$userInfo['user_id'])->setField('login_out',-1);
            $data=array(
                "user_id"=>$userInfo['user_id'],
                "wx_token"=>$userInfo['wx_token'],
                "mobile"=>"",
                "nickname"=>"",
                "avatar"=>"",
                "type"=>"",
                "money"=>"",
                "leiji_money"=>""
            );
            return json(self::callback(1,'',$data));
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));

        }
    }
    //生成邀请码
    public function yaoqingma()
    {
        try{
            $users=Db::name('user')->where('user_id','>',0)->where('invitation_code','eq','')->select();
            foreach ($users as $k=>$v){
                $invitation_code=createCode($v['user_id']);
                Db::name('user')->where('user_id',$v['user_id'])->setField('invitation_code',$invitation_code);
            }
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));
        }
    }


//刷新阅读量
    public function readnumber()
    {
        try{
            $stores=Db::name('store')->where('id','>',0)->select();
            foreach ($stores as $k=>$v){
                $n=mt_rand(500,1000);
                Db::name('store')->where('id',$v['id'])->setInc('read_number', $n);;
            }
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));
        }
    }
    //关注
    public function guanzhu()
    {
        try{
            $chaoda = Db:: view('chaoda_dianzan','id,chaoda_id,user_id')
                ->view('chaoda','store_id,fb_user_id','chaoda_dianzan.chaoda_id = chaoda.id','left')
                ->where('chaoda_dianzan.id','gt',0)
                ->select();

            foreach ($chaoda as $k=>$v){
                if($v['store_id']>0){
                    $rst= Db::table('chaoda_dianzan')->where('id', $v['id'])->update(['store_id' => $v['store_id']]);
                }elseif($v['fb_user_id']>0){
                    $rst= Db::table('chaoda_dianzan')->where('id', $v['id'])->update(['fb_user_id' => $v['fb_user_id']]);
                }else{

                }
            }

        if($rst){
            return '更新成功！';
        }else{
            return '更新失败！';
        }
        }catch (\Exception $e) {
            return json(self::callback(0,$e->getMessage()));
        }
    }
    public static function productCoupos(){
        $coupon_type=3;//商品优惠券
        $user_id=13077;
        $store_id=76;
        $product_id=5592;
        $money=9.99*1;//金额
        return UserLogic::productCoupos($money,$coupon_type,$user_id, $store_id, $product_id);
    }

    /**
     * 消息中心
     */
    public function msgList()
    {
        try {
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json) {
                return $userInfo;
            }
            $page = input('page') ? intval(input('page')) : 1;
            $size = input('size') ? intval(input('size')) : 10;
            $total = Db::view('user_msg_link')
                ->view('user_msg', 'title,content,type,create_time', 'user_msg.id = user_msg_link.msg_id', 'left')
                ->where('user_msg_link.user_id', $userInfo['user_id'])
                ->count();
            $list = Db::view('user_msg_link', 'id,is_read')
                ->view('user_msg', 'title,content,type,create_time', 'user_msg.id = user_msg_link.msg_id', 'left')
                ->where('user_msg_link.user_id', $userInfo['user_id'])
                ->order('user_msg_link.is_read', 'asc')
                ->order('user_msg.create_time', 'desc')
                ->page($page, $size)
                ->select();
            foreach ($list as $k=>$v){
                Db::name('user_msg_link')->where('id',$v['id'])->setField('is_read', 1);
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'', $data));

        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除个人消息
     */
    public function delmsgList()
    {
        try {
            $userInfo = \app\wxapi\common\User::checkToken();
            if ($userInfo instanceof json) {
                return $userInfo;
            }
            //获取msg_id
            $msg_id = $this->request->post('id');
            if(!$msg_id){
                return \json(self::callback(0,'参数错误',false));
            }
            $find = Db::view('user_msg_link','id ,user_id,msg_id')
                ->view('user_msg','id as user_msg_id','user_msg_link.msg_id = user_msg.id','left')
                ->where('user_msg_link.id',$msg_id)
                ->where('user_msg_link.user_id',$userInfo['user_id'])
                ->find();
            if(!$find){
                return json(self::callback(0,'没有找到这条消息',false));
            }

            $delmsg=Db::table('user_msg')->where('id',$find['msg_id'])->delete();
            if ($delmsg===false){

                throw new \Exception('操作失败:001');
            }
            $delmsglink=Db::table('user_msg_link')->where('id',$find['id'])->delete();
            if ($delmsglink===false){
                throw new \Exception('操作失败:002');
            }
        if($delmsg && $delmsglink){
            return \json(self::callback(1,'删除成功', true));
        }else{
            return \json(self::callback(0,'删除失败', false));
        }


        } catch (\Exception $e) {
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
 * 分享二维码
 * */
    public function getQRcode()
    {
        try {
            #验证token
//            $user_id = input('user_id',0,'intval');
//            $token = input('token','','addslashes,strup_tags,trim');
//            $userInfo = User::checkToken($user_id,$token);
//            if ($userInfo instanceof Json){
//                return $userInfo;
//            }

            #获取参数
            $page = input('page','','addslashes,strip_tags,trim');
            if(!$page)throw new \Exception('页面路劲参数缺失');
            $scene = input('scene','1=1','addslashes,strip_tags,trim');

            #获取二维码
            $qrcode = Weixin::getQrcode($scene,$page);
            if($qrcode['status']>0)throw new \Exception($qrcode['msg']);

//            return \json(self::callback(1,'',['qrcode'=>base64_encode($qrcode['data'])]));
//            return \json(self::callback(1,'',['qrcode'=>$qrcode['data']]));
            $qrcode = $qrcode['data'];
            header('content-type:image/png');
            echo $qrcode;die;

        } catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }

//---------------------------------------------------------------------------------


    /*
     * 商品详情
     * */
    public function product_content_p()
    {
        $id = input("id") ? intval(input("id")) : 0;
        if (!$id) {
            echo "内容不存在或者已被删除";
            die;
        }
        $data = Db::name('product')
            ->where("id", $id)
            ->field('content')
            ->find();

        if (!$data) {
            echo "内容不存在或者已被删除";
            die;
        }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/product_content_p', ['data' => $data]);
    }

    /*
     * 店铺banner
     * */
    public function store_banner_p()
    {
        $id = input("id") ? intval(input("id")) : 0;
        if (!$id) {
            echo "内容不存在或者已被删除";
            die;
        }
        $data = Db::name('store_category_img')
            ->where("id", $id)
            ->field('content')
            ->find();
        if (!$data) {
            echo "内容不存在或者已被删除";
            die;
        }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/store_banner', ['data' => $data]);
    }
public function erweima(){

    //生成二维码
    Loader::import('phpqrcode.phpqrcode');
    $QRcode = new \QRcode;
    $value = 'http://appwx.supersg.cn/app/download.html';
    $errorCorrectionLevel = 'L';//纠错级别：L、M、Q、H
    $matrixPointSize = 10;//二维码点的大小：1到10
    $download='download';
    $path=ROOT_PATH . 'public' . DS . 'uploads'. DS .'user_v2'. DS .$download.'.png';
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
    $store_info['qrcode']=  DS . 'uploads'. DS .'user_v2'. DS .$download.'.png';

}

    /*
     * 会员店铺页
     * */
    public function member_store_banner_p()
    {
        $id = input("id") ? intval(input("id")) : 0;
        if (!$id) {
            echo "内容不存在或者已被删除";
            die;
        }
        $data = Db::name('member_store_banner')
            ->where("id", $id)
            ->field('content')
            ->find();
        if (!$data) {
            echo "内容不存在或者已被删除";
            die;
        }

        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/store_banner', ['data' => $data]);
    }

    /*
     * 用户协议页
     * */
    public function user_p()
    {
        $id = input("id") ? intval(input("id")) : 0;
        if (!$id) {
            echo "内容不存在或者已被删除";
            die;
        }
        $data = Db::name('about_us')
            ->where("id", $id)
            ->field('user_protocol')
            ->find();
        if (!$data) {
            echo "内容不存在或者已被删除";
            die;
        }

        $data['content'] = html_entity_decode($data['user_protocol']);
        return $this->fetch('index/user_protocol', ['data' => $data]);
    }

    /*
     * 用户协议页
     * */
    public function member_p()
    {
        $id = input("id") ? intval(input("id")) : 1;
        if (!$id) {
            echo "内容不存在或者已被删除";
            die;
        }
        $data = Db::name('member_price')
            ->where("id", $id)
            ->field('content')
            ->find();
        if (!$data) {
            echo "内容不存在或者已被删除";
            die;
        }

        $data['title'] = '会员中心';
        $data['content'] = html_entity_decode($data['content']);
        return $this->fetch('index/banner', ['data' => $data]);
    }

    /**
     * 获取省市区
     */
    public function getProvinceData()
    {

        $data = Db::name('province')->field('id,province_name')->order(['paixu' => 'asc', 'id' => 'asc'])->select();

        foreach ($data as $k => $v) {
            $city_list = Db::name('city')->field('id,city_name')->where('province_id', $v['id'])->order(['paixu' => 'asc', 'id' => 'asc'])->select();

            foreach ($city_list as $k2 => $v2) {
                $city_list[$k2]['area_list'] = Db::name('area')->field('id,area_name1')->where('city_id', $v2['id'])->where('pid', 0)->select();
            }

            $data[$k]['city_list'] = $city_list;

        }
        return json(self::callback(1, '', $data));
    }

    /**
     * 获取城市数据
     * @is_hot  是否热门城市 可选
     * @province_id  省份id  可选
     */
    public function getCityData()
    {

        $is_hot = input('is_hot') ? intval(input('is_hot')) : 0;
        if (!empty($is_hot)) {
            $where['is_hot'] = array('eq', $is_hot);
        }
        $city = input('city');
        if (!empty($city)) {
            $where['city_name'] = ['like', "%$city%"];
        }

        $province_id = input('province_id') ? intval(input('province_id')) : 0;
        if (!empty($province_id)) {
            $where['province_id'] = array('eq', $province_id);
        }

        $data = Db::name('city')->field('id,city_name')->where('is_show', 1)->where($where)->order(['paixu' => 'asc', 'id' => 'asc'])->select();

        $Character = new Character();
        $data = $Character->groupByInitials($data, 'city_name');

        return json(self::callback(1, '', $data));
    }

    /**
     * 城市搜索
     */
    public function citySearch()
    {
        $city = input('city');
        if (!empty($city)) {
            $where['city_name'] = ['like', "%$city%"];
        }

        $data = Db::name('city')->field('id,city_name')->where('is_show', 1)->where($where)->order(['paixu' => 'asc', 'id' => 'asc'])->select();

        return json(self::callback(1, '', $data));
    }

    /**
     * 获取区域数据
     */
    public function getAreaData()
    {
        $city_id = input('city_id') ? intval(input('city_id')) : 0;

        if (!$city_id) {
            return json(self::callback(0, '参数错误'), 400);
        }

        $data = Db::name('city')->field('id,city_name')->where('id', $city_id)->find();

        if (!$data) {
            return json(self::callback(0, '城市不存在'));
        }

        $area = Db::name('area')
            ->field('id,area_name1,lng,lat')
            ->where('city_id', $city_id)
            ->where('pid', 0)
            ->order(['paixu' => 'asc', 'id' => 'asc'])
            ->select();

        foreach ($area as $k => $v) {
            $area[$k]['area2_list'] = Db::name('area')
                ->field('id,area_name2,lng,lat')
                ->where('pid', $v['id'])
                ->order(['paixu' => 'asc', 'id' => 'asc'])
                ->select();
        }

        $data['area1_list'] = $area;

        return json(self::callback(1, '', $data));
    }

    /**
     * 获取城市地铁数据
     */
    public function getSubwayData()
    {
        $city_id = input('city_id') ? intval(input('city_id')) : 0;

        if (!$city_id) {
            return json(self::callback(0, '参数错误'), 400);
        }

        $city_info = Db::name('city')->field('id,city_name')->where('id', $city_id)->find();

        $area = Db::name('subway_lines')
            ->field('id,lines_name')
            ->where('city_id', $city_id)
            ->order(['paixu' => 'asc', 'id' => 'asc'])
            ->select();

        foreach ($area as $k => $v) {
            $area[$k]['station_list'] = Db::name('subway_station')->field('id,station_name')->where('lines_id', 'eq', $v['id'])->order(['paixu' => 'asc', 'id' => 'asc'])->select();
        }

        $data['city_info'] = $city_info;
        $data['lines_list'] = $area;

        return json(self::callback(1, '', $data));
    }

    /**
     * 获取房源标签
     */
    public function getHouseTagData()
    {
        $type = input('type') ? intval(input('type')) : 1;
        $data = Db::name('house_tag')->field('id,tag_name')->where('status', 1)->where('type', $type)->select();
        return json(self::callback(1, '', $data));
    }


    /**
     * 获取房源类型
     */
    public function getHouseTypeData()
    {

        $type = input('type');

        $data = Db::name('house_type')->field('id,name')->where('type', $type)->select();

        return json(self::callback(1, '', $data));
    }

    /**
     * 获取小区列表数据
     */
    public function getHouseXiaoquData()
    {
        $city_id = input('city_id');

        $where['city_id'] = ['eq', $city_id];

        $keyword = input('keyword');
        if (isset($keyword)) {
            $where['xiaoqu_name'] = ['like', "%$keyword%"];
        }
        $data = Db::name('house_xiaoqu')->where($where)->select();
        return json(self::callback(1, '', $data));

    }

    /**
     * 获取房源配置数据
     */
    public function getHouseConfigData()
    {
        $type = input('type');

        $where['type'] = ['eq', $type];
        $where['status'] = ['eq', 1];

        $data = Db::name('room_config')->field('id,name,icon')->where($where)->select();

        return json(self::callback(1, '', $data));
    }


    /**
     * 获取短租交通与位置标签
     */
    public function getTrafficTagData()
    {
        $data = Db::name('short_traffic_tag')->field('id,name')->where('status', 1)->select();

        return json(self::callback(1, '', $data));
    }

    /**
     * 关于我们
     */
    public function aboutUs()
    {
        $data = Db::name('about_us')->where('id', 1)->find();
        return json(self::callback(1, '', $data));
    }
}