<?php


namespace app\common\behavior;

use think\Response;

class AllowCrossDomain {
    final public function run(&$response) {
        if (!($response instanceof Response)) {
            return;
        }
        $request = request();
        $header = [
            'Access-Control-Allow-Origin' => $request->server('HTTP_ORIGIN', '*'),
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'x-token,x-uid,x-token-check,x-requested-with,content-type,Host',
            'Access-Control-Max-Age' => 24 * 60 * 60,
        ];
        $response->header($header);
        return;
    }
}