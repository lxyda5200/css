<?php

/**
 *  密码加密
 * @param $password
 * @return false|string
 */
function passEncryption($password){
    $return = password_hash($password, PASSWORD_DEFAULT);
    return $return;
}

/**
 *  生成用户验证TOKEN
 * @param null $prefix
 * @return string
 */
function makeUserToken($unique = true, $prefix = null){
    mt_srand((double)microtime() * 10000);
    $charId = strtoupper(md5(uniqid(rand() , true)));
    $hyphen = chr(45);//"-"
    $uuid = chr(123)//"{"
        .substr($charId,0,8).$hyphen
        .substr($charId,8,4).$hyphen
        .substr($charId,12,4).$hyphen
        .substr($charId,16,4).$hyphen
        .substr($charId,20,12)
        .chr(125);//"}"

    $getUUID = strtoupper(str_replace("-","",$uuid));
    $generateReadableUUID = md5($prefix . date("ymdHis") . sprintf('%03d' , rand(0 , 999)) . substr($getUUID , 4 , 4));
//    if ($unique === true){
//        $uniqueCheck = \app\business\model\UserModel::where('token',$generateReadableUUID) -> count();
//        if ($uniqueCheck){
//            makeUserToken(true);
//        }
//    }
    return $generateReadableUUID;
}