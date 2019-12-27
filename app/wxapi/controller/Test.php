<?php
/**
 * Created by PhpStorm.
 * User: lxy
 * Date: 2018/7/19
 * Time: 17:43
 */

namespace app\wxapi\controller;

use app\common\controller\Base;
use aes\Aes;
use think\Db;
use think\Request;
use my_redis\MRedis;
use app\wxapi\common\User;
use think\Session;
use Predis\Client;

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
        $rst = \app\wxapi\common\Images::gaussian_blur($rr,null,null,2);
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
        $rst = \app\wxapi\common\Images::gaussian_blur($p,null,null,2);
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

            //拼装奖项数组
            // 奖项id，奖品，概率
            $prize_arr = array(
                '0' => array('id'=>1,'prize'=>'平板电脑','v'=>0),
                '1' => array('id'=>2,'prize'=>'数码相机','v'=>0),
                '2' => array('id'=>3,'prize'=>'音箱设备','v'=>3),
                '3' => array('id'=>4,'prize'=>'4G优盘','v'=>5),
                '4' => array('id'=>5,'prize'=>'10Q币','v'=>2),
                '5' => array('id'=>6,'prize'=>'谢谢参与','v'=>5),
            );
            foreach ($prize_arr as $key => $val) {
                $arr[$val['id']] = $val['v'];//概率数组
            }

            $rid = $this->get_rand($arr); //根据概率获取奖项id
            $res['yes'] = $prize_arr[$rid-1]['prize']; //中奖项
            unset($prize_arr[$rid-1]); //将中奖项从数组中剔除，剩下未中奖项
            shuffle($prize_arr); //打乱数组顺序
            for($i=0;$i<count($prize_arr);$i++){
                $pr[] = $prize_arr[$i]['prize'];  //未中奖项数组
            }
            $res['no'] = $pr;
            // var_dump($res);


            if($res['yes']!='谢谢参与'){
                $result['status']=1;
                $result['name']=$res['yes'];
            }else{
                $result['status']=-1;
                $result['msg']=$res['yes'];
            }
            //return $result;
            var_dump($result);
        }

        //计算中奖概率
        function get_rand($proArr) {
            $result = '';
            //概率数组的总概率精度
            $proSum = array_sum($proArr);
            //概率数组循环
            foreach ($proArr as $key => $proCur) {
                echo $proSum;echo '--';

                $randNum = mt_rand(1, $proSum);  //返回随机整数

                if ($randNum <= $proCur) {
                    $result = $key;
                    break;
                } else {
                    $proSum -= $proCur;
                }
            }
            unset ($proArr);
            return $result;
    }

public  function  test7(){

        $user=Db::name('user')->where('mobile',15756321683)->find();
        var_dump($user);
        $redis=new MRedis();
        $red= $redis->getRedis();
        $red->set($user['mobile'],$user);echo "--";
        $data=$red->get('15756321683');
        var_dump($data);

}

}