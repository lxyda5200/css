<?php
namespace app\admin\controller;

use app\admin\model\Store as storeModel;
use think\Db;
use app\admin\model\Product as productModel;
use think\Exception;
use think\Request;

class Product extends Permissions
{

    /*
     * 商品列表
     * */
    public function product_list(){
        $param = $this->request->param();

        if (!empty($param['id'])) {
            $where['product.store_id'] = ['eq', $param['id']];
        }

        if ($param['sh_status'] != '') {
            $where['product.sh_status'] = ['eq',$param['sh_status']];
        }else{
            $where3=('field(product.sh_status,0,1,-1) ASC');
        }

        if ($param['product_name'] != '') {
            $where['product.product_name'] = ['like',"%{$param['product_name']}%"];
        }

        /*$store_name = Db::name('store')->where('id',$param['id'])->value('store_name');
        $this->assign('store_name',$store_name);*/

        $lists = Db::view('product','id,product_name,sales,status,sh_status,create_time,is_recommend,sh_time,score_meddle')
            ->view('store','store_name','store.id = product.store_id','left')
            ->view('product_category','category_name','product_category.id = product.category_id','left')
            ->where($where)
            ->order($where3)
            ->order(['product.create_time'=>'desc'])->paginate(15,false,['query'=>$this->request->param()]);

        $list = $lists->all();
        foreach ($list as $k=>$v){
            $list[$k]['product_name'] = str_replace($param['product_name'],'<font color="red">'.$param['product_name'].'</font>',$v['product_name']);
            $list[$k]['price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('price');
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
        }


        $this->assign('param',$param);
        $this->assign('list',$list);
        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /*
     * 商品列表但是可以操作部分字段
     */
    public function refresh_data()
    {
        $param = $this->request->param();

        if (!empty($param['id'])) {
            $where['product.store_id'] = ['eq', $param['id']];
        }

        if ($param['sh_status'] != '') {
            $where['product.sh_status'] = ['eq',$param['sh_status']];
        }else{
            $where3=('field(product.sh_status,0,1,-1) ASC');
        }

        if ($param['product_name'] != '') {
            $where['product.product_name'] = ['like',"%{$param['product_name']}%"];
        }

        /*$store_name = Db::name('store')->where('id',$param['id'])->value('store_name');
        $this->assign('store_name',$store_name);*/

        $lists = Db::view('product','id,product_name,sales,status,sh_status,create_time,is_recommend,sh_time,fake_collect_num,collect_num')
            ->view('store','store_name','store.id = product.store_id','left')
            ->view('product_category','category_name','product_category.id = product.category_id','left')
            ->where($where)
            ->order($where3)
            ->order(['product.create_time'=>'desc'])->paginate(15,false,['query'=>$this->request->param()]);

        $list = $lists->all();
        foreach ($list as $k=>$v){
            $list[$k]['product_name'] = str_replace($param['product_name'],'<font color="red">'.$param['product_name'].'</font>',$v['product_name']);
            $list[$k]['price'] = Db::name('product_specs')->where('product_id',$v['id'])->value('price');
            $list[$k]['product_img'] = Db::name('product_img')->where('product_id',$v['id'])->value('img_url');
        }


        $this->assign('param',$param);
        $this->assign('list',$list);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
     * 编辑单个字段的值
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

        $allowField = ['fake_collect_num',];

        //目前只能是整数
        if (!is_numeric($val)){
            return $this->error('只能是数字');
        }

        $val = intval($val);

        if( !in_array($colName,$allowField) ){
            return $this->error('该字段不允许修改');
        }

        $res = ProductModel::where('id',$id)->update([$colName => $val]);

        if( $res ){
            return $this->success('修改成功');
        }else{
            return $this->error('修改失败');
        }
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
//                case 1:
//                    $filed='read_number';
//                    break;
//                case 2:
//                    $filed='dianzan';
//                    break;
                case 3:
                    $filed='fake_collect_num';
                    break;
                default:
                    return $this->error('未知错误');
                    break;
            }
            $products=productModel::where('id','>',0)->select();

            foreach ($products as $k=>$v){

                if($min_number>$max_number){
                    $n=mt_rand($max_number,$min_number);
                }else{
                    $n=mt_rand($min_number,$max_number);
                }
                $rst=  productModel::where('id',$v['id'])->setInc($filed, $n);
                if($rst===false){
                    return $this->error('刷新失败');
                }
            }
            return $this->success('刷新成功','admin/product/refresh_data?type='.$param['type'].'&min_number='.$param['min_number'].'&max_number='.$param['max_number'].'&page='.$param['page']);
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
//                case 1:
//                    $filed='read_number';
//                    break;
//                case 2:
//                    $filed='dianzan';
//                    break;
                case 3:
                    $filed='fake_collect_num';
                    break;
                default:
                    return $this->error('未知错误');
                    break;
            }
            $products =productModel::where('id','in',$param['ids'])->select();

            foreach ($products as $k=>$v){
                if($min_number>$max_number){
                    $n=mt_rand($max_number,$min_number);
                }else{
                    $n=mt_rand($min_number,$max_number);
                }
                $rst=  productModel::where('id',$v['id'])->setInc($filed, $n);
                if($rst===false){
                    return $this->error('刷新失败');
                }
            }
            return $this->success('刷新成功','admin/product/refresh_data?type='.$param['type'].'&min_number='.$param['min_number'].'&max_number='.$param['max_number'].'&page='.$param['page']);
        }else{
            return $this->error('参数错误');
        }
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

        $this->assign('product_name',input('product_name'));
        $this->assign('sh_status',input('sh_status'));
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

            #$store_id = Db::name('product')->where('id',$id)->value('store_id');
            $param['sh_time']=time();
            $res = Db::name('product')->where('id',$id)->strict(false)->update($param);

            if ($res) {
                addlog($id);//写入日志
                return $this->success('操作成功',url('admin/product/product_list',['sh_status'=>$param['param_sh_status'],'product_name'=>$param['param_product_name']]));
            }else{
                return $this->error('操作失败');
            }
        }else{
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
                return $this->success('设置成功','admin/product/product_list');
            }
        }
    }

    /**
     * 设置人工干预得分
     */
    public function editScoreMeddle(){
        try{
            $id = input('post.id',0,'intval');
            $score_meddle = input('post.score',0,'intval');
            if(!$id)throw new Exception('参数缺失');
            if($score_meddle < 0)throw new Exception('参数错误');

            $res = productModel::updateScoreMeddle($id, $score_meddle);
            if($res === false)throw new Exception('操作失败');

            return $this->success('操作成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }
    }

    public function productCate(){
        return $this->fetch();
    }

    public function industryCate(){
        return $this->fetch();
    }

}