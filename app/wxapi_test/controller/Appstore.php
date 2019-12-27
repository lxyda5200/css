<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/4/23
 * Time: 17:02
 */

namespace app\wxapi_test\controller;


use app\common\controller\Base;
use think\Db;

class Appstore extends Base
{

    /**
     * 苹果内购验证  购买会员
     * @return mixed
     */
    public function apple_pay() {
        /*苹果内购的验证收据
        这里坑有点大.我这里是因为客户端传过来的验证收据已经进行base64加密了，所以后端无需再次加密，但是传到后端后+号会变成空格等导致老是出现21002错误，解决办法就是下面这样进行一次正则替换，如果客户端传过来的没有进行加密，则后端再进行一次base64加密即可。*/
        #$receipt_data = preg_replace('/\s/', '+', input('post.apple_receipt'));
        $receipt_data = input('post.apple_receipt');
        $orderid = input('post.order_id') ? intval(input('post.order_id')) : 0 ;
        $sandbox = input('post.sandbox') ? intval(input('post.sandbox')) : 0 ;
        //获取订单信息
        $orderinfo = Db::name('member_order')->where('order_id',$orderid)->find();
        if (!empty($orderinfo) && ($orderinfo['status'] == 1)) {
            // 验证支付状态
            $result = validate_apple_pay($receipt_data,$sandbox);

            if($result['status']){

                // 验证通过后订单处理等逻辑
                Db::name('member_order')->where('order_id',$orderid)->update(['pay_type'=>'苹果内购','pay_time'=>time(),'status'=>2]);

                $userinfo = Db::name('user')->where('user_id',$orderinfo['user_id'])->find();

                Db::name('user')->where('user_id',$orderinfo['user_id'])->setField('type',2);

                //是否有邀请人
                if ($userinfo['invitation_user_id']) {
                    //返利
                    $fanli_money = Db::name('member_price')->where('id',1)->value('member_fanli_money');

                    $userinfo2 = Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->find();

                    if ($userinfo2){
                        Db::name('user')->where('user_id',$userinfo['invitation_user_id'])->setInc('money',$fanli_money);

                        Db::name('user_money_detail')->insert([
                            'user_id' => $userinfo['invitation_user_id'],
                            'order_id' => $orderinfo['id'],
                            'note' => '会员返利',
                            'money' => $fanli_money,
                            'balance' => $userinfo2['money'],
                            'create_time'=> time()
                        ]);
                    }


                }

                //是否有分享人
                if ($orderinfo['share_user_id']) {
                    //返利
                    $fanli_money = Db::name('member_price')->where('id',1)->value('member_fanli_money');

                    $userinfo2 = Db::name('user')->where('user_id',$orderinfo['share_user_id'])->find();

                    if ($userinfo2){
                        Db::name('user')->where('user_id',$orderinfo['share_user_id'])->setInc('money',$fanli_money);

                        Db::name('user_money_detail')->insert([
                            'user_id' => $orderinfo['share_user_id'],
                            'order_id' => $orderinfo['id'],
                            'note' => '会员返利',
                            'money' => $fanli_money,
                            'balance' => $userinfo2['money'],
                            'create_time'=> time()
                        ]);
                    }
                }

                return json(self::callback(1,'购买成功'));
            }else{
                // 验证不通过
                return json(self::callback(0,$result['message']));
            }
        }else{
            return json(self::callback(0,'订单不存在或订单已处理'));
        }
    }

}