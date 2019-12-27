<?php


namespace app\admin\controller;

use app\admin\model\ActivityEnoughRule;
use app\admin\model\ActivityLimitTime;
use app\admin\model\CouponRule;
use app\admin\model\Store as storeModel;
use think\Db;
use think\Exception;
use app\admin\validate\Activity as ActivityValidate;
use app\admin\model\ActivityBanner as ActivityBannerModel;
use app\admin\model\Product as ProductModel;
use app\admin\model\CouponRule as CouponRuleModel;
use app\admin\model\Activity as ActivityModel;
use app\admin\model\ActivityEnoughRule as ActivityEnoughModel;
use app\admin\model\ActivityReturnCouponRule as ActivityReturnCouponRuleModel;
use app\admin\model\ActivityLimitTime as ActivityLimitTimeModel;
use app\admin\model\ActivityType as ActivityTypeModel;
use app\admin\model\ActivityProduct as ActivityProductModel;
use app\admin\model\ProductSpecs as ProductSpecsModel;
use app\admin\model\RecommendBanner as RecommendbannerModel;

class Activity extends Admin
{

    public function lists(){
        $size = 10;
        $type = input('type',0,'intval');
        $keywords = input('keywords','','addslashes,strip_tags,trim');

        $where = "1=1";
        if($keywords)$where .= " AND title LIKE '%{$keywords}%' ";
        $time = time();
        switch($type){
            case 1:  //草稿
                $where .= " AND status = 1";
                break;
            case 2:  //待上线
                $where .= " AND status = 2 AND start_time > {$time} ";
                break;
            case 3:  //进行中
                $where .= " AND status = 2 AND start_time <= {$time} AND end_time > {$time}";
                break;
            case 4:  //下线
                $where .= " AND ( status = 3 OR (end_time <= {$time} AND status =2 ) )";
        }

        $list = ActivityModel::where($where)->field('status,title,id,start_time,end_time,cover,desc')->paginate($size,false,['query'=>$this->request->param()]);
        $page = $list->render();
        $data = $list->toArray()['data'];
        foreach($data as &$v){

            if($v['status'] == 1){
                $v['status_type'] = 1;  //草稿
                $v['status_txt'] = "草稿";
            }
            if($v['status'] == 2){
                if($v['start_time'] > time()){  //待上线
                    $v['status_type'] = 2;  //待上线
                    $v['status_txt'] = "待上线";
                }
                if($v['start_time'] <= time() && $v['end_time'] > time()){  //进行中
                    $v['status_type'] = 3;  //进行中
                    $v['status_txt'] = "进行中";
                }
                if($v['end_time'] <= time()){  //已结束
                    $v['status_type'] = 4;
                    $v['status_txt'] = "已结束";
                }
            }
            if($v['status'] == 3){
                $v['status_type'] = 5;
                $v['status_txt'] = "已下线";
            }
            $v['start_time'] = date('Y-m-d H:i', $v['start_time']);
        }

        ##查询活动数
        $num_1 = ActivityModel::getNumHas();
        $num_2 = ActivityModel::getNumWait();
        $num_3 = ActivityModel::getNumDoneThisMonth();

        $this->assign(compact('page','data','type','keywords','num_1','num_2','num_3'));

        return $this->fetch();
    }

    /**
     * 新增活动 || 修改活动
     */
    public function updateActivity(ActivityValidate $ActivityValidate){
//        $params = input('post.',[]);
//        print_r($params);die;
        #验证
        $res = $ActivityValidate->scene('add_activity')->check(input());
        if(!$res)return $this->error($ActivityValidate->getError());

        #逻辑
        $title = input('post.title','','addslashes,strip_tags,trim');
        $desc = input('post.desc','','addslashes,strip_tags,trim');
        $user_type = input('post.user_type',1,'intval');
        $client = input('post.client',1,'intval');
        $is_show_rule = input('post.is_show_rule','off','addslashes,strip_tags,trim');
        $is_show_rule = $is_show_rule=='on'?1:2;
        $rule = input('post.rule','','addslashes,strip_tags,trim');
        $cover = str_replace('\\','/', input('post.cover','','strip_tags,trim'));
        $preferential_type = input('post.preferential_type',1,'intval');
        $message_type = input('post.message_type',1,'intval');
        $message_model_id = input('post.message_model_id',1,'intval');
        $activity_type = input('post.activity_type',1,'intval');
        $activity_pro_type = input('post.activity_pro_type',1,'intval');
        $line_type = input('post.line_type',1,'intval');
        $activity_long = input('post.activity_long',0,'intval');

        if(!$title || !$user_type || !$client || !$is_show_rule || !$cover || !$preferential_type || !$message_type || !$activity_type || !$activity_pro_type || !$line_type || !$activity_long)return $this->error('参数缺失');

        if($is_show_rule==1 && !$rule)return $this->error('请输入规则说明');

        $banner = input('post.banner/a',[]);
        if(empty($banner))return $this->error('请添加banner');
        $banner_ids = implode(',',array_column($banner,'id'));

        if($activity_type == 2){  //抵扣
            $deduction_money = input('post.deduction_money',0,'floatval');
            if(!$deduction_money || $deduction_money <0)return $this->error('抵扣金额错误');
        }

        $recom_coupon = input('post.recom_coupon_ids/a',[]);
        foreach($recom_coupon as &$v){
            $v = "[{$v}]";
        }
        $recom_coupon_ids = implode(',',$recom_coupon);

        $message_content = config('config_common.message_model')[$message_model_id]['content'];

        $start_time = time();
        $is_clock = 2;
        if($line_type == 2){
            $start_time = input('post.start_time','');
            if(!$start_time)return $this->error('定时上线时间格式错误');
            $start_time = strtotime($start_time);
            if($start_time <= time())return $this->error('定时上线时间不能小于当前时间');
            $is_clock = 1;
        }
        $end_time = $start_time + $activity_long * 24 * 60 * 60;
        $clock_time = $start_time;

        $status = input('post.status',1,'intval');

        Db::startTrans();

        try{
            $discount = 1;
            $discount_max = 0;
            if($activity_type == 4){  //打折
                $discount = input('post.discount',1,'floatval');
                if($discount >= 1 || $discount <= 0)throw new Exception('折扣值错误');
                $discount_max = input('post.discount_max',0,'floatval');
                if(!$discount_max || $discount_max <= 0)throw new Exception('最高折扣金额错误');
            }
            $return_prop = 0;
            $return_max = 0;
            if($activity_type == 5){  //返现
                $return_prop = input('post.return_prop',0,'floatval');
                if(!$return_prop || $return_prop > 100)throw new Exception('返现比例错误');
                $return_max = input('post.return_max',0,'floatval');
                if(!$return_max || $return_max <= 0)throw new Exception('最高返现金额错误');
            }

            $is_limit_time = 0;
            if($activity_type==2 || $activity_type==4) {  //判断活动限时
                $is_limit_time = input('post.is_limit_time', '', 'addslashes,strip_tags,trim');
                $is_limit_time = $is_limit_time == 'on' ? 1 : 0;
            }

            $data_activity = compact('title','desc','user_type','client','is_show_rule','rule','cover','banner_ids','recom_coupon_ids','preferential_type','message_type','message_content','activity_type','deduction_money','discount','discount_max','return_prop','return_max','is_limit_time','start_time','end_time','is_clock','clock_time','activity_long','status','activity_pro_type','status','message_model_id');

            $id = input('post.id',0,'intval');
            if($id){  //更新
                ##修改活动主信息
                $res = ActivityModel::edit($id, $data_activity);
                if($res === false)throw new Exception('活动数据更新失败');
            }else{
                ##保存活动主信息
                $id = ActivityModel::add($data_activity);
                if(!$id)throw new Exception('活动数据添加失败');
            }

            if($activity_type == 3){  //满减
                $enough_rule = input('post.enough_rule/a',[]);
                if(empty($enough_rule))throw new Exception('请添加满减规则');
                $data_enough = [];
                foreach($enough_rule as $v){
                    $money1 = floatval($v['satisfy_money']);
                    $money2 = floatval($v['discount_money']);
                    if($money1 <= $money2)throw new Exception('满减金额需大于优惠金额');
                    $data_enough[] = [
                        'satisfy_money' => $money1,
                        'discount_money' => $money2,
                        'create_time' => time(),
                        'activity_id' => $id
                    ];
                }
                ##添加满减规则(且删除原有满减规则)
                $res = ActivityEnoughModel::add($id,$data_enough);
                if($res === false)throw new Exception('满减规则更新失败');
            }

            if($activity_type == 6){  //返优惠券
                $enough_coupon_rule = input('post.enough_coupon_rule/a',[]);
                if(empty($enough_coupon_rule))throw new Exception('请选择返回优惠券');
                $data_rtn_coupon = [];
                foreach($enough_coupon_rule as $val){
                    $money = floatval($val['satisfy_money']);
                    $coupon_id = intval($val['coupon_id']);
                    if($money <= 0)throw new Exception('满返优惠券金额错误');
                    if(!$coupon_id)throw new Exception('满返优惠券选择失败');
                    $data_rtn_coupon[] = [
                        'satisfy_money' => $money,
                        'coupon_id' => $coupon_id,
                        'create_time' => time(),
                        'activity_id' => $id
                    ];
                }

                ##添加返优惠券规则
                $res = ActivityReturnCouponRuleModel::add($id, $data_rtn_coupon);
                if($res === false)throw new Exception('返优惠券规则更新失败');
            }
//            $is_limit_time = 0;
            if($activity_type==2 || $activity_type==4){  //判断活动限时
//                $is_limit_time = input('post.is_limit_time','','addslashes,strip_tags,trim');
//                $is_limit_time = $is_limit_time=='on'?1:0;
                if($is_limit_time){
                    $limit_time = input('post.limit_time','','addslashes,strip_tags,trim');
                    if(!$limit_time)throw new Exception('请选择活动限时时间段');
                    $limit_time = explode('-',$limit_time);
                    if(count($limit_time) != 2)throw new Exception('活动限时时间段格式错误');

                    ##星期
                    $week = input('post.week/a',[]);
                    if(empty($week))throw new Exception('请选择活动限时时间');
                    $data_limit_time = [];
                    foreach($week as $v){
                        $data_limit_time[] = [
                            'start_time' => trim($limit_time[0]),
                            'end_time' => trim($limit_time[1]),
                            'create_time' => time(),
                            'week_day' => $v,
                            'activity_id' =>$id
                        ];
                    }

                    ##添加限时规则
                    $res = ActivityLimitTimeModel::add($id, $data_limit_time);
                    if($res === false)throw new Exception('限时规则更新失败');
                }
            }

            $pro_ids = [];

            ##商品
            if($activity_pro_type == 4){  //店铺方式
                $pro_data = input('post.pro_data_type_store/a',[]);
                $data_pro = [];
                if(empty($pro_data))throw new Exception('请选择活动商品');
                foreach($pro_data as $v){
                    if(empty($v['pro_data']))throw new Exception('有店铺未选择活动商品');
                    foreach($v['pro_data'] as $vv){
                        $data_pro[] = [
                            'activity_id'=>$id,
                            'product_id' => $vv['pro_id'],
                            'store_id' => $v['store_id'],
                            'create_time' => time(),
                            'activity_pro_type' => $activity_pro_type,
                            'type_id' => 0
                        ];
                        $pro_ids[] = $vv['pro_id'];
                    }
                }
                ##删除原有活动商品
                $res = ActivityProductModel::del($id);
                if($res === false)throw new Exception('活动商品更新失败');
                ##新增活动商品
                $res = ActivityProductModel::add($data_pro);
                if($res === false)throw new Exception('活动商品添加失败');
            }

            if($activity_pro_type == 5){ //自定义类别方式
                $pro_data = input('post.pro_data_type_diy/a',[]);

                if(empty($pro_data))throw new Exception('请选择活动商品');
                ##删除之前的自定义分类和活动商品
                $res = ActivityTypeModel::del($id);
                if($res === false)throw new Exception('更新自定义类别失败');
                ##删除之前的活动商品
                $res = ActivityProductModel::del($id);
                if($res === false)throw new Exception('更新活动商品失败');
                foreach($pro_data as $v){
                    if(empty($v['pro_data']))throw new Exception('自定义分类未选择活动商品');
                    $res = ActivityTypeModel::add(['type_name'=>$v['type_name'],'create_time'=>time(),'activity_id'=>$id]);
                    if($res === false)throw new Exception('自定义分类添加失败');
                    $data_pro = [];
                    foreach($v['pro_data'] as $vv){
                        $data_pro[] = [
                            'activity_id'=>$id,
                            'product_id' => $vv['pro_id'],
                            'store_id' => $v['store_id'],
                            'create_time' => time(),
                            'activity_pro_type' => $activity_pro_type,
                            'type_id' => $res
                        ];
                        $pro_ids[] = $vv['pro_id'];
                    }
                    ##添加活动商品
                    $res = ActivityProductModel::add($data_pro);
                    if($res === false)throw new Exception('活动商品添加失败');
                }
            }

            if($activity_type == 2 || $activity_type == 4){  //改变商品活动价(无论是否定时上线,都先将活动价格改变)
                ##获取规格
                $pro_specs_info = ProductSpecsModel::getBaseInfo($pro_ids);
                foreach($pro_specs_info as $v){
                    ##获取商品规格信息
                    $price = $v['price'];
                    if($activity_type == 2){  //抵扣
                        $price = $price - $deduction_money;
                        if($price <= 0)throw new Exception('商品金额不能小于抵扣金额');

                    }else if($activity_type == 4){
                        $price2 = $price * $discount;
                        if($price - $price2 > $discount_max){ //打折金额不能高于最高折扣金额
                            $price2 = $price - $discount_max;
                        }
                        $price = $price2;
                    }
                    ##更新活动价
                    $res = ProductSpecsModel::updateActivityPrice($v['id'],$price);
                    if($res === false)throw new Exception('商品价格更新失败');
                }
            }
            Db::commit();
            return $this->success('操作成功');

        }catch(Exception $e){
            Db::rollback();
            return $this->error($e->getMessage());
        }

    }

    /**
     * 新增 || 修改活动的展示页
     */
    public function index(){

        $id = input('id',0,'intval');
//        if($id){  //修改
            $info = ActivityModel::get($id);

            $banner_ids = explode(',',$info['banner_ids']);
            $banners = ActivityBannerModel::getBannerByIds($banner_ids);
            $banners = json_encode($banners);

            $coupon_can_use = CouponRuleModel::getCouponRecom();
            $coupon_can_rtn = CouponRuleModel::getCouponRtn();
        $coupon_can_use2 = json_encode($coupon_can_use);
        $coupon_can_rtn2 = json_encode($coupon_can_rtn);

            $info['recom_coupon_ids'] = explode(',',trimFunc($info['recom_coupon_ids']));
            $recom_coupon_ids = json_encode($info['recom_coupon_ids']);

            $enough_rule = ActivityEnoughRule::getActivityRule($id);
//            print_r(json_encode($enough_rule));die;
            $enough_rule2 = json_encode($enough_rule);

            $return_coupon = ActivityReturnCouponRuleModel::getActivityRtnCoupon($id);
            $return_coupon2 = json_encode($return_coupon);

            $limit_time = ActivityLimitTime::getActivityLimitTime($id);
            $info['limit_time'] = $limit_time?$limit_time[0]['start_time'] . " - " .$limit_time[0]['end_time']:'';
            $limit_time = json_decode(json_encode($limit_time),true);
            $info['week'] = array_column($limit_time,'week_day');

            $product_list = ActivityProductModel::getActivityPro($id);
            $product_list = json_decode(json_encode($product_list),true);
            $pro_store = $pro_diy = [];
            if($info['activity_pro_type'] == 4){  //店铺方式
                foreach($product_list as $v){
                    $pro_store[$v['store_id']]['pro_data'][] = [
                        'pro_id' => $v['product_id'],
                        'pro_name' => $v['product_name']
                    ];
                    $pro_store[$v['store_id']]['store_id'] = $v['store_id'];
                }
                foreach($pro_store as &$v){
                    $v['store_name'] = Db::name('store')->where(['id'=>$v['store_id']])->value('store_name');
                }
            }
            if($info['activity_pro_type'] == 5){  //自定义方式
                $diy_type = ActivityTypeModel::getActivityType($id);
                foreach($product_list as $v){
                    foreach($diy_type as $key => $val){
                        if($v['type_id'] == $val['id']){
                            $pro_diy[$val['id']]['type_id'] = $val['id'];
                            $pro_diy[$val['id']]['type_name'] = $val['type_name'];
                            $pro_diy[$val['id']]['pro_data'][] = [
                                'pro_id' => $v['product_id'],
                                'pro_name' => $v['product_name']
                            ];
                        }
                    }
                }
                $pro_diy = array_values($pro_diy);
                foreach($pro_diy as $k=> &$v){
                    $v['type_id'] = $k + 1;
                }
            }
            $pro_store = json_encode(array_values($pro_store));
            $pro_diy = json_encode(array_values($pro_diy));

            $info['clock_time'] = date('Y-m-d H:i:s',$info['clock_time']);


//        }

        ##短信模板
        $message_list = config('config_common.message_model');

        $this->assign(compact('info','banners','coupon_can_use','coupon_can_rtn','coupon_can_rtn2','coupon_can_use2','recom_coupon_ids','enough_rule','enough_rule2','return_coupon','return_coupon2','pro_store','pro_diy','message_list'));

        $this->assign(compact(''));

        return $this->fetch();
    }

    public function detail(){

        $id = input('id',0,'intval');
        $info = ActivityModel::get($id)->toArray();

        $status_type = 1;
        if($info['status'] == 1){
            $status_txt = "草稿";
        }
        if($info['status'] == 3){
            $status_txt = "已下线";
            $status_type = 4;
        }
        if($info['status'] == 2){
            if($info['start_time'] > time()){  //待上线
                $status_txt = "待上线";
                $status_type = 2;
            }else if($info['start_time'] <= time() && $info['end_time'] > time()){ //活动中
                $status_txt = "活动中";
                $status_type = 3;
            }else{  //已下线
                $status_txt = "已下线";
                $status_type = 4;
            }
        }

        $info['start_time'] = date('Y-m-d H:i:s',$info['start_time']);
        $info['end_time'] = date('Y-m-d H:i:s',$info['end_time']);
        $info['create_time'] = date('Y-m-d H:i:s',$info['create_time']);

        ##banner
        $banners = ActivityBannerModel::getBannerByIds(explode(',',trimFunc($info['banner_ids'])));

        ##推荐的优惠券
        $recom_coupons = CouponRuleModel::getActivityRecomCoupons(trimFunc($info['recom_coupon_ids']));

        switch($info['activity_type']){
            case 3:  //满减
                $enough_rule = ActivityEnoughModel::getActivityRule($id);
                $this->assign(compact('enough_rule'));
                break;
            case 6:
                $return_coupon = ActivityReturnCouponRuleModel::getActivityRtnCouponInfo($id);
                $this->assign(compact('return_coupon'));
                break;
        }

        if($info['is_limit_time']){
            $limit_time_txt = "";
            $week_config = config('config_common.week_day');
            $limit_time = ActivityLimitTimeModel::getActivityLimitTime($id);
            foreach($limit_time as $v){
                $limit_time_txt .= "每" . $week_config[$v['week_day']] . "，";
            }
            $limit_time_txt = trim($limit_time_txt,'，') . "的 {$limit_time[0]['start_time']} - {$limit_time[0]['end_time']}";
            $this->assign(compact('limit_time_txt'));
        }

        if($info['activity_pro_type'] == 4){
            $pro_list = ActivityProductModel::getActivityStorePro($id);
        }else if($info['activity_pro_type'] == 5){
            $pro_list = ActivityProductModel::getActivityTypePro($id);
        }

        $this->assign(compact('info','status_type','status_txt','banners','recom_coupons','pro_list'));

        return $this->fetch();

    }

    public function editActivityStatus(){

        try{
            $type = input('post.type',0,'intval');
            $activity_long = input('post.activity_long',0,'intval');
            $id = input('post.id',0,'intval');
            if(!$type || !$id)throw new Exception('参数错误');
            $start_time = time();
            if($type == 2){
                $start_time_str = input('post.start_time','','addslashes,strip_tags,trim');
                if(!$start_time_str)throw new Exception('参数缺失');
                $start_time = strtotime($start_time_str);
            }
            $end_time = $start_time + $activity_long * 24 * 60 * 60;
            $data = compact('start_time','end_time');
            $data['is_clock'] = $type==2?1:2;
            $data['clock_time'] = $start_time;
            $data['status'] = 2;
            $res = ActivityModel::onlineActivity($id, $data);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }

    }

    public function offLineActivity(){
        try{
            $id = input('post.id',0,'intval');
            $res = ActivityModel::offLine($id);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    public function tempNotLineActivity(){
        try{
            $id = input('post.id',0,'intval');
            $res = ActivityModel::tempNotLineActivity($id);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    public function addBanner(ActivityValidate $ActivityValidate){

        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $ActivityValidate->scene('add_banner')->check(input());
            if(!$res)throw new Exception($ActivityValidate->getError());

            #逻辑
            $img = str_replace('\\','/', input('post.banner','','strip_tags,trim'));
            $type = input('post.turn_type',0,'intval');
            $link_id = input('post.turn_link_id',0,'intval');

            $data = compact('img','type','link_id');
            ##添加banner
            $res = ActivityBannerModel::add($data);
            if($res === false)throw new Exception('新增失败');

            #返回
            return $this->success('添加成功','',$res);
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }

    }

    public function editBanner(ActivityValidate $ActivityValidate){

        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $ActivityValidate->scene('add_banner')->check(input());
            if(!$res)throw new Exception($ActivityValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $img = str_replace('\\','/', input('post.banner','','strip_tags,trim'));
            $type = input('post.turn_type',0,'intval');
            $link_id = input('post.turn_link_id',0,'intval');
            $data = compact('img','type','link_id');
            ##修改banner
            $res = ActivityBannerModel::edit($id, $data);
            if($res ===  false)throw new Exception('修改失败');

            #返回
            return $this->success('修改成功','',$id);
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }

    }

    public function couponList(){
        $coupon_can_use = CouponRuleModel::getCouponRecom();
        $coupon_can_rtn = CouponRuleModel::getCouponRtn();
        return $this->success('','',compact('coupon_can_use','coupon_can_rtn'));
    }

    public function couponRtnList(){
        $list = CouponRuleModel::getCouponRtn();
        return $this->success('','',$list);
    }

    public function productList(){
        $store_data = input('post.pro_data_type_store/a',[]);
        $store_id = input('post.store_id',0,'intval');
        $activity_id = input('post.id',0,'intval');
        $list = array_combine(array_column($store_data,'store_id'),$store_data);
        $pro_data = isset($list[$store_id]['pro_data'])?$list[$store_id]['pro_data']:[];
        $pro_ids = array_column($pro_data,'pro_id');
        $productList = ProductModel::getProductByStore($store_id, $pro_ids);

        ##活动中的商品
        $pro_in_activity = ActivityProductModel::productInActivity($activity_id);
        foreach($productList as $k => &$v){
//            $v['is_check'] = in_array($v['id'],$pro_ids)? true:false;
//            if(in_array($v['id'], $pro_ids))unset($productList[$k]);
            $v['is_disabled'] = in_array($v['id'],$pro_in_activity)? true:false;
        }
        return $this->success('','',$productList);
    }

    public function storeProductList(){
        $keywords = input('post.keywords','','addslashes,strip_tags,trim');
        if(!$keywords)return $this->success('','',[]);

        $data = input('post.data/a',[]);
        $pros = array_column($data,'pro_data');
        $pro_ids = [];
        foreach($pros as $k=>$v){
            $pro_ids = array_merge($pro_ids,array_column($v,'pro_id'));
        }

        $activity_id = input('post.id',0,'intval');
        ##活动中商品
        $pro_in_activity = ActivityProductModel::productInActivity($activity_id);

        $store_list = storeModel::getStoreListByKeywords($keywords);
        foreach($store_list as &$v){
            $pro_list = ProductModel::getProductByStore($v->id, $pro_ids);
            foreach($pro_list as &$vv){
               $vv['is_check'] = in_array($vv->id,$pro_ids)?true:false;
               $vv['is_disabled'] = in_array($vv['id'],$pro_in_activity)? true:false;
            }
            $v['pro_list'] = $pro_list;
        }
        return $this->success('','',$store_list);
    }

    public function storeList(){
        $keywords = input('keywords','','addslashes,strip_tags,trim');
        $type = input('type',0,'intval');
        $size = input('size',8,'intval');
        $from = input('from','','addslashes,strip_tags,trim');
        $store_id = input('val','','addslashes,strip_tags,trim');
        $store_id = $store_id?explode(',',$store_id):[];
        $size = $size <= 0?10:$size;
        #获取商铺列表

        $model = new storeModel();
        $param = $this->request->param();

        if ($keywords) {
            $where['store_name|brand_name|city'] = ['like', "%{$keywords}%"];
        }

        if ($type) {
            $where['type'] = ['eq',$type];
        }

        $lists = $model->where('sh_status',1)->where($where)->order(['create_time'=>'desc'])->paginate($size,false,['query'=>$this->request->param()]);

        foreach ($lists as $k=>$v){
            $v->store_name2 = $v->store_name;
            $v->store_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->store_name);
            $v->brand_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->brand_name);
            $v->city = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->city);
        }

        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        $this->assign(compact('from','store_id'));
        return $this->fetch();
    }
    /*
* 推荐banner列表
* */
    public function recommend_banner_list(){
        $param = $this->request->param();
        if (!empty($param['keywords'])) {
            if(substr_count($param['keywords'],'%') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($param['keywords'],'_') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($param['keywords'],'_') + substr_count($param['keywords'],'%')) == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_%'));
            $where['recommend_banner.title|recommend_banner.description'] = ['like',"%{$param['keywords']}%"];
        }
       $lists = Db::name('recommend_banner')->where($where)->order(['sort'=>'desc'])->paginate(15,false,['query'=>$this->request->param()]);
        $data = $lists->toArray()['data'];
        foreach($data as $k => $v){
            $data[$k]['activity'] = Db::name('activity')->where(['id'=>$v['activity_id']])->value('title');
        }
        $this->assign('param',$param);
        $this->assign('lists',$lists);
        $this->assign('data',$data);
        return $this->fetch();
    }
    /*
* 添加编辑推荐banner
* */
    public function recommend_banner_add()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new RecommendbannerModel();

        //编辑操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();

                //验证菜单是否存在
                $topic = $model->where('id',$id)->find();
                if(empty($topic)) {
                    return $this->error('id不正确');
                }
                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/activity/recommend_banner_list');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                ##获取活动列表
                $list = ActivityModel::getCanUseAcList();
                $this->assign(compact('list'));
                if(!empty($data)) {
                    $this->assign('data',$data);
//                    $this->assign('title','编辑风格');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            //是新增操作s
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $post['create_time']=time();
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/activity/recommend_banner_list');
                }
            } else {

                ##获取活动列表
                $list = ActivityModel::getCanUseAcList();
                $this->assign(compact('list'));
                //非提交操作
//                $this->assign('title','新增风格');
                return $this->fetch();
            }
        }

    }
    //删除推荐banner
    public function recommend_banner_delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('recommend_banner')->where('id',$id)->setField('status',-1)) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/activity/recommend_banner_list');
            }
        }
    }
    /*
* 推荐banner启用/禁用
* */
    public function recommend_banner_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('recommend_banner')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/activity/recommend_banner_list');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
    /*
  * banner排序
  * */
    public function paixu()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('recommend_banner')->where('id',$val)->value('sort');
                if($order != $post['sort'][$k]) {
                    if(false === Db::name('recommend_banner')->where('id',$val)->update(['sort'=>$post['sort'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/activity/recommend_banner_list');
        }
    }
}