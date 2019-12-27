<?php


namespace app\admin\controller;
use app\admin\model\CouponUseRule as CouponUseRuleModel;


class CouponUseRule extends Admin
{

    public function index(CouponUseRuleModel $couponUseRuleModel){
        $lists = $couponUseRuleModel->where(['is_common'=>1])->paginate(15);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

}