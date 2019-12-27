<?php


namespace app\admin\model;


use think\Model;

class Coupon extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取优惠券领取列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getCouponGetList(){
        $id = input('post.id',0,'intval');
        $staff_mobile = input('post.staff_mobile','','intval');
        $user_mobile = input('post.user_mobile','','trimStr');
        $status = input('post.status',0,'intval');
        $date = input('post.date','','trimStr');
        $page = input('post.page',1,'intval');
        $where = [
            'c.coupon_id' => $id
        ];
        if($status == 1){
            $where['cv.id'] = ['GT', 0];
        }elseif($status == 2){
            $where['cv.id'] = null;
        }
        if($staff_mobile){
            $where['b.id'] = $staff_mobile;
        }
        if($user_mobile){
            $where['u.mobile'] = ['LIKE', "%{$user_mobile}%"];
        }
        if($date){
            $start_time = strtotime("{$date} 00:00:01");
            $end_time = strtotime("{$date} 23:59:59");
            $where['c.create_time'] = ['BETWEEN',[$start_time, $end_time]];
        }

        $data = $this->alias('c')
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
            ->order('c.create_time','desc')
            ->paginate(15,false,['page'=>$page])
            ->toArray();

        $data['max_page'] = ceil($data['total'] / $data['per_page']);

        ##总领取张数
        $data['total_get'] = $this->where(['coupon_id'=>$id])->count('id');
        ##总核销张数
        $data['total_validate'] = CouponValidate::getCouponValidateNum($id);
        ##平台总补贴
        $data['total_subsidy'] = CouponValidate::getPlatformSubsidy($id);
        ##当前的领取张数
        $data['cur_get'] = $data['total'];

        $cur_validate = $cur_subsidy = 0;
        foreach($data['data'] as &$v){
            if($v['validate_no']){
                $cur_validate ++;
                $cur_subsidy += $v['platform_price'];
            }
            if($v['status'] == 1 && $v['expiration_time'] <= time())$v['status'] = 3;
            if($v['validate_time'])$v['validate_time'] = date("Y-m-d H:i",$v['validate_time']);
        }
        ##当前的核销张数
        $data['cur_validate'] = $cur_validate;
        $data['cur_subsidy'] = $cur_subsidy;

        return $data;
    }

}