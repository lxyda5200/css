<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/30
 * Time: 9:57
 */

namespace app\sale\controller;


use app\common\controller\Base;
use app\sale\model\LongOrder;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\user\model\HouseTag;
use app\user\model\RoomConfig;
use think\Db;
use think\response\Json;

class House extends Base
{

    /**
     * 委托房源列表
     */
    public function entrustHouseList(){
        $param = $this->request->post();

        $status = isset($param['status']) ? intval($param['status']) : 0 ;  //0待收录 1待提交 2审核中 3已收录 4未通过

        if (!$param){
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        if ($status == 0){

            $data = Db::name('house_entrust')->field('id,username,mobile,address,description,type')->where('sale_id',$param['sale_id'])->where('status',0)->select();

        }else{

            $data = [];
            $house_info = Db::name('house')
                ->alias('h')
                ->join('subway_lines lines','lines.id = h.lines_id','left')
                ->field('h.id,h.title,h.bedroom_number,h.parlour_number,h.acreage,h.entrust_id,h.lines_id,h.rent,h.tag_id,lines.lines_name')
                ->where('h.entrust_id','neq',0)
                ->where('h.sale_id',$param['sale_id'])
                ->where('h.status',$status)
                ->select();
            foreach ($house_info as $k=>$v){
                $house_info[$k]['tag_info'] = Db::name('house_tag')->whereIn('id',$v['tag_id'])->field('id,tag_name')->select();
                $house_info[$k]['house_img'] = Db::name('house_img')->where('house_id',$v['id'])->field('id,img_url')->select();
                $house_info[$k]['lines_name'] = !empty($v['lines_name']) ? $v['lines_name'] : '' ;
                $data[$k] = Db::name('house_entrust')->field('id,username,mobile,address,description,type')->where('id',$v['entrust_id'])->find();
                $data[$k]['house_info'] = $house_info[$k];
            }
        }
        
        return json(self::callback(1,'',$data));

    }


    /**
     * 发布的房源列表
     */
    public function houseList(){
        $param = $this->request->post();

        $status = !empty($param['status']) ? intval($param['status']) : 1 ;  // 1待提交 2审核中 3已收录 4未通过

        if (!$param || !$status){
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $data = Db::name('house')
            ->alias('h')
            ->join('subway_lines lines','lines.id = h.lines_id','left')
            ->field('h.id,h.title,h.bedroom_number,h.parlour_number,h.acreage,h.lines_id,h.rent,h.tag_id,lines.lines_name')
            ->where('h.entrust_id','eq',0)
            ->where('h.add_sale_id',$param['sale_id'])
            ->where('h.status',$status)
            ->select();

        foreach ($data as $k=>$v){
            $data[$k]['tag_info'] = Db::name('house_tag')->whereIn('id',$v['tag_id'])->field('id,tag_name')->select();
            $data[$k]['house_img'] = Db::name('house_img')->where('house_id',$v['id'])->field('id,img_url')->select();
            $data[$k]['lines_name'] = !empty($v['lines_name']) ? $v['lines_name'] : '' ;
            $data[$k]['bonus'] = Db::name('ticheng_config')->where('id',1)->value('s_sf_tincheng');
        }

        return \json(self::callback(1,'',$data));
    }

    /**
     * 录入委托房源
     */
    public function addEntrustHouse(){
        try{
            $param = $this->request->post();

            if (!$param){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $validate = new \app\sale\validate\House();

            if (!$validate->check($param,[])){

                return json(self::callback(0,$validate->getError()),400);
            }

            $entrust_info = Db::name('house_entrust')->where('id',$param['entrust_id'])->find();
            if (!$entrust_info){
                throw new \Exception('委托任务不存在');
            }

            /*if ($entrust_info['status'] != 0){
                throw new \Exception('委托任务已完成');
            }*/

            $files = $this->request->file('img');
            if (!$files){
                return json(self::callback(0,'房源照片不能为空'),400);
            }

            $area_info = Db::name('house_xiaoqu')->where('id',$param['xiaoqu_id'])->find();
            if (!$area_info){
                return json(self::callback(0,'小区不存在'));
            }

            $param['area_id1'] = $area_info['area_id1'];
            $param['area_id2'] = $area_info['area_id2'];
            $param['city_id'] = $area_info['city_id'];
            $param['xiaoqu_name'] = $area_info['xiaoqu_name'];
            $param['address'] = $area_info['address'];
            $param['shop_id'] = $userInfo['shop_id'];
            $param['entrust_username'] = $entrust_info['username'];
            $param['entrust_mobile'] = $entrust_info['mobile'];
            $param['source'] = $entrust_info['type'];

            $house_id = 0;

            foreach ($files as $key=>$file){

                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'house_img');
                if($info){

                    $img[$key]['house_id'] = &$house_id;
                    $img_url = $avatar = DS.'uploads'.DS.$this->request->module().DS.'house_img'.DS.$info->getSaveName();
                    $img[$key]['img_url'] = str_replace(DS,"/",$img_url);

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }

            Db::startTrans();
            $houseModel = new \app\sale\model\House();
            $result1 = $houseModel->allowField(true)->save($param);

            $house_id = intval($houseModel->id);

            if ($param['entrust_id']){
                $result2 = Db::name('house_entrust')->where('id',$param['entrust_id'])->setField('status',1);
                /*if (!$result2){
                    return \json(self::callback(0,'操作失败'),400);
                }*/
            }


            $result3 = Db::name('house_img')->insertAll($img);

            if (!$result1 || !$result3){

                Db::rollback();
                return json(self::callback(0,'操作失败'),400);
            }

            Db::commit();
            return json(self::callback(1,''));

        }catch (\Exception $e) {

            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取店铺列表
     */
    public function getShopList(){
        $city_id = input('city_id');
        #$keywords = input('keywords');
        $data = Db::name('shop_info')->where('city_id',$city_id)->field('id,shop_name,shopkeeper,address,mobile')->select();
        return \json(self::callback(1,'',$data));
    }

    /**
     * 获取销售列表
     */
    public function getSaleList(){
        $shop_id = input('shop_id');
        $data = Db::name('sale')->field('sale_id,nickname,mobile')->where('shop_id',$shop_id)->select();
        return \json(self::callback(1,'',$data));
    }

    /**
     * 发布房源
     */
    public function addHouse(){
        try{
            $param = $this->request->post();

            if (!$param || !$param['entrust_username'] || !$param['entrust_mobile'] || !$param['source']){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $validate = new \app\sale\validate\House();

            if (!$validate->check($param,[])){

                return json(self::callback(0,$validate->getError()),400);
            }


            $files = $this->request->file('img');
            if (!$files){
                return json(self::callback(0,'房源照片不能为空'),400);
            }

            $area_info = Db::name('house_xiaoqu')->where('id',$param['xiaoqu_id'])->find();
            if (!$area_info){
                return json(self::callback(0,'小区不存在'));
            }

            $param['area_id1'] = $area_info['area_id1'];
            $param['area_id2'] = $area_info['area_id2'];
            $param['city_id'] = $area_info['city_id'];
            $param['xiaoqu_name'] = $area_info['xiaoqu_name'];
            $param['address'] = $area_info['address'];
            //指定销售
            if ($param['appoint_shop_id'] && $param['appoint_sale_id']){
                $param['is_appoint'] = 1 ;  //指定销售
                $param['shop_id'] = $param['appoint_shop_id'];
                $param['sale_id'] = $param['appoint_sale_id'];
            }else{
                $param['shop_id'] = $userInfo['shop_id'];
            }
            $param['add_sale_id'] = $userInfo['sale_id'];
            #$param['entrust_username'] = $param['username'];
            #$param['entrust_mobile'] = $param['mobile'];
            #$param['source'] = $param['type'];

            $house_id = 0;

            foreach ($files as $key=>$file){

                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'house_img');
                if($info){

                    $img[$key]['house_id'] = &$house_id;
                    $img_url = $avatar = DS.'uploads'.DS.$this->request->module().DS.'house_img'.DS.$info->getSaveName();
                    $img[$key]['img_url'] = str_replace(DS,"/",$img_url);

                }else{
                    return json(self::callback(0,$file->getError()));
                }
            }

            Db::startTrans();
            $houseModel = new \app\sale\model\House();
            $result1 = $houseModel->allowField(true)->save($param);

            $house_id = intval($houseModel->id);

            $result3 = Db::name('house_img')->insertAll($img);

            if (!$result1 || !$result3){

                Db::rollback();
                return json(self::callback(0,'操作失败'),400);
            }

            Db::commit();
            return json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 修改房源信息
     */
    public function modifyHouseInfo(){
        try{

            $param = $this->request->post();

            $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

            if (!$param || !$house_id){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            if (!Db::name('house')->where('id',$house_id)->where('is_delete',0)->count()){
                return json(self::callback(0,'该房源不存在'));
            }

            if ($param['xiaoqu_id']) {
                $xiaoqu = Db::name('house_xiaoqu')->where('id',$param['xiaoqu_id'])->find();

                $param['city_id'] = $xiaoqu['city_id'];
                $param['area_id1'] = $xiaoqu['area_id1'];
                $param['area_id2'] = $xiaoqu['area_id2'];
                $param['xiaoqu_name'] = $xiaoqu['xiaoqu_name'];
                $param['address'] = $xiaoqu['address'];

            }

            if ($param['appoint_shop_id'] && $param['appoint_sale_id']) {
                #$modifyField.=',shop_id,sale_id';
                $param['shop_id'] = $param['appoint_shop_id'];
                $param['sale_id'] = $param['appoint_sale_id'];

            }else{
                unset($param['sale_id']);
            }

            #$modifyField = ['rent'];

            $houseModel = new \app\sale\model\House();
            $result = $houseModel->allowField(true)->save($param,['id'=>$house_id]);

            if (!$result){
                return json(self::callback(0,'操作失败'),400);
            }

            return json(self::callback(1,''));

        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }

    }


    /**
     * 删除房源图片
     */
    public function deleteHouseImg(){
        try{
            $param = $this->request->post();

            $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

            $img_id = gettype($param['img_id']) == 'array' ? $param['img_id'] : ( intval($param['img_id']) ? $param['img_id'] : 0 );  //图片id  数组或者整型

            if (!$param || !$house_id || !$img_id){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            if (!Db::name('house')->where('id',$house_id)->count()){
                return json(self::callback(0,'该房源不存在'));
            }

            $imgData = Db::name('house_img')->where('house_id',$house_id)->whereIn('id',$img_id)->column('img_url');

            if (!$imgData){
                return json(self::callback(0,'图片不存在'));
            }

            Db::startTrans();

            $result = Db::name('house_img')->where('house_id',$house_id)->whereIn('id',$img_id)->delete();

            if (!$result){
                return json(self::callback(0,'操作失败'),400);
            }

            foreach ($imgData as $url){
                $rs = unlink('.'.$url);
                if ($rs == false){
                    Db::rollback();
                    return json(self::callback(0,'图片源删除失败'));
                }
            }

            #unlink()
            Db::commit();
            return json(self::callback(1,''));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 上传房源图片
     */
    public function uploadHouseImg(){
        $param = $this->request->post();

        $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

        if (!$param || !$house_id){
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        if (!Db::name('house')->where('id',$house_id)->count()){
            return json(self::callback(0,'该房源不存在'));
        }

        $files = $this->request->file('img');
        if (!$files){
            return json(self::callback(0,'房源照片不能为空'),400);
        }

        foreach ($files as $key=>$file){

            $info = $file->validate(['ext'=>'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads'.DS.$this->request->module().DS.'house_img');
            if($info){

                $img[$key]['house_id'] = $house_id;
                $img_url = $avatar = DS.'uploads'.DS.$this->request->module().DS.'house_img'.DS.$info->getSaveName();
                $img[$key]['img_url'] = str_replace(DS,"/",$img_url);

            }else{
                return json(self::callback(0,$file->getError()));
            }
        }

        $result = Db::name('house_img')->insertAll($img);

        if (!$result){
            return json(self::callback(0,'操作失败'),400);
        }

        return json(self::callback(1,''));

    }

    /**
     * 提交审核
     */
    public function submitHouse(){

        $param = $this->request->post();

        $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

        if (!$param || !$house_id){
            return json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        if (!Db::name('house')->where('id',$house_id)->count()){
            return json(self::callback(0,'该房源不存在'));
        }

        Db::name('house')->where('id',$house_id)->setField('status',2);

        return json(self::callback(1,''));

    }

    /**
     * 删除房源
     */
    public function deleteHouse(){
        try{
            $param = $this->request->post();

            $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

            if (!$param || !$house_id){
                return json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }


            Db::startTrans();

            $house = \app\sale\model\House::get($house_id);

            if (!$house){
                throw new \Exception('该房源不存在');
            }

            if ($house->status != 4 && $house->status != 1){
                throw new \Exception('该房源不支持此操作');
            }

            $result = $house->delete();


            Db::name('house_img')->where('house_id',$house_id)->delete();

            if (!$result){
                Db::rollback();
                return json(self::callback(0,'操作失败'),400);
            }

            Db::commit();

            return json(self::callback(1,''));

        }catch (\Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));

        }

    }


    /**
     * 房源详情
     */
    public function houseDetail(){
        $param = $this->request->post();

        $house_id = $param['house_id'] ? intval($param['house_id']) : 0 ;

        if (!$param || !$house_id){
            return json(self::callback(0,'参数错误'),400);
        }

        $data = Db::view('house','id,title,description,rent,rent_mode,type,decoration_mode,bedroom_number,parlour_number,toilet_number,acreage,floor_type,floor,total_floor,orientation,house_type_id,years,is_elevator,xiaoqu_id,xiaoqu_name,is_subway,entrust_id,lines_id,station_id,tag_id,room_config_id,shop_id,sale_id,entrust_username,entrust_mobile,source,status')
            ->view('house_type','name as house_type_name','house_type.id=house.house_type_id','left')
            ->view('subway_lines','lines_name','subway_lines.id=house.lines_id','left')
            ->view('subway_station','station_name','subway_station.id=house.station_id','left')
            ->view('shop_info','shop_name','shop_info.id=house.shop_id','left')
            ->view('sale','nickname','sale.sale_id=house.sale_id','left')
            ->where('house.id',$house_id)
            ->find();

        if (!$data){
            return json(self::callback(0,'该房源不存在'));
        }

        $data['house_img'] = Db::name('house_img')->field('id,img_url')->where('house_id',$house_id)->select();
        $data['room_config'] = (new RoomConfig())->getRoomConfigInfo($data['room_config_id']);
        $data['tag_info'] = (new HouseTag())->getTagInfo($data['tag_id']);

        return json(self::callback(1,'',$data));
    }


    /**
     * 长租服务
     */
    public function longServiceList(){

        try{
            $status = input('status');  //1待出租  2已定  3出租中  4完结
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $where['sale_id'] = ['eq',$userInfo['sale_id']];
            $where['status'] = ['eq',3];
            $where['is_delete'] = ['eq',0];
            $filed = "id,title,type,rent,rent_mode,bedroom_number,parlour_number,toilet_number,acreage,lines_id,tag_id";

            switch ($status){
                case 1:

                    $where['renting_status'] = ['eq',1];

                    break;
                case 2:

                    $where['renting_status'] = ['eq',2];

                    break;
                case 3:

                    $where['renting_status'] = ['eq',3];

                    break;
                case 4:

                    $where['renting_status'] = ['eq',4];

                    break;
            }

            $total = Db::name('house')->where($where)->count();
            $list = \app\sale\model\House::with('houseImg')
                ->where($where)
                ->field($filed)
                ->page($page,$size)
                ->select();

            if ($list){
                $list->toArray();

                foreach ($list as $k=>$v){
                    $list[$k]['tag_info'] = Db::name('house_tag')->field('id,tag_name')->where('id','in',$v['tag_id'])->select();
                    $list[$k]['lines_name'] = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');
                    unset($list[$k]['tag_id']);
                    unset($list[$k]['lines_id']);

                    switch ($status){
                        case 1:

                            $list[$k]['renting_info'] = new \stdClass();

                            break;
                        case 2:

                            $list[$k]['renting_info'] = LongOrder::where('house_id',$v['id'])
                                ->field('id,order_no,username,mobile,reserve_money,pay_type,create_time')
                                ->where('status',2)
                                ->where('renting_status',0)
                                ->find();

                            break;
                        case 3:

                            $renting_info = LongOrder::where('house_id',$v['id'])
                                ->field('id,order_no,username,mobile,reserve_money,deposit_money,pay_type,create_time,property_id')
                                ->where('status',2)
                                ->where('renting_status',1)
                                ->find();
                            if (!$renting_info){
                                $list[$k]['renting_info'] = new \stdClass();
                                break;
                            }
                            $renting_info = $renting_info->toArray();

                            $property_name = Db::name('property')->where('property_id',$renting_info['property_id'])->value('nickname');
                            $renting_info['property_name'] = !empty($property_name) ? $property_name : '';

                            $renting_info2 = Db::name('long_rent_record')->field('money,end_time,create_time')->where('order_id',$renting_info['id'])->order('create_time','desc')->find();

                            $renting_info2['start_time'] = Db::name('long_rent_record')->where('order_id',$renting_info['id'])->order('create_time','asc')->value('start_time');

                            $renting_info2['create_time'] = date('Y-m-d',$renting_info2['create_time']);
                            $list[$k]['renting_info'] = array_merge($renting_info,$renting_info2);

                            break;
                        case 4:

                            $renting_info = LongOrder::where('house_id',$v['id'])
                                ->field('id,order_no,username,mobile,reserve_money,deposit_money,pay_type,create_time,property_id')
                                ->where('status',2)
                                ->where('renting_status',2)
                                ->find();

                            if (!$renting_info){
                                $list[$k]['renting_info'] = new \stdClass();
                                break;
                            }
                            $renting_info = $renting_info->toArray();

                            $property_name = Db::name('property')->where('property_id',$renting_info['property_id'])->value('nickname');
                            $renting_info['property_name'] = !empty($property_name) ? $property_name : '';

                            $renting_info2 = Db::name('long_rent_record')->field('money,start_time,end_time,create_time')->where('order_id',$renting_info['id'])->order('create_time','desc')->find();
                            $renting_info2['create_time'] = date('Y-m-d',$renting_info2['create_time']);
                            $list[$k]['renting_info'] = array_merge($renting_info,$renting_info2);

                            break;
                    }
                }


            }

            $data['max_page'] = ceil($total/$size);
            $data['total'] = $total;
            $data['list'] = $list;

            #dump($data);
            #die;
            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }

    }


    /**
     * 修改待出租
     */
    public function modifyDcz(){
        try{
            $order_id = input('order_id');

            if (!$order_id){
                return \json(self::callback(0,'参数错误',400));
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order = LongOrder::get($order_id);

            if (!$order){
                return \json(self::callback(0,'订单不存在'));
            }

            if ($order->status !=2 || $order->renting_status !=0 ){
                return \json(self::callback(0,'该订单不支持该操作'));
            }

            $order->status = -1;  //修改状态为已支付已取消
            $order->cancel_time = time();

            //todo 此处原路退款
            /*if ($order->pay_type == '支付宝') {
                $alipay = new AliPay();
                $res = $alipay->alipay_refund($order->order_no,$order->reserve_money);
            }elseif ($order->pay_type == '微信'){
                $wxpay = new WxPay();
                $res = $wxpay->wxpay_refund($order->order_no,$order->reserve_money,$order->reserve_money);
            }

            if ($res !== true){
                return \json(self::callback(0,'改为待出租退款失败'));
            }*/

            Db::startTrans();

            $result1 = $order->allowField(true)->save();

            //修改房源状态为待出租

            $houseModel = new \app\sale\model\House();
            $result2 = $houseModel->allowField(true)->save(['renting_status'=>1],['id'=>$order->house_id]);

            if (!$result1 || !$result2){
                Db::rollback();
                return \json(self::callback(0,'操作失败'));
            }

            Db::commit();

            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }


    }

    /**
     * 待出租签约
     */
    /*public function dczSignContract(){
        $house_id = input('house_id');
        $start_time = input('start_time');   //入住时间
        $end_time = input('end_time');    //到期时间
        $money = input('money');     //缴纳金额
        $type = input('type');   //租房类型 1整租 2合租
        $username = input('username');  //租客姓名
        $mobile = input('mobile');   //租客电话
        $deposit_money = input('deposit_money');  //缴纳押金
        $property_mobile = input('property_mobile');   //物业电话

        if (!$house_id || !$start_time || !$end_time || !$money || !$type || !$username || !$mobile){
            return \json(self::callback(0,'参数错误',400));
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $house = \app\sale\model\House::get($house_id);

        if (!$house){
            return \json(self::callback(0,'房源不存在'));
        }

        if ($house->status != 3 || $house->renting_status != 1){
            return \json(self::callback(0,'该订单不支持该操作'));
        }

        $property_info = Db::name('property')->where('mobile',$property_mobile)->find();

        if (!$property_info){
            return \json(self::callback(0,'物业账号不存在'));
        }

        $order_no = build_order_no('L');

        $longOrderModel = new LongOrder();
        $result1 = $longOrderModel->allowField(true)->save([
            'order_no' => $order_no,
            'house_id' => $house_id,
            'property_id' => !empty($property_info['property_id']) ? $property_info['property_id'] : 0 ,
            'sale_id' => $userInfo['sale_id'],
            'username' => $username,
            'mobile' => $mobile,
            'status' => 2,
            'renting_status' => 1,
            'create_time' => time()
        ]);

        $house->renting_status = 3;
        $result2 = $house->allowField(true)->save();

        $result3 = Db::name('long_rent_record')->insert([
            'order_id' => $longOrderModel->id,
            'money' => $money,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'create_time' => time()
        ]);


        if (!$result1 || !$result2 || !$result3){
            Db::rollback();
            return \json(self::callback(0,'操作失败'),400);
        }

        Db::commit();

        return \json(self::callback(1,''));
    }*/

    /**
     * 已定租房签约
     */
    public function ydSignContract(){
        $order_id = input('order_id');
        $start_time = input('start_time');   //入住时间
        $end_time = input('end_time');    //到期时间
        $money = input('money');     //缴纳金额
        $rent = input('rent');     //每月租金
        $type = input('type');   //租房类型 1整租 2合租
        $deposit_money = input('deposit_money');  //缴纳押金
        $property_mobile = input('property_mobile');   //物业电话


        if (!$order_id || !$start_time || !$end_time || !$money || !$rent || !$type){
            return \json(self::callback(0,'参数错误',400));
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $order = LongOrder::get($order_id);

        if (!$order){
            return \json(self::callback(0,'订单不存在'));
        }

        if ($order->status !=2 || $order->renting_status !=0 ){
            return \json(self::callback(0,'该订单不支持该操作'));
        }

        if ($property_mobile){

            $property_info = Db::name('property')->where('mobile',$property_mobile)->find();

            if (!$property_info){
                return \json(self::callback(0,'物业账号不存在'));
            }

            $order->property_id = $property_info['property_id'];
        }


        $order->renting_status = 1;  //修改订单租房状态为在租中
        $order->rent = $rent;
        $order->deposit_money = $deposit_money;


        Db::startTrans();

        //判断是否物业推荐房源
        $house_info = Db::name('house')->where('id',$order->house_id)->find();
        if ($house_info['entrust_id'] && $house_info['source'] == 2){

            $property_id = Db::name('house_entrust')->where('id',$house_info['entrust_id'])->where('type',2)->value('param_id');

            $ticheng = Db::name('ticheng_config')->where('id',1)->value('p_yxtj_ticheng');

            if ($property_id){
                // todo 增加物业有效推荐记录
                $res1 = Db::name('property_money_record')->insert([
                    'property_id' => $property_id,
                    'house_id' => $order->house_id,
                    'type' => 1,
                    'money' => $ticheng,
                    'create_time' => time()
                ]);
                //增加物业余额
                $res2 = Db::name('property')->where('property_id',$property_id)->setInc('money',$ticheng);

                if (!$res1 || !$res2){
                    Db::rollback();
                    return \json(self::callback(0,'增加物业有效推荐记录失败'));
                }
            }
        }

        if ($property_mobile){
            //todo 增加物业有效看房记录
            $ticheng = Db::name('ticheng_config')->where('id',1)->value('p_yxkf_ticheng');
            $res1 = Db::name('property_money_record')->insert([
                'property_id' => $property_info['property_id'],
                'house_id' => $order->house_id,
                'type' => 2,
                'money' => $ticheng,
                'create_time' => time()
            ]);
            //增加物业余额
            $res2 = Db::name('property')->where('property_id',$property_info['property_id'])->setInc('money',$ticheng);

            if (!$res1 || !$res2){
                Db::rollback();
                return \json(self::callback(0,'增加物业有效看房记录失败'));
            }
        }

        $result1 = $order->allowField(true)->save();

        $houseModel = new \app\sale\model\House();

        $result2 = $houseModel->allowField(true)->save(['renting_status'=>3,'type'=>$type],['id'=>$order->house_id]);

        $result3 = Db::name('long_rent_record')->insert([
            'order_id' => $order_id,
            'money' => $money,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'create_time' => time()
        ]);



        if (!$result1 || !$result2 || !$result3){
            Db::rollback();
            return \json(self::callback(0,'操作失败'));
        }

        Db::commit();

        return \json(self::callback(1,''));

    }

    /**
     * 长租房详情
     */
    public function longHouseDetail(){
        $param = $this->request->post();

        if (!$param || !$param['house_id']){
            return \json(self::callback(0,'参数错误'),400);
        }

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $data = \app\sale\model\House::with(['houseImg'])
            ->where('id',$param['house_id'])
            ->field('id,title,description,rent,rent_mode,type,decoration_mode,bedroom_number,parlour_number,toilet_number,acreage,floor_type,floor,total_floor,orientation,house_type_id,years,is_elevator,is_subway,lines_id,station_id,tag_id,room_config_id,entrust_username,xiaoqu_id,entrust_mobile,source')
            ->find();

        if (!$data){
            return \json(self::callback(0,'房源不存在'));
        }

        if ($data){
            
            $data['house_type'] = Db::name('house_type')->where('id',$data->house_type_id)->value('name');
            $lines_name = Db::name('subway_lines')->where('id',$data->lines_id)->value('lines_name');
            $data['lines_name'] = !empty($lines_name) ? $lines_name : '';
            $station_name = Db::name('subway_station')->where('id',$data->station_id)->value('station_name');
            $data['station_name'] = !empty($station_name) ? $station_name : '';
            $data['tag_info'] = (new HouseTag())->getTagInfo($data->tag_id);
            $data['room_config_info'] = (new RoomConfig())->getRoomConfigInfo($data->room_config_id);
            $data['xiaoqu_name'] = Db::name('house_xiaoqu')->where('id',$data['xiaoqu_id'])->value('xiaoqu_name');
            unset($data['tag_id']);
            unset($data['house_type_id']);
            unset($data['lines_id']);
            unset($data['station_id']);
            unset($data['room_config_id']);

        }

        return \json(self::callback(1,'',$data));
    }

    /**
     * 退房
     */
    public function refundHouse(){
        try{

            $order_id = input('order_id');
            $refund_deposit = input('refund_deposit');  //退回押金

            if (!$order_id || !$refund_deposit){
                return \json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order = LongOrder::get($order_id);

            if (!$order_id){
                return \json(self::callback(0,'订单不存在'));
            }

            if ($order->status != 2 || $order->renting_status != 1){
                return \json(self::callback(0,'该订单不支持该操作'));
            }

            $order->renting_status = 2;
            $order->refund_deposit = $refund_deposit;

            Db::startTrans();

            $result1 = $order->allowField(true)->save();

            $result2 = Db::name('house')->where('id',$order->house_id)->setField('renting_status',4);


            if (!$result1 || !$result2){
                Db::rollback();
                return \json(self::callback(0,'操作失败'),400);
            }
            Db::commit();
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            Db::rollback();

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 续租
     */
    public function renewalRent(){
        try{

            $order_id = input('order_id');
            $start_time = input('start_time');
            $end_time = input('end_time');
            $money = input('money');

            if (!$order_id || !$start_time || !$end_time || !$money){
                return \json(self::callback(0,'参数错误'),400);
            }

            //token 验证
            $userInfo = \app\sale\common\Sale::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }

            $order = LongOrder::get($order_id);

            if (!$order_id){
                return \json(self::callback(0,'订单不存在'));
            }

            if ($order->status != 2 || $order->renting_status != 1){
                return \json(self::callback(0,'该订单不支持该操作'));
            }

            $result = Db::name('long_rent_record')->insert([
                'order_id' => $order_id,
                'money' => $money,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'create_time' => time()
            ]);

            if (!$result){
                return \json(self::callback(0,'操作失败'),400);
            }

            return \json(self::callback(1,''));
        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 长租业绩
     */
    public function longAchievement(){
        $page = input('page') ? intval(input('page')) : 1 ;
        $size = input('size') ? intval(input('size')) : 10 ;

        //token 验证
        $userInfo = \app\sale\common\Sale::checkToken();
        if ($userInfo instanceof Json){
            return $userInfo;
        }

        $total = Db::view('long_rent_record','order_id,money,create_time')
            ->view('long_order','username,mobile','long_order.id=long_rent_record.order_id','left')
            ->view('house','id,title,description,rent,bedroom_number,parlour_number,toilet_number,acreage,tag_id,lines_id','house.id=long_order.house_id','left')
            ->where('long_order.sale_id',$userInfo['sale_id'])
            ->where('long_order.status',2)
            ->where('long_order.renting_status','>','0')
            ->count();

        $list = Db::view('long_rent_record','order_id,money,create_time')
            ->view('long_order','username,mobile','long_order.id=long_rent_record.order_id','left')
            ->view('house','id as house_id,title,description,rent,bedroom_number,parlour_number,toilet_number,acreage,tag_id,lines_id','house.id=long_order.house_id','left')
            ->where('long_order.sale_id',$userInfo['sale_id'])
            ->where('long_order.status',2)
            ->where('long_order.renting_status','>','0')
            ->page($page,$size)
            ->order('long_rent_record.create_time','desc')
            ->select();

        foreach ($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            $list[$k]['lines_name'] = Db::name('subway_lines')->where('id',$v['lines_id'])->value('lines_name');

            if (!$v['lines_id']){
                $list[$k]['lines_name'] = '';
            }

            $list[$k]['house_img'] = Db::name('house_img')->field('id,img_url')->where('house_id',$v['house_id'])->select();
            $list[$k]['tag_info'] = (new HouseTag())->getTagInfo($v['tag_id']);
        }

        $data['max_page'] = ceil($total/$size);
        $data['total'] = $total;
        $data['list'] = $list;

        return \json(self::callback(1,'',$data));
    }

}