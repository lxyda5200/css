<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/17
 * Time: 14:57
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\GoodsOrder as goodsOrderModel;
class GoodsOrder extends Admin
{
    public function index()
    {
        $model = new goodsOrderModel();

        $param = $this->request->param();

        if (!empty($param['order_status'])) {
            $where['order_status'] = ['in', $param['order_status']];
        }


        $lists = $model->order(['create_time'=>'desc'])->where($where)->paginate(15,false);

        foreach ($lists as $k=>$v){
            $v->shop_name = Db::name('shop_info')->where('id',$v->shop_id)->value('shop_name');
            $v->user = Db::name('user')->where('user_id',$v->user_id)->find();
        }

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
                $data = Db::view('goods_order')
                    ->view('user','nickname,mobile','user.user_id = goods_order.user_id','left')
                    ->view('sale','nickname as sale_nickname,mobile as sale_mobile','sale.sale_id = goods_order.sale_id','left')
                    ->view('shop_info','shop_name','shop_info.id = goods_order.shop_id','left')
                    ->where('goods_order.id',$id)
                    ->find();


                $goods_info = Db::view('goods_order_detail')
                    ->view('goods','goods_name','goods.id = goods_order_detail.goods_id','left')
                    ->where('goods_order_detail.order_id',$id)
                    ->select();

                if(!empty($data)) {

                    $this->assign('data',$data);
                    $this->assign('goods_info',$goods_info);
                    $this->assign('title','订单详情');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        }

    }

    public function fenpei(){
        $id = input('id');
        if ($this->request->isPost()){
            $param = $this->request->param();

            $res = Db::name('goods_order')->where('id',$id)->update(['shop_id'=>$param['shop_id']]);

            if(false == $res) {
                return $this->error('分配失败');
            } else {
                addlog($id);//写入日志
                return $this->success('分配成功','admin/goods_order/index');
            }

        }else{
            $data = Db::name('goods_order')
                ->where('id',$id)
                ->find();
            $shop = Db::name('shop_info')->select();
            $this->assign('shop',$shop);
            $this->assign('data',$data);
            return $this->fetch();
        }
    }

}