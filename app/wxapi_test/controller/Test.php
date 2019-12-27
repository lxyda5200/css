<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/7/19
 * Time: 17:43
 */

namespace app\wxapi_test\controller;

use app\common\controller\Base;
use app\common\controller\Character;
use app\common\controller\IhuyiSMS;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\wxapi_test\common\UserLogic;
use app\wxapi_test\common\Weixin;
use app\wxapi_test\model\ProductOrderDetail ;
use think\Exception;
use think\Loader;
use think\Log;
use think\response\Json;
use think\Db;
use think\Request;
use app\wxapi_test\common\User;


class Test extends Base
{

    /**
     * 测试
     */
    public function test($qq,$start,$end,$limit)
    { 
     try {
         $users = Db::name('user')->where('user_id','gt',0)->where('mobile','neq','')->column('mobile');
         $user_show = Db::name('user_show')->where('user_id','gt',0)->where('mobile','neq','')->column('mobile');
         $data= array_merge($users,$user_show);
         $user_id=[];
         $numbers=0;
foreach ($data as $k=>$v){
    if($numbers<$limit){
        $first= substr($v,0,7);
        $m='';
        for ($i=0;$i<4;$i++){
            $n=mt_rand(0,9);
            $m.=$n;
        }
        $new=$first.$m;
        if(!in_array($new, $data)){
                $q=$qq;
                $h=mt_rand($start,$end);
                $ti=$q.$h;
                $d=mt_rand($start,$end);
                $dl=$q.$d;
                $password = password_hash(88888888, PASSWORD_DEFAULT);
                $nickname = '新用户'.hide_phone($new);
                $newuser = [
                    'token' =>'0ae76de72f78a2d9f0ae5946bb3107032b74f752',
                    'token_expire_time' => $dl,
                    'mobile' => $new,
                    'password' => $password,
                    'nickname' => $nickname,
                    'avatar' => '/default/user_logo.png',
                    'create_time' => $ti,
                    'login_time' => $dl,
                    'authorize_time' => $dl,
                    'wx_token' => '0ae76de72f78a2d9f0ae5946bb3107032b74f752',
                    'source' => 2
                ];
                $user_id= Db::name('user_show')->insertGetId($newuser);
                $invitation_code = createCode($user_id);
                $rst= Db::name('user_show')->where('user_id',$user_id)->setField('invitation_code',$invitation_code);
                if($user_id && $rst){
                    $numbers++;
                }
        }
    }else{
        return $numbers;
    }

}
    return $numbers;

        } catch (\Exception $e) {
        return json(self::callback(0, $e->getMessage()));
    }

}
public function test2() {
        //9月
        $qq=156;
        $start=7267200;
        $end=9859199;
        $limit=50000;
        $rst=self::test($qq,$start,$end,$limit);
        return "成功插入".$rst."条新用户";
    }

public function test3() {

        $url='/default/test.jpg';
        $rr= 'http://wx.supersg.cn'.$url;
        $rst = \app\wxapi_test\common\Images::gaussian_blur($rr,null,null,2);
        $url2= substr(strrchr($rst, 'css/public'), 1);
//        $rst=$image_blur->gaussian_blur
    echo '<img src="'.$url2.'"/>';
    }
public function test4() {

    $bg_cover=  Db::name('topic')->where('id',6)->value('bg_cover');
    if(!$bg_cover){
        //$url='/uploads/product/cover/20190924/a71b4ccbd74fd829be3c903a0b670e5b.png';
  $url='https://outin-63b2bb549ca711e9a1a600163e1a65b6.oss-cn-shanghai.aliyuncs.com/cdef9e9fbac64b258ab35fe4b3ca45ff/snapshots/7e02f7a25eec4fb2bd7d5ef41923beca-00001.jpg';
        if(strpos($url,'http')!== false){
            $p= $url;
        }else{
            $url=$url;
            $p= 'http://wx.supersg.cn'.$url;
        }
        $rst = \app\wxapi_test\common\Images::gaussian_blur($p,null,null,2);
        $url2=strstr($rst,"/uploads/gaosi/");
        $bg=  Db::name('topic')->where('id',6)->setField('bg_cover',$url2);
        if ($bg===false){return json(self::callback(0,'话题图片更新失败!'));}
    }else{
        return 'You';
    }
    }
    public function  test5(){

        $param['chaoda_id']=732;

    $param['comment_id']=249;
        // 计算主评论的回复评论数量
        $data = self::where(['c.chaoda_id' => $param['chaoda_id'], 'c.pid' => $param['comment_id']])
            -> alias('c')
            -> field([
                'c.id', 'c.pid', 'c.create_time', 'c.user_id', 'c.content', 'c.chaoda_id', 'c.support', 'c.hate',
                'u.nickname', 'u.avatar'
            ])
            -> join('user u', 'c.user_id = u.user_id', 'left')

            -> order('c.id desc')
            -> select();

var_dump($data);

}

    public function  test6(){
        $lat = "30.549100";
        $lng = "104.067450";
        $list = Db::name('store')
            ->where('store_status','EQ',1)
            ->field([
                'store_name','cover as store_logo','address','signature','lng','lat','lat_lng_distance(30.549100,104.067450,30.559100,104.067450) as distance',

            ])
            ->limit(10)
            ->select();
        echo Db::name()->getLastSql();
        var_dump($list);
    }
}