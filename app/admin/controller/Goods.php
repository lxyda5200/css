<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/16
 * Time: 18:08
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\Goods as goodsModel;
use app\admin\model\GoodsClass as goodsClassModel;
class Goods extends Admin
{

    public function index()
    {
        $model = new goodsModel();

        $param = $this->request->param();

        if (!empty($param['class_id'])) {
            $where['class_id'] = ['eq', $param['class_id']];
        }


        $lists = $model->where('is_delete',0)->order(['create_time'=>'desc'])->where($where)->paginate(15,false);



        foreach ($lists as $k=>$v){
            $v->class_name = Db::name('goods_class')->where('id',$v->class_id)->value('class_name');
            $v->goods_img = Db::name('goods_img')->where('goods_id',$v->id)->find();
        }

        $goodsClassModel = new goodsClassModel();
        $goods_class = $goodsClassModel->select();

        $this->assign('goods_class',$goods_class);
        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new goodsModel();
        $goodsClassModel = new goodsClassModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['class_id', 'require', '商品分类不能为空'],
                    ['goods_name', 'require', '商品名称不能为空'],
                    ['spec', 'require', '规格不能为空'],
                    ['unit', 'require', '计量单位不能为空'],
                    ['price', 'require', '价格不能为空'],
                    ['number', 'require', '库存不能为空'],
                    ['description', 'require', '描述不能为空'],
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
                if($model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {


                    Db::name('goods_img')->where('goods_id',$id)->delete();

                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['goods_id'] = $id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('goods_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/goods/index');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                $goods_class = $goodsClassModel->select();
                $this->assign('goods_class',$goods_class);

                $goods_img = Db::name('goods_img')->where('goods_id',$data->id)->select();
                $this->assign('goods_img',$goods_img);

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑商品');
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
                    ['class_id', 'require', '商品分类不能为空'],
                    ['goods_name', 'require', '商品名称不能为空'],
                    ['spec', 'require', '规格不能为空'],
                    ['unit', 'require', '计量单位不能为空'],
                    ['price', 'require', '价格不能为空'],
                    ['number', 'require', '库存不能为空'],
                    ['description', 'require', '描述不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {

                    return $this->error('添加失败');
                } else {

                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['goods_id'] = $model->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('goods_img')->insertAll($img_data);

                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/goods/index');
                }
            } else {
                $goods_class = $goodsClassModel->select();

                $this->assign('goods_class',$goods_class);
                //非提交操作
                $this->assign('title','新增商品');
                return $this->fetch();
            }
        }

    }

    /*
     * 上架/下架
     * */
    public function status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('goods')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/goods/index');
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


            if(false == Db::name('goods')->where('id',$id)->setField('is_delete',1)) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/goods/index');
            }
        }
    }
}