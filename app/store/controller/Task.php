<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/8/17
 * Time: 17:11
 */

namespace app\store\controller;

use app\common\controller\AliSMS;
use app\common\controller\IhuyiSMS;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
use app\store\common\Logic;
use think\Db;
use think\Exception;
use think\Log;
class Task
{
//    /**
//     * 定时任务每天自动结转上一天线下优惠券平台补贴
//     * @return string
//     * @throws \Exception
//     */
//    public function autocarrymoney(){
//        ##获取待待结转列表
//        $list = Logic::GetCarryList();
//        try{
//            if($list){
//                ##循环增加商家收入
//                foreach($list as $v){
//
//
//
//
//
//
//                    if($v['mobile']){
//                        ##改状态
//                        $res = Logic::updateAcClockStatus($v['id']);
//                        if($res === false)throw new Exception('活动提醒状态更新失败');
//
//                        ##发信息
////                    $res = IhuyiSMS::ac_clock($v['mobile'], $v['message']);
//                        $res = AliSMS::sendAcClockMsg($v['mobile'],$v['product_name'],$v['product_id']);
//                        if($res['Code'] != 'OK')throw new Exception("活动开始提醒短息发送失败,失败信息【{$res}】");
//
//                    }
//                }
//
//            }
//            return 'SUCCESS';
//        }catch(Exception $e){
//
//            addErrLog($e->getMessage(),'活动提醒短信发送',8);
//            return 'FALSE';
//
//        }
//
//    }
    /**
     * 定时任务每天自动结转上一天线下优惠券平台补贴
     * @return string
     * @throws \Exception
     */
    public function AutoCarryMoney(){
        ##获取待待结转列表
        $list = Logic::GetCarryList();
        if(!$list)return "SUCCESS";
        $coupon_validates = array_column($list,'id');
        Db::startTrans();
        try{
            ##增加商家余额
            foreach($list as $v){
                $res = Logic::AutoMoney($v['id'], $v['store_id'],$v['platform_price']);
                if($res === false)throw new Exception('结转失败!');
            }
            ##更新为已结转
            $res = Logic::Carryed($coupon_validates);
            if($res === false)throw new Exception('更新结转状态失败');
            Db::commit();
            return "SUCCESS";

        }catch(Exception  $e){
            Db::rollback();
            return "FALSE";
        }

    }
}