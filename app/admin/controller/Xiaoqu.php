<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/10/11
 * Time: 14:39
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\HouseXiaoqu as houseXiaoquModel;
use app\admin\model\Area as areaModel;
class Xiaoqu extends Admin
{
    public function index()
    {
        $param = $this->request->param();

        if (!empty($param['city_id'])) {
            $where['city_id'] = ['eq', $param['city_id']];
            $area1 = Db::name('area')->where('city_id',$param['city_id'])->where('pid',0)->field('id,area_name1')->select();
            $this->assign('area1',$area1);
        }

        if (!empty($param['area_id1'])) {
            $where['area_id1'] = ['eq', $param['area_id1']];
            $area2 = Db::name('area')->where('city_id',$param['city_id'])->where('pid',$param['area_id1'])->field('id,area_name2')->select();
            $this->assign('area2',$area2);
        }

        if (!empty($param['area_id2'])) {
            $where['area_id2'] = ['eq', $param['area_id2']];
        }

        if (!empty($param['keywords'])) {
            $where['xiaoqu_name|address'] = ['like', "%{$param['keywords']}%"];
        }

        if (!empty($param['shop_id'])) {
            $where['shop_id'] = ['eq', $param['shop_id']];
        }

        $model = new houseXiaoquModel();
        $list = $model->where($where)->order(['id'=>'asc'])->paginate(15,false,$this->request->param());

        foreach ($list as $k=>$v){
            $v->xiaoqu_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->xiaoqu_name);
            $v->address = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->address);
            $v->city = Db::name('city')->where('id',$v->city_id)->value('city_name');
            $v->area_name1 = Db::name('area')->where('id',$v->area_id1)->value('area_name1');
            $v->area_name2 = Db::name('area')->where('id',$v->area_id2)->value('area_name2');
            $v->shop_name = Db::name('shop_info')->where('id',$v->shop_id)->value('shop_name');
        }

        $citys = Db::name('city')->select();
        $this->assign('citys',$citys);
        $shop = Db::name('shop_info')->select();
        $this->assign('shop',$shop);
        $this->assign('list',$list);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseXiaoquModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['city_id', 'require', '城市不能为空'],
                    ['area_id1', 'require', '一级区域不能为空'],
                    ['area_id2', 'require', '二级区域不能为空'],
                    ['xiaoqu_name', 'require', '小区名称不能为空'],
                    ['address', 'require', '详细地址不能为空'],
                    ['shop_id', 'require', '归属门店不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $info = $model->where('id',$id)->find();
                if(empty($info)) {
                    return $this->error('id不正确');
                }

                $city_name = Db::name('city')->where('id',$info['city_id'])->value('city_name');
                $area_name1 = Db::name('area')->where('id',$info['area_id1'])->value('area_name1');

                $l_arr = addresstolatlng($city_name.$area_name1.$post['xiaoqu_name'].$post['address']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];

                //关联修改
                if ($post['city_id'] != $info['city_id'] || $post['area_id1'] != $info['area_id1'] || $post['area_id2'] != $info['area_id2']) {
                    Db::name('house')->where('xiaoqu_id',$id)->update([
                        'city_id' => $post['city_id'],
                        'area_id1' => $post['area_id1'],
                        'area_id2' => $post['area_id2'],
                        'xiaoqu_name' => $post['xiaoqu_name'],
                        'address' => $post['address']
                    ]);

                    Db::name('house_short')->where('xiaoqu_id',$id)->update([
                        'city_id' => $post['city_id'],
                        'area_id1' => $post['area_id1'],
                        'area_id2' => $post['area_id2'],
                        'xiaoqu_name' => $post['xiaoqu_name'],
                        'address' => $post['address']
                    ]);
                }

                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/xiaoqu/index');
                }
            } else {
                //非提交操作
                $data = Db::name('house_xiaoqu')
                    ->where('id',$id)
                    ->find();

                $citys = Db::name('city')->select();
                $this->assign('citys',$citys);

                $area1 = Db::name('area')->where('city_id',$data['city_id'])->where('pid',0)->select();
                $this->assign('area1',$area1);

                $area2 = Db::name('area')->where('pid',$data['area_id1'])->select();
                $this->assign('area2',$area2);

                $shop = Db::name('shop_info')->select();
                $this->assign('shop',$shop);

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑小区');
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
                    ['city_id', 'require', '城市不能为空'],
                    ['area_id1', 'require', '一级区域不能为空'],
                    ['area_id2', 'require', '二级区域不能为空'],
                    ['xiaoqu_name', 'require', '小区名称不能为空'],
                    ['address', 'require', '详细地址不能为空'],
                    ['shop_id', 'require', '归属门店不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }

                $city_name = Db::name('city')->where('id',$post['city_id'])->value('city_name');
                $area_name1 = Db::name('area')->where('id',$post['area_id1'])->value('area_name1');

                $l_arr = addresstolatlng($city_name.$area_name1.$post['address']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/xiaoqu/index');
                }
            } else {
                //非提交操作

                $citys = Db::name('city')->select();
                $shop = Db::name('shop_info')->select();
                $this->assign('shop',$shop);
                $this->assign('citys',$citys);
                $this->assign('title','新增小区');
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

    public function get_area1(){

        $city_id = $this->request->has('id') ? $this->request->param('id', null, 'intval') : null;

        $model = new areaModel();
        $data = $model->where('city_id',$city_id)->where('pid',0)->select();

        return json($data);
    }

    public function get_area2(){

        $id = $this->request->has('id') ? $this->request->param('id', null, 'intval') : null;

        $model = new areaModel();
        $data = $model->where('pid',$id)->select();

        return json($data);
    }

    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if (Db::name('house')->where('xiaoqu_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }
            if (Db::name('house_short')->where('xiaoqu_id',$id)->count()){
                return $this->error('删除失败,此记录被关联引用');
            }

            if(false == Db::name('house_xiaoqu')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/xiaoqu/index');
            }
        }
    }
}