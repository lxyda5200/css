<?php
namespace app\business\model;

use think\Model;
use think\Validate;
use jiguang\JiG;
use think\Loader;
class UserModel extends Model
{

    protected $pk = 'id';

    protected $table = 'store';

    public static function userLogin($params){
        // 参数验证
        $rule = [
            'mobile'   => 'require|number|length:11',
            'password' => 'require',
        ];
        $msg = [
            'mobile.require'   => '缺少必要参数', 'mobile.number' => '缺少必要参数', 'mobile.length'=>'手机号错误',
            'password.require' => '缺少必要参数',
        ];
        $validate = new Validate($rule, $msg);
        if (!$validate->check($params)) {
            return $validate->getError();
        }

        // TODO 具体的验证逻辑

        $checkPass = BusinessModel::where('b.mobile',$params['mobile'])
            ->alias('b')
            ->join('store s','s.id = b.store_id')
            ->field(['b.password,b.id,b.store_id,s.cover,s.store_name,s.store_status,b.pid,IF(b.pid>0,0,1) as is_main_user,b.business_status,b.qrcode,b.mobile,b.avatar,b.business_name,b.money,b.jig_id,b.jig_pwd']) -> find();
        if(!$checkPass || !password_verify($params['password'], $checkPass['password'])) return '账号或密码错误';

        if ($checkPass['business_status'] != 1) return "权限不足，请联系管理员";
        if(empty($checkPass['jig_id']) || $checkPass['jig_id'] == 0){
            $jig = new JiG();
            $jig_arr = $jig->registerService($checkPass['id']);
            if($jig_arr['status'] == 1){
                BusinessModel::where('id',$checkPass['id']) -> update(['jig_id' => $jig_arr['jig_id'],'jig_pwd' => $jig_arr['jig_pwd']]);
                $checkPass['jig_id'] = $jig_arr['jig_id'];
                $checkPass['jig_pwd'] = $jig_arr['jig_pwd'];
            }
        }
        if(empty($checkPass['qrcode'])){
            $qr = self::qrcode($checkPass['store_id'],1,$checkPass['id']);
            $checkPass['qrcode'] = $qr;
        }
        //头像添加测试地址
        //$checkPass['avatar'] = 'http://121.196.214.146/csswx/css/public'.$checkPass['avatar'];
        // 重新生成TOKEN
        $new_token = makeUserToken(true);
        // 更新token
        $updateToken = BusinessModel::where('id',$checkPass['id']) -> update(['token' => $new_token,'token_expire_time' => time()+7*24*60*60]);
        if (!$updateToken) return false;
        $checkPass['token'] = $new_token;
        unset($checkPass['password']);
        return $checkPass;
    }

    /**
     * 退出登录
     */
    public static function userLogout($user_id){
        $data = array('token'=>'','token_expire_time'=>'0');
        $re = BusinessModel::where('id',$user_id) -> setField($data);
        if($re){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @return mixed
     * 生成二维码
     */
    public static function qrcode($store_id,$type,$add){
        //生成二维码
        Loader::import('phpqrcode.phpqrcode');
        $QRcode = new \QRcode;
        //$value = 'http://appwx.supersg.cn/app/download.html';
        $value = 'http://appwx.supersg.cn/app/download.html?store_id='.$store_id.'&type='.$type.'&business_id='.$add;
        $errorCorrectionLevel = 'L';//纠错级别：L、M、Q、H
        $matrixPointSize = 10;//二维码点的大小：1到10
        $path=ROOT_PATH . 'public' . DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS.$add.'.png';
        $QRcode::png ( $value, $path, $errorCorrectionLevel, $matrixPointSize, 2 );//不带Logo二维码的文件名
        $logo = ROOT_PATH . 'public' . DS .'logo.png';//需要显示在二维码中的Logo图像
        $QR =$path;
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring ( file_get_contents ( $QR ) );
            $logo = imagecreatefromstring ( file_get_contents ( $logo ) );
            $QR_width = imagesx ( $QR );
            $QR_height = imagesy ( $QR );
            $logo_width = imagesx ( $logo );
            $logo_height = imagesy ( $logo );
            $logo_qr_width = $QR_width / 6.2;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            imagecopyresampled ( $QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height );
        }
        imagepng ( $QR, $path );//带Logo二维码的文件名
        $qrcode=  DS . 'uploads'. DS .'busisess'. DS .'qrcode'.DS .$add.'.png';
        //将qrcode 写入到员工表
        BusinessModel::where(['id' => $add]) -> update(['qrcode' => $qrcode]);
        return $qrcode;
    }

}