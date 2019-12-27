<?php

namespace app\store\controller;
date_default_timezone_set('Asia/Shanghai');
use app\common\controller\Base;
use think\Cache;
use think\Db;
use think\Exception;
use think\response\Json;
use app\store\model\CouponUseRule as CouponUseRuleModel;
use app\store\model\Product as ProductModel;
use app\store\validate\Coupon as CouponValidate;
use app\store\model\Coupon as CouponModel;
use app\store\model\CouponValidate as CouponValidateModel;


class Coupon extends Base
{
    /**
     **优惠券页面信息
     **/
    public function coupon_info() {
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $data['maidan_info']=Db::name('maidan')->field('id,status,putong_user,member_user,create_time')->where('store_id',$store_info['id'])->find();
            $data['coupon_info']=Db::name('coupon_rule')->field('id,coupon_name,is_open as status,satisfy_money,coupon_money,start_time,end_time,type,fb_user,days,coupon_type,create_time,total_number,use_number,surplus_number,zengsong_number,can_stacked,rule_model_id,product_ids,kind')
                ->where('store_id',$store_info['id'])
                ->where('is_open',1)
                ->where('fb_user','商家')
                ->select();
            foreach ($data['coupon_info'] as $k => &$v){
                $new_start_time=date("Y-m-d H:i:s", $v['start_time']);
                $new_end_time=date("Y-m-d H:i:s", $v['end_time']);
                unset($v['start_time']);
                unset($v['end_time']);
                $data['coupon_info'][$k]['start_time']=$new_start_time;
                $data['coupon_info'][$k]['end_time']=$new_end_time;
                $v['rule'] = CouponUseRuleModel::getRuleTitles(trim($v['rule_model_id'],','));
                $v['products'] = ProductModel::getProNames(trimFunc($v['product_ids']));
            }
            if(empty($data['maidan_info'])){
                $data['maidan_info']=[];
            }
            if(empty($data['coupon_info'])){
                $data['coupon_info']=[];
            }
            return \json(self::callback(1,'成功',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     **设置买单信息
     **/
    public function maidan_set() {
        try{
        //token 验证
        $store_info = \app\store\common\Store::checkToken();
        if ($store_info instanceof json){
            return $store_info;
        }
            $param = $this->request->post();
            $status=$param['status'];
            $putong_user=$param['putong_user'];
            if(!$status || (!$putong_user && $putong_user !=0)){
                throw new \Exception('参数错误');
            }
        if(($putong_user<=0 || $putong_user>10)){
            throw new \Exception('设置的优惠区间不正确,请在大于0小于等于10之间');
        }

            $data=Db::name('maidan')->field('id')->where('store_id',$store_info['id'])->find();
        if($data){
            //修改
            $genxin = [
                'status' => $status,
                'putong_user' => $putong_user
            ];
            $rst = Db::name('maidan')->where('store_id',$store_info['id'])->update($genxin);
        }else{
            //新增
            $new = [
                'store_id' => $store_info['id'],
                'status' => $status,
                'putong_user' => $putong_user,
                'member_user' => 10,
                'create_time' => time()
            ];
            $rst = Db::name('maidan')->insert($new);
        }
       if($rst===false){
           return \json(self::callback(0,'失败',-1));
       }
            return \json(self::callback(1,'成功',true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }
    /**
     **添加优惠券
     **/
    public function coupon_add()
    {
        try{
//            print_r(request()->post());
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $coupons=$param['coupons'];
            if(empty($coupons['0'])){
                return \json(self::callback(0,'优惠券信息不能为空',false));
            }

            Db::startTrans();
            foreach ($coupons as $k=>$v){
                $coupon_name=$v['coupon_name'];
                $satisfy_money=intval($v['satisfy_money']);
                $coupon_money=intval($v['coupon_money']);
                $type=$v['coupon_type'];
                $coupon_type = $type + 1;
                $is_open=$v['status'];
                $can_stacked=$v['can_stacked'];
                $start_time=strtotime($v['start_time']);
                $end_time=strtotime($v['end_time']);
                $total_number=$v['total_number'];
                $client_type = $v['client_type'];
                $product_ids = "";
                $is_solo = 0;
                $rules = $v['rules'];
                $create_time=time();
                if($type == 3){  //商品券
                    $product_ids = $v['product_ids'];
                    if(!$product_ids)return \json(self::callback(0,'请选择商品',false));
                    if(count($product_ids) == 1)$is_solo = $product_ids[0];
                    foreach($product_ids as &$vv)$vv = "[{$vv}]";
                    $product_ids = implode(',',$product_ids);
                }
                if(!$rules)return \json(self::callback(0,'请添加优惠券使用规则'));
                if(count($rules) > 3)return \json(self::callback(0,'优惠券使用规则最多添加3条'));

                ##构建规则
                $rule_model_id = [];
                foreach ($rules as $vv){
                    if(!isset($vv['id'])){
                        $rule_data = [
                            'title' => $vv['title'],
                            'create_time' => time()
                        ];
                        $rule_id = CouponUseRuleModel::addDiyRule($rule_data);
                        if(!$rule_id)throw new Exception('自定义规则添加失败');
                    }else{
                        $rule_id = $vv['id'];
                    }
                    $rule_model_id[] = $rule_id;
                }
                $rule_model_id = implode(',',$rule_model_id);

                if($satisfy_money==$coupon_money){
                    return \json(self::callback(0,'满减金额不能相等',false));
                }
                if($satisfy_money<0 || $coupon_money<0){
                    return \json(self::callback(0,'满减金额不能小于0',false));
                }
                if($total_number<0 || $total_number>100000){
                    return \json(self::callback(0,'优惠券张数不能小于0或者大于10万张',false));
                }

                if($start_time>=$end_time){
                    return \json(self::callback(0,'优惠券使用时间不正确',false));
                }

                if($satisfy_money==0){
                    //无门槛
                    if(!$coupon_name ||  !$coupon_money || !$coupon_type || !$is_open || !$start_time || !$end_time ||!$total_number){
                        return \json(self::callback(0,'数据不完整，请重新填写',false));
                    }
                }elseif($satisfy_money>0){
                //有门槛
                    if(!$coupon_name ||!$satisfy_money  || !$coupon_money || !$coupon_type || !$is_open || !$start_time || !$end_time ||!$total_number){
                        return \json(self::callback(0,'数据不完整，请重新填写',false));
                    }
                    if(isset($satisfy_money) && $satisfy_money>0){
                        if($satisfy_money<=$coupon_money){
                            return \json(self::callback(0,'减的金额不能大于满的金额',false));
                        }
                    }
                }else{
                    return \json(self::callback(0,'满金额不能小于0',false));
                }
                if($start_time>0 || $end_time>0 ){$days=0;}
                $data = [
                    'coupon_name' => $coupon_name,
                    'satisfy_money' => $satisfy_money,
                    'coupon_money' => $coupon_money,
                    'is_open' => $is_open,
                    'can_stacked' => $can_stacked,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'type' => $type,
                    'store_id' => $store_info['id'],
                    'store_name' => $store_info['store_name'],
                    'fb_user' => '商家',
                    'total_number' => $total_number,
                    'surplus_number' => $total_number,
                    'create_time' => $create_time,
                    'coupon_type' => $coupon_type,
                    'product_ids' => $product_ids,
                    'is_solo' => $is_solo,
                    'days' => $days,
                    'client_type' => $client_type,
                    'rule_model_id' => $rule_model_id
                ];
                $rst=Db::table('coupon_rule')->insert($data);

                if($rst===false){
                    throw new Exception('添加失败');
                }
            }
            Db::commit();
            return \json(self::callback(1,'添加成功',true));

        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage(),false));
        }

    }
    /**
     **添加改版优惠券管理
     **/
    public function CouponAdd()
    {
        try{
//            print_r(request()->post());
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $coupons=$param['coupons'];
            if(empty($coupons['0'])){
                return \json(self::callback(0,'优惠券信息不能为空',false));
            }
            Db::startTrans();
            foreach ($coupons as $k=>$v){
                $coupon_name=trim($v['coupon_name']);
                $satisfy_money=trim($v['satisfy_money']);
                $coupon_money=trim($v['coupon_money']);
                $type=$v['type'];
                if($type==10){$type=2;}
                $coupon_type = 10;
                $is_open=$v['is_open'];
                $start_time=strtotime($v['start_time']);
                $end_time=strtotime($v['end_time']);
                $total_number = $v['total_number'];
                $rules = $v['rules'];
                $create_time=time();
                $client_type =2;
                $time_type = $v['time_type'];
                    if($time_type==1){
                        $start_time=strtotime($v['start_time']);
                        $end_time=strtotime($v['end_time']);
                        if($end_time <= $start_time)return $this->error('结束时间必须大于开始时间');
                        if($end_time <= time())return $this->error('结束时间必须大于当前时间');
                        $days=0;
                    }else{
                        $days = $v['days'];
                        if(!$days || $days < 0)return $this->error('请填写正确有效天数');
                    }
                    $kind = $v['kind'];
                    if(!$kind)return $this->error('请选择优惠券种类');
                    if(!$total_number || $total_number < 0)return $this->error('请填写正确的发券总数');

                if(!$rules)return \json(self::callback(0,'请添加优惠券使用规则'));
                if(count($rules) > 3)return \json(self::callback(0,'优惠券使用规则最多添加3条'));
                ##构建规则
                $rule_model_id = [];

                foreach ($rules as $vv){
                    if(!isset($vv['id'])){
                        $rule_data = [
                            'title' => $vv['title'],
                            'create_time' => time()
                        ];
                        $rule_id = CouponUseRuleModel::addDiyRule($rule_data);
                        if(!$rule_id)throw new Exception('自定义规则添加失败');
                    }else{
                        $rule_id = $vv['id'];
                    }
                    $rule_model_id[] = $rule_id;
                }
                $rule_model_id = implode(',',$rule_model_id);

                if($total_number<0 || $total_number>100000){
                    return \json(self::callback(0,'优惠券张数不能小于0或者大于10万张',false));
                }
                $data = [
                    'coupon_name' => $coupon_name,
                    'satisfy_money' => $satisfy_money>0?$satisfy_money:0,
                    'coupon_money' =>$coupon_money>0?$coupon_money:0,
                    'is_open' => $is_open,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'type' => $type,
                    'store_id' => $store_info['id'],
                    'store_name' => $store_info['store_name'],
                    'fb_user' => '商家',
                    'total_number' => $total_number,
                    'surplus_number' => $total_number,
                    'create_time' => $create_time,
                    'coupon_type' => $coupon_type,
                    'client_type' => $client_type,
                    'days' => $days,
                    'kind' => $kind,
                    'rule_model_id' => $rule_model_id
                ];
                $rst=Db::table('coupon_rule')->insert($data);
                if($rst===false){
                    throw new Exception('添加失败');
                }

            }
            Db::commit();
            return \json(self::callback(1,'添加成功',true));

        }catch (\Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage(),false));
        }

    }
    public function test(){
        return request()->method();
    }

    //删除优惠券
    public function coupon_delete(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(!$id){
                return \json(self::callback(0,'参数错误',400));
            }
            if(!is_numeric($id)){
                return \json(self::callback(0,'参数不正确',400));
            }
            $rst=Db::name('coupon_rule')->where('id',$id)->where('store_id',$store_info['id'])->setField('is_open',0);
            if($rst===false){
                return \json(self::callback(0,'删除失败',-1));
            }else{
                return \json(self::callback(1,'删除成功',true));
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取卡券公共模板
     */
    public function couponCommonRule(){
        try{
            #验证
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            #逻辑
            ##获取公共模板
            $list = CouponUseRuleModel::getCommonLists();

            #返回
            return \json(self::callback(1,'',$list));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 优惠券列表
     */
    public function CouponList(){
        try{
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $keywords=trim($param['keywords']);
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $kind=$param['kind'];//123,4:全部
            $is_open=$param['is_open'];//0下架 1上架 2全部
            $fb_user=$param['fb_user'];//0全部 1平台 2商家
            if($keywords){
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where['coupon_name'] = ['like',"%$keywords%"];
            }
            if($kind){
                $where['kind'] = ['EQ',$kind];
                if($kind==4){$where['kind'] = ['IN','1,2,3'];}
            }
            if($is_open=='' || $is_open==2){
               $where['is_open'] = ['IN','0,1'];
            }else{
                $where['is_open'] = ['EQ',$is_open];
            }
            if($fb_user==1){
                {$where['fb_user'] = ['EQ','平台'];}
            }elseif($fb_user==2){
                $where['fb_user'] = ['EQ','商家'];
            }
            $start_time = strtotime($param['start_time']);
            $end_time = strtotime($param['end_time']);
            if($start_time && $end_time){
                $end_time+=86399;
                $where['create_time'] = ['BETWEEN', [$start_time, $end_time]];
            }elseif($start_time && !$end_time){
                $where['create_time'] = ['EGT', $start_time];
            }elseif(!$start_time && $end_time){
                $where['create_time'] = ['ELT', $end_time];
            }
            $total = $rst=Db::name('coupon_rule')
                ->where('type',2)
                ->where('coupon_type',10)
                ->where('store_id',$store_info['id'])
                ->where($where)
                ->count();
            $list = $rst=Db::name('coupon_rule')
                ->where('type',2)
                ->where('coupon_type',10)
                ->where('store_id',$store_info['id'])
                ->where($where)
                ->page($page,$size)
                ->select();

            foreach ($list as &$v){
                unset($v['check_num']);
                //领取数量
                $v['use_number'] = Db::name('coupon')
                    ->where('coupon_id',$v['id'])
                    ->count();
                //核销数量
                $v['checked_num'] = Db::name('coupon_validate')
                    ->where('coupon_rule_id',$v['id'])
                    ->count();
            } $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            #返回
            return \json(self::callback(1,'',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 优惠券上下架
     */
    public function ChangeCouponStatus(){
        try{
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id   = input('id',0,'intval');
            if(!$id){ return \json(self::callback(0,'参数错误'),400);}
            $info = Db::name('coupon_rule')->where(['id'=>$id,'coupon_type'=>10])->field('id,coupon_name,is_open,satisfy_money,coupon_money,days,start_time,end_time,check_num,kind,platform_bear,rule_model_id')->find();
            if(!$info)throw new Exception('优惠券信息不存在或已删除');
            if($info['is_open']==1){
                $result=Db::name('coupon_rule')->where('id',$id)->setField('is_open',0);
            }else{
                $result=Db::name('coupon_rule')->where('id',$id)->setField('is_open',1);
            }
            if($result===false){
                return \json(self::callback(0,'设置失败',false,true));
            }else{
                return \json(self::callback(1,'设置成功',true));
            }

        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 优惠券使用详情
     */
    public function CouponUseDetail(){
        try{
            #验证
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id   = input('id',0,'intval');
            if(!$id){ return \json(self::callback(0,'参数错误'),400);}

            $info = Db::name('coupon_rule')->where(['id'=>$id,'coupon_type'=>10])->field('id,coupon_name,is_open,satisfy_money,coupon_money,days,start_time,end_time,days,check_num,kind,platform_bear,rule_model_id')->find();
            if(!$info)throw new Exception('优惠券信息不存在或已删除');

            //使用规则
            if(isset($info['rule_model_id'])){
                $rule_model_id = explode(',',$info['rule_model_id']);
                $info['rules'] = Db::name('coupon_use_rule')
                    ->field('id,title')->where(['id'=>['IN',$rule_model_id],'status'=>1])->select();
            }
            $info['platform_bear'] = $info['platform_bear'] * 100;

            #返回
            return \json(self::callback(1,'',$info));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 优惠券使用详情列表
     */
    public function CouponUseDetailList(){
        try{
            #验证
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id',0,'intval');
            $staff_mobile = input('staff_mobile','','trim');
            $user_mobile = input('user_mobile','','trim');
            $status = input('status',0,'intval');
//            $date = input('post.date','','trimStr');
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $start_time = input('start_time','','trim');
            $end_time = input('end_time','','trim');
            if(!$id) return \json(self::callback(0,'参数错误',false));
            $where = [
                'c.coupon_id' => $id
            ];
            if($status == 1){
                $where['cv.id'] = ['GT', 0];
            }elseif($status == 2){
                $where['cv.id'] = null;
            }
            if($staff_mobile){
                $where['b.mobile|b.id'] = ['LIKE', "%{$staff_mobile}%"];
            }
            if($user_mobile){
                $where['u.mobile'] = ['LIKE', "%{$user_mobile}%"];
            }
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time);
            if($start_time && $end_time){
                $end_time+=86399;
                $where['c.create_time'] = ['BETWEEN', [$start_time, $end_time]];
            }elseif($start_time && !$end_time){
                $where['c.create_time'] = ['EGT', $start_time];
            }elseif(!$start_time && $end_time){
                $where['c.create_time'] = ['ELT', $end_time];
            }
            $total = Db::view('coupon')->alias('c')
                ->join('coupon_validate cv','cv.coupon_id = c.id','LEFT')
                ->join('business b','b.id = cv.staff_id','LEFT')
                ->join('user u','u.user_id = c.user_id','LEFT')
                ->where($where)
                ->field('
                c.user_id,c.create_time,c.status,c.validate_code,
                u.mobile as user_mobile,
                cv.validate_no,cv.create_time as validate_time,cv.staff_id,cv.platform_price,
                b.business_name as staff_name
            ')
                ->count();
            $list = Db::view('coupon')->alias('c')
                ->join('coupon_validate cv','cv.coupon_id = c.id','LEFT')
                ->join('business b','b.id = cv.staff_id','LEFT')
                ->join('user u','u.user_id = c.user_id','LEFT')
                ->where($where)
                ->field('
                c.user_id,c.create_time,c.status,c.validate_code,c.coupon_id,
                u.mobile as user_mobile,
                cv.validate_no,cv.create_time as validate_time,cv.staff_id,cv.platform_price,
                b.business_name as staff_name
            ')
                ->order('c.create_time','desc')
                ->page($page,$size)
                ->select();


            foreach ($list as &$v2){
                $v2['cur_get'] = Db::name('coupon')->where('coupon_id',$id)->where('user_id',$v2['user_id'])->count();
            }
            ##总领取张数
            $data['total_get'] = Db::name('coupon')->where(['coupon_id'=>$id])->count('id');
            ##总核销张数
            $data['total_validate'] = Db::name('coupon_validate')->where(['coupon_rule_id'=>$id])->count('id');
            ##平台总补贴
            $data['total_subsidy'] = Db::name('coupon_validate')->where(['coupon_rule_id'=>$id])->sum('platform_price');
            ##当前的领取张数

            $cur_validate = $cur_subsidy = 0;
            foreach($list as &$v){
                if($v['validate_no']){
                    $cur_validate ++;
                    $cur_subsidy += $v['platform_price'];
                }
                if($v['status'] == 1 && $v['expiration_time'] <= time())$v['status'] = 3;
            }
            ##当前的核销张数
            $data['cur_validate'] = $cur_validate;
            $data['cur_subsidy'] = $cur_subsidy;
            $data['cur_get'] = $total;
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            #返回
            return \json(self::callback(1,'',$data));
        }catch(Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 核销线下优惠券
     * @param CouponValidate $couponValidate
     * @param CouponModel $couponModel
     * @param CouponValidateModel $couponValidateModel
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function validateCoupon(CouponValidate $couponValidate, CouponModel $couponModel, CouponValidateModel $couponValidateModel){
        try{
            #验证
            ##token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $res = $couponValidate->scene('validate_coupon')->check(input());
            if(!$res)throw new Exception($couponValidate->getError());
            #逻辑
            ##获取卡券信息
            $coupon_code = input('post.coupon_code','','addslashes,strip_tags,trim');
            $info = $couponModel->getInfoByValidateCode($coupon_code);
            if(!$info)throw new Exception('优惠券信息不存在,请核实');
            ##判断卡券信息
            if($info['status'] != 1)throw new Exception('优惠券已失效');
            if($info['expiration_time'] < time())throw new Exception('优惠券已过期');
            if($info['is_open'] != 1)throw new Exception('优惠券已下架');
            if($info['check_num'] && $info['kind'] == 2){
                ##检查今日核销数是否超过限制
                $cur_check_num = $couponValidateModel->getCurValidateNum($info['coupon_id']);
                if($cur_check_num >= $info['check_num'])throw new Exception('今日的优惠券核销数已达上限');
            }

            Db::startTrans();
            ##核销
            ###增加核销记录
            $couponValidateModel->validateCoupon($info);
            ###使用优惠券
            $couponModel->useCoupon($info);

            Db::commit();
            #返回
            return \json(self::callback(1,"核销成功,可优惠金额￥{$info['coupon_money']}"));
        }catch(Exception $e){
            Db::rollback();
            return \json(self::callback(0,$e->getMessage()));
        }
    }

}