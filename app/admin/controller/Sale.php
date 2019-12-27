<?php

namespace app\admin\controller;

use \app\admin\model\Sale as saleModel;
use think\Db;
use think\Session;

class Sale extends Permissions
{
    /*
     * 普通用户列表
     * */
    public function index(){
        $model = new saleModel();
        $post = $this->request->param();

        if (!empty($post['shop_id'])) {
            $where['shop_id'] = ['eq', $post['shop_id']];
        }
        if (!empty($post['keywords'])) {
            $where['nickname'] = ['like', '%' . $post['keywords'] . '%'];
        }
        if (isset($post['sale_status']) and $post['sale_status'] != '') {
            $where['sale_status'] = ['eq',$post['sale_status']];
        }
        /*if (!empty($post['create_time'])) {
            $where["FROM_UNIXTIME(create_time,'%Y-%m-%d')"] = ['eq',$post['create_time']];
        }*/


        $sales = $model->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);

        foreach ($sales as $k=>$v){
            $v->shop_name = Db::name('shop_info')->where('id',$v['shop_id'])->value('shop_name');
        }
        $shop = Db::name('shop_info')->select();

        $this->assign('shop',$shop);

        $this->assign('sales',$sales);
        $this->assign('param',$post);
        return $this->fetch();
    }

    /*
     * 编辑
     * */
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new saleModel();
        #$cateModel = new cateModel();
        //是正常添加操作


        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['nickname', 'require', '昵称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $data = $model->where('sale_id',$id)->find();
                if(empty($data)) {
                    return $this->error('id不正确');
                }



                if(false == $model->allowField(true)->save($post,['sale_id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('修改成功','admin/sale/index');
                }
            } else {
                //非提交操作
                $sale = $model->where('sale_id',$id)->find();
                $shop = Db::name('shop_info')->select();
                $this->assign('shop',$shop);
                if(!empty($sale)) {
                    $this->assign('sale',$sale);
                    $this->assign('title','编辑店员');
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
                    ['nickname', 'require', '昵称不能为空'],
                    ['mobile', 'require', '手机号不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }

                $sale = $model->where('mobile',$post['mobile'])->find();
                if ($sale){
                    return $this->error('该手机号已注册');
                }

                $post['password'] = password_hash(88888888, PASSWORD_DEFAULT);
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->sale_id);//写入日志
                    return $this->success('添加成功','admin/sale/index');
                }
            } else {
                //非提交操作
                $shop_id = input('shop_id');
                $shop = Db::name('shop_info')->select();
                $this->assign('shop',$shop);
                $this->assign('shop_id',$shop_id);
                $this->assign('title','新增店员');
                return $this->fetch();
            }
        }

    }

    /*
     * 启用/禁用
     * */
    public function sale_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $sale_status = $post['sale_status'];
                if(false == Db::name('sale')->where('sale_id',$id)->update(['sale_status'=>$sale_status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/sale/index');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
}