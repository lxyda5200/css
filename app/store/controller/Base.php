<?php


namespace app\store\controller;


use app\store\common\Store;
use think\Request;
use think\response\Json;

class Base extends \app\common\controller\Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        ##token 验证
        $store_info = Store::checkToken();
        if ($store_info instanceof Json){
            return $store_info;
        }
    }
}