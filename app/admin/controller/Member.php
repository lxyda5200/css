<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/13
 * Time: 17:52
 */

namespace app\admin\controller;


use think\Db;

class Member extends Admin
{

    /*
     * 会员中心
     * */
    public function member_content(){
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            if(false == Db::name('member_price')->strict(false)->where('id',$id)->update($post)) {
                return $this->error('设置失败');
            } else {
                addlog($id);//写入日志
                return $this->success('设置成功','admin/member/member_content');
            }
        }else{
            $data = Db::name('member_price')->where('id',1)->find();
            $this->assign('data',$data);
            return $this->fetch();
        }
    }


//会员卡列表

public function index()
{

    $data = Db::table('member_card')->order('id desc')->paginate(15,false);
    $this->assign('data',$data);
    return $this->fetch();
}


//会员卡编辑新增
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
    
        //是正常添加操作
        if($id > 0) {

            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $param = $this->request->post();
                $id=$param['id'];
                $price=$param['price'];
                $card_name=$param['card_name'];
                $status=$param['status'];
                //验证菜单是否存在
                $data = Db::name('member_card')->where('id',$id)->find();
                if(empty($data)) {
                    return $this->error('id不正确');
                }

                $genxin = [
                    'price' => $price,
                    'card_name' => $card_name,
                    'status' => $status
    
                ];
                $rst = Db::name('member_card')->where('id',$id)->update($genxin);
                if($rst===false){
                    return $this->error('修改失败');
                }else {
            
                    return $this->success('修改成功','admin/member/index');
                }
            } else {
             
                //非提交操作
                $data = Db::name('member_card')->where('id',$id)->find();
                $this->assign('data',$data);
                return $this->fetch();

            }
        } else {
            //是新增操作
            if($this->request->isPost()) {
                //是提交操作
                $param = $this->request->post();
      $price=$param['price'];
      $card_name=$param['card_name'];
      $status=$param['status'];
      $create_time=time(); 
if(!$price||!$card_name||!$status){
    return $this->error('操作失败,数据不完整');
}else{

    $data = ['price' => $price, 'card_name' => $card_name,'status' => $status,'create_time' => $create_time];
   $rst= Db::table('member_card')->insert($data);
   if($rst===false){
    return $this->error('添加失败');
   }
    return $this->success('添加成功','admin/member/index');
}

            } else {
                //非提交操作
               
                // $data = Db::name('member_card')->select();

                // $this->assign('data',$data);

                // $this->assign('title','会员卡列表');
                return $this->fetch();
            }
        }

    }
//删除会员卡
    public function delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            Db::name('member_card')->where('id',$id)->delete();

            if(false == Db::name('member_card')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/member/index');
            }
        }
    }

}