<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/25
 * Time: 16:52
 */

namespace app\admin\controller;

use think\Db;
use think\Session;
use app\admin\model\Area as areaModel;
use app\admin\model\City as cityModel;
class Area extends Admin
{

    public function index()
    {
        $model = new areaModel();

        $post = $this->request->param();


        if (!empty($post['city_id'])) {
            $where['city_id'] = ['eq', $post['city_id']];
        }

        $where['pid'] = ['eq', 0];

        $areas = $model->where($where)->order(['paixu'=>'asc','id'=>'asc'])->paginate(15,false,['query'=>$this->request->param()]);


        foreach ($areas as $k=>$v){
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
        }

        $citys = Db::name('city')->select();
        $this->assign('citys',$citys);
        $this->assign('areas',$areas);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new areaModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['area_name1', 'require', '区域名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $area = $model->where('id',$id)->find();
                if(empty($area)) {
                    return $this->error('id不正确');
                }

                $city_name = Db::name('city')->where('id',$post['city_id'])->value('city_name');

                $l_arr = addresstolatlng($city_name.$post['area_name1']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];


                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/area/index');
                }
            } else {
                //非提交操作
                $areas = Db::view('area')
                    ->view('city','city_name','city.id = area.city_id','left')
                    ->where('area.id',$id)
                    ->find();

                $citys = Db::name('city')->select();

                $this->assign('citys',$citys);
                if(!empty($areas)) {
                    $this->assign('areas',$areas);
                    $this->assign('title','编辑一级区域');
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
                    ['city_id', 'require', '所属城市不能为空'],
                    ['area_name1', 'require', '区域名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }

                $city_name = Db::name('city')->where('id',$post['city_id'])->value('city_name');

                $l_arr = addresstolatlng($city_name.$post['area_name1']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/area/index');
                }
            } else {
                //非提交操作
                $pid = $this->request->has('pid') ? $this->request->param('pid', null, 'intval') : null;
                if(!empty($pid)) {
                    $this->assign('pid',$pid);
                }
                $citys = Db::name('city')->select();
                $this->assign('citys',$citys);
                $this->assign('title','新增一级区域');
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
                $order = Db::name('area')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('area')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/area/index');
        }
    }


    public function index2()
    {
       # $model = new areaModel();

        $post = $this->request->param();

        if (!empty($post['city_id'])) {
            $where['a1.city_id'] = ['eq', $post['city_id']];
            $area1 = Db::name('area')->where('city_id',$post['city_id'])->where('pid',0)->field('id,area_name1')->select();
            $this->assign('area1',$area1);
        }

        if (!empty($post['pid'])) {
            $where['a1.pid'] = ['eq', $post['pid']];
        }

        $areas = Db::view('area a1','id,city_id,area_name2,lng,lat,pid,paixu')
            ->view('area a2','area_name1','a2.id = a1.pid','left')
            ->view('city c','city_name','c.id = a1.city_id','left')
            ->where($where)
            ->where('a1.pid','neq',0)
            ->order(['paixu'=>'asc','id'=>'asc'])
            ->paginate(15,false,['query'=>$this->request->param()]);

        $citys = Db::name('city')->select();
        $this->assign('citys',$citys);
        $this->assign('areas',$areas);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    public function publish2()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new areaModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['area_name2', 'require', '区域名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $area = $model->where('id',$id)->find();
                if(empty($area)) {
                    return $this->error('id不正确');
                }

                $city_name = Db::name('city')->where('id',$post['city_id'])->value('city_name');

                $l_arr = addresstolatlng($city_name.$post['area_name2']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];


                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/area/index2');
                }
            } else {
                //非提交操作
                $area = Db::view('area a1','id,area_name2,pid,city_id')
                    ->view('city','city_name','city.id = area.city_id','left')
                    ->view('area a2','area_name1','a2.id = a1.pid','left')
                    ->where('area.id',$id)
                    ->find();


                $citys = Db::name('city')->select();
                $areas1 = Db::name('area')->where('pid',0)->where('city_id',$area['city_id'])->select();
                $this->assign('areas1',$areas1);
                $this->assign('citys',$citys);
                if(!empty($area)) {
                    $this->assign('area',$area);
                    $this->assign('title','编辑二级区域');
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
                    ['city_id', 'require', '所属城市不能为空'],
                    ['pid', 'require', '上级区域不能为空'],
                    ['area_name2', 'require', '区域名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }

                $city_name = Db::name('city')->where('id',$post['city_id'])->value('city_name');

                $l_arr = addresstolatlng($city_name.$post['area_name2']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/area/index2');
                }
            } else {
                //非提交操作
                $pid = $this->request->has('pid') ? $this->request->param('pid', null, 'intval') : null;
                if(!empty($pid)) {
                    $this->assign('pid',$pid);
                }
                $citys = Db::name('city')->select();
                $areas = $model->select();
                $this->assign('citys',$citys);
                $this->assign('areas',$areas);
                $this->assign('title','新增二级区域');
                return $this->fetch();
            }
        }

    }

    public function paixu2()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('area')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('area')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/area/index');
        }
    }

    public function get_area(){

        $city_id = $this->request->has('id') ? $this->request->param('id', null, 'intval') : null;

        $model = new areaModel();
        $data = $model->where('city_id',$city_id)->where('pid',0)->select();

        return json($data);
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('area')->where('pid',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if (Db::name('house_xiaoqu')->where('area_id1',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('area')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/area/index');
            }
        }
    }

    public function delete2()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('house_xiaoqu')->where('area_id2',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('area')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/area/index2');
            }
        }
    }
}