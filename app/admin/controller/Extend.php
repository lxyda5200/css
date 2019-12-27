<?php


namespace app\admin\controller;

use app\admin\model\Extend as ExtendModel;
use app\admin\validate\Extend as ExtendVaildate;
use think\Exception;

class Extend extends Admin
{

    public function lists(ExtendModel $ExtendModel){

        $keywords = input('keywords','','addslashes,strip_tags,trim');
        $status = input('status',0,'intval');
        $type = input('type',0,'intval');
        $where = [];
        if($keywords)$where['extend_name|mobile'] = ['LIKE',"%{$keywords}%"];
        if($status)$where['status'] = $status;
        if($type)$where['type'] = $type;

        $list = $ExtendModel ->where($where) -> field('id,extend_name,create_time,type,status,mobile') -> paginate(10,false,['query'=>compact('keywords','status','type')]);

        $page = $list -> render();

        $list = $list -> toArray()['data'];

        $this->assign(compact('list','page','keywords','status','type'));

        return $this->fetch();

    }

    public function publish(ExtendModel $ExtendModel, ExtendVaildate $ExtendValidate){
        $id = input('id',0,'intval');
        if(request()->isPost()){

            #验证
            $res = $ExtendValidate->scene('publish')->check(input());
            if(!$res)return $this->error($ExtendValidate->getError());

            #逻辑
            $extend_name = input('post.extend_name','','addslashes,strip_tags,trim');
            $mobile = input('post.mobile','','addslashes,strip_tags,trim');
            $type = input('post.type',0,'intval');
            $status = input('post.status',0,'intval');

            $data = compact('extend_name','mobile','type','status');
            if($id){  //修改
                $res = $ExtendModel->edit($data,$id);
            }else{  //新增
                $res = $ExtendModel->add($data);
            }
            if($res === false)return $this->error('操作失败');
            return $this->success('操作成功',url('Extend/lists'));
        }else{
            if($id){
                $data = ExtendModel::get($id);
                if(!$data)return $this->error('数据不存在');

                $this->assign(compact('data'));
            }
            return $this->fetch();
        }

    }

    /**
     * 修改状态
     * @param ExtendModel $ExtendModel
     * @param ExtendVaildate $ExtendValidate
     */
    public function status(ExtendModel $ExtendModel, ExtendVaildate $ExtendValidate){
        #验证
        if(!request()->isPost())return $this->error('非法请求');
        $res = $ExtendValidate->scene('edit_status')->check(input());
        if(!$res)return $this->error($ExtendValidate->getError());

        #逻辑
        $id = input('post.id',0,'intval');
        $status = input('post.status',1,'intval') == 1?2:1;

        $res = $ExtendModel->updateField('status',$status,$id);
        if($res === false)return $this->error('修改失败');

        #返回
        return $this->success('修改成功');
    }

    public function delete(ExtendVaildate $ExtendValidate){

        try{
            #验证
            if(!request()->isPost())throw new Exception('非法请求');
            $res = $ExtendValidate->scene('delete')->check(input());
            if(!$res)throw new Exception($ExtendValidate->getError());

            #逻辑
            $id = input('post.id',0,'intval');
            $res = ExtendModel::destroy($id);

            if($res === false)throw new Exception('删除失败');

            return $this->success('删除成功');
        }catch(Exception $e){
            return $this->error($e->getMessage());
        }

    }

}