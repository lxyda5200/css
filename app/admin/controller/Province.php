<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/29
 * Time: 14:18
 */

namespace app\admin\controller;

use think\Db;
use think\Session;
use app\admin\model\Province as provinceModel;
class Province extends Admin
{

    public function index()
    {
        $model = new provinceModel();
        $lists = $model->order(['paixu'=>'asc','id'=>'asc'])->paginate(15,false);;
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new provinceModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['province_name', 'require', '省份名称不能为空']
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
                    return $this->success('修改成功','admin/province/index');
                }
            } else {
                //非提交操作
                $province = $model->where('id',$id)->find();

                if(!empty($province)) {
                    $this->assign('province',$province);
                    $this->assign('title','编辑省份');
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
                    ['province_name', 'require', '省份名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/province/index');
                }
            } else {
                //非提交操作
                $this->assign('title','新增省份');
                return $this->fetch();
            }
        }

    }

    public function paixu()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('province')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('province')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/province/index');
        }
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('city')->where('province_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('province')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/province/index');
            }
        }
    }
}