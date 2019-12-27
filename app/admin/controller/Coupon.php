<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/26
 * Time: 10:28
 */

namespace app\admin\controller;

use app\admin\model\CouponRule;
use app\admin\model\CouponRule as CouponRuleModel;
use think\Db;
use app\admin\model\Store as storeModel;
use think\Exception;
use app\admin\model\Extend as ExtendModel;
use app\admin\model\Product as ProductModel;
use app\admin\model\CssCouponCode as CouponCodeModel;
use app\admin\model\CouponUseRule as CouponUseRuleModel;
use app\admin\validate\CouponRule as CouponRuleValidate;
use think\Loader;

class Coupon extends Admin
{
    public function coupon_set(){

        if ($this->request->isPost()){

            $id = input('id');

            $post = $this->request->post();

            $post['satisfy_money'] = ($post['a'] == 1) ? $post['satisfy_money'] : 0 ;

            $result = Db::name('coupon_rule')->strict(false)->where('id',$id)->update($post);

            if ($result !== false){
                return $this->success('修改成功');
            }else{
                return $this->error('修改失败');
            }


        }else{
            $data = Db::name('coupon_rule')->where('id',1)->find();
            $this->assign('data',$data);
            return $this->fetch();
        }

    }
//优惠券列表
    public function index()
    {
        ##参数
        $keywords = input('keywords','','addslashes,strip_tags,trim');
        if($keywords)$where['coupon_name'] = ['like', "%{$keywords}%"];
        $is_open = input('is_open',-1,'intval');
        if($is_open >= 0)$where['is_open'] = $is_open;
        $coupon_type = input('coupon_type',0,'intval');
        if($coupon_type)$where['coupon_type'] = $coupon_type;
        $type = input('type',0,'intval');
        if($type)$where['type'] = $type;
        $where['fb_user'] = '平台';
        if(!$coupon_type)$where['coupon_type'] = ['NOT IN', [10, 11]];

        $model = new CouponRule();
        $data = $model
            ->where($where)
            ->order('id desc')
            ->paginate(15,false,['query'=>$this->request->param()]);
        foreach($data as &$v){
            $v->product_name = Db::name('product')->where(['id'=>$v->product_id])->value('product_name');
            $v->show_total_handle = Db::name('coupon')->where(['coupon_id'=>$v->id])->count()?0:1;
            $rule_model_ids = explode(',',$v->rule_model_id);
            $rule = [];
            foreach($rule_model_ids as &$vv){
                $rule[] = Db::name('coupon_use_rule')->where(['id'=>$vv])->value('title');
            }
            $v->rule = $rule;
        }
        $this->assign('data',$data);
        $this->assign(compact('keywords','is_open','coupon_type','type'));
        return $this->fetch();
    }

    public function publish(CouponUseRuleModel $CouponUseRule)
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
//        $model = new Css_couponModel();
        $model = new CouponRule();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $param = $this->request->post();

                $rst=Db::name('coupon_rule')->where('id',$id)->find();
                $type = $rst['type'];
                $coupon_type = $rst['coupon_type'];
                if(empty($rst)) {
                    return $this->error('id不正确');
                }
                ##判断优惠券是否已领取
                $check = Db::name('coupon')->where(['coupon_id'=>$id])->count('id');
                if($check)return $this->error('优惠券已被领取,不能修改');

                ##必传参数
                $coupon_name = $param['coupon_name'];
                $satisfy_money = $param['satisfy_money'];
                $coupon_money = $param['coupon_money'];

                $use_type = intval($param['use_type']);
                $is_open = intval($param['status']);
                $can_stacked = intval($param['can_stacked']);
                $is_superposition = input('post.is_superposition',1,'intval');
                $zengsong_number = $param['get_number'];
                $rule_model_id = input('post.rule_model_id/a',0,'intval');
                $platform_bear = input('post.platform_bear',0,'intval');
                $mall_stacked = input('post.mall_stacked',0,'intval');
                if($can_stacked == 1){
                    $platform_bear = $type==1?1:0;
                }

                ###初步判断
                if(!$coupon_name || !$zengsong_number ||!$rule_model_id)return $this->error('参数缺失');
                if(($coupon_type != 10 && $coupon_type != 11) && (!$use_type || !$can_stacked || !$is_superposition))return $this->error('参数缺失');
                if($satisfy_money > 0 && $coupon_money >= $satisfy_money)return $this->error('优惠券金额必须大于满减金额');
                if($platform_bear > 100 || $platform_bear < 0)return $this->error('平台承担比例错误');
                $platform_bear = $platform_bear / 100;
                $rule_model_id = implode(',',$rule_model_id);

                ##设置默认值
                $start_time = 0;
                $end_time = 0;
                $total_number = $surplus_number = $rst['total_number'];
                $days = 0;
                $member_card_id = 0;
                $client_type = 0;
                $grant_object = 3;
                $cooperate_name = '';
                $product_id = 0;
                $product_ids = "";
                $store_name = "";
                $store_id = 0;
                $store_ids = "";
                $is_solo = 0;

                ##不同类型数据
                if($type == 2){  //店铺券
//                    $store_ids = input('post.store_ids/a',[]);
//                    if(!$store_ids)return $this->error('请选择店铺');
//                    $is_solo = count($store_ids)>1?0:$store_ids[0];
//                    foreach($store_ids as &$v)$v = "[{$v}]";
//                    $store_ids = implode(',',$store_ids);
                }

                if($type == 3){  //商品券
                    $store_id = input('post.store_id/d',0,'intval');
                    if(!$store_id)return $this->error('请选择店铺');
                    $store_name = Db::name('store')->where(['id'=>$store_id])->value('store_name');
                    $product_ids = input('post.product_id/a',[]);
                    if(!$product_ids)return $this->error('请选择商品');
                    $is_solo = count($product_ids)>1?0:(int)$product_ids[0];
                    foreach($product_ids as &$v)$v = "[{$v}]";
                    $product_ids = implode(',',$product_ids);
                }

                if($type == 1 && $coupon_type == 5){  //平台券
                    $cooperate_name = input('post.cooperate_name','','addslashes,strip_tags,trim');
                    if(!$cooperate_name)return $this->error('请填写合作平台名称');
                }

                if($coupon_type == 1){  //新人券
                    $client_type = input('post.client_type',0,'intval');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 2){  //会员优惠券
                    $member_card_id = input('post.member_card_id',0,'intval');
//                    if(!$member_card_id)return $this->error('请选择会员类型');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 6){  //推广券
                    $extend_id = input('post.extend_id/a',[]);
                    if(!$extend_id)return $this->error('请选择推广人');
                }

                if($coupon_type == 7){  //邀请券
                    $grant_object = input('post.grant_object',0,'intval');
                    if(!$grant_object)return $this->error('请选择发放对象');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 5 || $coupon_type == 6 || $coupon_type == 8 || $coupon_type == 9 || $coupon_type == 12){
                    $start_time=strtotime($param['start_time']);
                    $end_time=strtotime($param['end_time']);
                    if($end_time <= $start_time)return $this->error('结束时间必须大于开始时间');
//                    if($end_time <= time())return $this->error('结束时间必须大于当前时间');
                    if($coupon_type == 6 || $coupon_type == 8 || $coupon_type == 9 || $coupon_type == 12){
                        $total_number = $surplus_number = input('post.total_number',0,'intval');
                        if(!$total_number || $total_number < 0)return $this->error('请填写正确的发券总数');
                    }
                }

                if($coupon_type == 10 || $coupon_type == 11){  //线下优惠券
                    $time_type = input('post.time_type',1,'intval');
                    if($time_type==1){
                        $start_time=strtotime($param['start_time']);
                        $end_time=strtotime($param['end_time']);
                        if($end_time <= $start_time)return $this->error('结束时间必须大于开始时间');
                        if($end_time <= time())return $this->error('结束时间必须大于当前时间');
                    }else{
                        $days = input('post.days',0,'intval');
                        if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                    }
                    $check_num = input('post.check_num',0,'intval');

                    $total_number = input('post.total_number',0,'intval');
                    if(!$total_number || $total_number < 0)return $this->error('请填写正确的发券总数');
                    $surplus_number = $total_number;

                    //只能一张
                    if($zengsong_number>1)return $this->error('线下券每人只能领取一张!');
                }

                $data = compact('cooperate_name','coupon_name','is_open','satisfy_money','coupon_money','start_time','end_time','total_number','use_type','days','zengsong_number','member_card_id','client_type','is_superposition','grant_object','can_stacked','rule_model_id','product_id','product_ids','is_solo','platform_bear','surplus_number','check_num','mall_stacked');

                Db::startTrans();
                try{
                    $rst2 = $model->where('id',$id)->update($data);
                    if($rst2===false){
                        throw new Exception('修改失败');
                    }
//                    if($total_number < $info['total_number'] && $coupon_type == 5){
//                        $num = $info['total_number'] - $total_number;
//                        $coupon_code_ids = Db::name('css_coupon_code')->where(['css_coupon_id'=>$id,'css_coupon_table_num'=>1])->limit($num)->order('id','asc')->column('id');
//                        $res = Db::name('css_coupon_code')->where(['id'=>['IN',$coupon_code_ids]])->delete();
//                        if($res === false)throw new Exception('兑换码更新失败');
//                    }

                    if($coupon_type == 6){  //商务推广券
                        ##查询以前的改券的所有推广码
                        $list_pre = Db::name('css_coupon_code')->where(['css_coupon_id'=>$id,'type'=>2])->field('id,extend_id,status')->select();
                        $arr_add = [];
                        $arr_on = [];
                        $list_pre2 = $list_pre;
                        foreach($extend_id as $k =>$v){
                            $flag = 0;
                            foreach($list_pre as $key =>$val){
                                if($v == $val['extend_id']){
                                    unset($list_pre2[$key]);
                                    if($val['status'] == -1){ //修改上线
                                        $arr_on[] = $val['id'];
                                    }
                                    continue;
                                }
                                $flag ++ ;
                                if($v != $val['extend_id'] && $flag >= count($list_pre)){ //增加
                                    $arr_add[] = [
                                        'css_coupon_id' => $id,
                                        'create_time' => time(),
                                        'type' => 2,
                                        'extend_id' => $v,
                                        'exchange_code' => get_extend_coupon_code($id,$v)
                                    ];
                                    continue;
                                }
                            }
                        }
                        if(!empty($arr_on)){  //上线
                            $res = Db::name('css_coupon_code')->where(['id'=>['IN',$arr_on]])->setField('status',1);
                            if($res == false)throw new Exception('兑换码更新失败1');
                        }
                        if(!empty($arr_add)){
                            ##添加
                            $res = Db::name('css_coupon_code')->insertAll($arr_add);
                            if($res === false)throw new Exception('兑换码新增失败');
                        }
                        if(!empty($list_pre2)){ //下线
                            $arr_down = array_column($list_pre2,'id');
                            $res = Db::name('css_coupon_code')->where(['id'=>['IN',$arr_down]])->setField('status',-1);
                            if($res === false)throw new Exception('兑换码更新失败2');
                        }
                    }

                    Db::commit();
                }catch(\Exception $e){
                    Db::rollback();
                    return $this->error($e->getMessage());
                }
                return $this->success('修改成功','admin/coupon/index');

            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                $extends = [];
                $extend_ids = '';
                if($data['coupon_type'] == 6){
                    $extends = Db::name('css_coupon_code')->alias('c')
                        ->join('extend e','e.id = c.extend_id','LEFT')
                        ->where(['c.css_coupon_id'=>$data['id']])
                        ->field('e.id,e.extend_name,c.status')
                        ->select();
                    $extend_ids = array_column($extends,'id');
                    $extend_ids = implode(',',$extend_ids);
                }
                ##店铺
                $store_ids = explode(',',str_replace(']','',str_replace('[','',$data['store_ids'])));
                $stores = Db::name('store')->where(['id'=>['IN',$store_ids]])->field('id,store_name')->select();
                $data['store_ids'] = str_replace(']','',str_replace('[','',$data['store_ids']));
                $store_info = empty($stores)?[]:$stores[0];
                ##商品
                $product_ids = explode(',',str_replace(']','',str_replace('[','',$data['product_ids'])));
                $products = Db::name('product')->where(['id'=>['IN',$product_ids]])->field('id,product_name')->select();
                ##查询会员类型
                $memberLists = Db::name('member_card')->where(['status'=>1])->field('id,card_name')->select();
                ##查询是否可修改
                $can_edit = Db::name('coupon')->where(['coupon_id'=>$id])->count()?0:1;
                ##规则模板
                $ruleLists = $CouponUseRule->getCanUseLists();

                $ruleCommon = $CouponUseRule->getCanUseIds();

                $data['platform_bear'] = $data['platform_bear'] * 100;
                $rule_model_id = explode(',',$data['rule_model_id']);
                $rule_models = [];
                foreach($rule_model_id as $v){
                    $rule_models[] = Db::name('coupon_use_rule')->where(['id'=>$v])->field('id,title')->find();
                }

                $data['product_ids'] = str_replace(']','',str_replace('[','',$data['product_ids']));
                $this->assign(compact('data','extends','extend_ids','memberLists','stores','store_info','products','can_edit','ruleLists','rule_models','rule_model_id','ruleCommon'));
                return $this->fetch();
            }
        } else {
            //是新增操作
            if($this->request->isPost()) {
                //是提交操作
                $param = $this->request->post();
                ##必传参数
                $type = $param['type'];
                $coupon_name = $param['coupon_name'];
                $satisfy_money = $param['satisfy_money'];
                $coupon_money = $param['coupon_money'];
                $coupon_type = $param['coupon_type'];

                $use_type = intval($param['use_type']);
                $is_open = intval($param['status']);
                $can_stacked = intval($param['can_stacked']);
                $is_superposition = input('post.is_superposition',1,'intval');
                $zengsong_number = $param['get_number'];
                $rule_model_id = input('post.rule_model_id/a',0,'intval');
                $platform_bear = input('post.platform_bear',0,'intval');
                $mall_stacked = input('post.mall_stacked',0,'intval');
                if($can_stacked == 1){
                    $platform_bear = $type==1?1:0;
                }

                ###初步判断
                if(!$type || !$coupon_type || !$coupon_name || !$zengsong_number || !$rule_model_id)return $this->error('参数缺失');
                if(($coupon_type != 10 && $coupon_type != 11) && (!$use_type || !$can_stacked || !$is_superposition))return $this->error('参数缺失');
                if($satisfy_money > 0 && $coupon_money >= $satisfy_money)return $this->error('优惠券金额必须大于满减金额');
                if($platform_bear > 100 || $platform_bear < 0)return $this->error('平台承担比例错误');
                $platform_bear = $platform_bear / 100;
                $rule_model_id = implode(',',$rule_model_id);

                ##设置默认值
                $create_time = time();
                $fb_user = '平台';
                $store_name = '';   //未使用
                $store_id = 0;
                $store_ids = "";
                $product_id = 0;
                $product_ids = "";
                $start_time = 0;
                $end_time = 0;
                $surplus_number = $total_number = 0;
                $days = 0;
                $member_card_id = 0;
                $client_type = 0;
                $grant_object = 3;
                $cooperate_name = '';
                $is_solo = 0;
                $check_num = 0;
                $kind = 0;

                ##不同类型数据
                if($type == 1 && $coupon_type == 5){  //平台券
                    $cooperate_name = input('post.cooperate_name','','addslashes,strip_tags,trim');
                    if(!$cooperate_name)return $this->error('请填写合作平台名称');
                }
                if($type == 2){  //店铺券
                    $store_ids = input('post.store_ids/a',[]);
                    if(!$store_ids)return $this->error('请选择店铺');
//                    $is_solo = count($store_ids)>1?0:$store_ids[0];
//                    foreach($store_ids as &$v)$v = "[{$v}]";
//                    $store_ids = implode(',',$store_ids);
                }
                if($type == 3){  //商品券
                    $store_id = input('post.store_id/d',0,'intval');
                    if(!$store_id)return $this->error('请选择店铺');
                    $store_name = Db::name('store')->where(['id'=>$store_id])->value('store_name');
                    $product_ids = input('post.product_id/a',[]);
                    if(!$product_ids)return $this->error('请选择商品');
                    $is_solo = count($product_ids)>1?0:$product_ids[0];
                    foreach($product_ids as &$v)$v = "[{$v}]";
                    $product_ids = implode(',',$product_ids);
                }

                if($coupon_type == 1){  //新人券
                    $client_type = input('post.client_type',0,'intval');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 2){  //会员优惠券
                    $member_card_id = input('post.member_card_id',0,'intval');
//                    if(!$member_card_id)return $this->error('请选择会员类型');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 6){  //推广券
                    $extend_id = input('post.extend_id/a',[]);
                    if(!$extend_id)return $this->error('请选择推广人');
                }

                if($coupon_type == 7){  //邀请券
                    $grant_object = input('post.grant_object',0,'intval');
                    if(!$grant_object)return $this->error('请选择发放对象');
                    $days = input('post.days',0,'intval');
                    if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                }

                if($coupon_type == 5 || $coupon_type == 6 || $coupon_type == 8 || $coupon_type == 9 || $coupon_type == 12){
                    $start_time=strtotime($param['start_time']);
                    $end_time=strtotime($param['end_time']);
                    if($end_time <= $start_time)return $this->error('结束时间必须大于开始时间');
                    if($end_time <= time())return $this->error('结束时间必须大于当前时间');
                    $total_number = input('post.total_number',0,'intval');
                    if(!$total_number || $total_number < 0)return $this->error('请填写正确的发券总数');
                    $surplus_number = $total_number;
                }

                if($coupon_type == 5 && $total_number > 5000){
                    $this->error('商家合作券一次最多生成5000张');
                }

                if($coupon_type == 10 || $coupon_type == 11){  //线下优惠券
                    $time_type = input('post.time_type',1,'intval');
                    if($time_type==1){
                        $start_time=strtotime($param['start_time']);
                        $end_time=strtotime($param['end_time']);
                        if($end_time <= $start_time)return $this->error('结束时间必须大于开始时间');
                        if($end_time <= time())return $this->error('结束时间必须大于当前时间');
                    }else{
                        $days = input('post.days',0,'intval');
                        if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                    }
                    $check_num = input('post.check_num',0,'intval');
                    $kind = input('post.kind',0,'intval');
                    if(!$kind)return $this->error('请选择优惠券种类');

                    $total_number = input('post.total_number',0,'intval');
                    if(!$total_number || $total_number < 0)return $this->error('请填写正确的发券总数');
                    $surplus_number = $total_number;

                    //只能一张
                    if($zengsong_number>1)return $this->error('线下券每人只能领取一张!');


                }

                $data = compact('cooperate_name','coupon_name','is_open','satisfy_money','coupon_money','start_time','end_time','type','fb_user','coupon_type','create_time','total_number','surplus_number','use_type','days','zengsong_number','member_card_id','client_type','is_superposition','grant_object','can_stacked','store_name','rule_model_id','product_ids','is_solo','platform_bear','check_num','kind','mall_stacked');

                $data_arr = [];

                if($type == 1){  //平台券
                    $data['store_id'] = 0;
                    $data['product_id'] = 0;
                    $data_arr[] = $data;
                }

                if($type == 2){  //店铺券
                    foreach($store_ids as $v){
                        $data['store_id'] = $v;
                        $data['store_name'] = Db::name('store')->where(['id'=>$v])->value('store_name');
                        $data_arr[] = $data;
                    }
                }

                if($type == 3){  //商品券
                    $data['store_id'] = $store_id;
                    $data_arr[] = $data;
                }

                Db::startTrans();
                try{
//                    ##添加卡券
//                    $rst=$model->insertGetId($data);
//                    if($rst===false){
//                        throw new Exception('添加失败');
//                    }
//                    ##如果是合作商券,则生成兑换码
//                    if($coupon_type == 5){
//                        #生成
//                        $codes = get_coupon_code($rst,$total_number);
//                        $data_coupon_code = [];
//                        foreach($codes as $k => $v){
//                            $data_coupon_code[] = [
//                                'css_coupon_id' => $rst,
//                                'exchange_code' => $v,
//                                'create_time' => time()
//                            ];
//                        }
//                        $res = Db::name('css_coupon_code')->insertAll($data_coupon_code);
//                        if($res === false)throw new Exception('兑换码生成失败');
//                    }
//                    ##如果是商务推广券
//                    if($coupon_type == 6){
//                        #生成
//                        $codes = get_coupon_code($rst, count($extend_id));
//                        $data_coupon_code = [];
//                        foreach($codes as $k => $v){
//                            $data_coupon_code[] = [
//                                'css_coupon_id' => $rst,
//                                'exchange_code' => $v,
//                                'create_time' => time(),
//                                'type' => 2,
//                                'extend_id' => $extend_id[$k]
//                            ];
//                        }
//                        $res = Db::name('css_coupon_code')->insertAll($data_coupon_code);
//                        if($res === false)throw new Exception('兑换码生成失败');
//                    }

                    foreach($data_arr as $k => $v){
                        $data = $v;
                        ##添加卡券
                        $rst=$model->insertGetId($data);
                        if($rst===false){
                            throw new Exception('添加失败');
                        }
                        ##如果是合作商券,则生成兑换码
                        if($coupon_type == 5){
                            #生成
                            $codes = get_coupon_code($rst,$total_number);
                            $data_coupon_code = [];
                            foreach($codes as $kk => $vv){
                                $data_coupon_code[] = [
                                    'css_coupon_id' => $rst,
                                    'exchange_code' => $vv,
                                    'create_time' => time()
                                ];
                            }
                            $res = Db::name('css_coupon_code')->insertAll($data_coupon_code);
                            if($res === false)throw new Exception('兑换码生成失败');
                        }
                        ##如果是商务推广券
                        if($coupon_type == 6){
                            #生成
                            $codes = get_coupon_code($rst, count($extend_id));
                            $data_coupon_code = [];
                            foreach($codes as $kk => $vv){
                                $data_coupon_code[] = [
                                    'css_coupon_id' => $rst,
                                    'exchange_code' => $vv,
                                    'create_time' => time(),
                                    'type' => 2,
                                    'extend_id' => $extend_id[$kk]
                                ];
                            }
                            $res = Db::name('css_coupon_code')->insertAll($data_coupon_code);
                            if($res === false)throw new Exception('兑换码生成失败');
                        }

                    }
                    Db::commit();
                }catch(\Exception $e){
                    Db::rollback();
                    return $this->error($e->getMessage());
                }
                return $this->success('添加成功',url('Coupon/index'));
            } else {
                ##查询会员类型
                $memberLists = Db::name('member_card')->where(['status'=>1])->field('id,card_name')->select();
                ##查询优惠券使用规则模板
                $ruleLists = $CouponUseRule->getCanUseLists();
                $this->assign(compact('memberLists','ruleLists'));
                //非提交操作
                return $this->fetch();
            }
        }

    }
    public function delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            ##获取卡券信息
            $info = Db::name('coupon_rule')->where(['id'=>$id])->field('total_number,coupon_type,surplus_number')->find();
            if($info['total_number'] > $info['surplus_number'])
                return $this->error('卡券已经发放,不支持删除,您可以选择下架');
            $rst=Db::name('coupon_rule')->where('id',$id)->delete();
            if($rst===false) {
                return $this->error('删除失败');
            } else {
                return $this->success('删除成功','admin/coupon/index');
            }
        }
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

    public function extendList(){
        $keywords = input('keywords','','addslashes,strip_tags,trim');
        $type = input('type',0,'intval');
        $size = input('size',8,'intval');
        $size = $size <= 0?10:$size;
        #获取推广列表

        $model = new ExtendModel();
        $param = $this->request->param();

        if ($keywords) {
            $where['extend_name|mobile'] = ['like', "%{$keywords}%"];
        }

        if ($type) {
            $where['type'] = ['eq',$type];
        }

        $lists = $model->where('status',1)->where($where)->order(['create_time'=>'desc'])->paginate($size,false,['query'=>$this->request->param()]);

        foreach ($lists as $k=>$v){
            $v->store_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->extend_name);
            $v->brand_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->mobile);
        }

        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function productList(){
        $keywords = input('keywords','','addslashes,strip_tags,trim');
        $store_id = input('store_id',1,'intval');
        $product_ids = input('product_ids','','addslashes,strip_tags,trim');
        $product_ids = explode(',',$product_ids);

        $size = input('size',8,'intval');
        $size = $size <= 0?10:$size;
        #获取推广列表

        $model = new ProductModel();
        $param = $this->request->param();

        $where['p.store_id'] = $store_id;
        $where['p.status'] = 1;
        $where['p.sh_status'] = 1;
        if ($keywords) {
            $where['p.product_name'] = ['like', "%{$keywords}%"];
        }

        $lists = $model->alias('p')
            ->where($where)
            ->order(['p.create_time'=>'desc'])
            ->field('p.id,p.product_name')
            ->paginate($size,false,['query'=>$this->request->param()]);

        $list = $lists->toArray();
        $list = $list['data'];
        foreach($list as &$v){
            $v['cover'] = Db::name('product_specs')->where(['product_id'=>$v['id']])->value('cover');
        }

        $list_js = Db::name('product')->where(['store_id'=>$store_id,'status'=>1,'sh_status'=>1])->field('id,product_name')->select();
        $arr_key = array_column($list_js,'id');
        $list_js = json_encode(array_combine($arr_key,$list_js));

        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        $this->assign(compact('store_id','list','product_ids','list_js'));
        return $this->fetch();
    }

    /**
     * 修改状态
     * @param CouponRuleValidate $CouponRuleValidate
     * @param CouponRuleModel $CouponRuleModel
     */
    public function status(CouponRuleValidate $CouponRuleValidate, CouponRuleModel $CouponRuleModel){
        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $CouponRuleValidate->scene('edit_status')->check(input());
            if(!$res)throw new Exception($CouponRuleValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $status = input('post.status',1,'intval');

            $res = $CouponRuleModel->updateField('is_open',$status,$id);
            if($res === false)throw new Exception('修改失败');

            #返回
            return $this->success('修改成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    /**
     * 修改状态
     * @param CouponRuleValidate $CouponRuleValidate
     * @param CouponRuleModel $CouponRuleModel
     */
    public function setAttr(CouponRuleModel $CouponRuleModel){
        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $field = input('post.field','','addslashes,strip_tags,trim');
            $value = input('post.value','');
            $id = input('post.id',0,'intval');
            if(!$field || !$id)return $this->error('参数缺失');

            $res = $CouponRuleModel->updateField($field,$value,$id);
            if($res === false)throw new Exception('修改失败');

            #返回
            return $this->success('修改成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    public function extendCouponList(CouponCodeModel $CouponCodeModel){
        $extend_id = input('extend_id',0,'intval');
        $size = input('size',8,'intval');
        $size = $size <= 0?10:$size;

        $lists = $CouponCodeModel->alias('cc')
            ->join('coupon_rule cr','cc.css_coupon_id = cr.id','LEFT')
            ->where(['cc.extend_id'=>$extend_id,'cc.status'=>1,'cc.type'=>2])
            ->field('cc.id,cc.create_time,cr.coupon_name,cr.is_open,cc.exchange_code')
            ->paginate($size,false,['query'=>$this->request->param()]);

        foreach ($lists as &$v){
            $v['exchange_num'] = Db::name('coupon_exchange_record')->where(['extend_id'=>$extend_id,'code_id'=>$v['id']])->count('id');
            $v['extend_num'] = Db::name('coupon_exchange_record')->where(['extend_id'=>$extend_id,'code_id'=>$v['id']])->group('user_id')->count('id');
        }
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function addRuleModel(CouponRuleValidate $couponRuleValidate, CouponUseRuleModel $CouponUseRuleModel){
        try{
            #验证
            $res = $couponRuleValidate->scene('coupon_rule_add')->check(input());
            if(!$res)throw new Exception($couponRuleValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $title = input('post.title','','addslashes,strip_tags,trim');
            $is_common = input('post.is_common',0,'intval');

            $data = compact('title','is_common');
            $res = $id?$CouponUseRuleModel->edit($id, $data):$CouponUseRuleModel->add($data);
            if($res === false)throw new Exception('操作失败');

            ##获取最新的模板列表
            $list = $CouponUseRuleModel->getCanUseLists();
            $id = $res;

            #返回
            return rtnJson(1,'操作成功',compact('list','id'));

        }catch(Exception $e){
            return rtnJson(0,$e->getMessage());
        }
    }

    public function delRuleModel(){
        try{
            $id = input('post.id',0,'intval');
            if(!$id)throw new Exception('参数缺失');

            #逻辑
            $res = CouponUseRuleModel::destroy($id);
            if($res === false)throw new Exception('操作失败');

            #返回
            return $this->success('删除成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    /**
     * 修改状态
     * @param CouponRuleValidate $CouponRuleValidate
     * @param CouponRuleModel $CouponRuleModel
     */
    public function ruleModelStatus(CouponRuleValidate $CouponRuleValidate, CouponUseRuleModel $CouponUseRuleModel){
        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $CouponRuleValidate->scene('edit_status')->check(input());
            if(!$res)throw new Exception($CouponRuleValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $status = input('post.status',1,'intval');

            $res = $CouponUseRuleModel->updateField($id,'status',$status);
            if($res === false)throw new Exception('修改失败');

            #返回
            return $this->success('修改成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    /*
  PHPExcel
 */
    public function excel(){

        $coupon_id = input('coupon_id',0,'intval');
        if(!$coupon_id)return $this->error('请选择卡券');

        Loader::import('PHPExcel.Classes.PHPExcel'); //thinkphp5加载类库
        $objPHPExcel = new \PHPExcel();  //实例化PHPExcel类，
        $objSheet = $objPHPExcel->getActiveSheet();  //获取当前活动的sheet对象
        $objSheet->setTitle("test");  //给当前活动sheet起个名称

        /*字符串方式填充数据，开发中可以将数据库取出的数据根据具体情况遍历填充*/
        $objSheet->setCellValue("A1","兑换码")->setCellValue("B1","状态");  //填充数据
        // $objSheet->setCellValue("A2","张三")->setCellValue("B2","3434346354634563443634634634563")->setCellValue("C2","一班");  //填充数据
        //$objSheet->setCellValue("A2","张三")->setCellValueExplicit("B2","123216785321321321312",\PHPExcel_Cell_DataType::TYPE_STRING)->setCellValue("C2","一班");//填充数据时添加此方法，并且使用getNumberFormat方法和setFormatCode方法设置，可以让如订单号等长串数字不使用科学计数法

        /*数组方式填充数据*/
//        $arr = [
//            [],  //空出第一行，打印出的效果将空出第一行
//            ['','信息'],  //空出第一列，打印出的效果将空出第一列
//            ['',"姓名\nname",'年龄','性别','分数','年级'],  //空出第一列,*这里的\n是为了*配合setWrapText自动换行
//            ['','李四','33','男','33543653456346363646','4'],
//            ['','李四','33','男','54546456456447478548','4'],
//            ['','李四','33','男','56635374658465632545','5'],
//            ['','李四','33','男','87473457856856745646','5'],
//            ['','李四','33','男','32','7'],
//            ['','李四','33','男','98','5'],
//        ];
//        $arr = [
//            '0' => [],
//            ['1','2']
//        ];
        $arr = $this->getCouponCodeData($coupon_id);
        $objSheet->fromArray($arr);  //填充数组数据，较为消耗资源且阅读不便，不推荐


        /*样式配置信息--方法配置*/
        //$objSheet->mergecells("B2:F2");  //合并单元格
        $objSheet->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//设置excel文件默认水平垂直方向居中,垂直setVertical，水平setHorizontal,因为是基于thinkPHP5所以这里PHPExcel_Style_Alignment前使用"\"引入
        $objSheet->getDefaultStyle()->getFont()->setSize(14)->setName("微软雅黑");//设置所有默认字体大小和格式
        //$objSheet->getStyle("B2:F2")->getFont()->setSize(20)->setBold(true);//设置指定范围内字体大小和加粗
        //$objSheet->getDefaultRowDimension()->setRowHeight(33);//设置所有行默认行高
        //$objSheet->getRowDimension(2)->setRowHeight(50);//设置指定行（第二行）行高
        //$objSheet->getStyle("B2:F2")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('EEC591');//指定填充背景颜色，不需要加"#"定义样式数组，字体，背景，边框等都此方法设置，这里展示边框
        //$objSheet->getStyle("B3")->getAlignment()->setWrapText(true);//设置文字自动换行，要用getStyle()方法选中范围，同时要在内容中添加"\n",而且该内容要用双引号才会解析
        //$objSheet->getStyle("E")->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);//设置某列单元格格式为文本格式，便于禁用科学计数法

        /*数组配置*/
        $styleArray = array(
            'borders' => array(
                'outline' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THICK,
                    'color' => array('rgb' => 'EE0000'),
                ),
            ),
        );
        //$objSheet->getStyle("B3:G3")->applyFromArray($styleArray);//设置指定区域的边框，设置边框必须要使用getStyle()选中范围


        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');//生成objWriter对象，Excel2007(xlsx)为指定格式，还有Excel5表示Excel2003(xls)

        /*浏览器查看，浏览器保存*/
        browser_excel('Excel2007','test.xlsx');//输出到浏览器,参数1位Excel类型可为Excel5和Excel2007，第二个参数为文件名(需加后缀名)，此方法为自定义
        $objWriter->save("php://output");  //save()里可以直接填写保存路径
        /*保存到知道路径*/
        //$objWriter->save(ROOT_PATH."excel.xlsx");  //save()里可以直接填写保存路径

    }

    public function getCouponCodeData($coupon_id){
        $list = Db::name('css_coupon_code')->where(['css_coupon_id'=>$coupon_id])->field('exchange_code,status')->select();
        $data = [];
        $data[0][] = '';
        $data[0][] = '';
        $len = 0;
        foreach($list as $k=>&$v){
            ++$len;
            $data[$len][] = $v['exchange_code'];
            $data[$len][] = $v['status'] == 1?"可兑换":"已兑换";
        }
        return $data;
    }

    /**
     * 线下优惠券列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function offlineList(){
        $keywords_coupon = input('keywords_coupon','','trimStr');
        $keywords_store = input('keywords_store','','trimStr');
        $kind = input('kind',0,'intval');
        $is_open = input('is_open',-1,'intval');
        $fb_user = input('fb_user','','trimStr');
        $create_time = input('create_time','','trimStr');
        $where = [
            'cr.coupon_type' => ['IN', [10, 11]]
        ];
        if($keywords_coupon)$where['cr.coupon_name'] = ['LIKE', "%{$keywords_coupon}%"];
        if($keywords_store)$where['cr.store_name'] = ['LIKE', "%{$keywords_store}%"];
        if($kind)$where['cr.kind'] = $kind;
        if($is_open>=0)$where['cr.is_open'] = $is_open;
        if($fb_user)$where['cr.fb_user'] = $fb_user;
        if($create_time){
            $start_time = strtotime("$create_time 00:00:01");
            $end_time = strtotime("$create_time 23:59:59");
            $where['cr.create_time'] = ['BETWEEN', [$start_time, $end_time]];
        }
        $model = new CouponRule();
        $data = $model->alias('cr')
            ->where($where)
            ->order('cr.id desc')
            ->paginate(15,false,['query'=>$this->request->param()]);

        foreach($data as &$v){
            $v->show_total_handle = Db::name('coupon')->where(['coupon_id'=>$v->id])->count()?0:1;
            $rule_model_ids = explode(',',$v->rule_model_id);
            $rule = [];
            foreach($rule_model_ids as &$vv){
                $rule[] = Db::name('coupon_use_rule')->where(['id'=>$vv])->value('title');
            }
            $v->rule = $rule;
            $v->finish_num = Db::name('coupon_validate')->where(['coupon_rule_id'=>$v->id,'status'=>2])->count('id');
        }
        $param = $this->request->param();
        $param['kind'] = $kind;
        $param['is_open'] = $is_open;
        $this->assign(compact('data','param'));
        return $this->fetch();
    }

    /**
     * 修改线下优惠券是否在领券中心
     * @param CouponRuleValidate $CouponRuleValidate
     * @param CouponRuleModel $CouponRuleModel
     */
    public function isShowCouponCenter(CouponRuleValidate $CouponRuleValidate, CouponRuleModel $CouponRuleModel){
        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $CouponRuleValidate->scene('edit_is_show_coupon_center')->check(input());
            if(!$res)throw new Exception($CouponRuleValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $is_show_coupon_center = input('post.is_show_coupon_center',0,'intval')==1?0:1;

            $res = $CouponRuleModel->updateField('is_show_coupon_center',$is_show_coupon_center,$id);
            if($res === false)throw new Exception('修改失败');

            #返回
            return $this->success('修改成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    public function offlineDetail(){
        return $this->fetch();
    }

}