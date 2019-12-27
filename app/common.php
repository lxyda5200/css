<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------

// 应用公共文件

use think\Request;
use think\Db;

/**
 * 根据附件表的id返回url地址
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function geturl($id)
{
	if ($id) {
		$geturl = \think\Db::name("attachment")->where(['id' => $id])->find();
		if($geturl['status'] == 1) {
			//审核通过
			return $geturl['filepath'];
		} elseif($geturl['status'] == 0) {
			//待审核
			return '/uploads/xitong/beiyong1.jpg';
		} else {
			//不通过
			return '/uploads/xitong/beiyong2.jpg';
		} 
    }
    return false;
}

/**
 * [SendMail 邮件发送]
 * @param [type] $address  [description]
 * @param [type] $title    [description]
 * @param [type] $message  [description]
 * @param [type] $from     [description]
 * @param [type] $fromname [description]
 * @param [type] $smtp     [description]
 * @param [type] $username [description]
 * @param [type] $password [description]
 */
function SendMail($address)
{
    vendor('phpmailer.PHPMailerAutoload');
    //vendor('PHPMailer.class#PHPMailer');
    $mail = new \PHPMailer();          
     // 设置PHPMailer使用SMTP服务器发送Email
    $mail->IsSMTP();                
    // 设置邮件的字符编码，若不指定，则为'UTF-8'
    $mail->CharSet='UTF-8';         
    // 添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address); 

    $data = \think\Db::name('emailconfig')->where('email','email')->find();
            $title = $data['title'];
            $message = $data['content'];
            $from = $data['from_email'];
            $fromname = $data['from_name'];
            $smtp = $data['smtp'];
            $username = $data['username'];
            $password = $data['password'];   
    // 设置邮件正文
    $mail->Body=$message;           
    // 设置邮件头的From字段。
    $mail->From=$from;  
    // 设置发件人名字
    $mail->FromName=$fromname;  
    // 设置邮件标题
    $mail->Subject=$title;          
    // 设置SMTP服务器。
    $mail->Host=$smtp;
    // 设置为"需要验证" ThinkPHP 的config方法读取配置文件
    $mail->SMTPAuth=true;
    //设置html发送格式
    $mail->isHTML(true);           
    // 设置用户名和密码。
    $mail->Username=$username;
    $mail->Password=$password; 
    // 发送邮件。
    return($mail->Send());
}


/**
 * 阿里大鱼短信发送
 * @param [type] $appkey    [description]
 * @param [type] $secretKey [description]
 * @param [type] $type      [description]
 * @param [type] $name      [description]
 * @param [type] $param     [description]
 * @param [type] $phone     [description]
 * @param [type] $code      [description]
 * @param [type] $data      [description]
 */
function SendSms($param,$phone)
{
    // 配置信息
    import('dayu.top.TopClient');
    import('dayu.top.TopLogger');
    import('dayu.top.request.AlibabaAliqinFcSmsNumSendRequest');
    import('dayu.top.ResultSet');
    import('dayu.top.RequestCheckUtil');

    //获取短信配置
    $data = \think\Db::name('smsconfig')->where('sms','sms')->find();
            $appkey = $data['appkey'];
            $secretkey = $data['secretkey'];
            $type = $data['type'];
            $name = $data['name'];
            $code = $data['code'];
    
    $c = new \TopClient();
    $c ->appkey = $appkey;
    $c ->secretKey = $secretkey;
    
    $req = new \AlibabaAliqinFcSmsNumSendRequest();
    //公共回传参数，在“消息返回”中会透传回该参数。非必须
    $req ->setExtend("");
    //短信类型，传入值请填写normal
    $req ->setSmsType($type);
    //短信签名，传入的短信签名必须是在阿里大于“管理中心-验证码/短信通知/推广短信-配置短信签名”中的可用签名。
    $req ->setSmsFreeSignName($name);
    //短信模板变量，传参规则{"key":"value"}，key的名字须和申请模板中的变量名一致，多个变量之间以逗号隔开。
    $req ->setSmsParam($param);
    //短信接收号码。支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，一次调用最多传入200个号码。
    $req ->setRecNum($phone);
    //短信模板ID，传入的模板必须是在阿里大于“管理中心-短信模板管理”中的可用模板。
    $req ->setSmsTemplateCode($code);
    //发送
    

    $resp = $c ->execute($req);
}


/**
 * 替换手机号码中间四位数字
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function hide_phone($str){
    $resstr = substr_replace($str,'****',3,4);  
    return $resstr;  
}

/**
 * 互易无限发送验证码
 * @param $curlPost
 * @param $url
 * @return mixed
 */
function Post($curlPost,$url){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
    $return_str = curl_exec($curl);
    curl_close($curl);
    return $return_str;
}

/**
 * 生成随机码
 * @param int $length
 * @param int $numeric
 * @return string
 */
function random($length = 6 , $numeric = 0)
{
    PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
    if ($numeric) {
        $hash = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
    } else {
        $hash = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ1234567890abcdefghjkmnpqrstuvwxyz';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }
    return $hash;
}

/**
 * @param $xml
 * @return mixed
 * 生成查询
 */
function xml_to_array($xml){
    $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
    if(preg_match_all($reg, $xml, $matches)){
        $count = count($matches[0]);
        for($i = 0; $i < $count; $i++){
            $subxml= $matches[2][$i];
            $key = $matches[1][$i];
            if(preg_match( $reg, $subxml )){
                $arr[$key] = xml_to_array( $subxml );
            }else{
                $arr[$key] = $subxml;
            }
        }
    }
    return $arr;
}

/**
 * Password Hashing API 加密
 * @param $password
 * @return bool|string
 */
function get_pwd($password){
    $salt = '$2y$11$' . substr(md5(uniqid(rand(), true)), 0, 22);
    $options = [
        'salt' => $salt,
        'cost' => 12
    ];
    $hash = password_hash($password, PASSWORD_BCRYPT, $options);
    return $hash;
}

/**
 * 随机生成token
 * @return string
 */
function create_token(){
    $token = md5(uniqid(rand(),TRUE).'');
    return $token;
}

/**
 * 屏蔽电话号码中间四位
 * @param $phone
 * @return null|string|string[]
 */
function hidtel($phone){
    $IsWhat = preg_match('/(0[0-9]{2,3}[\-]?[2-9][0-9]{6,7}[\-]?[0-9]?)/i',$phone); //固定电话
    if($IsWhat == 1){
        return preg_replace('/(0[0-9]{2,3}[\-]?[2-9])[0-9]{3,4}([0-9]{3}[\-]?[0-9]?)/i','$1****$2',$phone);
    }else{
        return  preg_replace('/(1[358]{1}[0-9])[0-9]{4}([0-9]{4})/i','$1****$2',$phone);
    }
}

/**
 * 根据地址 获取经纬度  高德地图
 * @param $address
 * @return mixed
 */
function addresstolatlng($address){


    $url='http://restapi.amap.com/v3/geocode/geo?address='.$address.'&key=eb7b56f169ff4372b43bdd7dabf489fd';
    if($result=file_get_contents($url))
    {
        $result = json_decode($result,true);
        //判断是否成功
        if(!empty($result['count'])){
            return  explode(',',$result['geocodes']['0']['location']);

        }else{
            return false;
        }

    }

}

/**
 * 根据经纬度 获取地址
 * @param $address23.2322,12.15544 经度,纬度 高德地图
 * @return mixed
 */
function getaddress($address){



    $url="http://restapi.amap.com/v3/geocode/regeo?output=json&location=".$address."&key=eb7b56f169ff4372b43bdd7dabf489fd";
    if($result=file_get_contents($url))
    {
        $result = json_decode($result,true);
        if(!empty($result['status'])&&$result['status']==1){

            return $result['regeocode']['formatted_address'];

        }else{
            return false;
        }



    }

}

/**
 * 获取地址对应的坐标  百度地图
 * @param $address
 * @return array
 */
function baidu_get_address_point($address){
    $lng = 0;
    $lat = 0;
    $url = 'http://api.map.baidu.com/geocoder?output=json&address=' . urlencode($address);
    if(function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
    }else{
        $data = file_get_contents($url,false,stream_context_create(array(
            "http"=>array(
                "method"=>"GET",
                "timeout"=>1
            ),
        )));
    }

    $data = json_decode($data,true);
    if($data && $data['status'] == 'OK' && isset($data['result']) && isset($data['result']['location']))
    {
        $lng = $data['result']['location']['lng'];
        $lat = $data['result']['location']['lat'];
    }
    return array($lng,$lat);
}

/**
 * 逆地理编码专属请求  百度地图
 * User: Lg
 * Date: 2016/4/11
 * @param $address
 * @return array
 */
function baidu_get_address($lat,$lng){
    $location = $lat.','.$lng;
    $url = 'http://api.map.baidu.com/geocoder?callback=renderReverse&location='.$location.'&output=json&pois=1';
    if(function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
    }else{
        $data = file_get_contents($url,false,stream_context_create(array(
            "http"=>array(
                "method"=>"GET",
                "timeout"=>1
            ),
        )));
    }

    $data = json_decode($data,true);
    if($data && $data['status'] == 'OK' && isset($data['result']) && isset($data['result']['addressComponent']))
    {
        $province = $data['result']['addressComponent']['province'];
        $city = $data['result']['addressComponent']['city'];
        $district = $data['result']['addressComponent']['district'];
    }
    return array($province,$city,$district);
}


/**
 * 二维数组去重
 * @param $array2D
 * @return mixed
 */
function array_quchong($array2D){
    foreach ($array2D as $k=>$v){
        $v=join(',',$v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
        $temp[$k]=$v;
    }
    #dump($temp);
    $temp=array_unique($temp); //去掉重复的字符串,也就是重复的一维数组
    foreach ($temp as $k => $v){
        $array=explode(',',$v); //再将拆开的数组重新组装
        //下面的索引根据自己的情况进行修改即可
        $temp2[$k]['xiaoqu_name'] =$array[0];
        $temp2[$k]['address'] =$array[1];
    }
    return array_values($temp2);
}

/**
 * 二维数组排序
 * @param $arr
 * @param $shortKey
 * @param int $short
 * @param int $shortType
 * @return mixed
 */
function multi_array_sort($arr,$shortKey,$short=SORT_DESC,$shortType=SORT_REGULAR)
{
    foreach ($arr as $key => $data){
        $name[$key] = $data[$shortKey];
    }
    array_multisort($name,$shortType,$short,$arr);
    return $arr;
}

/**
 * 生成订单号
 * @param string $x
 * @return string
 */
function build_order_no($x=''){
    return $x.date('Ymd').substr(microtime(),2,5).rand(0,9);
}

/**
 * 求两个日期之间相差的天数
 * (针对1970年1月1日之后，求之前可以采用泰勒公式)
 * @param string $date1
 * @param string $date2
 * @return number
 */
function diff_date($date1, $date2)
{
    if ($date1 > $date2) {
        $startTime = strtotime($date1);
        $endTime = strtotime($date2);
    } else {
        $startTime = strtotime($date2);
        $endTime = strtotime($date1);
    }
    $diff = $startTime - $endTime;
    $day = $diff / 86400;
    return intval($day);
}


/**
 * 写错误日志，方便测试（看网站需求，也可以改成把记录存入数据库）
 * 注意：服务器需要开通fopen配置
 * @param string $word 要写入日志里的文本内容 默认值：空值
 */
function WriteLog($word='') {
    $date = date("Y-m-d",$_SERVER['REQUEST_TIME']);
    #$fp = fopen("/home/www/lg/log/error/{$date}_Error.txt","a");
    $fp = fopen("/tpl/log/{$date}_Error.txt","a");
    flock($fp, LOCK_EX) ;
    fwrite($fp,"执行日期：".strftime("%Y%m%d%H%M%S",time())." 调试数据：".$word."\r\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * 根据经纬度获取距离
 * @param $lat1
 * @param $lng1
 * @param $lat2
 * @param $lng2
 * @return int
 */
function getDistance($lat1, $lng1, $lat2, $lng2){

    //将角度转为狐度

    $radLat1=deg2rad($lat1);//deg2rad()函数将角度转换为弧度

    $radLat2=deg2rad($lat2);

    $radLng1=deg2rad($lng1);

    $radLng2=deg2rad($lng2);

    $a=$radLat1-$radLat2;

    $b=$radLng1-$radLng2;

    $s=2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6378.137;

    return $s;

}

/**
 * 根据ID获取邀请码
 * @param $user_id
 * @return string
 */
function createCode($user_id) {

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';

    $num = $user_id;

    $code = '';

    while ( $num > 0) {

        $mod = $num % 35;

        $num = ($num - $mod) / 35;

        $code = $source_string[$mod].$code;

    }

    if(empty($code[3]))

        $code = str_pad($code,4,'0',STR_PAD_LEFT);

    return $code;

}

/**
 * 根据code获取user_id
 * @param $code
 * @return float|int
 */
function decode($code) {

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';

    if (strrpos($code, '0') !== false)

        $code = substr($code, strrpos($code, '0')+1);

    $len = strlen($code);

    $code = strrev($code);

    $num = 0;

    for ($i=0; $i < $len; $i++) {

        $num += strpos($source_string, $code[$i]) * pow(35, $i);

    }

    return $num;

}


function curlHtml($url, $data = ''){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if(!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    //释放curl句柄
    curl_close($ch);
    return $output;
}

/**
 * 验证AppStore内付
 * @param  string $receipt_data 付款后凭证
 * @return array                验证是否成功
 */
function validate_apple_pay($receipt_data,$sandbox) {
    /**
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     */
    function acurl($receipt_data, $sandbox=0) {
        //小票信息
        #$secret = "666666";    // APP固定密钥，在itunes中获取
        $POSTFIELDS = array("receipt-data" => $receipt_data);
        $POSTFIELDS = json_encode($POSTFIELDS);


        //正式购买地址 沙盒购买地址
        $url_buy     = "https://buy.itunes.apple.com/verifyReceipt";
        $url_sandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $url = $sandbox ? $url_sandbox : $url_buy;
        return curlHtml($url, $POSTFIELDS);
    }
    // 验证参数
    if (strlen($receipt_data)<20){
        $result = ['status'=>false, 'message'=>'非法参数'];
        return $result;
    }
    // 请求验证
    $html = acurl($receipt_data,$sandbox);


    $data = json_decode($html,true);


    // 如果是沙盒数据 则验证沙盒模式
    if($data['status'] == '21007'){
        // 请求验证
        $html = acurl($receipt_data, 1);
        $data = json_decode($html,true);
        $data['sandbox'] = '1';
    }

    if (isset($_GET['debug'])) {
        exit(json_encode($data));
    }

    // 判断是否购买成功
    if(intval($data['status']) === 0){
        $result = ['status'=>true, 'message'=>'购买成功'];
    }else{
        $result = ['status'=>false, 'message' => '购买失败 status:'.$data['status'] ];
    }
    return $result;
}


/**
 * @param $codeLength   指定要生成的长度
 * @param $codeCount    指定需要的个数
 * @return array    生成字符串的集合
 */
function randomCode($codeLength, $codeCount)
{
    $str1 = '1234567890';
    $str2 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $str3 = 'abcdefghijklmnopqrstuvwxyz';
    $arr = [$str1 , $str2 , $str3] ;
//    var_dump($str);die;
    $code_list = array();    // 接收随机数的数组

    // 生产制定个数
    for ($j = 1; $j <= $codeCount; $j++) {
        $code = "";
        for ($i = 1; $i <= $codeLength; $i++) {  // 生成指定位随机数
            $str = implode('',$arr);
//            var_dump($str);die;
            $code .= $str[mt_rand(0, strlen($str) - 1)];
        }
        if (!in_array($code, $code_list)) {
            $code_list[$j] = $code;
        } else {
            $j--;
        }
    }
    return $code_list;
}

function tempQrCode($data){
    \think\Loader::import('phpqrcode.phpqrcode');
    $object = new QRcode();
    $errorCorrectionLevel = 'L';    //容错级别
    $matrixPointSize = 5;            //生成图片大小
    //打开缓冲区
//    header("Content-type: image/png");
    ob_start();
    //生成二维码图片
    $returnData = $object->png($data,false,$errorCorrectionLevel, $matrixPointSize, 2);
    //这里就是把生成的图片流从缓冲区保存到内存对象上，使用base64_encode变成编码字符串，通过json返回给页面。
    return ob_get_contents();
}

/**
 * 生成二维码图片（可生成带logo的二维码）
 *
 * @param string $data 二维码内容
 *         示例数据：http://www.tf4.cn或weixin://wxpay/bizpayurl?pr=0tELnh9
 * @param string $saveDir 保存路径名（示例:Qrcode）
 * @param string $logo 图片logo路径
 *         示例数据：./Public/Default/logo.jpg
 *         注意事项：1、前面记得带点（.）；2、建议图片Logo正方形，且为jpg格式图片；3、图片大小建议为xx*xx
 *
 * 注意：一般用于生成带logo的二维码
 *
 * @return
 */
function createQrcode($data,$saveDir="user/invitation_img",$logo = "")
{
    $rootPath = "uploads/";
    $path = $saveDir.'/'.date("Y-m-d").'/';
    $fileName = uniqid();
    if (!is_dir($rootPath.$path))
    {
        mkdir($rootPath.$path,0777,true);
    }
    $originalUrl = $path.$fileName.'.png';

    \think\Loader::import('phpqrcode.phpqrcode');
    $object = new \QRcode();
    $errorCorrectionLevel = 'M';    //容错级别
    $matrixPointSize = 20;            //生成图片大小（这个值可以通过参数传进来判断）
    $object->png($data,$rootPath.$originalUrl,$errorCorrectionLevel, $matrixPointSize, 2);

    //判断是否生成带logo的二维码
    if(file_exists($logo))
    {
        $QR = imagecreatefromstring(file_get_contents($rootPath.$originalUrl));        //目标图象连接资源。
        $logo = imagecreatefromstring(file_get_contents($logo));    //源图象连接资源。

        $QR_width = imagesx($QR);            //二维码图片宽度
        $QR_height = imagesy($QR);            //二维码图片高度
        $logo_width = imagesx($logo);        //logo图片宽度
        $logo_height = imagesy($logo);        //logo图片高度
        $logo_qr_width = $QR_width / 5;       //组合之后logo的宽度(占二维码的1/5)
        $scale = $logo_width/$logo_qr_width;       //logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height/$scale;  //组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2;   //组合之后logo左上角所在坐标点

        //重新组合图片并调整大小
        //imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);

        //输出图片
        imagepng($QR, $rootPath.$originalUrl);
        imagedestroy($QR);
        imagedestroy($logo);
    }

    return $rootPath.$originalUrl;

}

/**
 * 添加错误日志
 * @param string $content
 * @param string $title
 * @param int $level
 */
function addErrLog($content="", $title="常规错误打印", $level=1){
    $request = Request::instance();
    $module = $request->module();
    $controller = $request->controller();
    $action = $request->action();
    $param = json_encode($request->param());
    if(is_array($content))$content = json_encode($content);
    $create_time = time();
    $data = compact('content','title','level','module','controller','action','param','create_time');
    Db::name('error_log')->insert($data);
}

/**
 * 生成优惠券核销码
 * @return string
 */
function createCouponValidateCode($id=1001){
    $gap = rand(0,9);
    $max_length = 10;
    $rest_length = $max_length - strlen($id) - 3;
    $min = "1" . (string)str_repeat(0,$rest_length);
    $max = "9" . (string)str_repeat(9,$rest_length);
    $rand_num = rand($min, $max);
    $replace = $gap%9+1;
    $rand_num = str_replace($gap,$replace,$rand_num);
    return "{$gap}{$id}{$gap}{$rand_num}";
}

/**
 * 去除emoji表情
 *  * @param string $str
 */

function filterEmoji($str) {
    $str = preg_replace_callback('/./u', function (array $match) {
        return strlen($match[0]) >= 4 ? '' : $match[0];
    }, $str);
    return $str;
}

function getMessageConf($type){
    $config = config('config_common.jig_content');
    return isset($config[$type])?$config[$type]:[];
}

/**
 *获取文件夹下文件名
 * @param $path
 * @return array
 */
function readFolderFiles($path)
{
    $list     = [];
    $resource = opendir($path);
    while ($file = readdir($resource))
    {
        //排除根目录
        if ($file != ".." && $file != ".")
        {
            //根目录下的文件
            $list[] = $file;
        }
    }
    closedir($resource);
    return $list ? $list : [];
}

/**
 * 获取动态头像
 * @param int $len
 * @return array
 */
function getDefaultHeadPic($len=1){
    $conf = config('config_common.head_pic');

    $nums = noRand(0,87,$len);
    $imgs = [];
    foreach($nums as $v){
        $file_name = $conf[$v];
        $imgs[] = [
            'user_id' => 0,
            'avatar' => "/head_pic/{$file_name}"
        ];
    }
    return $imgs;
}

function noRand($begin=0,$end=20,$limit=5){
    $rand_array=range($begin,$end);
    shuffle($rand_array);//调用现成的数组随机排列函数
    return array_slice($rand_array,0,$limit);//截取前$limit个
}

function getPrefix(){
    $path = __FILE__;
    return strstr($path,'csswx')?"test":"formal";
}

function getMainDrawKey(){
    return getPrefix() . "_css_main_draw";
}

function getDrawKey($draw_id){
    return getPrefix() . "_css_draw_" . $draw_id;
}

function getDrawLockKey($draw_id){
    return getPrefix() . "_css_draw_lock_" . $draw_id;
}


/**
 * 获取当前上传显示配置
 */
function get_fileconfig(){
    $data = Db::table('upload_config')->where(['id'=>1])->find();
    if($data['local_url']=='' && $data['aliyun_url']==''){
        return $_SERVER['SERVER_NAME'];
    }else{
        if($data['type'] == 1){return $data['local_url'];}else{return $data['aliyun_url'];}
    }
}

/**
 * 获取实际中奖记录redis键
 * @param $draw_id
 * @return string
 */
function getRecordKey($draw_id){
    return getPrefix() . "_css_draw_reward_record_" . $draw_id;
}

/**
 * 获取抽奖记录导入数据redis键
 * @return string
 */
function getDrawImportRecordKey(){
    return getPrefix() . "_css_draw_import_record";
}

/**
 * 获取抽奖记录redis键
 * @param $draw_id
 * @return string
 */
function getDrawRecordKey($draw_id){
    return getPrefix() . "_css_draw_record_" . $draw_id;
}

/**
 * 获取夹带虚拟中奖数据的redis键
 * @param $draw_id
 * @return string
 */
function getDrawWithFakeRecordKey($draw_id){
    return getPrefix() . "_css_draw_with_fake_record_" . $draw_id;
}

/**
 * 获取用户可抽奖次数的redis键
 * @param $user_id
 * @param $draw_id
 * @return string
 */
function getUserDrawNumKey($draw_id){
    return getPrefix() . "_css_draw_num_" . $draw_id;
}

/**
 * 获取用户已抽奖次数的redis值
 * @param $user_id
 * @param $draw_id
 * @return string
 */
function getUserDidDrawNumKey($draw_id){
    return getPrefix() . "_css_did_draw_num_" . $draw_id;
}

/**
 * 获取随机抽奖的奖池列表
 * @param $draw_id
 * @return string
 */
function getDrawRandomKey($draw_id){
    return getPrefix() . "_css_draw_random_" . $draw_id;
}

/**
 * 获取概率抽奖的奖池列表
 * @param $draw_id
 * @return string
 */
function getDrawManicKey($draw_id){
    return getPrefix() . "_css_draw_manic_" . $draw_id;
}

/**
 *构造虚拟中奖记录
 * @param $num
 * @param $reward
 * @param $start_time
 * @return array
 */
function createFakeRewardRecord($num, $reward, $start_time){
    if($num <= 0)return [];
    $num = $num<=50?$num:20;
    $imgs = getDefaultHeadPic($num);
    foreach($reward as $k=>$v){  //过滤无奖品的
        if($v['type'] == 2)unset($reward[$k]);
    }
    $keys = array_keys($reward);
    $len = count($keys) - 1;
    foreach($imgs as &$v){
        $start_time += rand(60, 300);
        $reward_index = rand(0,$len);
        unset($v['user_id']);
        $v['user_name'] = createFakeMobile();
        $v['draw_time'] = $start_time;
        $v['gift_title'] = $reward[$keys[$reward_index]]['coupon_name'];
        $v = json_encode($v);
    }
    return $imgs;
}

/**
 * 创建假的电话号码
 * @return string
 */
function createFakeMobile(){
    $prefix = ['159','136','177','133','173','139','183'];
    $rand1 = rand(0,6);
    $rand2 = rand(1000,9999);
    return (string)($prefix[$rand1] . "****" .$rand2);
}

/**
 * 获取默认的无奖id
 * @param $reward_list
 * @return int|mixed
 */
function getDefaultRewardId($reward_list){
    $reward_id = "0";
    foreach($reward_list as $v){
        if($v['type'] == 2){
            $reward_id = $v['id'];
            break;
        }
    }
    return $reward_id;
}

/**
 * 构造用户抽奖redis值
 * @param $draw_num
 * @param $did_draw_num
 * @return string
 */
function makeUserDrawVal($draw_num, $did_draw_num){
    return "{$draw_num}|{$did_draw_num}";
}

/**
 * 分解用户抽奖redis值
 * @param $val
 * @return array
 */
function explodeUserDrawVal($val){
    $val = explode('|', $val);
    $draw_num = isset($val[0])?intval($val[0]):0;
    $did_draw_num = isset($val[1])?intval($val[1]):0;
    return compact('draw_num','did_draw_num');
}

/**计算距离
 *@param $lat1 用户纬度
 *  *@param $lng1 用户经度
 *  *@param $lat2 店铺纬度
 *  *@param $lng2 店铺经度
 */
function distance($lat1,$lng1,$lat2,$lng2){
    return $distance="ROUND(
    6378.138 * 2 * ASIN(
        SQRT(
            POW(
                SIN(
                    (
                        {$lat1} * PI() / 180 - {$lat2} * PI() / 180
                    ) / 2
                ),
                2
            ) + COS({$lat1} * PI() / 180) * COS({$lat2} * PI() / 180) * POW(
                SIN(
                    (
                        {$lng1} * PI() / 180 - {$lng2} * PI() / 180
                    ) / 2
                ),
                2
            )
        )
),2 )";

}