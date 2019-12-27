<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/17
 * Time: 17:30
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\HouseTag as houseTagModel;
class HouseTag extends Admin
{
    public function index()
    {
        $model = new houseTagModel();

        $param = $this->request->param();

        if (!empty($param['type'])) {
            $where['type'] = ['eq', $param['type']];
        }


        $lists = $model->order(['id'=>'desc'])->where($where)->paginate(15,false);


        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function publish()
    {

        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $type = $this->request->param('type',0,'intval');
        $model = new houseTagModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['tag_name', 'require', '标签名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $city = $model->where('id',$id)->find();
                if(empty($city)) {
                    return $this->error('id不正确');
                }
                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功',url('admin/house_tag/index',['type'=>$post['type']]));
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                $this->assign('type',$type);
                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑标签');
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
                    ['tag_name', 'require', '标签名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功',url('admin/house_tag/index',['type'=>$post['type']]));
                }
            } else {
                //非提交操作
                $this->assign('type',$type);
                $this->assign('title','新增标签');
                return $this->fetch();
            }
        }

    }

    public function status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->param();
                $status = $post['status'];
                if(false == Db::name('house_tag')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功',url('admin/house_tag/index',['type'=>$post['type']]));
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }


    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            $type = $this->request->has('type') ? $this->request->param('type', 0, 'intval') : 0;

            if(false == Db::name('house_tag')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                if ($type == 1){
                    return $this->success('删除成功',url('admin/house_tag/index',array('type'=>1)));
                }else{
                    return $this->success('删除成功',url('admin/house_tag/index',array('type'=>2)));
                }

            }
        }
    }
}