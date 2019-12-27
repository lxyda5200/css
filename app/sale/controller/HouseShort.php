<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/15
 * Time: 15:45
 */

namespace app\sale\controller;


use app\common\controller\Base;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user\model\ShortOrder;
use think\Db;
use think\response\Json;

class HouseShort extends Base
{
    /**
     * 短租服务列表
     */
    public function shortServiceList(){
        $status = input('status') ? intval(input('status')) : 1 ;  //1待租  2已定  3出租中  4完成
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;
        $today = date('Y-m-d');

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $where['sale_id'] = ['eq',$userInfo['sale_id']];

        switch ($status){
            case 1:

                //查询出被当天被预定的房源id
                $id = Db::name('short_order')
                    ->where('start_time','<=',$today)
                    ->where('end_time','>',$today)
                    ->where($where)
                    ->where('status','in','1,2,3')
                    ->column('short_id');

                if (!empty($id)){
                    $where['id'] = ['not in',$id];
                }

                $total = Db::name('house_short')
                    ->where($where)
                    ->where('status',2)
                    ->count();

                $list = Db::name('house_short')
                    ->field('id,title,description,rent,bedroom_number,parlour_number,toilet_number,people_number')
                    ->where($where)
                    ->where('status',2)
                    ->page($page,$size)
                    ->select();

                foreach ($list as $k=>$v){
                    $list[$k]['reserve_info'] = Db::name('short_order')
                        ->field('start_time,end_time')
                        ->where('short_id',$v['id'])
                        ->where('status','in','2,3')
                        ->select();
                    $list[$k]['house_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['id'])->select();
                }

                break;
            case 2:

                //查询出当天被预定的房源id
                $id = Db::name('short_order')
                    ->where('start_time','<=',$today)
                    ->where('end_time','>',$today)
                    ->where('status',2)
                    ->where($where)
                    ->column('short_id');

                $order_id = Db::name('short_order')
                    ->where('start_time','<=',$today)
                    ->where('end_time','>',$today)
                    ->where('status',2)
                    ->where($where)
                    ->column('id');

                $total = Db::name('house_short')

                    ->where('id','in',$id)
                    ->count();

                $list = Db::view('house_short','id,title,description,rent,bedroom_number,parlour_number,toilet_number,people_number')
                    ->view('short_order','id as order_id,order_no,username,mobile,start_time,end_time,pay_money,pay_type,room_number,people_number as rz_people_number,occupant_id,create_time','short_order.short_id = house_short.id','left')
                    ->where('house_short.id','in',$id)
                    ->where('short_order.id','in',$order_id)
                    ->page($page,$size)
                    ->select();


                foreach ($list as $k=>$v){
                    $list[$k]['day'] = diff_date($v['start_time'],$v['end_time']);   //预租天数
                    $list[$k]['occupant_info'] = Db::name('short_occupant')->field('realname,id_card')->where('id','in',$v['occupant_id'])->select();
                    $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                    $list[$k]['house_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['id'])->select();
                }

                break;
            case 3:
                //查询出被当天被预定的房源id
                $id = Db::name('short_order')
                    ->where('start_time','<=',$today)
                    ->where('end_time','>',$today)
                    ->where('status',3)
                    ->where($where)
                    ->column('short_id');

                //查询出被当天被预定的订单id
                $order_id = Db::name('short_order')
                    ->where('start_time','<=',$today)
                    ->where('end_time','>',$today)
                    ->where('status',3)
                    ->where($where)
                    ->column('id');

                $where['id'] = ['in',$id];

                $total = Db::name('house_short')
                    ->where($where)
                    ->count();

                $list = Db::view('house_short','id,title,description,rent,bedroom_number,parlour_number,toilet_number,people_number')
                    ->view('short_order','id as order_id,order_no,username,mobile,start_time,end_time,pay_money,pay_type,room_number,people_number as rz_people_number,occupant_id,deposit_money,create_time','short_order.short_id=house_short.id','left')
                    ->where('house_short.id','in',$id)
                    ->where('short_order.id','in',$order_id)
                    ->page($page,$size)
                    ->select();
                foreach ($list as $k=>$v){
                    $list[$k]['day'] = diff_date($v['start_time'],$v['end_time']);   //预租天数
                    $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                    $list[$k]['occupant_info'] = Db::name('short_occupant')->field('realname,id_card')->where('id','in',$v['occupant_id'])->select();
                    $list[$k]['house_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['id'])->select();
                }
                break;
            case 4:
                //查询出被当天被预定的房源id
                $id = Db::name('short_order')
                    ->where('end_time','<=',$today)
                    ->where('status',3)
                    ->where($where)
                    ->column('short_id');

                //查询出被当天被预定的订单id
                $order_id = Db::name('short_order')
                    ->where('end_time','<=',$today)
                    ->where('status',3)
                    ->where($where)
                    ->column('id');

                $where['id'] = ['in',$id];

                $total = Db::name('house_short')
                    ->where($where)
                    ->count();

                $list = Db::view('house_short','id,title,description,rent,bedroom_number,parlour_number,toilet_number,people_number')
                    ->view('short_order','id as order_id,order_no,username,mobile,start_time,end_time,pay_money,pay_type,room_number,people_number as rz_people_number,occupant_id,deposit_money,refund_deposit,create_time','short_order.short_id=house_short.id','left')
                    ->where('house_short.id','in',$id)
                    ->where('short_order.id','in',$order_id)
                    ->page($page,$size)
                    ->select();

                foreach ($list as $k=>$v){
                    $list[$k]['day'] = diff_date($v['start_time'],$v['end_time']);   //预租天数
                    $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                    $list[$k]['occupant_info'] = Db::name('short_occupant')->field('realname,id_card')->where('id','in',$v['occupant_id'])->select();
                    $list[$k]['house_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['id'])->select();
                }
                break;
            default:
                return \json(self::callback(0,'参数错误'),400);
                break;
        }


        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }

    /**
     * 修改为待出租
     */
    public function modifyDcz(){
        try{
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

            if (!$order_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order = ShortOrder::get($order_id);

            if (!$order){
               throw new \Exception('订单不存在');
            }

            if ($order->status != 2){
                return \json(self::callback(0,'该订单不支持该操作'));
            }

            $order->status = -2;
            $order->cancel_time = time();

            //todo 此处原路退款
            if ($order->pay_type == '支付宝') {
                $alipay = new AliPay();
                $res = $alipay->alipay_refund($order->order_no,$order->pay_money);
            }elseif ($order->pay_type == '微信'){
                $wxpay = new WxPay();
                $res = $wxpay->wxpay_refund($order->order_no,$order->pay_money,$order->pay_money);
            }

            if ($res !== true){
                return \json(self::callback(0,'改为待出租退款失败'));
            }

            $result = $order->allowField(true)->save();

            if (!$result){
                return \json(self::callback(0,'操作失败'));
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改为出租中
     */
    public function modifyCzz(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        $deposit_money = input('deposit_money');

        if (!$order_id || !$deposit_money){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = ShortOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status != 2){
            return \json(self::callback(0,'该订单不支持该操作'));
        }

        $order->status = 3;
        $order->deposit_money = $deposit_money;

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 修改为可租
     */
    public function modifyKz(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        $refund_deposit = input('refund_deposit');

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = ShortOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status != 3){
            return \json(self::callback(0,'该订单不支持该操作'));
        }

        $order->status = 4;
        $order->refund_deposit = $refund_deposit;

        $result = $order->allowField(true)->save();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 短租业绩
     */
    public function shortAchievement(){
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $total = Db::view('short_order','start_time,end_time,username,mobile,pay_time,pay_money')
            ->view('house_short','title,bedroom_number,parlour_number,toilet_number,people_number','house_short.id=short_order.short_id','left')
            ->where('short_order.sale_id',$userInfo['sale_id'])
            ->where('short_order.status','>',1)
            ->count();

        $list = Db::view('short_order','start_time,end_time,username,mobile,pay_time,pay_money')
            ->view('house_short','id as short_id,title,bedroom_number,parlour_number,toilet_number,people_number','house_short.id=short_order.short_id','left')
            ->where('short_order.sale_id',$userInfo['sale_id'])
            ->where('short_order.status','>',1)
            ->page($page,$size)
            ->order('short_order.pay_time','desc')
            ->select();

        foreach ($list as $k=>$v){
            $list[$k]['house_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['short_id'])->select();
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }
}