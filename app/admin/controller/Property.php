<?php

namespace app\admin\controller;

use \app\admin\model\Property as propertyModel;
use think\Db;
use think\Session;

class Property extends Permissions
{
    /*
     * 普通用户列表
     * */
    public function index(){
        $model = new propertyModel();
        $post = $this->request->param();


        if (!empty($post['keywords'])) {
            $where['nickname|mobile'] = ['like', '%' . $post['keywords'] . '%'];
        }
        if (isset($post['property_status']) and $post['property_status'] != '') {
            $where['property_status'] = ['eq',$post['property_status']];
        }
        if (!empty($post['create_time'])) {
            $where["FROM_UNIXTIME(create_time,'%Y-%m-%d')"] = ['eq',$post['create_time']];
        }


        $propertys = $model->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);

        foreach ($propertys as $k=>$v){

            $v->nickname = str_replace($post['keywords'],'<font color="red">'.$post['keywords'].'</font>',$v->nickname);
            $v->mobile = str_replace($post['keywords'],'<font color="red">'.$post['keywords'].'</font>',$v->mobile);
            $v->xiaoqu_name = Db::name('house_xiaoqu')->where('id',$v->xiaoqu_id)->value('xiaoqu_name');
        }

        $this->assign('propertys',$propertys);
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
        $model = new propertyModel();
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
                $article = $model->where('property_id',$id)->find();
                if(empty($article)) {
                    return $this->error('id不正确');
                }

                if(false == $model->allowField(true)->save($post,['property_id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('修改成功','admin/property/index');
                }
            } else {
                //非提交操作
                $property = $model->where('property_id',$id)->find();
                $xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('xiaoqu',$xiaoqu);
                if(!empty($property)) {
                    $this->assign('property',$property);
                    $this->assign('title','编辑物业人员');
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

                //验证手机号码是否已注册过
                if ($model->where('mobile',$post['mobile'])->count()){
                    $this->error('手机号码已注册');
                }

                $post['password'] = password_hash(88888888, PASSWORD_DEFAULT);

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->property_id);//写入日志
                    return $this->success('添加成功','admin/property/index');
                }
            } else {
                $xiaoqu_id = input('xiaoqu_id');
                $xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('xiaoqu',$xiaoqu);
                $this->assign('xiaoqu_id',$xiaoqu_id);
                //非提交操作
                $this->assign('title','新增物业人员');
                return $this->fetch();
            }
        }

    }

    /*
     * 启用/禁用
     * */
    public function property_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $property_status = $post['property_status'];
                if(false == Db::name('property')->where('property_id',$id)->update(['property_status'=>$property_status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/property/index');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
}