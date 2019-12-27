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

//admin模块公共函数

/**
 * 管理员密码加密方式
 * @param $password  密码
 * @param $password_code 密码额外加密字符
 * @return string
 */
function password($password, $password_code='lshi4AsSUrUOwWV')
{
    return md5(md5($password) . md5($password_code));
}

/**
 * 管理员操作日志
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function addlog($operation_id='')
{
    //获取网站配置
    $web_config = \think\Db::name('webconfig')->where('web','web')->find();
    if($web_config['is_log'] == 1) {
        $data['operation_id'] = $operation_id;
        $data['admin_id'] = \think\Session::get('admin');//管理员id
        $request = \think\Request::instance();
        $data['ip'] = $request->ip();//操作ip
        $data['create_time'] = time();//操作时间
        $url['module'] = $request->module();
        $url['controller'] = $request->controller();
        $url['function'] = $request->action();
        //获取url参数
        $parameter = $request->path() ? $request->path() : null;
        //将字符串转化为数组
        $parameter = explode('/',$parameter);
        //剔除url中的模块、控制器和方法
        foreach ($parameter as $key => $value) {
            if($value != $url['module'] and $value != $url['controller'] and $value != $url['function']) {
                $param[] = $value;
            }
        }

        if(isset($param) and !empty($param)) {
            //确定有参数
            $string = '';
            foreach ($param as $key => $value) {
                //奇数为参数的参数名，偶数为参数的值
                if($key%2 !== 0) {
                    //过滤只有一个参数和最后一个参数的情况
                    if(count($param) > 2 and $key < count($param)-1) {
                        $string.=$value.'&';
                    } else {
                        $string.=$value;
                    }
                } else {
                    $string.=$value.'=';
                }
            } 
        } else {
            //ajax请求方式，传递的参数path()接收不到，所以只能param()
            $string = [];
            $param = $request->param();
            foreach ($param as $key => $value) {
                if(!is_array($value)) {
                    //这里过滤掉值为数组的参数
                    $string[] = $key.'='.$value;
                }
            }
            $string = implode('&',$string);
        }
        $data['admin_menu_id'] = empty(\think\Db::name('admin_menu')->where($url)->where('parameter',$string)->value('id')) ? \think\Db::name('admin_menu')->where($url)->value('id') : \think\Db::name('admin_menu')->where($url)->where('parameter',$string)->value('id');

        if (empty($data['admin_menu_id'])) {
            $data['admin_menu_id'] = 0;
        }

        //return $data;
        \think\Db::name('admin_log')->insert($data);
    } else {
        //关闭了日志
        return true;
    }
	
}


/**
 * 格式化字节大小
 * @param  number $size      字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '') {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}


/**
 * 获取ip + 地址
 * @param null $ip
 * @return null|string
 */
function _get_ip_dizhi($ip=null){
    $opts = array(
        'http'=>array(
            'method'=>"GET",
            'timeout'=>5,)
    );
    $context = stream_context_create($opts);


    if($ip){
        $ipmac = $ip;
    }else{
        $ipmac=_get_ip();
        if(strpos($ipmac,"127.0.0.") === true)return '';
    }

    $url_ip='http://ip.taobao.com/service/getIpInfo.php?ip='.$ipmac;
    $str = @file_get_contents($url_ip, false, $context);
    if(!$str) return "";
    $json=json_decode($str,true);
    if($json['code']==0){

        $json['data']['region'] = addslashes(_htmtocode($json['data']['region']));
        $json['data']['city'] = addslashes(_htmtocode($json['data']['city']));

        $ipcity= $json['data']['region'].$json['data']['city'];
        $ip= $ipcity.','.$ipmac;
    }else{
        $ip="";
    }
    return $ip;
}

/**
 * 获取客户端ip
 * @return string
 */
function _get_ip(){
    if (isset($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], "unknown"))
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], "unknown"))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
        $ip = $_SERVER['REMOTE_ADDR'];
    else if (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
        $ip = $_SERVER['REMOTE_ADDR'];
    else if (isset($_SERVER['HTTP_X_REAL_IP']) && strcasecmp($_SERVER['HTTP_X_REAL_IP'], "unknown"))
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    else $ip = "";
    return ($ip);
}

/**
 * HTML安全过滤
 * @param $content
 * @return mixed
 */
function _htmtocode($content) {
    $content = str_replace('%','%&lrm;',$content);
    $content = str_replace("<", "&lt;", $content);
    $content = str_replace(">", "&gt;", $content);
    $content = str_replace("\n", "<br/>", $content);
    $content = str_replace(" ", "&nbsp;", $content);
    $content = str_replace('"', "&quot;", $content);
    $content = str_replace("'", "&#039;", $content);
    $content = str_replace("$", "&#36;", $content);
    $content = str_replace('}','&rlm;}',$content);
    return $content;
}

function get_rand($len){
    $start = "1" . str_repeat("0",$len-1);
    $end = "9" . str_repeat("9",$len-1);
    return rand($start,$end);
}

/**
 * 生成商务推广码
 * @param $id
 * @param $extend_id
 * @param string $bh
 * @return string
 */
function get_extend_coupon_code($id,$extend_id,$bh="01"){
    $len = 8 - strlen($id) - strlen($extend_id);
    return $len>0 ? (strlen($id) . $id . $extend_id . strlen($extend_id) . get_rand($len) . $bh) : (strlen($id) . $id . $extend_id . strlen($extend_id) . $bh)
    ;
}

function get_coupon_code($id,$num=1000,$bh="01"){
    $id_len = strlen($id);
    $rand_len = 9 - $id_len;
    $arr = [];
    for($i=0;$i<$num;$i++){
        $rand = get_rand($rand_len);
        $code = strlen($id) . $id . $rand . $bh;
        if(!in_array($code,$arr)){$arr[] = $code;};
    }
    $arr = createLotsCouponCode($id,$num,$bh,$rand_len,$arr);
    return $arr;
}

function createLotsCouponCode($id,$num,$bh,$rand_len,$arr){
    if(!$id)return false;
    if(count($arr)<$num){
        $rand = get_rand($rand_len);
        $code = strlen($id) . $id . $rand . $bh;

        if(!in_array($code,$arr)){$arr[] = $code;};
        return createLotsCouponCode($id,$num,$bh,$rand_len,$arr);
    }else{
        return $arr;
    }
}

/*
 浏览器打开时设置header头
 $type excel版本类型 Excel5---Excel2003, Excel2007
 $filename 输出的文件名
*/
function browser_excel($type,$filename){
    if($type=="Excel5"){
        header('Content-Type: application/vnd.ms-excel');//告诉浏览器将要输出excel03文件
    }else{
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');//告诉浏览器数据excel07文件
    }
    header('Content-Disposition: attachment;filename="'.$filename.'"');//告诉浏览器将输出文件的名称，要是没有设置，会把当前文件名设置为名称
    header('Cache-Control: max-age=0');//禁止缓存
}

function rtnJson($status=0,$msg='',$data=[]){
    return json_encode(compact('status','msg','data'));
}

function toSimple($data){
    return json_decode(json_encode($data),true);
}

##去掉字符串的[]
function trimFunc($str){
    return str_replace('[','',str_replace(']','',$str));
}

/**
 * 字符串过滤
 * @param $val
 * @return string
 */
function trimStr($val){
    return addslashes(strip_tags(trim($val)));
}