<?php


namespace system_message;


use think\Db;
use think\Exception;

class Msg
{

    /**
     * 添加发货系统消息
     * @param $order_id
     * @return array
     */
    public static function addDeliverGoodsSysMsg($order_id){
        $data = Config::getConfig('order_deliver_goods');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('order_deliver_goods'), $order_id);
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }

    }

    /**
     * 添加售后失败系统消息
     * @param $order_id
     * @return array
     */
    public static function addShouhouRefuseSysMsg($order_id){
        $data = Config::getConfig('shouhou_refuse');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = Config::getParam('shouhou_refuse');
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加售后成功系统消息
     * @param $order_id
     * @return array
     */
    public static function addShouhouPassSysMsg($order_id){
        $data = Config::getConfig('shouhou_pass');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = Config::getParam('shouhou_pass');
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加售后退款成功系统消息
     * @param $order_id
     * @return array
     */
    public static function addShouhouRefundSysMsg($order_id){
        $data = Config::getConfig('shouhou_refund');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = Config::getParam('shouhou_refund');
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加系统取消订单系统消息
     * @param $order_id
     * @return array
     */
    public static function addSysCancelOrderSysMsg($order_id){
        $data = Config::getConfig('order_cancel_by_system');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('order_cancel_by_system'), $order_id);
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加系统取消未支付订单系统消息
     * @param $order_id
     * @return array
     */
    public static function addSysCancelNotPayOrderSysMsg($order_id){
        $data = Config::getConfig('order_cancel_by_system_not_pay');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('order_cancel_by_system_not_pay'), $order_id);
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
 * 添加订单即将取消提醒系统消息 1小时
 * @param $order_id
 * @return array
 */
    public static function addOrderCancelWarningSysMsg60($order_id){
        $data = Config::getConfig('warning_order_will_cancel_60');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('warning_order_will_cancel_60'), $order_id);
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加订单即将取消提醒系统消息 半小时
     * @param $order_id
     * @return array
     */
    public static function addOrderCancelWarningSysMsg30($order_id){
        $data = Config::getConfig('warning_order_will_cancel_30');
        try{
            ##创建系统消息
            $order_info = self::getOrderInfo($order_id);
            $data['content'] = sprintf($data['content'], $order_info['order_no']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('warning_order_will_cancel_30'), $order_id);
            self::userLinkMsg($order_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 添加活动即将开始提醒系统消息
     * @param $clock_id
     * @return array
     */
    public static function addActivityWillStartSysMsg($clock_id){
        $data = Config::getConfig('activity_will_start_clock');
        try{
            ##创建系统消息
            $clock_info = self::getActivityClockInfo($clock_id);
            $data['content'] = sprintf($data['content'], $clock_info['product_name']);
            $msg_id = self::addSysMsg($data);
            ##用户绑定消息
            $param = sprintf(Config::getParam('activity_will_start_clock'), $clock_info['product_id']);
            self::userLinkMsg($clock_info['user_id'], $param, $msg_id);
            return ['status'=>1, 'msg'=>'系统消息添加成功'];
        }catch(Exception $e){
            return ['status'=>0, 'msg'=>$e->getMessage()];
        }
    }

    /**
     * 创建系统消息
     * @param $data
     * @return int|string
     * @throws Exception
     */
    protected static function addSysMsg($data){
        $data['type'] = 1;
        $data['create_time'] = time();
        $msg_id = Db::name('user_msg')->insertGetId($data);
        if($msg_id === false)throw new Exception('系统消息创建失败');
        return $msg_id;
    }

    /**
     * 绑定用户和系统消息
     * @param $user_ids
     * @param $params
     * @param $msg_id
     * @return bool
     * @throws Exception
     */
    protected static function userLinkMsg($user_ids, $params, $msg_id){
        $data = self::createUserLinkData($user_ids, $params, $msg_id);
        $res = Db::name('user_msg_link')->insertAll($data);
        if($res === false)throw new Exception('用户添加系统消息失败');
        return true;
    }

    /**
     * 创建用户系统消息连接数据
     * @param $user_ids
     * @param $params
     * @param $msg_id
     * @return array
     */
    protected static function createUserLinkData($user_ids, $params, $msg_id){
        $data = [];
        if(is_array($user_ids)){
            foreach($user_ids as $k=> $v){
                $data[] = [
                    'user_id' => $v,
                    'msg_id' => $msg_id,
                    'is_read' => 0,
                    'param' => $params[$k]
                ];
            }
        }else{
            $data[] = [
                'user_id' => $user_ids,
                'msg_id' => $msg_id,
                'is_read' => 0,
                'param' => $params
            ];
        }
        return $data;
    }

    /**
     * 获取订单信息
     * @param $order_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function getOrderInfo($order_id){
        return Db::name('product_order')->where(['id'=>$order_id])->field('order_no,user_id')->find();
    }

    /**
     * 获取活动提醒信息
     * @param $clock_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function getActivityClockInfo($clock_id){
        return Db::name('activity_clock')->alias('ac')->join('product p','p.id = ac.product_id')->where(['ac.id'=>$clock_id])->field('ac.user_id,ac.product_id,p.product_name')->find();
    }

}