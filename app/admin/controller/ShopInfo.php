<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/18
 * Time: 9:21
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\ShopInfo as shopInfoModel;
use app\admin\model\Sale as saleModel;
class ShopInfo extends Admin
{

    public function index()
    {
        $model = new shopInfoModel();

        $list = $model->order('id','desc')->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
        }

        $this->assign('list',$list);
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new shopInfoModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['shop_name', 'require', '店名不能为空'],
                    ['address', 'require', '地址不能为空'],
                    ['shopkeeper', 'require', '店主不能为空'],
                    ['mobile', 'require', '手机号不能为空'],
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

                $l_arr = addresstolatlng($post['address']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];


                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/shop_info/index');
                }
            } else {

                $citys = Db::name('city')->select();
                $this->assign('citys',$citys);
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
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
                    ['city_id', 'require', '城市不能为空'],
                    ['shop_name', 'require', '店名不能为空'],
                    ['address', 'require', '地址不能为空'],
                    ['shopkeeper', 'require', '店主不能为空'],
                    ['mobile', 'require', '手机号不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                $l_arr = addresstolatlng($post['address']);

                $post['lng'] = $l_arr[0];
                $post['lat'] = $l_arr[1];

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/shop_info/index');
                }
            } else {
                //非提交操作
                $citys = Db::name('city')->select();
                $this->assign('citys',$citys);

                $this->assign('title','新增门店');
                return $this->fetch();
            }
        }

    }

    public function sale_list(){
        $param = $this->request->param();

        if (!empty($param['shop_id'])) {
            $where['shop_id'] = ['eq', $param['shop_id']];
        }
        if (isset($param['sale_status']) and $param['sale_status'] != '') {
            $where['sale_status'] = ['eq', $param['sale_status']];
        }

        $model = new shopInfoModel();
        $saleModel = new saleModel();
        $list = $saleModel->where($where)->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->shop_name = $model->where('id',$v['shop_id'])->value('shop_name');
        }


        $shop = $model->select();

        $this->assign('shop',$shop);
        $this->assign('list',$list);
        $this->assign('param',$this->request->param());
        return $this->fetch();
    }
}