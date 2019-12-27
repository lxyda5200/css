<?php


namespace app\business\model;


use think\Db;
use think\Model;
use app\user_v5\controller\AliPay;
class BusinessTiXianModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_tixian_record';

    public static function businessTiXian($business_id, $money, $store_id, $alipay_account){
        $balance = BusinessModel::where(['id' => $business_id])->field(['money','withdraw_money'])->find();

        if (!$balance) return "数据检索失败";
        if ($balance['money'] < $money) return "提现金额大于余额";
        if($money < 0.1) return "单笔最低转账金额0.1元";
        //if($money > 1) return "测试 提现金额不能大于1";
        if(!self::ALIAccountVerify($alipay_account)) return "支付宝账号无效";

        $insertData = [
            'order_no'=> $order_no = build_order_no('T'),
            'money' => $money,
            'business_id' => $business_id,
            'store_id' => $store_id,
            'alipay_account' => $alipay_account,
            'code' => 0,
            'create_at' => date('Y-m-d H:i:s')
        ];

        // TODO 调用支付宝
        Db::startTrans();
        $id = self::insertGetId($insertData);
        // 调用支付宝返回结果
        $aliPay = new AliPay();
        $alipay_return = $aliPay->transfer($order_no,$alipay_account,$money);
        // 插入提现记录返回结果
        Db::name('business_tixian_record')->where('id',$id)->update(['code'=>$alipay_return['code'],'order_id'=>$alipay_return['order_id']]);
        if(!empty($alipay_return['code']) && $alipay_return['code'] == 10000){ //提现成功
            // 添加员工流水
            $money_details = BusinessMoneyDetailsModel::insert([
                'order_id' => $id,
                'user_id' => $business_id,
                'note' =>'提现',
                'money' =>'-'.$money,
                'balance' =>$balance['money']-$money,
                'create_time' =>time(),
                'type' =>6,
            ]);
            // 更新员工余额及提现金额
            $update = BusinessModel::where('id', $business_id) -> update(['money' => $balance['money']-$money, 'withdraw_money' => $balance['withdraw_money']+$money]);
            if (!$alipay_return || !$id || !$update || !$money_details){
                Db::rollback();
                return false;
            }
            Db::commit();
            return true;
        }else{
            Db::rollback();
            return false;
        }
    }

    /**
     * 支付宝账号验证
     * @param $alipay_account
     * @return bool
     */
    public function ALIAccountVerify($alipay_account){
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if(preg_match("/^1[34578]\d{9}$/", $alipay_account) || preg_match($pattern,$alipay_account)){
            return true;
        }
        return false;
    }
}