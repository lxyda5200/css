<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/2/18
 * Time: 9:53
 */

namespace app\admin\controller;

use think\Db;
class ProductOrder extends Admin
{
    public function index()
    {

        $order_status = input('order_status');
        if (!empty($order_status)){
            $where['product_order.order_status'] = ['eq',$order_status];
        }

        $store_id = input('store_id');
        if (!empty($store_id)){
            $where['product_order.store_id'] = ['eq',$store_id];
        }

        $pay_order_no = input('pay_order_no');
        if (!empty($pay_order_no)){
            $where['product_order.pay_order_no'] = ['like',"%$pay_order_no%"];
        }

        $order_no = input('order_no');
        if (!empty($order_no)){
            $where['product_order.order_no'] = ['like',"%$order_no%"];
        }

        $lists = Db::view('product_order')
            ->view('user','nickname,mobile','user.user_id = product_order.user_id','left')
            ->view('store','store_name','product_order.store_id = store.id','left')
            ->where($where)
            ->where('(product_order.store_id IN (217,98,76,580) AND pay_money >1) OR (product_order.store_id NOT IN (217,98,76,580)) ') //屏蔽订单1元以下测试数据
            ->where('product_order.user_id NOT IN (15953,13955,16904,16871,12929,17054,17027,16166,11510,16059,16908,13042,16197,16862,15361,10655,15169,13145,13828,16883)')//屏蔽测试
            ->order('create_time','desc')
            ->paginate(15,false,['query'=>$this->request->param()]);


        $sum_money = Db::name('product_order')->where($where)->sum('pay_money');

        $this->assign('sum_money',$sum_money);
        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {

            } else {
                //非提交操作
                $data = Db::view('product_order')
                    ->view('user','nickname,mobile','user.user_id = product_order.user_id','left')
                    ->view('store','store_name','store.id = product_order.store_id','left')
                    ->where('product_order.id',$id)
                    ->find();


                $goods_info = Db::name('product_order_detail')
                    ->where('order_id',$id)
                    ->select();

                $shouhou = Db::name('product_order_detail')
                    ->where('order_id',$id)
                    ->where('is_shouhou',1)
                    ->select();

                foreach ($shouhou as $k=>$v){
                    $shouhou_info = Db::name('product_shouhou')->where('order_id',$v['order_id'])->where('product_id',$v['product_id'])->where('specs_id',$v['specs_id'])->find();
                    $shouhou[$k]['description'] = $shouhou_info['description'];
                    $shouhou[$k]['refuse_description'] = $shouhou_info['refuse_description'];
                    $shouhou[$k]['return_mode'] = $shouhou_info['return_mode'];
                }

                if(!empty($data)) {

                    $this->assign('data',$data);
                    $this->assign('goods_info',$goods_info);
                    $this->assign('shouhou_goods_info',$shouhou);
                    $this->assign('title','订单详情');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        }
    }
}