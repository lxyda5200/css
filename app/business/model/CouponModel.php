<?php


namespace app\business\model;

use think\Db;
use think\Model;
use aes\Aes;
class CouponModel extends Model
{

    protected $pk = 'id';

    protected $table = 'coupon_rule';


    /**
     * 优惠券列表
     * @param $name
     * @param $kind
     * @param $is_open
     */
    public static function couponList($name,$kind,$is_open,$user_id,$store_id,$is_main_user,$page,$limit){
        $pre = ($page -1)*$limit;
        //线下优惠券 type 10
        $where['c.coupon_type'] = ['eq', 10];
        //商店
        $where['c.store_id'] = ['eq', $store_id];
        //优惠券名称模糊查询
        if(!empty($name)){
            $where['c.coupon_name'] = ['like', '%'.$name.'%'];
        }
        //线下优惠券类别  1.实物礼品券；2.满减券；3.体验券；0.无',
        if($kind != 0){
            $where['c.kind'] = ['eq', $kind];
        }
        //'是否开启 1开启 0关闭',
        if($is_open != 2){
            $where['c.is_open'] = ['eq', $is_open];
        }
        //子账号
        if($is_main_user != 1){
            $where['cv.staff_id'] = ['eq', $user_id];
        }

        $data = self::where($where)
            ->alias('c')
            ->join('coupon_validate cv', 'cv.coupon_rule_id = c.id','left')
            ->field(['c.id', 'c.store_id','c.coupon_name','c.is_open','c.satisfy_money','c.coupon_money','c.start_time','c.end_time',
            'c.total_number','c.use_number','c.surplus_number','c.get_number','c.use_type','c.days','c.can_stacked','c.rule_model_id',
                'c.platform_bear','c.kind', 'cv.staff_id'
            ])
            ->group('c.id')
            ->order('c.create_time desc')
            ->limit($pre,$limit)
            ->select();

        foreach ($data as $k => $v){
            //使用规则模版id
            $v['is_main_user'] = $is_main_user;
            $model_ids = explode(',',$v['rule_model_id']);
            $coupon_use_rule = Db::name('coupon_use_rule')
                ->where(['id'=>['in', $model_ids],'status'=> 1])
                ->field('title')
                ->select();
            $v['rule'] = array_map('array_shift', $coupon_use_rule);
            $fake_use_number = Db('coupon_validate')->where(['coupon_rule_id'=>$v['id'],'store_id'=>$store_id])->count();
            $v['fake_use_number'] = (string)$fake_use_number;
            $subsidy = $v['fake_use_number']*$v['coupon_money']*$v['platform_bear'];
            //$v['subsidy'] = (string)number_format($subsidy, 2);
            $v['subsidy'] = (string)sprintf("%.2f",$subsidy);;

        }
        return $data;
    }

    /**
     * 领取优惠券列表
     * @param $coupon_id
     * @param $store_id
     * @param $page
     * @param $limit
     * @return mixed
     */
    public static function getCoupon($coupon_id,$store_id,$page,$limit){
        $pre = ($page -1)*$limit;
        //线下优惠券 type 10
        $where['c.coupon_id'] = ['eq', $coupon_id];
        //商店
        $where['c.store_id'] = ['eq', $store_id];

        $data['list'] = Db('coupon')->where($where)
            ->alias('c')
            ->join('user u', 'u.user_id = c.user_id','left')
            ->join('coupon_validate cv', 'cv.coupon_id = c.id','LEFT')  //  关联核销表
            ->field([
                'u.mobile','FROM_UNIXTIME(c.create_time,\'%Y/%m/%d %H:%i\') as create_time','c.status','IF(cv.create_time > 0 ,1 ,0) validate','IF(cv.create_time > 0 ,cv.id ,0) validate_id',
            ])
            ->limit($pre,$limit)
            ->order('c.create_time desc')
            ->select();
        $data['total_num'] = Db('coupon')->where(['coupon_id'=>$coupon_id,'store_id'=>$store_id])->count();
        return $data;
    }

    /**
     * 核销列表详情
     * @param $type
     * @param $id
     * @param $store_id
     * @param $page
     * @param $limit
     * @return mixed
     */
    public static function validateDetail($type,$id,$store_id,$page,$limit,$user_id = 0){
        $pre = ($page -1)*$limit;

        if($type == 1){//$id = $validate_id   //主账号从领取列表    点击核销详情   获取核销列表
            $where['cv.store_id'] = ['eq', $store_id];
            $where['cv.id'] = ['eq', $id];
            $data['list'] = Db('coupon_validate')->where($where)
                ->alias('cv')
                ->join('business b', 'b.id = cv.staff_id','left')
                ->join('coupon c', 'c.id = cv.coupon_id','left')
                ->field([
                    'cv.validate_no','cv.create_time','c.validate_code','b.mobile','b.id business_id','b.business_name',
                ])
                ->select();
            $data['type'] = 1;
            $data['total_num'] = 1;
        }elseif($type == 2){ //$id = $coupon_rule_id   //主账号点击总核销数量    获取核销列表
            $where['cv.store_id'] = ['eq', $store_id];
            $where['cv.coupon_rule_id'] = ['eq', $id];
            $data['list'] = Db('coupon_validate')->where($where)
                ->alias('cv')
                ->join('business b', 'b.id = cv.staff_id','left')
                ->join('coupon c', 'c.id = cv.coupon_id','left')
                ->field([
                    'cv.validate_no','cv.create_time','c.validate_code','b.mobile','b.id business_id','b.business_name',
                ])
                ->limit($pre,$limit)
                ->order('cv.create_time desc')
                ->select();
            $data['type'] = 2;
            $data['total_num'] = Db('coupon_validate')->where($where)
                ->alias('cv')
                ->join('business b', 'b.id = cv.staff_id','left')
                ->join('coupon c', 'c.id = cv.coupon_id','left')
                ->field([
                    'cv.validate_no','cv.create_time','c.validate_code','b.mobile','b.id business_id','b.business_name',
                ])
                ->count();
        }else{//$id = $coupon_rule_id     子账号点击优惠券列表    获取核销列表
            $where['cv.store_id'] = ['eq', $store_id];
            if($id > 0){
                $where['cv.coupon_rule_id'] = ['eq', $id];
            }
            $where['cv.staff_id'] = ['eq', $user_id];
            $data['list'] = Db('coupon_validate')->where($where)
                ->alias('cv')
                ->join('business b', 'b.id = cv.staff_id','left')
                ->join('coupon c', 'c.id = cv.coupon_id','left')
                ->join('user u', 'u.user_id = cv.user_id','left')
                ->field([
                    'cv.validate_no','cv.create_time','c.validate_code','u.mobile','b.id business_id','b.business_name',
                ])
                ->limit($pre,$limit)
                ->order('cv.create_time desc')
                ->select();
            $data['type'] = 3;
            $data['total_num'] = Db('coupon_validate')->where($where)
                ->alias('cv')
                ->join('business b', 'b.id = cv.staff_id','left')
                ->join('coupon c', 'c.id = cv.coupon_id','left')
                ->join('user u', 'u.user_id = cv.user_id','left')
                ->field([
                    'cv.validate_no','cv.create_time','c.validate_code','u.mobile','b.id business_id','b.business_name',
                ])
                ->count();
        }

        return $data;
    }

    /**
     * 核销券码
     * @param $code
     * @param $user_id
     * @param $store_id
     */
    public static function validateCoupon($code,$user_id,$store_id,$type){
        //type == 2时，需要解密
        if($type == 2){
            $aes = new Aes();
            $code = $aes->decrypt($code);
        }
        $info = Db('coupon')->alias('c')
            ->join('coupon_rule cr','c.coupon_id = cr.id','LEFT')
            ->where(['c.validate_code'=>$code,'cr.store_id'=>$store_id,'c.status'=>1])
            ->field('
                c.id,c.expiration_time,c.status,c.coupon_id,c.user_id,c.coupon_money,c.validate_expiration_time,
                cr.coupon_type,cr.is_open,cr.store_id,cr.kind,cr.platform_bear,cr.check_num
            ')
            ->find();
        if(!$info) return '优惠券信息不存在,请核实';
        if($info['is_open'] != 1) return'优惠券已下架';
        if($info['status'] != 1) return '优惠券已失效';
        if($info['expiration_time'] < time()) return'优惠券已过期';
        if($info['validate_expiration_time'] < time()) return'优惠券券码已过期，请重新获取';

        if($info['check_num'] && $info['kind'] == 2){
            ##检查今日核销数是否超过限制
            $cur_check_num = Db('coupon_validate')->where(['coupon_rule_id'=>$info['coupon_id']])->count('id');
            if($cur_check_num >= $info['check_num']) return'今日的优惠券核销数已达上限';
        }

        Db::startTrans();
        ##核销
        ###增加核销记录
        $data = [
            'user_id' => $info['user_id'],
            'store_id' => $info['store_id'],
            'coupon_id' => $info['id'],
            'coupon_rule_id' => $info['coupon_id'],
            'platform_bear' => $info['platform_bear'],
            'coupon_money' => $info['coupon_money'],
            'validate_no' => build_order_no('CV'),
            'staff_id' => $user_id,
            'create_time' => time()
        ];

        $platform_price = $info['platform_bear'] * $info['coupon_money'];
        $data['platform_price'] = $platform_price;
        $id = Db('coupon_validate')->insertGetId($data);
        if(!$id){
            Db::rollback();
            return '优惠券核销失败-优惠券使用失败';
        }
        ###使用优惠券
        $res = Db('coupon')->where(['id'=>$info['id']])->update(['status'=>2,'use_time'=>time()]);
        if(!$res){
            Db::rollback();
            return '优惠券核销失败-优惠券使用失败';
        }
        Db::commit();
        #返回
        return true;
    }
}