<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/18
 * Time: 9:23
 */

namespace app\admin\controller;

use think\Db;
use app\common\controller\Base;
use app\admin\model\Banner as bannerModel;
use app\admin\model\UserMsg as userMsgModel;
class Share extends Admin
{
    public function index()
    {
        $model = new bannerModel();
        $lists = $model->order(['paixu'=>'asc','id'=>'asc'])->paginate(15,false);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    //小程序分享图片标题设置
    public function share_set()
    {
        $post = $this->request->post();
        $id=$post['id'];
        if($id>0){
            //修改
            $id=$post['id'];
            $share_title=$post['share_title'];
            $share_cover=$post['share_cover'];
            $description=$post['description'];
            $qrcode=$post['qrcode'];

            if(!$share_title || !$share_cover){
                return $this->error('修改失败,标题和cover必须填');
            }
            $set=[
                'share_title'=>$share_title,
                'description'=>$description,
                'qrcode'=>$qrcode,
                'share_cover'=>$share_cover
            ];
            $data = Db::name('share_set')->where('id',1)->update($set);
            if($data){
                return $this->success('修改成功','admin/share/share_set');
            }else{
                return $this->error('修改失败');
            }

        }

        //查询
        $data = Db::name('share_set')->where('id',1)->find();

        $this->assign('data',$data);
        return $this->fetch();

    }
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new bannerModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['title', 'require', '标题不能为空'],
                    ['content', 'require', '内容不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $data = $model->where('id',$id)->find();
                if(empty($data)) {
                    return $this->error('id不正确');
                }
                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/banner/index');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑banner');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            //是新增操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['title', 'require', '标题不能为空'],
                    ['content', 'require', '内容不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/banner/index');
                }
            } else {
                //非提交操作
                $this->assign('title','新增banner');
                return $this->fetch();
            }
        }

    }

    public function is_show()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('banner')->where('id',$post['id'])->update(['is_show'=>$post['is_show']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/banner/index');
            }
        }
    }

    public function paixu()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('banner')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('banner')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/banner/index');
        }
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('banner')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/banner/index');
            }
        }
    }

    /*
     * 提成设置
     * */
    public function ticheng(){
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if(false == Db::name('ticheng_config')->strict(false)->where('id',$id)->update($post)) {
                return $this->error('设置失败');
            } else {
                addlog($id);//写入日志
                return $this->success('设置成功','admin/banner/ticheng');
            }
        }else{
            $data = Db::name('ticheng_config')->where('id',1)->find();
            $this->assign('data',$data);
            return $this->fetch();
        }

    }

    /*
     * 关于我们&联系客服
     * */
    public function about_us(){
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if(false == Db::name('about_us')->strict(false)->where('id',$id)->update($post)) {
                return $this->error('设置失败');
            } else {
                addlog($id);//写入日志
                return $this->success('设置成功','admin/banner/about_us');
            }
        }else{
            $data = Db::name('about_us')->where('id',1)->find();
            $this->assign('data',$data);
            return $this->fetch();
        }
    }

    /*
     * 用户协议
     * */
    public function user_protocol(){
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if(false == Db::name('about_us')->strict(false)->where('id',$id)->update($post)) {
                return $this->error('设置失败');
            } else {
                addlog($id);//写入日志
                return $this->success('设置成功','admin/banner/user_protocol');
            }
        }else{
            $data = Db::name('about_us')->where('id',1)->find();
            $this->assign('data',$data);
            return $this->fetch();
        }
    }

    /*
     * 消息列表
     * */
    public function msg(){
        $model = new userMsgModel();
        $lists = $model->where('type',1)->order(['create_time'=>'desc','id'=>'asc'])->paginate(15,false);;
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
     * 发布消息
     * */
    public function msg_publish(){
        if ($this->request->post()){

            $model = new userMsgModel();

            $post = $this->request->post();
            //验证  唯一规则： 表名，字段名，排除主键值，主键名
            $validate = new \think\Validate([
                ['title', 'require', '标题不能为空'],
                ['content', 'require', '内容不能为空'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($post)) {
                $this->error('提交失败：' . $validate->getError());
            }

            $result = $model->allowField(true)->save($post);

            $user_data = Db::name('user')->field('user_id')->select();

            foreach ($user_data as $k=>$v){
                $user_data[$k]['msg_id'] = $model->id;
            }
            Db::name('user_msg_link')->insertAll($user_data);

            //todo 推送所有用户
            if(false == $result) {
                return $this->error('添加失败');
            } else {
                addlog($model->id);//写入日志
                return $this->success('添加成功','admin/banner/msg');
            }

        }else{
            return $this->fetch();
        }
    }

    /*
     * 删除消息
     * */
    public function delete_msg(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            $result1 = Db::name('user_msg')->where('id',$id)->delete();
            $result2 = Db::name('user_msg_link')->where('msg_id',$id)->delete();
            if(false == $result1 || false == $result2) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/banner/msg');
            }
        }
    }

}
