<?php


namespace app\store_v1\controller;


class EnterBase extends \app\common\controller\Base
{
    /**
     * ajax返回
     * @param int $status
     * @param string $msg
     * @param null $data
     */
    public function ajaxReturn($status = 1, $msg = 'success', $data = null) {
        exit(json_encode(['status' => $status, 'msg' => $msg, 'data' => $data]));
    }
}