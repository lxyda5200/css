<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/2/15
 * Time: 16:29
 */

namespace app\admin\controller;

use think\Db;
class Version extends Admin
{

    public function publish()
    {
        if ($this->request->isPost()){
            $ios = input('ios');
            $android = input('android');
            $is_qiangzhi = input('is_qiangzhi');

            $res1 = Db::name('version')->where('system','ios')->update(['version_number'=>$ios,'is_qiangzhi'=>$is_qiangzhi]);
            $res2 = Db::name('version')->where('system','android')->setField(['version_number'=>$android,'is_qiangzhi'=>$is_qiangzhi]);

            if ($res1 !== false || $res2 !== false){
                return $this->success('修改成功');
            }else{
                return $this->error('修改失败');
            }



        }else{
            $lists = Db::name('version')->select();
            $data['ios'] = $lists[0]['version_number'];
            $data['android'] = $lists[1]['version_number'];
            $data['is_qiangzhi'] = $lists[0]['is_qiangzhi'];
            $this->assign('data',$data);
            return $this->fetch();
        }
    }
}