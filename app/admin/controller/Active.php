<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/4/4
 * Time: 9:44
 */

namespace app\admin\controller;


use think\Db;

class Active extends Admin
{

    /**
     * 用户活跃
     */
    public function active(){

        $post = $this->request->post();

        if (!empty($post['active_date'])) {
            $where1['active_date'] = ['like', "{$post['active_date']}%"];
            $post['active_date'] = mb_substr($post['active_date'],0,7);
            $where2['active_date'] = ['like', "{$post['active_date']}%"];
        }else{
            $date = date('Y-m-d');

            $date2 = date('Y-m');
            $where1['active_date'] = ['eq',$date];
            $where2['active_date'] = ['like',"{$date2}%"];
        }



        $data['day_active'] = Db::name('active_count')->where($where1)->sum('active_number');

        $data['month_active'] = Db::name('active_count')->where($where2)->sum('active_number');

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }
}