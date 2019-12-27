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


namespace app\admin\controller;

use \think\Db;
use \think\Cookie;
use app\admin\controller\Permissions;
use think\Session;

class Main extends Permissions
{
    public function index()
    {
        $mysql = Db::query('SELECT VERSION()');
        //tplay版本号
        $info['tplay'] = TPLAY_VERSION;
        //tp版本号
        $info['tp'] = THINK_VERSION;
        //php版本
        $info['php'] = PHP_VERSION;
        //操作系统
        $info['win'] = PHP_OS;
        //最大上传限制
        $info['upload_size'] = ini_get('upload_max_filesize');
        //脚本执行时间限制
        $info['execution_time'] = ini_get('max_execution_time').'S';
        //服务器域名/ip
        $info['service_domain'] = $_SERVER['SERVER_NAME'] . ' [ ' . gethostbyname($_SERVER['SERVER_NAME']) . ' ]';
        //mysql版本
        $info['mysql'] = $mysql[0]['VERSION()'];
        //post最大限制
        $info['post_max_size'] = ini_get('post_max_size');
        //环境
       /* $sapi = php_sapi_name();
        if($sapi = 'apache2handler') {
        	$info['environment'] = 'apache';
        } elseif($sapi = 'cgi-fcgi') {
        	$info['environment'] = 'cgi';
        } else {
        	$info['environment'] = 'cli';
        }*/

        $info['environment'] = $_SERVER["SERVER_SOFTWARE"];
        //剩余空间大小
        $info['disk'] = round(disk_free_space("/")/1024/1024,1).'M';
        $this->assign('info',$info);


        /**
         *网站信息
         */
        /*$shop_id = Session::get('shop_id');
        if (!empty($shop_id)){
            $where['shop_id'] = ['eq',$shop_id];
        }*/

        //用户数
        $web1 = Db::name('user')->count();
        $web2 = Db::name('user_show')->count();
        $web['user_num']=$web1+$web2;

        $web['admin_cate'] = Db::name('admin_cate')->count();
        #$ip_ban = Db::name('webconfig')->value('black_ip');
        #$web['ip_ban'] = empty($ip_ban) ? 0 : count(explode(',',$ip_ban));

        $web['dsh_house'] = Db::name('house')->where('status',2)->where('is_delete',0)->count();

        $web['dsh_store'] = Db::name('store')->where('sh_status',0)->count();
        
        $web['goods_num'] = Db::name('product')->where('sh_status',1)->count();
        $web['status_article'] = Db::name('article')->where('status',0)->count();
        $web['top_article'] = Db::name('article')->where('is_top',1)->count();
        $web['long_num'] = Db::name('house')->where('status',3)->where('is_delete',0)->count();
        $web['status_file'] = Db::name('attachment')->where('status',0)->count();
        $web['ref_file'] = Db::name('attachment')->where('status',-1)->count();
        $web['short_num'] = Db::name('house_short')->where('is_delete',0)->count();
        $web['look_message'] = Db::name('messages')->where('is_look',0)->count();


        //登陆次数和下载次数
        $today = date('Y-m-d');

        //取当前时间的前十四天
        $date = [];
        $date_string = '';
        for ($i=9; $i >0 ; $i--) { 
            $date[] = date("Y-m-d",strtotime("-{$i} day"));
            $date_string.= date("Y-m-d",strtotime("-{$i} day")) . ',';
        }
        $date[] = $today;
        $date_string.= $today;
        $web['date_string'] = $date_string;

        $login_sum = '';
        foreach ($date as $k => $val) {
            $min_time = strtotime($val);
            $max_time = $min_time + 60*60*24;
            $where['create_time'] = [['>=',$min_time],['<=',$max_time]];
            $login_sum.= Db::name('admin_log')->where(['admin_menu_id'=>50])->where($where)->count() . ',';
        }
        $web['login_sum'] = $login_sum;

        $admin_uid = Session::get('admin');
        $admin_uinfo = Db::view('admin','nickname,login_time,login_ip')
            ->view('admin_cate','name as cate_name','admin_cate.id=admin.admin_cate_id','LEFT')
            ->where('admin.id',$admin_uid)
            ->find();
        #dump($admin_uinfo);die;

        $this->assign('admin_uinfo',$admin_uinfo);
        $this->assign('web',$web);

        return $this->fetch();
    }
}
