<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/22
 * Time: 15:48
 */

namespace app\admin\controller;

use app\admin\model\HouseEntrust;
use \app\admin\model\Sale as saleModel;
use think\Db;
use think\Session;
use \app\admin\model\HouseShort as houseShortModel;
use \app\admin\model\House as houseModel;
class MyShop extends Admin
{
    /*
         * 店员列表
         * */
    public function index(){
        $shop_id = Session::get('shop_id');

        $model = new saleModel();
        $post = $this->request->param();

        $where['shop_id'] = ['eq', $shop_id];
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
     * 新增/编辑店员
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
                    return $this->success('修改成功','admin/my_shop/index');
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
                    return $this->success('添加成功','admin/my_shop/index');
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
     * 查看业绩
     * */
    public function see_achievement(){

        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

        $data['sf_ticheng'] = Db::name('house')->where('add_sale_id',$id)->where('status',3)->count();
        $data['sh_ticheng'] = Db::name('goods_order')->where('order_status','in','4,5')->where('sale_id',$id)->count();
        $data['long_ticheng'] = Db::name('long_order')->where('status',2)->where('renting_status','in','1,2')->where('sale_id',$id)->count();
        $data['short_ticheng'] = Db::name('short_order')->where('status','in','4,5')->where('sale_id',$id)->count();

        $this->assign('data',$data);
        $this->assign('title','业绩详情');
        return $this->fetch();
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
                    return $this->success('设置成功','admin/my_shop/index');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 订单列表-商城
     * */
    public function goods_list(){

        $shop_id = Session::get('shop_id');

        $model = new \app\admin\model\GoodsOrder();
        $param = $this->request->param();

        if (!empty($param['order_status'])) {
            $where['order_status'] = ['in', $param['order_status']];
        }

        $list = $model
            ->where('shop_id',$shop_id)
            ->where($where)
            ->order('create_time','desc')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->user_name = Db::name('user')->where('user_id',$v->user_id)->value('nickname');
            $v->sale_name = Db::name('sale')->where('sale_id',$v->sale_id)->value('nickname');
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function goods_order_detail(){
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

    /*
     * 商品订单发货
     * */
    public function fahuo(){
        $shop_id = Session::get('shop_id');

        $id = input('id');
        if ($this->request->isPost()){
            $param = $this->request->param();

            $res = Db::name('goods_order')->where('id',$id)->update(['order_status'=>3,'sale_id'=>$param['sale_id']]);

            if(false == $res) {
                return $this->error('发货失败');
            } else {
                addlog($id);//写入日志
                return $this->success('发货成功','admin/my_shop/goods_list');
            }

        }else{
            $data = Db::name('goods_order')
                ->where('id',$id)
                ->find();
            $sale = Db::name('sale')->where('shop_id',$shop_id)->select();
            $this->assign('sale',$sale);
            $this->assign('data',$data);
            return $this->fetch();
        }
    }

    /*
     * 商城统计
     * */
    public function goods_count(){
        $shop_id = Session::get('shop_id');

        $data['zxs_money'] = Db::name('goods_order')->where('shop_id',$shop_id)->where('order_status','in','2,3,4,5')->sum('pay_money');

        $data['ztk_money'] = Db::name('goods_order')->where('shop_id',$shop_id)->where('order_status',-2)->sum('pay_money');
        $this->assign('data',$data);
        return $this->fetch();
    }

    /*
     * 长租订单列表
     * */
    public function long_list(){
        $shop_id = Session::get('shop_id');

        $param = $this->request->param();

        if (!empty($param['status'])) {
            $where['long_order.status'] = ['eq',$param['status']];
        }

        $list = Db::name('long_order')
            ->join('sale','sale.sale_id = long_order.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->join('user','user.user_id = long_order.user_id','left')
            ->where('shop_info.id',$shop_id)
            ->where($where)
            ->field('long_order.*,sale.nickname as sale_name,user.nickname as user_name')
            ->order('long_order.create_time','desc')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 长租订单详情
     * */
    public function long_order_detail(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {

            } else {
                //非提交操作
                $data = Db::view('long_order')
                    ->view('user','nickname,mobile','user.user_id = long_order.user_id','left')
                    ->view('shop_info','shop_name','shop_info.id = long_order.shop_id','left')
                    ->view('property','nickname as property_name','property.property_id = long_order.property_id','left')
                    ->view('house','title,rent','house.id = long_order.house_id','left')
                    ->view('sale','nickname as sale_name','sale.sale_id = long_order.sale_id','left')
                    ->where('long_order.id',$id)
                    ->find();

                $house_img = Db::name('house_img')->where('house_id',$data['house_id'])->select();
                $this->assign('house_img',$house_img);

                $rent_info = Db::name('long_rent_record')->where('order_id',$id)->select();
                $this->assign('rent_info',$rent_info);

                $sum_money = Db::name('long_rent_record')->where('order_id',$id)->sum('money');
                $this->assign('sum_money',$sum_money);

                if(!empty($data)) {

                    $this->assign('data',$data);

                    $this->assign('title','订单详情');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        }
    }

    /*
     * 长租统计
     * */
    public function long_count(){

        $shop_id = Session::get('shop_id');

        //总收房款
        $data['zsfk'] = Db::name('long_order')
            ->join('long_rent_record','long_rent_record.order_id = long_order.id','left')
            ->where('long_order.shop_id',$shop_id)
            ->sum('long_rent_record.money');

        //总收押金
        $data['zsyj'] = Db::name('long_order')
            ->where('shop_id',$shop_id)
            ->sum('deposit_money');

        //退还租金
        $data['thzj'] = Db::name('long_order')
            ->where('shop_id',$shop_id)
            ->sum('refund_rent');

        //退还押金
        $data['thyj'] = Db::name('long_order')
            ->where('shop_id',$shop_id)
            ->sum('refund_deposit');

        $this->assign('data',$data);
        return $this->fetch();
    }

    /*
     *
     * 短租订单列表*/
    public function short_list(){
        $shop_id = Session::get('shop_id');
        $param = $this->request->param();

        $list = Db::name('short_order')
            ->join('sale','sale.sale_id = short_order.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->join('user','user.user_id = short_order.user_id','left')
            ->where('shop_info.id',$shop_id)
            ->field('short_order.*,sale.nickname as sale_name,user.nickname as user_name')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 短租订单详情
     * */
    public function short_order_detail(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {

            } else {
                //非提交操作
                $data = Db::view('short_order')
                    ->view('user','nickname,mobile','user.user_id = short_order.user_id','left')
                    ->view('shop_info','shop_name','shop_info.id = short_order.shop_id','left')
                    ->view('house_short','title,rent','house_short.id = short_order.short_id','left')
                    ->view('sale','nickname as sale_name','sale.sale_id = short_order.sale_id','left')
                    ->where('short_order.id',$id)
                    ->find();

                $house_img = Db::name('house_short_img')->where('short_id',$data['short_id'])->select();
                $this->assign('house_img',$house_img);

                $occupant_info = Db::name('short_occupant')->where('id','in',$data['occupant_id'])->select();
                $this->assign('occupant_info',$occupant_info);

                /*$sum_money = Db::name('long_rent_record')->where('order_id',$id)->sum('money');
                $this->assign('sum_money',$sum_money);*/

                if(!empty($data)) {

                    $this->assign('data',$data);
                    $this->assign('title','订单详情');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        }
    }

    /*
     * 短租订单统计
     * */
    public function short_count(){
        $shop_id = Session::get('shop_id');

        //总收房款
        $data['zsfk'] = Db::name('short_order')
            ->where('status','in','2,3,4,5')
            ->where('shop_id',$shop_id)
            ->sum('pay_money');

        //总收押金
        $data['zsyj'] = Db::name('short_order')
            ->where('status','in','2,3,4,5')
            ->where('shop_id',$shop_id)
            ->sum('deposit_money');

        //退还租金
        $data['thzj'] = Db::name('short_order')
            ->where('status','-2')
            ->where('shop_id',$shop_id)
            ->sum('pay_money');

        //退还押金
        $data['thyj'] = Db::name('short_order')
            ->where('status','-2')
            ->where('shop_id',$shop_id)
            ->sum('refund_deposit');

        $this->assign('data',$data);
        return $this->fetch();
    }


    /*
     * 长租房列表
     * */
    public function house_list(){
        $shop_id = Session::get('shop_id');
        $param = $this->request->param();

        $list = Db::name('house')
            ->join('sale','sale.sale_id = house.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->where('shop_info.id',$shop_id)
            ->where('house.is_delete',0)
            ->where('house.status','in','3,5')
            ->field('house.*,sale.nickname as sale_name')
            ->order('create_time','desc')
            ->paginate(15,false);


        $page = $list->render();
        $list = $list->all();
        foreach ($list as $k=>$v){
            $list[$k]['house_img'] = Db::name('house_img')->where('house_id',$v['id'])->find();
        }

        $this->assign('page',$page);
        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 长租房上架/下架 审核
     * */
    public function long_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('house')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    if ($status == 4) {
                        return $this->success('操作成功','admin/my_shop/house_list');
                    }else{
                        return $this->success('操作成功','admin/my_shop/house_list');
                    }

                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 长租房编辑
     * */
    public function house_long_publish(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([

                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $short = $model->where('id',$id)->find();
                if(empty($short)) {
                    return $this->error('id不正确');
                }

                $xiaoqu = Db::name('house_xiaoqu')->where('id',$post['xiaoqu_id'])->find();
                $post['city_id'] = $xiaoqu['city_id'];
                $post['area_id1'] = $xiaoqu['area_id1'];
                $post['area_id2'] = $xiaoqu['area_id2'];
                $post['xiaoqu_name'] = $xiaoqu['xiaoqu_name'];
                $post['address'] = $xiaoqu['address'];
                if ($post['is_subway']==0){
                    $post['lines_id'] = 0;
                    $post['station_id'] = 0;

                }
                $post['tag_id'] = implode(',',$post['tag_id']);
                $post['room_config_id'] = implode(',',$post['room_config_id']);

                if(false === $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {

                    Db::name('house_img')->where('house_id',$id)->delete();
                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['house_id'] = $model->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('house_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/my_shop/house_list');
                }
            } else {
                //非提交操作
                $data = Db::view('house')
                    ->where('id',$id)
                    ->find();

                $house_img = Db::name('house_img')->where('house_id',$id)->select();
                $this->assign('house_img',$house_img);

                $house_type = Db::name('house_type')->where('type',1)->select();
                $this->assign('house_type',$house_type);

                $house_tag = Db::name('house_tag')->where('type',1)->select();
                $this->assign('house_tag',$house_tag);

                $house_room_config = Db::name('room_config')->where('type',1)->select();
                $this->assign('house_room_config',$house_room_config);


                $house_xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('house_xiaoqu',$house_xiaoqu);

                $lines = Db::name('subway_lines')->select();
                $this->assign('lines',$lines);

                $station = Db::name('subway_station')->select();
                $this->assign('station',$station);



                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑长租');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 短租房列表
     * */
    public function house_short_list(){
        $shop_id = Session::get('shop_id');

        $param = $this->request->param();

        $list = Db::name('house_short')
            ->join('sale','sale.sale_id = house_short.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->where('shop_info.id',$shop_id)
            ->where('house_short.is_delete',0)
            ->field('house_short.*,sale.nickname as sale_name')
            ->order('create_time','desc')
            ->paginate(15,false);


        $page = $list->render();
        $list = $list->all();
        foreach ($list as $k=>$v){
            $list[$k]['house_short_img'] = Db::name('house_short_img')->where('short_id',$v['id'])->find();
        }

        $this->assign('page',$page);
        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 短租房编辑
     * */
    public function house_short_publish(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseShortModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([

                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $short = $model->where('id',$id)->find();
                if(empty($short)) {
                    return $this->error('id不正确');
                }

                $post['house_id'] = $id;
                $xiaoqu = Db::name('house_xiaoqu')->where('id',$post['xiaoqu_id'])->find();
                $post['city_id'] = $xiaoqu['city_id'];
                $post['area_id1'] = $xiaoqu['area_id1'];
                $post['area_id2'] = $xiaoqu['area_id2'];
                $post['xiaoqu_name'] = $xiaoqu['xiaoqu_name'];
                $post['address'] = $xiaoqu['address'];
                if ($post['is_subway']==0){
                    $post['lines_id'] = 0;
                    $post['station_id'] = 0;

                }
                $post['tag_id'] = implode(',',$post['tag_id']);
                $post['room_config_id'] = implode(',',$post['room_config_id']);
                $post['traffic_tag_id'] = implode(',',$post['traffic_tag_id']);

                if(false === $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {

                    if (!Db::name('short_rule')->where('short_id',$id)->count()) {
                        $post['short_id'] = $id;
                        Db::name('short_rule')->strict(false)->insert($post);
                    }else{
                        Db::name('short_rule')->where('short_id',$id)->strict(false)->update($post);
                    }


                    Db::name('house_short_img')->where('short_id',$id)->delete();
                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['short_id'] = $model->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('house_short_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/my_shop/house_short_list');
                }
            } else {
                //非提交操作
                $data = Db::view('house_short')
                    ->view('short_rule','fksz,yfff,xxsyj,ewfy,bdgh,jdsj,zsrz,rzsj,tfsj,zdrzts,short_id','short_rule.short_id = house_short.id','left')
                    ->where('house_short.id',$id)
                    ->find();

                $house_short_img = Db::name('house_short_img')->where('short_id',$id)->select();
                $this->assign('house_short_img',$house_short_img);

                $house_type = Db::name('house_type')->where('type',2)->select();
                $this->assign('house_type',$house_type);

                $house_tag = Db::name('house_tag')->where('type',2)->select();
                $this->assign('house_tag',$house_tag);

                $house_room_config = Db::name('room_config')->where('type',2)->select();
                $this->assign('house_room_config',$house_room_config);

                $short_traffic_tag = Db::name('short_traffic_tag')->where('status',1)->select();
                $this->assign('short_traffic_tag',$short_traffic_tag);

                $house_xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('house_xiaoqu',$house_xiaoqu);

                $lines = Db::name('subway_lines')->select();
                $this->assign('lines',$lines);

                $station = Db::name('subway_station')->select();
                $this->assign('station',$station);



                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑短租');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 短租房上架/下架 审核
     * */
    public function short_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();

                $status = $post['status'];
                if(false == Db::name('house_short')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('操作成功','admin/my_shop/house_short_list');

                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 委托任务列表
     * */
    public function entrust_list(){
        $shop_id = Session::get('shop_id');

        $param = $this->request->param();

        $model = new HouseEntrust();
        $where['sale_id'] = ['eq',0];

        if (!empty($param['is_fenpei'])){
            if ($param['is_fenpei'] == 1){
                $where['sale_id'] = ['eq',0];
            }else{
                $where['sale_id'] = ['neq',0];
            }
        }

        $list = $model->where($where)->where('shop_id',$shop_id)->order('create_time','desc')->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->sale_name = Db::name('sale')->where('sale_id',$v['sale_id'])->value('nickname');
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 委托分配
     * */
    public function entrust_fenpei(){
        $shop_id = Session::get('shop_id');

        $id = input('id');
        if ($this->request->isPost()){
            $param = $this->request->param();

            $res = Db::name('house_entrust')->where('id',$id)->update(['sale_id'=>$param['sale_id']]);

            if(false == $res) {
                return $this->error('分配失败');
            } else {
                addlog($id);//写入日志
                return $this->success('分配成功','admin/my_shop/entrust_list');
            }

        }else{
            $data = Db::name('house_entrust')
                ->where('id',$id)
                ->find();
            $sale = Db::name('sale')->where('shop_id',$shop_id)->select();
            $this->assign('sale',$sale);
            $this->assign('data',$data);
            return $this->fetch();
        }
    }
}