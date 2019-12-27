<?php


use app\common\controller\IhuyiSMS;
use app\wxapi_test\common\Weixin;
use app\wxapi_test\model\ProductOrder;
use app\wxapi_test\model\ProductOrderDetail;
use think\Db;
use think\Log;
use app\wxapi_test\common\UserLogic;
use think\Exception;
use templateMsg\CreateTemplate;

if (!function_exists('bccomp')) {

    /**
     * 支持正数和负数的比较
     * ++ -- +-
     * @param $numOne
     * @param $numTwo
     * @param null $scale
     * @return int|string
     */
    function bccomp($numOne, $numTwo, $scale = null)
    {
        //先判断是传过来的两个变量是否合法,不合法都返回'0'
        if (!preg_match("/^([+-]?)\d+(\.\d+)?$/", $numOne, $numOneSign) ||
            !preg_match("/^([+-]?)\d+(\.\d+)?$/", $numTwo, $numTwoSign)
        ) {
            return '0';
        }

        $signOne = $numOneSign[1] === '-' ? '-' : '+';
        $signTwo = $numTwoSign[1] === '-' ? '-' : '+';

        if ($signOne !== $signTwo) {    //异号
            if ($signOne === '-' && $signTwo === '+') {
                return -1;
            } else if ($signOne === '+' && $signTwo === '-') {
                return 1;
            } else {
                return '0';
            }
        } else {  //同号
            //两个负数比较
            if ($signOne === "-" && $signTwo === '-') {
                $numOne = abs($numOne);
                $numTwo = abs($numTwo);
                $flag = bccompPositiveNum($numOne, $numTwo, $scale);
                if ($flag === 0) {
                    return 0;
                } else if ($flag === 1) {
                    return -1;
                } else if ($flag === -1) {
                    return 1;
                } else {
                    return '0';
                }
            } else {    //两个正数比较
                //两正数比较
                return bccompPositiveNum($numOne, $numTwo, $scale);
            }
        }
    }
}

if (!function_exists('bccompPositiveNum')) {
    /**
     * 比较正数的大小写问题
     * @param $numOne
     * @param $numTwo
     * @param null $scale
     * @return int|string
     */
    function bccompPositiveNum($numOne, $numTwo, $scale = null)
    {
        // check if they're valid positive numbers, extract the whole numbers and decimals
        if (!preg_match("/^\+?(\d+)(\.\d+)?$/", $numOne, $tmpOne) ||
            !preg_match("/^\+?(\d+)(\.\d+)?$/", $numTwo, $tmpTwo)
        ) {
            return '0';
        }

        // remove leading zeroes from whole numbers
        $numOne = ltrim($tmpOne[1], '0');
        $numTwo = ltrim($tmpTwo[1], '0');

        // first, we can just check the lengths of the numbers, this can help save processing time
        // if $numOne is longer than $numTwo, return 1.. vice versa with the next step.
        if (strlen($numOne) > strlen($numTwo)) {
            return 1;
        } else {
            if (strlen($numOne) < strlen($numTwo)) {
                return -1;
            } // if the two numbers are of equal length, we check digit-by-digit
            else {

                // remove ending zeroes from decimals and remove point
                $Dec1 = isset($tmpOne[2]) ? rtrim(substr($tmpOne[2], 1), '0') : '';
                $Dec2 = isset($tmpTwo[2]) ? rtrim(substr($tmpTwo[2], 1), '0') : '';

                // if the user defined $scale, then make sure we use that only
                if ($scale != null) {
                    $Dec1 = substr($Dec1, 0, $scale);
                    $Dec2 = substr($Dec2, 0, $scale);
                }

                // calculate the longest length of decimals
                $DLen = max(strlen($Dec1), strlen($Dec2));

                // append the padded decimals onto the end of the whole numbers
                $numOne .= str_pad($Dec1, $DLen, '0');
                $numTwo .= str_pad($Dec2, $DLen, '0');

                // check digit-by-digit, if they have a difference, return 1 or -1 (greater/lower than)
                for ($i = 0; $i < strlen($numOne); ++$i) {
                    if ((int)$numOne{$i} > (int)$numTwo{$i}) {
                        return 1;
                    } elseif ((int)$numOne{$i} < (int)$numTwo{$i}) {
                        return -1;
                    }
                }

                // if the two numbers have no difference (they're the same).. return 0
                return 0;
            }
        }
    }
}




/**
 * 获取汉字首字母函数
 * @param $str
 * @return null|string
 */
function getFirstCharter($str)
{
    if (empty($str)) {
        return '';
    }

    $fchar = ord($str{0});

    if ($fchar >= ord('A') && $fchar <= ord('z'))
        return strtoupper($str{0});

    $s1 = iconv('UTF-8', 'gb2312', $str);

    $s2 = iconv('gb2312', 'UTF-8', $s1);

    $s = $s2 == $str ? $s1 : $str;

    $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;

    if ($asc >= -20319 && $asc <= -20284)
        return 'A';

    if ($asc >= -20283 && $asc <= -19776)
        return 'B';

    if ($asc >= -19775 && $asc <= -19219)
        return 'C';

    if ($asc >= -19218 && $asc <= -18711)
        return 'D';

    if ($asc >= -18710 && $asc <= -18527)
        return 'E';

    if ($asc >= -18526 && $asc <= -18240)
        return 'F';

    if ($asc >= -18239 && $asc <= -17923)
        return 'G';

    if ($asc >= -17922 && $asc <= -17418)
        return 'H';

    if ($asc >= -17417 && $asc <= -16475)
        return 'J';

    if ($asc >= -16474 && $asc <= -16213)
        return 'K';

    if ($asc >= -16212 && $asc <= -15641)
        return 'L';

    if ($asc >= -15640 && $asc <= -15166)
        return 'M';

    if ($asc >= -15165 && $asc <= -14923)
        return 'N';

    if ($asc >= -14922 && $asc <= -14915)
        return 'O';

    if ($asc >= -14914 && $asc <= -14631)
        return 'P';

    if ($asc >= -14630 && $asc <= -14150)
        return 'Q';

    if ($asc >= -14149 && $asc <= -14091)
        return 'R';

    if ($asc >= -14090 && $asc <= -13319)
        return 'S';

    if ($asc >= -13318 && $asc <= -12839)
        return 'T';

    if ($asc >= -12838 && $asc <= -12557)
        return 'W';

    if ($asc >= -12556 && $asc <= -11848)
        return 'X';

    if ($asc >= -11847 && $asc <= -11056)
        return 'Y';

    if ($asc >= -11055 && $asc <= -10247)
        return 'Z';

    return null;

}



function getArrayMax($arr,$field)
{
    foreach ($arr as $k=>$v){
        $temp[]=$v[$field];
    }
    return max($temp);
}

/**
 * 返回数组
 * @param int $status
 * @param string $msg
 * @param array $data
 * @return array
 */
function returnArr($status=0,$msg='success',$data=[]){
    return compact('status','data','msg');
}

/**
 * 判断是否为json
 * @param $str
 * @return bool
 */
function not_json($str){
    return is_null(json_decode($str));
}

/*
 * 以json方式执行curl会话操作
*/
function http($url,$json){
//    header("Content-type: image/jpeg");
    $header = "Accept-Charset: utf-8";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: '.strlen($json))
    );
    $data = curl_exec($ch);
    //$data = curl_exec($ch);
    if($data){
        curl_close($ch);
        return $data;
    }else {
        $error = curl_errno($ch);
        echo "call faild, errorCode:$error\n";
        curl_close($ch);
        return false;
    }
}
//HTTP请求（支持HTTP/HTTPS，支持GET/POST）
function http_request($url, $data = null)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    $output = curl_exec($curl);
    curl_close($curl);
    return $output;
}
function getArrayMax2($arr,$field,$distribution_mode)
{
    foreach ($arr as $k=>$v){
        $temp[] = $v['distribution_mode'] ==1?0:$v[$field];
    }
    return ($distribution_mode==1)?0:max($temp);
}

/**
 * 自动下发新人优惠券
 * @param $user_id用户id
 */
function autocoupon($user_id){
    try{
        $coupons=Db::table('coupon_rule')
            ->where('coupon_type',1)
            ->where('is_open',1)
            ->where('client_type','in','0,1')
            ->select();

      //  Log::info(print_r($coupons,true));
        if($coupons){
            $coupon_name = "";
            $number = 0;
            $expiration_time = 0;
            foreach ($coupons as $k=>$v ){
                if($v['client_type']==0){
                    $u= Db::table('coupon')->where('user_id',$user_id)->where('coupon_id',$v['id'])->find();
                    if($u){}else{
                        $cur_expiration_time = time() + 24 * 3600 * $v['days'];
                        for ($i = 0; $i < $v['zengsong_number']; $i++) {
                            $tongyong[] = [
                                'coupon_id' => $v['id'],
                                'user_id' => $user_id,
                                'coupon_name' => $v['coupon_name'],
                                'satisfy_money' => $v['satisfy_money'],
                                'coupon_money' => $v['coupon_money'],
                                'status' => 1,
                                'expiration_time' => $cur_expiration_time,
                                'create_time' => time(),
                                'coupon_type' => $v['coupon_type']
                            ];
                        }
                        $coupon_name .= ",{$v['coupon_name']}";
                        $number += $v['zengsong_number'];
                        if($cur_expiration_time < $expiration_time || $expiration_time == 0)$expiration_time = $cur_expiration_time;
                    }
                }else{
                    $cur_expiration_time = time() + 24 * 3600 * $v['days'];
                    for ($i = 0; $i < $v['zengsong_number']; $i++) {
                        $data[] = [
                            'coupon_id' => $v['id'],
                            'user_id' => $user_id,
                            'coupon_name' => $v['coupon_name'],
                            'satisfy_money' => $v['satisfy_money'],
                            'coupon_money' => $v['coupon_money'],
                            'status' => 1,
                            'expiration_time' => $cur_expiration_time,
                            'create_time' => time(),
                            'coupon_type' => $v['coupon_type']
                        ];
                    }
                    $coupon_name .= ",{$v['coupon_name']}";
                    $number += $v['zengsong_number'];
                    if($cur_expiration_time < $expiration_time || $expiration_time == 0)$expiration_time = $cur_expiration_time;
                }
            }
          //  Log::info(print_r($data,true));
            Db::startTrans();
            $res1 = $res2 = true;
            if(isset($tongyong) && $tongyong){
                $res1 = Db::name('coupon')->insertAll($tongyong);
            }
            if(isset($data) && $data){
                $res2 = Db::name('coupon')->insertAll($data);
            }
            if($res1 === false || $res2 === false){
                addErrLog(['user_id'=>$user_id,'coupon_data'=>[$tongyong, $data]],'小程序新人优惠券下发失败',3);
                throw new Exception('新人券下发失败');
            }

            Db::commit();
            if($number > 0){
                ##发送领券模板消息通知
                $type = 'coupon_get_notice';
                $commonData = UserLogic::getTemplateUserData($user_id);
                if(!$commonData['status']){
                    addErrLog($commonData['msg'],'新人优惠券下发消息通知发送失败',3);
                    return true;
                }

                $commonData = $commonData['data'];

                $data = [
                    'coupon_name' => trim($coupon_name,","),
                    'store_name' => "超神宿平台",
                    'use_desc' => "新人优惠券,可在超神宿平台使用",
                    'use_limit' => "超神宿平台下部分商品",
                    'expiration_time' => $expiration_time,
                    'number' => $number,
                ];
                $templateInfo = UserLogic::getTemplateInfo($type, $data);

                ##更新模板信息的状态
                $res = UserLogic::useFormId($commonData['form']['id']);
                if($res === false){
                    addErrLog('FORM_ID更新失败','新人优惠券下发消息通知发送失败',3);
                    return true;
                }

                ##发送消息
                $res = CreateTemplate::sendTemplateMsg($commonData['open_id'], $templateInfo, $commonData['form_id'], $commonData['access_token']);
                $result = json_decode($res, true);
                if($result && isset($result['errcode'])){
                    $errCode = $result['errcode'];
                    if($errCode > 0){
                        addErrLog($result,'新人优惠券下发消息通知发送失败',3);
                    }
                }else{
                    addErrLog($res,'新人优惠券下发消息通知发送失败',3);
                }
            }
        }
    }catch (\Exception $e){
        Db::rollback();
        return $e->getMessage();
    }
    return true;
}
/**
 * 小程序分享
 * @param $user_id用户id
 */
function share_set(){
    try{
       return $data= Db::table('share_set')->field('share_title,share_cover,description')->where('id',1)->find();

}catch (\Exception $e){
    return $e->getMessage();
}
}
/**
 * 小程序分享
 * @param $user_id用户id
 */
function share_set2(){
    try{
        return $data= Db::table('share_set')->field('share_title,share_cover,description,qrcode')->where('id',1)->find();

    }catch (\Exception $e){
        return $e->getMessage();
    }
}

/**
 * 二维数组按照指定字段进行排序
 * @params array $array 需要排序的数组
 * @params string $field 排序的字段
 * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
 */
function arraySequence($array, $field, $sort = 'SORT_DESC') {
    $arrSort = array();
    foreach ($array as $uniqid => $row) {
        foreach ($row as $key => $value) {
            $arrSort[$key][$uniqid] = $value;
        }
    }
    array_multisort($arrSort[$field], constant($sort), $array);
    return $array;
}

/**
 *  图片内容审核
 * @param $path
 * @return bool
 */
function imgSecCheck($path){
    $obj = new \CURLFile($path);
    $obj->setMimeType("image/jpeg");
    $res = Weixin::imgSecCheck($obj);
    if(!$res)return false;
    $res = json_decode($res,true);
    return $res['errcode'] == 87014 ? false : true;
}

/**
 * 生成缩略图
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function createThumb($path, $root, $type){
    $config = config('config_common.compress_config');
    $path = str_replace("\\","/",$path);
    $path = trim($path,'/');
    if(file_exists($path)){
        $img = \think\Image::open($path);
        $ext = substr($path, strrpos($path,'.'));
        $path = $root . time() . rand(100000,999999) . "_" . $config[$type][0] . "X" . $config[$type][1] . $ext;
        $img->thumb($config[$type][0], $config[$type][1])->save($path);
        $path = "/" . $path;
        return $path;
    }
    return "";
}
/**
 * 判断是否有新的优惠券
 * @param $path
 * @param $root
 * @return string
 */
function getcoupon($user_id,$login_time){
    $where1['ucc.expiration_time'] = ['gt',time()];
    $where1['ucc.create_time'] = ['egt',$login_time];
    $where = [
        'ucc.user_id' => $user_id,
        'ucc.status' => 1
    ];
    $list = Db::name('coupon')->alias('ucc')
        ->join('coupon_rule cc','cc.id = ucc.coupon_id','LEFT')
        ->field('ucc.id,ucc.coupon_name,ucc.expiration_time,cc.coupon_type,ucc.satisfy_money,ucc.coupon_money,cc.type,cc.store_ids,cc.store_id,cc.product_ids,cc.is_solo')
        ->where($where)
        ->where($where1)
        ->where(['cc.client_type'=>['IN',[0,1]],'cc.use_type'=>['IN',[1,3]]])  //卡券的适用平台限制
        ->order('ucc.coupon_money','desc')
        ->order('ucc.expiration_time','desc')
        ->select();
    if($list){
        foreach ($list as $k=>$v){
            if($v['type']==3 && $v['is_solo']==0){
        //商品
                $list[$k]['coupon_name']='平台下多商品满'.$v['satisfy_money'].'可用';
            }elseif ($v['type']==2){
                unset($list[$k]['is_solo']);
            }
        }
        return $list;
    }
    return "";
    }

##去掉字符串的[]
function trimFunc($str){
    return str_replace('[','',str_replace(']','',$str));
}

/**
 * 创建支付成功消息通知数据
 * @param $user_id
 * @param $order_id
 * @param $order_no
 * @param $price_order
 * @param $product_name
 * @return array
 */
function createPayTemplateData($user_id, $order_id, $order_no, $price_order, $product_name){
    try{
        $type = "order_pay_notice";

        ##获取openid
        $open_id = UserLogic::getUserOpenId($user_id);
        if(!$open_id)throw new Exception('操作成功,小程序消息通知发送失败[用户不存在]');

        ##获取access_token
        $access_token = Weixin::getAccessToken();
        if(!$access_token)throw new Exception('操作成功,小程序消息通知发送失败[获取access_token失败]');

        ##获取用户的form_id
        $form_id = UserLogic::getUserFormId($user_id);
        if(!$form_id)throw new Exception('操作成功,小程序消息通知发送失败[没有可用form_id]');

        ##获取模板id
        $data = [
            'order_no' => $order_no,
            'price_order' => $price_order,
            'product_name' => $product_name
        ];

        $templateInfo = UserLogic::getTemplateInfo($type, $data);
        $templateInfo['page'] .= "?id={$order_id}";

        return ['status'=>1,'data'=>compact('open_id','form_id','templateInfo','access_token')];
    }catch(Exception $e){
        return ['status'=>0,'msg'=>$e->getMessage()];
    }
}

/**
 * 创建退款成功消息通知数据
 * @param $user_id
 * @param $order_id
 * @param $money_refund
 * @param $product_name
 * @param $store_name
 * @param $refund_type
 * @return array
 */
function createRefundTemplateData($user_id, $order_id, $money_refund, $product_name, $store_name, $refund_type){
    try{
        $type = "refund_notice";

        ##获取openid
        $open_id = UserLogic::getUserOpenId($user_id);
        if(!$open_id)throw new Exception('操作成功,小程序消息通知发送失败[用户不存在]');

        ##获取access_token
        $access_token = Weixin::getAccessToken();
        if(!$access_token)throw new Exception('操作成功,小程序消息通知发送失败[获取access_token失败]');

        ##获取用户的form_id
        $form_id = UserLogic::getUserFormId($user_id);
        if(!$form_id)throw new Exception('操作成功,小程序消息通知发送失败[没有可用form_id]');

        ##获取模板id
        $data = [
            'money_refund' => $money_refund,
            'product_name' => $product_name,
            'store_name' => $store_name,
            'refund_type' => $refund_type,
        ];

        $templateInfo = UserLogic::getTemplateInfo($type, $data);
        $templateInfo['page'] .= "?id={$order_id}";

        return ['status'=>1,'data'=>compact('open_id','form_id','templateInfo','access_token')];
    }catch(Exception $e){
        return ['status'=>0,'msg'=>$e->getMessage()];
    }
}

/**
 * 创建获利到账消息通知数据
 * @param $user_id
 * @param $price_profit
 * @param $order_no
 * @param $product_name
 * @return array
 */
function createProfitGetTemplateData($user_id, $price_profit, $order_no, $product_name){
    try{
        $type = "profit_get_notice";

        ##获取openid
        $open_id = UserLogic::getUserOpenId($user_id);
        if(!$open_id)throw new Exception('操作成功,小程序消息通知发送失败[用户不存在]');

        ##获取access_token
        $access_token = Weixin::getAccessToken();
        if(!$access_token)throw new Exception('操作成功,小程序消息通知发送失败[获取access_token失败]');

        ##获取用户的form_id
        $form_id = UserLogic::getUserFormId($user_id);
        if(!$form_id)throw new Exception('操作成功,小程序消息通知发送失败[没有可用form_id]');

        ##获取模板id
        $data = [
            'price_profit' => $price_profit,
            'order_no' => $order_no,
            'product_name' => $product_name
        ];

        $templateInfo = UserLogic::getTemplateInfo($type, $data);
//        $templateInfo['page'] .= "?id={$order_id}";

        return ['status'=>1,'data'=>compact('open_id','form_id','templateInfo','access_token')];
    }catch(Exception $e){
        return ['status'=>0,'msg'=>$e->getMessage()];
    }
}

/**
 * 文字内容审核
 * @param $content
 * @return bool
 */
function msgSecCheck($content){
    $res = Weixin::msgSecCheck($content);
    if(!$res)return false;
    $res = json_decode($res,true);
    return $res['errcode'] == 87014 ? false : true;
}

/**
 * 本地文字内容审核--原来的
 * @param $content
 * @return array
 */
//function msgSecCheckLocal($content){
//    $bad_wrods = config('config_bad_words');
//    $check = [];
//    foreach($bad_wrods as $v){
//        $pattern = "/(".implode("|",$v).")/i"; //定义正则表达式
//        if(preg_match_all($pattern, $content, $matches)){
//            $check[] = $matches[0];
//        }
//    }
//    return $check;
//}
/**
 * 本地文字内容审核--新增的
 * @param $content
 * @return array
 */
function msgSecCheckLocal($content){
    $bad_wrods = config('config_bad_words');
    $check = [];
    foreach($bad_wrods as $v){
        foreach ($v as $v1){
            // 判断定义违规词是否在用户输入内容中
            $pattern = "/^(".$content.")$/i"; //定义正则表达式
            if(preg_match_all($pattern, $v1, $matches)){
                $check[] = $matches[0];
            }
        }
    }
    return $check;
}

