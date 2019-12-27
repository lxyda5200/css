<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/25
 * Time: 16:52
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\City as cityModel;
class City extends Admin
{
    public function index()
    {
        $model = new cityModel();
        $citys = $model->order(['paixu'=>'asc','id'=>'asc'])->paginate(15,false);

        foreach ($citys as $k=>$v){
            $v->province_name = Db::name('province')->where('id',$v->province_id)->value('province_name');
        }

        $this->assign('citys',$citys);
        return $this->fetch();
    }

    public function is_hot()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('city')->where('id',$post['id'])->update(['is_hot'=>$post['is_hot']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/city/index');
            }
        }
    }

    public function is_show()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('city')->where('id',$post['id'])->update(['is_show'=>$post['is_show']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/city/index');
            }
        }
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new cityModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['city_name', 'require', '城市名称不能为空']
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
                    return $this->success('修改成功','admin/city/index');
                }
            } else {
                //非提交操作
                $citys = $model->where('id',$id)->find();

                $province = Db::name('province')->select();

                $this->assign('province',$province);

                if(!empty($citys)) {
                    $this->assign('citys',$citys);
                    $this->assign('title','编辑城市');
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
                    ['city_name', 'require', '城市名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/city/index');
                }
            } else {
                //非提交操作

                $province = Db::name('province')->select();

                $this->assign('province',$province);
                $this->assign('title','新增城市');
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
                $order = Db::name('city')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('city')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/city/index');
        }
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('area')->where('city_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }
            if (Db::name('house_xiaoqu')->where('city_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }
            if (Db::name('subway_lines')->where('city_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('city')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/city/index');
            }
        }
    }
}