<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/29
 * Time: 17:32
 */

namespace app\admin\controller;

use think\Db;
use think\Session;
use app\admin\model\SubwayLines as subwayLinesModel;
class SubwayLines extends Admin
{

    public function index()
    {
        $model = new subwayLinesModel();

        $post = $this->request->post();

        if (!empty($post['city_id'])) {
            $where['city_id'] = ['eq', $post['city_id']];
        }

        $lines = $model->where($where)->order(['paixu'=>'asc','id'=>'asc'])->paginate(15,false,['query'=>$this->request->param()]);

        foreach ($lines as $k=>$v){
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
        }

        #dump($lines);
        $citys = Db::name('city')->select();
        $this->assign('citys',$citys);
        $this->assign('lines',$lines);
        $this->assign('param',$this->request->post());

        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new subwayLinesModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['lines_name', 'require', '地铁线路不能为空'],
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
                    return $this->success('修改成功','admin/subway_lines/index');
                }
            } else {
                //非提交操作
                $line = Db::view('subway_lines')
                    ->view('city','city_name','city.id = subway_lines.city_id','left')
                    ->where('subway_lines.id',$id)
                    ->find();

                $citys = Db::name('city')->select();

                $this->assign('citys',$citys);
                if(!empty($line)) {
                    $this->assign('line',$line);
                    $this->assign('title','编辑地铁线路');
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
                    ['lines_name', 'require', '地铁线路不能为空'],
                ]);



                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/subway_lines/index');
                }
            } else {
                //非提交操作
                $citys = Db::name('city')->select();
                $this->assign('citys',$citys);
                $this->assign('title','新增地铁线路');
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
                $order = Db::name('subway_lines')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('subway_lines')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/subway_lines/index');
        }
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('subway_station')->where('lines_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }
            if (Db::name('house')->where('lines_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }
            if (Db::name('house_short')->where('lines_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('subway_lines')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/subway_lines/index');
            }
        }
    }
}