<?php

namespace app\openapi\controller;

use app\openapi\controller\common\Base;
use app\openapi\controller\common\Logic;
use think\Exception;

use app\openapi\validate\Check;

class MessageApi extends Base
{

    /**
     * H5获取商品活动信息
     * @param Check $check
     * @return \think\response\Json
     */
    public function getActivityStartTime(Check $check){
        try{

            #验证
            $res = $check->scene('get_activity_start_time')->check(input());
            if(!$res)throw new Exception($check->getError());

            $product_id = input('post.product_id',0,'intval');

            $activity_info = Logic::productActivityInfo($product_id);

            if(!$activity_info)throw new Exception('产品不存在');

            $activity_data = [

                'status' => $activity_info['status'],

                'start_time' => $activity_info['start_time'],

                'cur_time' => time(),

            ];

            #返回
            return json(self::callback(1,'', $activity_data));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

}