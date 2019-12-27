<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/4
 * Time: 10:15
 */

namespace app\admin\controller;

use app\admin\model\Maidan;
use think\Db;
use app\admin\model\Store as storeModel;
use think\Exception;
use app\admin\validate\Store as StoreValidate;
use think\Request;

class Store extends Admin
{

    public function dsh()
    {
        #$model = new storeModel();

        $lists = Db::name('store')->where('sh_status',0)->paginate(15,false);

        $this->assign('lists',$lists);

        return $this->fetch();
    }

    public function index()
    {
        $model = new storeModel();
        $param = $this->request->param();

        if (!empty($param['keywords'])) {
            $where['store_name|brand_name|city'] = ['like', "%{$param['keywords']}%"];
        }

        if (!empty($param['type'])) {
            $where['type'] = ['eq',$param['type']];
        }
        $lists = $model->where('sh_status',1)->where($where)->order(['create_time'=>'desc'])->paginate(15,false,['query'=>$this->request->param()]);
        foreach ($lists as $k=>$v){
            $v->store_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->store_name);
            $v->brand_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->brand_name);
            $v->city = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->city);
            $v->product_count = Db::name('product')->where('store_id',$v->id)->count();
            $v->dsh_product_count = Db::name('product')->where('store_id',$v->id)->where('sh_status',0)->count();
            $v->maidan_info = Maidan::getStoreMaidan($v->id);
        }
        $sum_money = $model->where($where)->sum('money');
        $this->assign('sum_money',$sum_money);
        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /*
     * 是否允许修改
     * */
    public function is_allow_edit()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('store')->where('id',$post['id'])->update(['is_allow_edit'=>$post['is_allow_edit']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store/index');
            }
        }
    }

    /*
    * 是否置顶
    * */
    public function is_zhiding()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('store')->where('id',$post['id'])->update(['is_zhiding'=>$post['is_zhiding']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store/index');
            }
        }
    }

    /*
     * 待审核详情
     * */
    public function dsh_publish(){

        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new storeModel();

        $data = $model->where('id',$id)->find();

        if ($data->type == 1){
            $data->category_name = Db::name('store_category')->where('id',$data->category_id)->value('category_name');
        }else{
            $data->category_name = Db::name('member_store_category')->where('id',$data->category_id)->value('category_name');
        }

        $data->is_ziqu = $data->is_ziqu == 1 ? '是' : '否' ;

        if ($data['sh_type'] == 2){
            $store_img = Db::name('store_img')->where('store_id',$id)->select();
            $this->assign('store_img',$store_img);
        }

        $this->assign('data',$data);
        return $this->fetch();
    }


    /*
     * 审核
     * */
    public function sh(){
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if ($id){
            $param = $this->request->param();
            if (!$param['sh_type'] == 1) {
                $param['password'] = password_hash(88888888, PASSWORD_DEFAULT);  //初始密码
            }
            $param['sh_time']=time();
            $res = Db::name('store')->where('id',$id)->strict(false)->update($param);
            if ($res) {
                addlog($id);//写入日志
                return $this->success('操作成功','admin/store/dsh');
            }else{
                return $this->error('操作失败');
            }
        }else{
            return $this->error('id不正确');
        }
    }

    /*
     * 启用/禁用
     * */
    public function store_status(){
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0 ;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $store_status = $post['store_status'];
                if(false == Db::name('store')->where('id',$id)->update(['store_status'=>$store_status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/store/index');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 商家详情
     * */
    public function publish()
    {
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0 ;
        $model = new storeModel();

        $data = $model->where('id',$id)->find();

        if ($data->type == 1){
            $data->category_name = Db::name('store_category')->where('id',$data->category_id)->value('category_name');
        }else{
            $data->category_name = Db::name('member_store_category')->where('id',$data->category_id)->value('category_name');
        }
        $store_img = Db::name('store_img')->where('store_id',$data->id)->select();
        $data->is_ziqu = $data->is_ziqu == 1 ? '是' : '否' ;
        $this->assign('store_img',$store_img);
        $this->assign('data',$data);
        return $this->fetch();

    }
    /*
     * 修改商家信息
     * */
    public function edit()
    {
        $param = $this->request->post();
        $id=intval($param['id']);
        $address=trim($param['address']);
        $maidan_deduct = floatval($param['maidan_deduct']);
//        $type=intval($param['type']);
        if(!$id ||!$address){return $this->error('参数错误');}
        $rst=Db::name('store')->where('id',$id)->update(['address'=>$address,'maidan_deduct'=>$maidan_deduct]);
        if($rst===false) {
            return $this->error('设置失败');
        } else {
            addlog($id);//写入日志
            return $this->success('修改成功','admin/store/index');
        }
    }
    /*
     * 商品列表
     * */
    public function product_list(){
        #$model = new storeModel();
        $param = $this->request->param();


        if (!empty($param['id'])) {
            $where['product.store_id'] = ['eq', $param['id']];
        }
        if (empty($param['sh_status'])) {
            $where3=('field(product.sh_status,0,1,-1) ASC');
        }
        $store_name = Db::name('store')->where('id',$param['id'])->value('store_name');
        $this->assign('store_name',$store_name);

        $lists = Db::view('product','id,product_name,sales,status,sh_status,create_time,is_recommend,sh_time')
            ->view('product_category','category_name','product_category.id = product.category_id','left')
            ->where($where)
            ->order($where3)
            ->order(['product.create_time'=>'desc'])->paginate(15,false,['query'=>$this->request->param()]);

        $list = $lists->all();
        foreach ($list as $k=>$v){
            $list[$k]['price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('price');
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
        }

        #dump($list);

        #$lists = Db::name('product')->where('sh_status',1)->where($where)->order(['create_time'=>'desc'])->paginate(15,false);

        $this->assign('param',$param);
        $this->assign('list',$list);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
     * 商品上架/下架
     * */
    public function product_status(){
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0 ;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];

                if($status==0){
                    //下架
                    $genxin = [
                        'status' => $status,
                        'xiajia_time' => time()
                    ];
                }elseif($status==1){
                    //上架
                    $genxin = [
                        'status' => $status,
                        'shangjia_time' => time()

                    ];
                }
                if(false == Db::name('product')->where('id',$id)->update($genxin)) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
    * 是否推荐
    * */
    public function is_recommend()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if (!Db::name('product')->where('id',$post['id'])->where('sh_status',1)->count()){
                return $this->error('请推荐审核通过的商品');
            }
            if(false == Db::name('product')->where('id',$post['id'])->update(['is_recommend'=>$post['is_recommend']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store/product_list');
            }
        }
    }
    public function refresh_data()
    {
        $model = new storeModel();
        $param = $this->request->param();

        //判断页面上点击的是哪个form
        $formType = $param['formType'];

        $mdl = $model->where('sh_status',1);

        //搜索
        if( $formType == 'search' ){

            if( $param['keywords'] ){
                $mdl->where('store_name','like','%'.$param['keywords'].'%')
                ->whereOr('brand_name','like',"%{$param['keywords']}%");
            }

        }

        $lists = $mdl->order(['create_time'=>'desc'])->paginate(15,false);

        foreach ($lists as $k=>&$v){
            $v->store_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->store_name);
            $v->brand_name = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->brand_name);
            $v->city = str_replace($param['keywords'],'<font color="red">'.$param['keywords'].'</font>',$v->city);
            $v->product_count = Db::name('product')->where('store_id',$v->id)->count();
            $v->dsh_product_count = Db::name('product')->where('store_id',$v->id)->where('sh_status',0)->count();
            $v['real_collect_number']=Db::name('store_follow')->where('store_id',$v->id)->count();
        }
        $this->assign('param',$this->request->param());
        $this->assign('page',$this->request->param('page'));
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    //刷新全部数据
    public function refresh_all_data()
    {
        $param = $this->request->param();
        if(isset($param['min_number']) && isset($param['max_number']) && isset($param['type'])){
            $min_number=is_numeric($param['min_number']);
            $max_number=is_numeric($param['max_number']);
            $type=intval($param['type']);
            if(!$min_number ||!$max_number ||!$type){
                return $this->error('参数错误');
            }
            $min_number=trim(intval($param['min_number']));
            $max_number=trim(intval($param['max_number']));
            //更新操作
            if($min_number<0 || $min_number>10000 || $max_number<0 || $max_number>10000 || ($min_number==$max_number)){
                return $this->error('输入的值需要在0-10000之间');
            }
            switch ($type){
                case 1:
                    $filed='read_number';
                    break;
                case 2:
                    $filed='dianzan';
                    break;
                case 3:
                    $filed='collect_number';
                    break;
                default:
                    return $this->error('未知错误');
                    break;
            }
            $stores=Db::name('store')->where('id','>',0)->select();
            foreach ($stores as $k=>$v){
                if($min_number>$max_number){
                    $n=mt_rand($max_number,$min_number);
                }else{
                    $n=mt_rand($min_number,$max_number);
                }
              $rst=  Db::name('store')->where('id',$v['id'])->setInc($filed, $n);
              if($rst===false){
                  return $this->error('刷新失败');
              }
            }
            return $this->success('刷新成功','admin/store/refresh_data?type='.$param['type'].'&min_number='.$param['min_number'].'&max_number='.$param['max_number'].'&page='.$param['page']);
        }else{
            return $this->error('没有参数');
        }
    }
    //刷新部分数据
    public function refresh_part_data()
    {
        $param = $this->request->param();
        if(isset($param['min_number']) && isset($param['max_number']) && isset($param['ids']) && isset($param['type'])){
            $min_number=is_numeric($param['min_number']);
            $max_number=is_numeric($param['max_number']);
            $type=intval($param['type']);
            if(!$min_number || !$max_number || !$type){
                return $this->error('参数错误');
            }
            $min_number=trim(intval($param['min_number']));
            $max_number=trim(intval($param['max_number']));
            //更新操作
            if($min_number<0 || $min_number>10000 || $max_number<0 || $max_number>10000 || ($min_number==$max_number)){
                return $this->error('输入的值需要在0-10000之间');
            }
            switch ($type){
                case 1:
                    $filed='read_number';
                    break;
                case 2:
                    $filed='dianzan';
                    break;
                case 3:
                    $filed='collect_number';
                    break;
                default:
                    return $this->error('未知错误');
                    break;
            }
            $stores=Db::name('store')->where('id','in',$param['ids'])->select();
            foreach ($stores as $k=>$v){
                if($min_number>$max_number){
                    $n=mt_rand($max_number,$min_number);
                }else{
                    $n=mt_rand($min_number,$max_number);
                }
                $rst=  Db::name('store')->where('id',$v['id'])->setInc($filed, $n);
                if($rst===false){
                    return $this->error('刷新失败');
                }
            }
            return $this->success('刷新成功','admin/store/refresh_data?type='.$param['type'].'&min_number='.$param['min_number'].'&max_number='.$param['max_number'].'&page='.$param['page']);
        }else{
            return $this->error('参数错误');
        }
    }
    /*
     * 商品详情
     * */
    public function product_publish(){

        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0 ;

        #$store_info = Db::name('store')->where('id',$id)->find();

        $data = Db::view('product')
            ->view('store','id as store_id,store_name','product.store_id = store.id','left')
            ->view('product_category','category_name','product_category.id = product.category_id','left')
            ->where('product.id',$id)
            ->find();

        $product_img = Db::name('product_img')->where('product_id',$id)->select();
        $product_specs = Db::name('product_specs')->where('product_id',$id)->select();

        foreach ($product_specs as $k=>$v){
            $product_specs[$k]['product_specs'] = trim(trim(str_replace('"','',$v['product_specs']),'}'),'{');
        }

        $this->assign('product_specs',$product_specs);
        $this->assign('product_img',$product_img);
        $this->assign('data',$data);
        return $this->fetch();
    }

    /*
     * 商品审核
     * */
    public function dsh_product(){

        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0 ;

        #$store_info = Db::name('store')->where('id',$id)->find();

        $data = Db::view('product')
            ->view('store','id as store_id,store_name','product.store_id = store.id','left')
            ->view('product_category','category_name','product_category.id = product.category_id','left')
            ->where('product.id',$id)
            ->find();

        $product_img = Db::name('product_img')->where('product_id',$id)->select();
        $product_specs = Db::name('product_specs')->where('product_id',$id)->select();

        foreach ($product_specs as $k=>$v){
            $product_specs[$k]['product_specs'] = trim(trim(str_replace('"','',$v['product_specs']),'}'),'{');
        }

        $this->assign('product_specs',$product_specs);
        $this->assign('product_img',$product_img);
        $this->assign('data',$data);
        return $this->fetch();
    }

    /*
     * 审核
     * */
    public function sh_product(){
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if ($id){

            $param = $this->request->param();
            if($param['status']==1){
                $param['shangjia_time']=time();
            }elseif ($param['status']==0){
                $param['xiajia_time']=time();
            }

            $store_id = Db::name('product')->where('id',$id)->value('store_id');
            $param['sh_time']=time();
            $res = Db::name('product')->where('id',$id)->strict(false)->update($param);

            if ($res) {
                addlog($id);//写入日志
                return $this->success('操作成功',url('admin/store/product_list',['id'=>$store_id]));
            }else{
                return $this->error('操作失败');
            }
        }else{
            return $this->error('id不正确');
        }
    }

    /*
     * 普通店铺统计
     * */
    public function store_count(){
        $start_time = input('start_time');
        $end_time = input('end_time');

        if(!empty($start_time)){
            $where['product_order.pay_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['product_order.pay_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['product_order.pay_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }

        $id_arr2 = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',1)
            ->where('product_order.order_status','>=',2)
            ->column('product_order.id');
        $product_order2 = Db::name('product_order_detail')->where('order_id','in',$id_arr2)->select();

        $total_freight2 = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',1)
            ->where('product_order.order_status','>=',2)
            ->sum('product_order.total_freight');

        #echo Db::name()->getLastSql();

        $total_order_money2 = 0 ;
        foreach ($product_order2 as $k=>$v){
            $total_order_money2 += $v['price'] * $v['number'];
        }

        //普通店铺总交易金额
        $data['total_money'] = $total_order_money2 + $total_freight2;

        $data['order_number'] = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',1)
            ->where('product_order.order_status','>=',2)
            ->count();

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    /*
    * 会员店铺统计
    * */
    public function member_store_count(){
        $start_time = input('start_time');
        $end_time = input('end_time');

        if(!empty($start_time)){
            $where['product_order.pay_time'] = array('egt',strtotime($start_time.' 00:00:00'));
        }
        if(!empty($end_time)){
            if(!empty($start_time)) {
                $where['product_order.pay_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59'))) ;

            }else{
                $where['product_order.pay_time'] = array('elt',strtotime($end_time.' 23:59:59'));
            }
        }


        $id_arr2 = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',2)
            ->where('product_order.order_status','>=',2)
            ->column('product_order.id');
        $product_order2 = Db::name('product_order_detail')->where('order_id','in',$id_arr2)->select();

        $total_freight2 = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',2)
            ->where('product_order.order_status','>=',2)
            ->sum('product_order.total_freight');

        #echo Db::name()->getLastSql();

        $total_order_money2 = 0 ;
        foreach ($product_order2 as $k=>$v){
            $total_order_money2 += $v['price'] * $v['number'];
        }

        //普通店铺总交易金额
        $data['total_money'] = $total_order_money2 + $total_freight2;

        $data['order_number'] = Db::name('product_order')
            ->join('store','store.id = product_order.id','left')
            ->where($where)
            ->where('store.type',2)
            ->where('product_order.order_status','>=',2)
            ->count();

        $this->assign('data',$data);
        $this->assign('param',$this->request->param());

        return $this->fetch();
    }

    /*
     * 提现记录
     * */
    public function tixian_record(){
        $param = $this->request->param();

        if (!empty($param['order_no'])) {
            $where['store_tixian_record.order_no'] = ['like', "%{$param['order_no']}%"];
        }

        if (!empty($param['store_id'])) {
            $where['store_tixian_record.store_id'] = ['eq', $param['store_id']];
        }

        if(!empty($param['start_create_at'])){
            $where['store_tixian_record.create_at'] = array('egt',"{$param['start_create_at']} 00:00:00");
        }

        if(!empty($param['end_create_at'])){
            if(!empty($param['start_create_at'])) {
                $where['store_tixian_record.create_at'] = array(array('egt',"{$param['start_create_at']} 00:00:00"),array('elt',"{$param['end_create_at']} 23:59:59")) ;

            }else{
                $where['store_tixian_record.create_at'] = array('elt',"{$param['end_create_at']} 23:59:59");
            }
        }

        try{
            $lists = Db::view('store_tixian_record')
                ->view('store','store_name','store.id = store_tixian_record.store_id','left')
                ->where($where)
                ->order(['store_tixian_record.create_at'=>'desc'])
                ->paginate(15,false);

        }catch (\Exception $e){
            return $e->getMessage();
        }

        $sum_tixian_money = Db::view('store_tixian_record')
            ->view('store','store_name','store.id = store_tixian_record.store_id','left')
            ->where($where)
            ->where('store_tixian_record.code',10000)
            ->sum('store_tixian_record.money');

        $this->assign('sum_tixian_money',$sum_tixian_money);
        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 设置人工干预得分
     */
    public function setScoreMeddle(){
        try{

            $id = input('post.id',0,'intval');
            $score_meddle = input('post.val',0,'intval');
            if(!$id)throw new Exception('参数缺失');

            if($score_meddle < 0)throw new Exception('参数错误');

            $res = storeModel::setStoreScoreMeddle($id, $score_meddle);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');

        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    /**
     * 设置平台买单提成比
     */
    public function editMaidanDeduct(){
        try{

            $id = input('post.id',0,'intval');
            $maidan_deduct = input('post.maidan_deduct',0,'floatval');
            if(!$id)throw new Exception('参数缺失');

            if($maidan_deduct <0 || $maidan_deduct > 100)throw new Exception('平台买单分成比必须是0-100之间的数');

            $res = storeModel::setStoreMaidanDeduct($id, $maidan_deduct);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');

        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新会员买单折扣
     * @param StoreValidate $storeValidate\
     */
    public function editMaiDanDiscount(StoreValidate $storeValidate){
        try{
            #验证
            $check = $storeValidate->scene('edit_maidan_info')->check(input());
            if(!$check)throw new Exception($storeValidate->getError());

            #逻辑
            $store_id = input('post.id',0,'intval');
            $member_user = input('post.member_user',0,'floatval');
            $member_user = round($member_user,2);

            $res = Maidan::editStoreMaiDanMember($store_id, $member_user);
            if($res === false)throw new Exception('操作失败');

            #返回
            return $this->success('操作成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }


    /*
     * 刷新商家列表页，编辑单个字段
     */
    public function editOneCol(Request $request)
    {
        $colName = $request->get('colName');

        if( !$colName ){
            return $this->error('没有字段名称');
        }

        $id = $request->get('id');

        if( !$id || !is_numeric($id)){
            return $this->error('参数id错误');
        }

        $val = $request->get('val');

        if( $val === null ){
            return $this->error('请填写想要修改后的值');
        }

        $allowField = ['read_number','dianzan','collect_number'];

        //目前只能是整数
        if (!is_numeric($val)){
            return $this->error('只能是数字');
        }

        $val = intval($val);

        if( !in_array($colName,$allowField) ){
            return $this->error('该字段不允许修改');
        }

        $res = storeModel::where('id',$id)->update([$colName => $val]);

        if( $res ){
            return $this->success('修改成功');
        }else{
            return $this->error('修改失败');
        }
    }

    /**
     * 商家审核
     * @return mixed
     */
    public function merchant(){
        return $this->fetch();
    }

    /**
     * 店铺审核
     * @return mixed
     */
    public function shopMerchant(){
        return $this->fetch();
    }

}