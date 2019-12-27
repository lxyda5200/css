<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/20
 * Time: 17:03
 */

namespace app\user_v5\controller;
use app\common\controller\Base;
use app\user_v5\model\HouseTag;
use app\user_v5\model\LongOrder;
use app\user_v5\model\RoomConfig;
use app\user_v5\validate\HouseEntrust;
use think\Db;
use think\response\Json;
use app\user_v5\model\House as HouseModel;
use app\user_v5\model\User as UserModel;
use app\user_v5\common\User as UserFunc;
use app\user_v5\model\HouseEntrust as HouseEntrustModel;

class House extends Base
{
    /**
     * 长租房源列表 - 筛选
     */
    public function houseList(HouseModel $HouseModel){

        try{
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $city_id = input('city_id') ? intval(input('city_id')) : 0 ;
            if (!$city_id){
                return \json(self::callback(0,'参数错误'),400);
            }

            $where['city_id'] = ['eq',$city_id];

            $where['status'] = ['eq',3];   //房源状态 1待提交 2待审核 3审核成功 4审核失败 5已下架
            $where['renting_status'] = ['eq',1];     //出租状态 1待租 2已定 3已租
            $where['is_delete'] = ['eq',0];

            $type = input('type') ? intval(input('type')) : 0 ;   //类型 1整租 2合租 3整合租
            switch ($type){
                case 1:
                    $where['type'] = ['in','1,3'];
                    break;
                case 2:
                    $where['type'] = ['in','2,3'];
                    break;
            }

            $keywords = input('keywords');  //小区搜索
            #$address = input('keywords');   //街道地址搜索
            if ($keywords){
                $where['xiaoqu_name|address'] = ['like',"%$keywords%"];
            }
            /*if ($address){
                $where['address'] = ['eq',$address];
            }*/

            $area_id1 = input('area_id1');   //区域id
            if ($area_id1){
                $where['area_id1'] = ['eq',$area_id1];
            }

            $area_id2 = input('area_id2');   //子区域id
            if ($area_id2){
                $where['area_id2'] = ['eq',$area_id2];
            }

            $lines_id = input('lines_id');  //地铁线路id
            if ($lines_id){
                $where['lines_id'] = ['eq',$lines_id];
            }

            $station_id = input('station_id');  //地铁站id
            if ($station_id){
                $where['station_id'] = ['eq',$station_id];
            }

            $bedroom = input('bedroom');  //户型
            if ($bedroom){

                $where['bedroom_number'] = ['eq',$bedroom];

                //五室及以上
                if ($bedroom>5){

                    $where['bedroom_number'] = ['egt',$bedroom];
                }
            }

            $rent = input('rent');  //租金  1-6
            switch ($rent){
                case 1:
                    $where['rent'] = ['lt',1000];
                    break;
                case 2:
                    $where['rent'] = ['between','1000,1500'];
                    break;
                case 3:
                    $where['rent'] = ['between','1500,2000'];
                    break;
                case 4:
                    $where['rent'] = ['between','2000,2500'];
                    break;
                case 5:
                    $where['rent'] = ['between','2500,3000'];
                    break;
                case 6:
                    $where['rent'] = ['et','3000'];
                    break;
                default:
                    break;
            }

            $acreage = input('acreage');  //面积  1-6
            switch ($acreage){
                case 1:
                    $where['acreage'] = ['lt',40];
                    break;
                case 2:
                    $where['acreage'] = ['between','40,60'];
                    break;
                case 3:
                    $where['acreage'] = ['between','60-80'];
                    break;
                case 4:
                    $where['acreage'] = ['between','80,100'];
                    break;
                case 5:
                    $where['acreage'] = ['between','100,120'];
                    break;
                case 6:
                    $where['acreage'] = ['egt',120];
                    break;
            }

            $orientation = input('orientation');  //朝向
            if ($orientation){
                $where['orientation'] = ['eq',$orientation];
            }

            $tag_id = input('tag_id');  //亮点 标签
            if ($tag_id){
                $where['tag_id'] = ['like',"%$tag_id%"];
            }

            $floor_type = input('floor_type');  //楼层 1低楼层 2中楼层 3高楼层
            if ($floor_type){
                $where['floor_type'] = ['eq',$floor_type];
            }

            $is_elevator = input('is_elevator');  //电梯 1有电梯 0无电梯
            if ($is_elevator != ""){
                $where['is_elevator'] = ['eq',$is_elevator];
            }

            $total = $HouseModel->where($where)->count();

            $list = HouseModel::getHouseList($page,$size,$where);

            if ($list){
                $list = $list->toArray();
                foreach ($list as $k=>$v){
                    $lines_name = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');
                    $list[$k]['lines_name'] = !empty($lines_name) ? $lines_name : '';
                    $list[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);;
                    unset($list[$k]['tag_id']);
                    unset($list[$k]['lines_id']);
                }

            }

            $data['max_page'] = ceil($total/$size);
            $data['total'] = $total;
            $data['list'] = $list;

            return json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }


    }


    /**
     * 地图租房
     */
    public function mapHouse(){
        try{

            $map_type = input('map_type') ? intval(input('map_type')) : 0 ;  //区域房源 2子区域房源 3小区房源

            if (!$map_type){
                return \json(self::callback(0,'参数错误'),400);
            }

            $where['status'] = ['eq',3];   //房源状态 1待提交 2待审核 3审核成功 4审核失败 5已下架
            $where['renting_status'] = ['eq',1];     //出租状态 1待租 2已定 3已租
            $where['is_delete'] = ['eq',0];

            $type = input('type') ? intval(input('type')) : 0 ;   //类型 1整租 2合租 3整合租
            switch ($type){
                case 1:
                    $where['type'] = ['in','1,3'];
                    break;
                case 2:
                    $where['type'] = ['in','2,3'];
                    break;
            }

            $acreage = input('acreage');  //面积  1-6
            switch ($acreage){
                case 1:
                    $where['acreage'] = ['lgt',40];
                    break;
                case 2:
                    $where['acreage'] = ['between','40,60'];
                    break;
                case 3:
                    $where['acreage'] = ['between','60-80'];
                    break;
                case 4:
                    $where['acreage'] = ['between','80,100'];
                    break;
                case 5:
                    $where['acreage'] = ['between','100,120'];
                    break;
                case 6:
                    $where['acreage'] = ['egt',120];
                    break;
            }

            $orientation = input('orientation');  //朝向
            if ($orientation){
                $where['orientation'] = ['eq',$orientation];
            }

            $tag_id = input('tag_id');  //亮点 标签
            if ($tag_id){
                $where['tag_id'] = ['like',"%$tag_id%"];
            }

            $floor_type = input('floor_type');  //楼层 1低楼层 2中楼层 3高楼层
            if ($floor_type){
                $where['floor_type'] = ['eq',$floor_type];
            }

            $is_elevator = input('is_elevator');  //电梯 1有电梯 0无电梯
            if ($is_elevator != ""){
                $where['is_elevator'] = ['eq',$is_elevator];
            }

            switch ($map_type){
                case 1:
                    $city_id = input('city_id');

                    if (!$city_id) return \json(self::callback(0,'参数错误'),400);

                    $data = Db::name('area')->field('id,area_name1,lng,lat')->where('city_id',$city_id)->where('pid',0)->select();
                    foreach ($data as $k=>$v){
                        $data[$k]['house_count'] = Db::name('house')->where('area_id1',$v['id'])->where($where)->count();
                        if ($data[$k]['house_count'] == 0){
                            unset($data[$k]);
                        }
                    }
                    break;
                case 2:
                    $area_id1 = input('area_id1');

                    if ($area_id1){
                        $area_where['pid'] = ['eq',$area_id1];
                    }

                    #if (!$area_id1) return \json(self::callback(0,'参数错误'),400);
                    $data = Db::name('area')->field('id,area_name2,lng,lat')->where($area_where)->select();

                    foreach ($data as $k=>$v){
                        $data[$k]['house_count'] = Db::name('house')->where('area_id2',$v['id'])->where($where)->count();
                        if ($data[$k]['house_count'] == 0){
                            unset($data[$k]);
                        }
                    }

                    break;
                case 3:

                    $area_id2 = input('area_id2');

                    #if (!$area_id2) return \json(self::callback(0,'参数错误'),400);

                    if ($area_id2){
                        $area_where['area_id2'] = ['eq',$area_id2];
                    }

                    $data = Db::name('house_xiaoqu')->field('id,xiaoqu_name,address,lng,lat')->where($area_where)->select();
                    foreach ($data as $k=>$v){
                        $data[$k]['house_count'] = Db::name('house')->where('xiaoqu_id',$v['id'])->where($where)->count();
                        if ($data[$k]['house_count'] == 0){
                            unset($data[$k]);
                        }
                    }
                    break;
            }

            $data = multi_array_sort($data,'house_count');

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 收藏
     */
    public function collection(){
        $param = $this->request->post();

        if (!$param || !$param['house_id']){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $house = HouseModel::get($param['house_id']);

        if (!$house){
            return \json(self::callback(0,'房源不存在'));
        }

        if (Db::name('house_collection')->where('house_id',$param['house_id'])->where('user_id',$userInfo['user_id'])->count()){
            return \json(self::callback(0,'该房源已收藏'));
        }

        $result = Db::name('house_collection')->insert(['user_id'=>$userInfo['user_id'],'house_id'=>$param['house_id'],'create_time'=>time()]);

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 取消收藏
     */
    public function cancelCollection(){
        $param = $this->request->post();

        if (!$param || !$param['house_id']){
            return \json(self::callback(0,'参数错误'),400);
        }

        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $house = HouseModel::get($param['house_id']);

        if (!$house){
            return \json(self::callback(0,'房源不存在'));
        }

        if (!Db::name('house_collection')->where('house_id',$param['house_id'])->where('user_id',$userInfo['user_id'])->count()){
            return \json(self::callback(0,'该房源未收藏'));
        }

        $result = Db::name('house_collection')->where('user_id',$userInfo['user_id'])->where('house_id',$param['house_id'])->delete();

        if (!$result){
            return \json(self::callback(0,'操作失败'),400);
        }

        return \json(self::callback(1,''));
    }

    /**
     * 长租房源详情
     */
    public function houseDetail(){

        $param = $this->request->post();

        if (!$param || !$param['house_id']){
            return \json(self::callback(0,'参数错误'),400);
        }

        if (isset($param['user_id']) || isset($param['token'])){
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
        }

        $data = HouseModel::getHouseDetail($param['house_id']);

        if (!$data){
            return \json(self::callback(0,'房源不存在'));
        }

        if ($data){
            #dump($data->xiaoqu);

            $data['is_collection'] = Db::name('house_collection')->where('user_id',$param['user_id'])->where('house_id',$param['house_id'])->count();
            $data['house_type'] = Db::name('house_type')->where('id',$data->house_type_id)->value('name');
            $lines_name = Db::name('subway_lines')->where('id',$data->lines_id)->value('lines_name');
            $data['lines_name'] = !empty($lines_name) ? $lines_name : '';
            $station_name = Db::name('subway_station')->where('id',$data->station_id)->value('station_name');
            $data['station_name'] = !empty($station_name) ? $station_name : '';
            $data['tag_info'] = (new HouseTag())->getTagInfo($data->tag_id);
            $data['room_config_info'] = (new RoomConfig())->getRoomConfigInfo($data->room_config_id);
            $data->house_xiaoqu = Db::name('house_xiaoqu')->where('id',$data->xiaoqu_id)->field('id,xiaoqu_name,address,lng,lat')->find();
            $data['property_info'] = Db::name('property')->field('mobile,nickname,avatar')->where('xiaoqu_id',$data->xiaoqu_id)->select();
            unset($data['tag_id']);
            unset($data['house_type_id']);
            unset($data['lines_id']);
            unset($data['station_id']);
            unset($data['room_config_id']);

        }

        return \json(self::callback(1,'',$data));

    }


    /**
     * 房屋委托
     */
    public function houseEntrust(HouseEntrust $HouseEntrust){
        try{
            $param = $this->request->post();

            if(!$param) {
                return json(self::callback(0,'参数错误'),400);
            }

            if (!$HouseEntrust->check($param,[])) {
                throw new \Exception($HouseEntrust->getError());
            }

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            //根据地址获取经纬度
            $latlng = addresstolatlng($param['province'].$param['city'].$param['area'].$param['address']);

            $param['lng'] = $latlng[0];
            $param['lat'] = $latlng[1];
            $param['type'] = 1;  //委托类型 1用户委托 2物业委托
            $param['param_id'] = $userInfo['user_id'];
            //todo 按距离分配给商家 否则随机分配
            $shop_id = $this->getShopId($param['lng'],$param['lat']);

            if (!$shop_id){
                $shop = Db::name('shop_info')->order('rand()')->find();
                $shop_id = $shop['id'];
            }

            $param['shop_id'] = $shop_id;

            $result = (new HouseEntrustModel())->allowField(true)->save($param);

            if (!$result){
                return \json(self::callback(0,'操作失败'),400);
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 根据经纬度返回最近的商家id
     * @param $lng
     * @param $lat
     */
    public function getShopId($lng,$lat){
        $field = "ROUND(6378.138 * 2 * ASIN(
            SQRT(
                POW(
                    SIN(
                        (
                            $lat * PI() / 180 - lat * PI() / 180
                        ) / 2
                    ),
                    2
                ) + COS($lat * PI() / 180) * COS(lat * PI() / 180) * POW(
                    SIN(
                        (
                            $lng * PI() / 180 - lng * PI() / 180
                        ) / 2
                    ),
                    2
                )
            )
        ) * 1000
    ) AS m";

        $shop = Db::name('shop_info')->field('id,'.$field)->order('m','asc')->limit(0,1)->select();


        return $shop[0]['id'];
    }

    /**
     * 按小区/街道搜索长租房源
     */
    public function searchHouse(){
        $keywords = input('keywords');
        if (!$keywords){
            return \json(self::callback(0,'参数错误'),400);
        }

        $where1['xiaoqu_name'] = ['like',"%$keywords%"];
        $where2['address'] = ['like',"%$keywords%"];


        $houseInfo = Db::name('house')->where($where1)->whereOr($where2)->field('xiaoqu_name,address')->select();

        if (!$houseInfo){
            return \json(self::callback(1,''));
        }

        $houseInfo = array_quchong($houseInfo);

        foreach ($houseInfo as $k=>$v){
            $houseInfo[$k]['house_count'] = Db::name('house')->where('xiaoqu_name','eq',$v['xiaoqu_name'])->where('address','eq',$v['address'])->count();
        }

        return \json(self::callback(1,'',$houseInfo));
    }

    /**
     * 推荐房源列表
     */
    public function recommendHouseList(HouseModel $HouseModel){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('szie') ? intval(input('size')) : 10 ;
        $city_id = input('city_id') ? intval(input('city_id')) : 0 ;

        if ($city_id){
            $where['city_id'] = ['eq',$city_id];
        }

        $where['status'] = ['eq',3];
        $where['renting_status'] = ['eq',1];
        $where['is_recommend'] = ['eq',1];
        $where['is_delete'] = ['eq',0];

        $total = $HouseModel->where($where)->count();

        $list = HouseModel::getHouseList($page,$size,$where);

        if ($list){
            $list = $list->toArray();
            foreach ($list as $k=>$v){
                $lines_name = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');
                $list[$k]['lines_name'] = !empty($lines_name) ? $lines_name : '';
                $list[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);
                unset($list[$k]['tag_id']);
                unset($list[$k]['lines_id']);
            }
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return json(self::callback(1,'',$data));
    }

    /**
     * 预定租房 提交订单
     */
    public function reserveRenting(){
        try{
            $param = $this->request->post();

            if(!$param || !$param['username'] || !$param['mobile'] || !$param['house_id'] || !$param['reserve_money']) {
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $house_info = \app\user\model\House::get($param['house_id']);

            if (!$house_info){
                throw new \Exception('房源不存在');
            }

            if ($house_info->renting_status != 1 || $house_info->status != 3){
                throw new \Exception('该房源不能预订');
            }

            if (Db::name('long_order')->where('status',1)->where('house_id',$param['house_id'])->count() ){
                throw new \Exception('该房源已被预订');
            }

            $param['order_no'] = build_order_no('L');
            $param['sale_id'] = $house_info->sale_id;
            $param['shop_id'] = Db::name('sale')->where('sale_id',$house_info->sale_id)->value('shop_id');
            $param['create_time'] = time();
            $param['status'] = 1;

            Db::startTrans();
            $id = Db::name('long_order')->strict(false)->insertGetId($param);
            Db::name('house')->where('id',$param['house_id'])->setField('renting_status',2);

           # $result2 = Db::name('house')->where('id',$param['house_id'])->setField('renting_status',1);

            if (!$id){
                Db::rollback();
                return \json(self::callback(0,'操作失败'));
            }

            Db::commit();
            return \json(self::callback(1,'',['id'=>$id,'order_no'=>$param['order_no']]));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取支付信息
     */
    public function getPayInfo(LongOrder $orderModel){
        try{
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $data['order_no'] = $order_no = input('order_no');
            $pay_type = input('pay_type') ? intval(input('pay_type')) : 0; //支付方式  1支付宝 2微信

            if(!$order_no || !$pay_type){
                return json(self::callback(0, "参数错误"), 400);
            }

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
                    $notify_url = SERVICE_FX."/user/ali_pay/long_alipay_notify";
                    $aliPay = new AliPay();
                    $data['alipay_order_info'] = $aliPay->appPay($order_no,$order->reserve_money,$notify_url);

                    break;
                case 2:
                    $order->pay_type = "微信";
                    $notify_url = SERVICE_FX."/user/wx_pay/long_wxpay_notify";
                    $wxPay = new WxPay();
                    $data['wxpay_order_info'] = $wxPay->getOrderSign($order_no,$order->reserve_money,$notify_url);
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
     * 长租订单列表
     */
    public function orderList(){

        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $total = Db::name('long_order')->where('user_id',$userInfo['user_id'])->where('is_delete',0)->count();

        $list = Db::view('long_order','id,order_no,reserve_money,house_id,status,renting_status')
            ->view('house','title,rent,type,bedroom_number,parlour_number,toilet_number,acreage','house.id = long_order.house_id','left')
            ->where('long_order.user_id',$userInfo['user_id'])
            ->where('long_order.is_delete',0)
            ->page($page,$size)
            ->order('long_order.create_time','desc')
            ->select();

        foreach ($list as $k=>$v){
            $list[$k]['house_img'] = Db::name('house_img')->field('id,img_url')->where('house_id',$v['house_id'])->select();
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = LongOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status == 1){

            $result = LongOrder::destroy($order_id);
            Db::name('house')->where('id',$order['house_id'])->setField('renting_status',1);

        } else{
            return \json(self::callback(0,'该订单不支持此操作'));
        }

        if (!$result){
            return \json(self::callback(0,'操作失败'));
        }

        return \json(self::callback(1,''));
    }

    /**
     * 删除订单-长租
     */
    public function deleteOrder(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = Db::name('long_order')->where('id',$order_id)->where('is_delete',0)->find();

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        $result = Db::name('long_order')->where('id',$order_id)->setField('is_delete',1);

        if (!$result){
            return \json(self::callback(0,'操作失败'));
        }

        return \json(self::callback(1,''));
    }

    /**
     * 订单详情
     */
    public function orderDetail(){
        $order_id = input('order_id') ? intval(input('order_id')) : 0 ;

        if (!$order_id){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = UserFunc::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $data = Db::name('long_order')->field('id,order_no,sale_id,pay_type,reserve_money,house_id,status,renting_status,username,mobile,create_time')->where('id',$order_id)->find();

        if (!$data){
            return \json(self::callback(0,'订单不存在'));
        }

        $data['create_time'] = date('Y/m/d H:i',$data['create_time']);

        //获取房源信息
        $house_info = HouseModel::with('houseImg')->field('id,title,rent,rent_mode,type,bedroom_number,parlour_number,toilet_number,acreage')->where('id',$data['house_id'])->find();

        $data['house_info'] = !empty($house_info) ? $house_info : [] ;

        //获取销售信息
        $data['sale_info'] = Db::name('sale')->field('nickname,mobile,avatar')->where('sale_id',$data['sale_id'])->find();

        if ($data['status'] == 2 && $data['renting_status'] >0 ){
            $record = Db::name('long_rent_record')->field('money,create_time,end_time')->where('order_id',$order_id)->order('create_time','asc')->select();

            foreach ($record as $k=>$v) {
                $record[$k]['create_time'] = date('Y-m-d');
            }

            $data['rent_record'] = $record;

        }
        return \json(self::callback(1,'',$data));
    }


}