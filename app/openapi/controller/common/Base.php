<?php


namespace app\openapi\controller\common;


use think\Controller;

class Base extends Controller
{

    /**
     * 接口回调
     * @param $status
     * @param $msg
     * @param $data
     * @return array
     */
    public static function callback($status = 1,$msg = '',$data = []){
        return ['status'=>$status,'msg'=>$msg,'data'=>$data];
    }

}