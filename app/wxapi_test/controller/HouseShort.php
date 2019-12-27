<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/14
 * Time: 11:32
 */

namespace app\wxapi_test\controller;


use app\common\controller\Base;
use app\wxapi_test\model\HouseImg;
use app\wxapi_test\model\HouseTag;
use app\wxapi_test\model\RoomConfig;
use app\wxapi_test\model\ShortComment;
use app\wxapi_test\model\ShortOrder;
use app\wxapi_test\model\ShortTrafficTag;
use think\Db;
use think\response\Json;

class HouseShort extends Base
{

    /**
     * 入住须知
     */
    public function shortRule(){

        $short_id = input('short_id');

        $data = Db::name('short_rule')->where('short_id',$short_id)->select();
        return json(self::callback(1,'',$data));
    }

    /**
     * 搜索地址
     */
    public function searchAddress(){
        $keywords = input('keywords');
        if ($keywords){
            $where['address'] = ['like',"%$keywords%"];
        }

        $city_id = input('city_id');
        if ($city_id){
            $where['city_id'] = ['eq',$city_id];
        }


        $data = Db::name('house_xiaoqu')->field('address')->where($where)->select();

        return \json(self::callback(1,'',$data));
    }

    /**
     * 短租房源列表
     */
    public function houseList(){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;

        $house_where['status'] = ['eq',2];   //房源状态 1待审核  2已上架 3已下架



        $start_time = input('start_time');
        $end_time = input('end_time');
        $end_time = date('Y-m-d',strtotime("-1 day",strtotime($end_time)));   //减一天

        $is_recommend = input('is_recommend');
        if ($is_recommend){
            $house_where['is_recommend'] = ['eq',1];
        }

        $city_id = input('city_id');
        if ($city_id){
            $house_where['city_id'] = ['eq',$city_id];
        }

        $life_id = input('life_id');
        if ($life_id){
            $house_where['life_id'] = ['eq',$life_id];
        }

        $house_type_id = input('house_type_id');
        if ($house_type_id){
            $house_where['house_type_id'] = ['eq',$house_type_id];
        }

        $keywords = input('keywords');  //小区、街道地址搜索

        if ($keywords){
            $house_where['address|xiaoqu_name'] = ['like',"%$keywords%"];
        }

        $area_id1 = input('area_id1');   //区域id
        if ($area_id1){
            $house_where['area_id1'] = ['eq',$area_id1];
        }

        $area_id2 = input('area_id2');   //子区域id
        if ($area_id2){
            $house_where['area_id2'] = ['eq',$area_id2];
        }

        $lines_id = input('lines_id');  //地铁线路id
        if ($lines_id){
            $house_where['lines_id'] = ['eq',$lines_id];
        }

        $station_id = input('station_id');  //地铁站id
        if ($station_id){
            $house_where['station_id'] = ['eq',$station_id];
        }

        $max_rent = input('max_rent');  //每晚最大价格
        $min_rent = input('min_rent');  //每晚最小价格
        if ($max_rent && $min_rent){
            $house_where['rent'] = ['between',"$min_rent,$max_rent"];
        }

        $bedroom_number = input('bedroom_number');  //户型
        if ($bedroom_number){
            $house_where['bedroom_number'] = ['eq',$bedroom_number];
        }

        $people_number = input('people_number') ? intval(input('people_number')) : 0 ;  //人数
        if ($people_number){
            $house_where['people_number'] = ['egt',$people_number];
        }

        $bed_number = input('bed_number') ? intval(input('bed_number')) : 0 ;  //床位数
        if ($bed_number){
            $house_where['bed_number'] = ['eq',$bed_number];
        }

        $tag_id = input('tag_id');  //特色 标签
        if ($tag_id){
            $house_where['tag_id'] = ['like',"%$tag_id%"];
        }

        $room_config_id = input('room_config_id');  //房间配置id
        if ($room_config_id){
            $house_where['room_config_id'] = ['like',"%$room_config_id%"];
        }

        $traffic_tag_id = input('traffic_tag_id');  //交通与位置id
        if ($traffic_tag_id){
            $house_where['traffic_tag_id'] = ['like',"%$traffic_tag_id%"];
        }

        //日期选择
        if ($start_time && $end_time){
            //查询已被预订的短租房源
            #$where['start_time'] = array('between',"$start_time,$end_time");
            #$whereOr['end_time'] = array('between',"$start_time,$end_time");
            $where = "(`start_time` BETWEEN '{$start_time}' AND '{$end_time}' 
OR `ruzhu_end_time` BETWEEN '{$start_time}' AND '{$end_time}')";
            $id = Db::name('short_order')->where($where)->where('status','in','1,2,3')->column('short_id');
            $house_where['id'] = ['not in',$id];
        }

        $model = new \app\user\model\HouseShort();

        $total = $model->where($house_where)->count();


        $list = \app\user\model\HouseShort::getHouseShortList($page,$size,$house_where);

        if ($list){
            $list = $list->toArray();
            foreach ($list as $k=>$v){
                $list[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);

                $list[$k]['traffic_tag_info'] = (new ShortTrafficTag())->getTrafficTagInfo($v['traffic_tag_id']);

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


        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return json(self::callback(1,'',$data));
    }


    /**
     * 短租房源详情
     */
    public function houseDetail(){
        $param = $this->request->post();

        if (!$param || !$param['short_id']){
            return \json(self::callback(0,'参数错误'),400);
        }

        if (isset($param['user_id']) || isset($param['token'])){
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
        }

        $data = \app\user\model\HouseShort::getHouseShortDetail($param['short_id']);

        if (!$data){
            return \json(self::callback(0,'房源不存在'));
        }

        if ($data){
            $data['is_collection'] = Db::name('short_collection')->where('user_id',$param['user_id'])->where('short_id',$param['short_id'])->count();
            $data['house_type'] = Db::name('house_type')->where('id',$data->house_type_id)->value('name');
            $lines_name = Db::name('subway_lines')->where('id',$data->lines_id)->value('lines_name');
            $data['lines_name'] = !empty($lines_name) ? $lines_name : '';
            $station_name = Db::name('subway_station')->where('id',$data->station_id)->value('station_name');
            $data['station_name'] = !empty($station_name) ? $station_name : '';
            $data['tag_info'] = (new HouseTag())->getTagInfo($data->tag_id);
            $data['room_config_info'] = (new RoomConfig())->getRoomConfigInfo($data->room_config_id);
            $data['traffic_tag_info'] = (new ShortTrafficTag())->getTrafficTagInfo($data->traffic_tag_id);
            $data['sale_info'] = Db::name('sale')->field('mobile,nickname,avatar')->where('sale_id',$data->sale_id)->find();
            $data['reserve_info'] = Db::name('short_order')
                ->field('start_time,end_time')
                ->where('end_time','>=',date('Y-m-d'))
                ->where('short_id',$param['short_id'])
                ->where('status','in','1,2,3')
                ->select();

            $data['comment_number'] = Db::name('short_comment')->where('short_id',$param['short_id'])->count();
            $hygiene_score = Db::name('short_comment')->where('short_id',$param['short_id'])->avg('hygiene_score');
            $service_score = Db::name('short_comment')->where('short_id',$param['short_id'])->avg('service_score');
            $position_score = Db::name('short_comment')->where('short_id',$param['short_id'])->avg('position_score');
            $renovation_score = Db::name('short_comment')->where('short_id',$param['short_id'])->avg('renovation_score');

            $avg_score = ($hygiene_score + $service_score + $position_score + $renovation_score)/4;

            $data['avg_score'] = round($avg_score,1);

            $data['house_xiaoqu'] = Db::name('house_xiaoqu')->field('id,xiaoqu_name,address,lng,lat')->where('id',$data->xiaoqu_id)->find();

            unset($data['tag_id']);
            unset($data['traffic_tag_id']);
            unset($data['house_type_id']);
            unset($data['lines_id']);
            unset($data['station_id']);
            unset($data['room_config_id']);

        }

        return \json(self::callback(1,'',$data));


    }

    /**
     * 短租评论列表
     */
    public function commentList(){
        $short_id = input('?post.short_id') ? intval(input('short_id')) : 0 ;
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;

        if (!$short_id){
            return json(self::callback(0,'参数错误'),400);
        }

        $house_short = \app\user\model\HouseShort::get($short_id);
        if (!$house_short){
            return json(self::callback(0,'该短租房源不存在'));
        }

        $where['short_id'] = ['eq',$short_id];

        $total = ShortComment::where($where)->count();

        $list = ShortComment::getCommentList($where,$page,$size,['create_time'=>'desc']);
        if ($list){
            $list = $list->toArray();
            foreach ($list as $k=>$v){
                $score = $v['hygiene_score'] + $v['service_score'] + $v['position_score'] + $v['renovation_score'];
                $list[$k]['avg_score'] = round($score/4,1);
            }
        }

        $score = $avg_hygiene_score = Db::name('short_comment')->where('short_id',$short_id)->avg('hygiene_score');
        $score += $avg_service_score = Db::name('short_comment')->where('short_id',$short_id)->avg('service_score');
        $score += $avg_position_score = Db::name('short_comment')->where('short_id',$short_id)->avg('position_score');
        $score += $avg_renovation_score = Db::name('short_comment')->where('short_id',$short_id)->avg('renovation_score');

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['avg_hygiene_score'] = round($avg_hygiene_score,1);
        $data['avg_service_score'] = round($avg_service_score,1);
        $data['avg_position_score'] = round($avg_position_score,1);
        $data['avg_renovation_score'] = round($avg_renovation_score,1);
        $data['total_avg_score'] = round($score/4,1);
        $data['list'] = $list;

        return json(self::callback(1,'',$data));
    }


    /**
     * 添加入住人
     */
    public function addPeople(){
        try{

            $realname = input('realname');
            $id_card = input('id_card');

            if (!$realname || !$id_card){
                return \json(self::callback(0,'参数错误'),400);
            }

            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $result = Db::name('short_occupant')->strict(false)->insert(['realname'=>$realname,'id_card'=>$id_card,'user_id'=>$userInfo['user_id'],'create_time'=>time()]);

            if (!$result){
                return \json(self::callback(0,'操作失败'));
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 删除入住人
     */
    public function deletePeople(){
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $id = input('id');

        if (!$id){ return \json(self::callback(0,'参数错误'),400); }

        $result = Db::name('short_occupant')->where('id',$id)->setField('is_delete',1);

        if (!$result){
            return \json(self::callback(0,'操作失败'));
        }

        return \json(self::callback(1,''));

    }

    /**
     * 入住人列表
     */
    public function peopleList(){
        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $data = Db::name('short_occupant')->field('id,realname,id_card')->where('user_id',$userInfo['user_id'])->where('is_delete',0)->select();

        return \json(self::callback(1,'',$data));

    }

    /**
     * 预定短租 提交订单
     */
    public function reserve(){
        try{
            $param = $this->request->post();

            if (!$param || !$param['start_time'] || !$param['end_time'] || !$param['people_number'] || !$param['mobile'] || !$param['occupant_id'] || !$param['pay_money']){
                return \json(self::callback(0,'参数错误'),400);
            }

            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $house_info = Db::name('house_short')->where('id',$param['short_id'])->where('status',2)->find();

            if (!$house_info){
                throw new \Exception('该房源不存在');
            }

            if ($house_info['status'] != 2){
                throw new \Exception('该房源不能预订');
            }

            $param['ruzhu_end_time'] = date('Y-m-d',strtotime("$param[end_time] -1 day"));
            $day = diff_date($param['start_time'],$param['end_time']);   //预租天数
            $money = $day * $house_info['rent'];


            if ($param['pay_money'] != $money){
                throw new \Exception('支付金额错误');
            }

            //查询已被预定的房源
            $where = "(`start_time` BETWEEN '{$param['start_time']}' AND '{$param['ruzhu_end_time']}' 
OR `ruzhu_end_time` BETWEEN '{$param['start_time']}' AND '{$param['ruzhu_end_time']}')";
            $id = Db::name('short_order')->where($where)->where('status','in','1,2,3')->column('short_id');

            if (in_array($param['short_id'],$id) == true){
                throw new \Exception('当前入住时间不能预定');
            }

            $param['order_no'] = build_order_no('L');
            $param['sale_id'] = $house_info['sale_id'];
            $param['shop_id'] = Db::name('sale')->where('sale_id',$house_info['sale_id'])->value('shop_id');
            $param['create_time'] = time();
            $param['status'] = 1;
            $param['pay_type'] = '';
            $param['pay_time'] = 0;
            $param['cancel_time'] = 0;
            $param['is_delete'] = 0;

            $id = Db::name('short_order')->strict(false)->insertGetId($param);

            if (!$id){
                return \json(self::callback(0,'操作失败'));
            }

            return \json(self::callback(1,'',['id'=>$id,'order_no'=>$param['order_no'],'order'=>$param['order_no']]));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取支付信息
     */
    public function getPayInfo(){
        try{
            //token 验证
            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $data['order_no'] = $order_no = input('order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

            $orderModel = new ShortOrder();
            $order = $orderModel->where('order_no',$order_no)->find();
            $data['order_id'] = $order->id;

            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->status != 1){
                throw new \Exception('订单不支持该操作');
            }

            switch($pay_type){
                case 1:
                    $order->pay_type = "支付宝";
                    $notify_url = SERVICE_FX."/user/ali_pay/short_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->pay_money,$notify_url);

                    break;
                case 2:
                    $order->pay_type = "微信";
                    $notify_url = SERVICE_FX."/user/wx_pay/short_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$order->pay_money,$notify_url);
                    break;
                default:
                    throw new \Exception('支付方式错误');
                    break;
            }

            $order->allowField(true)->save();

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 短租订单列表
     */
    public function orderList(){
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;


        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $total = Db::name('short_order')->where('user_id',$userInfo['user_id'])->where('is_delete',0)->count();

        $list = Db::view('short_order','id,order_no,pay_money,start_time,end_time,short_id,status')
            ->view('house_short','title,rent,bedroom_number,parlour_number,toilet_number,acreage','house_short.id = short_order.short_id','left')
            ->where('short_order.user_id',$userInfo['user_id'])
            ->page($page,$size)
            ->order('short_order.create_time','desc')
            ->select();

        foreach ($list as $k=>$v){
            #$list[$k]['end_time'] = date('Y-m-d',strtotime("$v[end_time] +1 day"));
            $day = diff_date($v['start_time'], $v['end_time']);
            $list[$k]['day'] = $day;
            $list[$k]['house_short_img'] = Db::name('house_short_img')->field('id,img_url')->where('short_id',$v['short_id'])->select();
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }

    /**
     * 订单详情-短租
     */
    public function orderDetail(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = ShortOrder::where('id',$order_id)->field('id,order_no,sale_id,short_id,room_number,people_number,pay_money,pay_type,start_time,end_time,username,mobile,occupant_id,create_time,status')->find();
        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        $day = diff_date($order['start_time'], $order['end_time']);
        $order['day'] = $day;

        $house_info = \app\user\model\HouseShort::with('houseShortImg')->field('id,title,rent,bedroom_number,parlour_number,toilet_number,acreage')->where('id',$order['short_id'])->find();

        $order['house_info'] = !empty($house_info) ? $house_info : [] ;

        $order['occupant_info'] = Db::name('short_occupant')->field('realname,id_card')->where('id','in',$order['occupant_id'])->select();

        //获取销售信息
        $order['sale_info'] = Db::name('sale')->field('nickname,mobile,avatar')->where('sale_id',$order['sale_id'])->find();

        return \json(self::callback(1,'',$order));
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = ShortOrder::get($order_id);
        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status == 1){

            $result = ShortOrder::destroy($order_id);

            if (!$result){
                return \json(self::callback(0,'操作失败'));
            }

        } elseif ($order->status == 2){
            $order->status = -2;
            $order->cancel_time = time();
            //是否全额退款
            $order->is_refund = 0;

            $time1 = time();
            $day = 60 * 60 * 24 * 3;
            $time2 = $order->start_time;
            $time2 = strtotime($time2);

            if (($time2 - $time1) >= $day) {
                //todo  判断是否3天内取消订单 如果是则此处加退款业务

                $order->is_refund = 1;

                if ($order->pay_type == '支付宝') {
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order->order_no,$order->pay_money);
                }elseif ($order->pay_type == '微信'){
                    $wxpay = new WxPay();
                    $res = $wxpay->wxpay_refund($order->order_no,$order->pay_money,$order->pay_money);
                }


                if ($res !== true){
                    return \json(self::callback(0,'取消订单退款失败'));
                }
            }

            $result = $order->allowField(true)->save();

            if (!$result){
                return \json(self::callback(0,'操作失败'));
            }

        } else{
            return \json(self::callback(0,'该订单不支持此操作'));
        }



        return \json(self::callback(1,''));

    }

    /**
     * 删除订单
     */
    public function deleteOrder(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = ShortOrder::get($order_id);
        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status != 4){
            return \json(self::callback(0,'该订单不支持此操作'));
        }

        $result = ShortOrder::where('id',$order_id)->setField('is_delete',1);

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 评论短租
     */
    public function comment(){

        try{
            $param = $this->request->post();

            if (!$param || !$param['order_id'] || !$param['content'] || !$param['hygiene_score'] || !$param['service_score'] || !$param['position_score'] || !$param['renovation_score']){
                return \json(self::callback(0,'参数错误'),400);
            }

            $userInfo = \app\user\common\User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order = ShortOrder::get($param['order_id']);
            if (!$order){
                throw new \Exception('订单不存在');
            }

            if ($order->status != 4){
                throw new \Exception('该订单不支持此操作');
            }

            $param['short_id'] = $order->short_id;

            $model = new ShortComment();
            $result1 = $model->allowField(true)->save($param);

            $result2 = Db::name('short_order')->where('id',$param['order_id'])->setField('status',5);

            if (!$result1 || !$result2){
                return \json(self::callback(0,'操作失败'));
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 收藏短租
     */
    public function collection(){
        $short_id = input('short_id') ? intval(input('short_id')) : 0 ;

        if (!$short_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        /*if (!Db::name('house_short')->where('status',1)->where('id',$short_id)->count()){
            return \json(self::callback(0,'该房源不存在'));
        }*/

        if (Db::name('short_collection')->where('user_id',$userInfo['user_id'])->where('short_id',$short_id)->count()){
            return \json(self::callback(0,'该房源已收藏'));
        }

        $result = Db::name('short_collection')->insert(['user_id'=>$userInfo['user_id'],'short_id'=>$short_id,'create_time'=>time()]);

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 取消收藏
     */
    public function cancelCollection(){
        $short_id = input('short_id') ? intval(input('short_id')) : 0 ;

        if (!$short_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = \app\user\common\User::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        if (!Db::name('house_short')->where('status',1)->where('id',$short_id)->count()){
            return \json(self::callback(0,'该房源不存在'));
        }

        if (!Db::name('short_collection')->where('user_id',$userInfo['user_id'])->where('short_id',$short_id)->count()){
            return \json(self::callback(0,'该房源未收藏'));
        }

        $result = Db::name('short_collection')->where('user_id',$userInfo['user_id'])->where('short_id',$short_id)->delete();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

}