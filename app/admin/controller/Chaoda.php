<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/5/23
 * Time: 14:18
 */

namespace app\admin\controller;


use app\wxapi\common\UserLogic;
use app\wxapi\common\Weixin;
use templateMsg\CreateTemplate;
use think\Config;
use think\Db;
use app\admin\model\ProductStyle as styleModel;
use app\admin\model\Topic as topicModel;
use app\admin\model\Tag as tagModel;
class Chaoda extends Admin
{

    /*
     * 搭配风格
     * */
    public function style(){
        $list = Db::name('product_style')->where('is_delete',0)->order(['id'=>'asc'])->paginate(15,false);


        $this->assign('list',$list);
        return $this->fetch();
    }

    //待审核列表
    public function dsh()
    {

        $lists = Db::name('chaoda')
            ->where('is_pt_user',1)
            ->where(("(is_delete = 1 AND status=1 ) OR (is_delete=0 AND status = 2) "))
            ->order(['id'=>'asc'])
            ->paginate(15,false);

        $this->assign('lists',$lists);

        return $this->fetch();
    }

    /*
 * 待审核列表
 * */
    public function dsh_list(){
        $param = $this->request->param();
        if(empty($param['status'])){
            $param['status']='';
        }
        if(empty($param['title'])){
            $param['title']='';
        }
        if(empty($param['page'])){
            $param['page']=1;
        }
        if ($param['status'] != '') {
            //待审核
            if($param['status']==1){
                $where['is_delete'] = ['eq',1];
                $where['status'] = ['eq',1];
            }elseif($param['status']==2){
                //已通过审核
                $where['is_delete'] = ['eq',0];
                $where['status'] = ['eq',2];
            }elseif($param['status']==3) {
                //未通过审核
                $where['is_delete'] = ['eq', 1];
                $where['status'] = ['eq', -1];
            }else{
                return $this->error('参数错误!');
            }
        }else{

            $where3=('field(status,1,2,-1) ASC');
           // $where=("(is_delete = 1 AND status=1 ) OR (is_delete=0 AND status = 2) OR (is_delete=1 AND status = -1)");
        }
        if ($param['title'] != '') {
            $param['title']=trim($param['title']);
            $where2['title|description'] = ['like',"%{$param['title']}%"];
        }
        $lists = Db::name('chaoda')
            ->where('is_pt_user',1)
            ->where($where)
            ->where($where2)
            ->order($where3)
            ->order(['id'=>'asc'])
            ->paginate(15,false,['query'=>$this->request->param()]);
        $this->assign('param',$param);

        $this->assign('lists',$lists);
        return $this->fetch();
    }
    /*
 * 话题列表
 * */
    public function topic_list(){
        $param = $this->request->param();
        $page=$param['page'];
        if (!empty($param['keywords'])) {
            if(substr_count($param['keywords'],'%') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($param['keywords'],'_') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($param['keywords'],'_') + substr_count($param['keywords'],'%')) == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_%'));
            $where['topic.title|topic.description|user.nickname'] = ['like',"%{$param['keywords']}%"];
        }
        $lists =  Db::view('topic','id,title,description,bg_cover,status,user_id,create_time')
            ->view('user','nickname','topic.user_id = user.user_id','left')
            ->where('topic.status','neq',-1)
            ->where($where)
            ->order(['id'=>'desc'])
            ->paginate(15,false,['query'=>$this->request->param()]);
        $this->assign('page',$page);
        $this->assign('param',$param);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    /*
* 添加编辑话题
* */
    public function topic_add()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new topicModel();
        $param = $this->request->param();
        $page=$param['page'];
        //编辑操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证菜单是否存在
                $topic = $model->where('id',$id)->find();
                if(empty($topic)) {
                    return $this->error('id不正确');
                }
                if($topic['bg_cover']!=$post['bg_cover']){
                    //修改了图片
                    $url=$post['bg_cover'];
                    $web_path = Config::get('web_path');
                    $p= $web_path.$url;
                    $rst = \app\wxapi\common\Images::gaussian_blur($p,null,null,2);
                    $url2=strstr($rst,"/uploads/gaosi/");
                    $data['bg_cover']=$url2;

                }else{
                    $data['bg_cover']=$post['bg_cover'];
                }
                $data['list_bg_cover']=$post['bg_cover'];
                $data['description']=trim($post['description']);
                $data['status']=$post['status'];
                $data['title']=trim($post['title']);
                $data['client_type'] = intval($post['client_type']);
                $result=Db::name('topic')
                    ->where('id', $id)
                    ->update($data);
                if($result===false) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/chaoda/topic_list?page='.$page);
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('page',$page);
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
                $data['title']=trim($post['title']);
                $is_title = $model->where('title',$data['title'])->find();
                if($is_title ){return $this->error('该话题已存在，请换个话题吧!');}
                $data['create_time']=time();
                $data['description']=trim($post['description']);
                $data['bg_cover']=$post['bg_cover'];
                $data['list_bg_cover']=$post['bg_cover'];
                $data['status']=$post['status'];
                $data['client_type'] = intval($post['client_type']);
                $topic_id = Db::name('topic')->insertGetId($data);

                if($topic_id) {
                    $result = Db::name('topic')->where('id',$topic_id)->find();
                    if(isset($result['bg_cover'])){
                        //高斯模糊处理
                        $url=$result['bg_cover'];
                        $web_path = Config::get('web_path');
                        $p= $web_path.$url;
                        $rst = \app\wxapi\common\Images::gaussian_blur($p,null,null,2);
                        $url2=strstr($rst,"/uploads/gaosi/");

                        $bg=  Db::name('topic')->where('id',$topic_id)->setField('bg_cover',$url2);
                        if ($bg===false)return $this->error('更新话题背景图片失败!');
                    }
                    addlog($topic_id);//写入日志
                    return $this->success('添加成功','admin/chaoda/topic_list');

                } else {
                    return $this->error('添加失败');
                }
            } else {
                //非提交操作
                $this->assign('page',$page);
                return $this->fetch();
            }
        }

    }
    /*
* 标签列表
* */
    public function tag_list(){
        $param = $this->request->param();
        $page=$param['page'];
        if (!empty($param['keywords'])) {
            if(substr_count($param['keywords'],'%') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($param['keywords'],'_') == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($param['keywords'],'_') + substr_count($param['keywords'],'%')) == strlen($param['keywords']))return \json(self::callback(0,'关键词不能包含关键词_%'));
            $where['tag.title|tag.description|user.nickname'] = ['like',"%{$param['keywords']}%"];
        }
        $lists =  Db::view('tag','id,title,description,bg_cover,status,user_id,create_time')
            ->view('user','nickname','tag.user_id = user.user_id','left')
            ->where('tag.status','neq',-1)
            ->where($where)
            ->order(['id'=>'desc'])
            ->paginate(15,false,['query'=>$this->request->param()]);
        $this->assign('page',$page);
        $this->assign('param',$param);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    /*
* 添加编辑标签
* */
    public function tag_add()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $param = $this->request->param();
        $page=$param['page'];
        $model = new tagModel();
        //编辑操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证菜单是否存在
                $topic = $model->where('id',$id)->find();
                if(empty($topic)) {
                    return $this->error('id不正确');
                }
                if($topic['bg_cover']!=$post['bg_cover']){
                    //修改了图片
                    $url=$post['bg_cover'];
                    $web_path = Config::get('web_path');
                    $p= $web_path.$url;
                    $rst = \app\wxapi\common\Images::gaussian_blur($p,null,null,2);
                    $url2=strstr($rst,"/uploads/gaosi/");
                    $data['bg_cover']=$url2;
                }else{
                    $data['bg_cover']=$post['bg_cover'];
                }
                $data['list_bg_cover']=$post['bg_cover'];
                $data['description']=trim($post['description']);
                $data['status']=$post['status'];
                $data['title']=trim($post['title']);
                $result=Db::name('tag')
                    ->where('id', $id)
                    ->update($data);
                if($result===false) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/chaoda/tag_list?page='.$page);
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('page',$page);
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            //是新增操作s
            if($this->request->isPost()) {
                $post = $this->request->post();
                //是提交操作
                $data['title']=trim($post['title']);
                $is_title = $model->where('title',$data['title'])->find();
                if($is_title){return $this->error('该标签已存在，请换个标签吧!');}
                $data['create_time']=time();
                $data['description']=trim($post['description']);
                $data['bg_cover']=$post['bg_cover'];
                $data['status']=$post['status'];
                $data['list_bg_cover']=$post['bg_cover'];
                $tag_id = Db::name('tag')->insertGetId($data);
                if($tag_id) {
                    $result = Db::name('tag')->where('id',$tag_id)->find();
                    if(isset($result['bg_cover'])){
                        //高斯模糊处理
                        $url=$result['bg_cover'];
                        $web_path = Config::get('web_path');
                        $p= $web_path.$url;
                        $rst = \app\wxapi\common\Images::gaussian_blur($p,null,null,2);
                        $url2=strstr($rst,"/uploads/gaosi/");
                        $bg=  Db::name('tag')->where('id',$tag_id)->setField('bg_cover',$url2);
                        if ($bg===false)return $this->error('更新标签背景图片失败!');
                    }
                    addlog($tag_id);//写入日志
                    return $this->success('添加成功','admin/chaoda/tag_list');

                } else {
                    return $this->error('添加失败');
                }
            } else {
                //非提交操作
                $this->assign('page',$page);
                return $this->fetch();
            }
        }
    }
    /*
    * 详情
    * */
    public function detail(){
        $param = $this->request->param();
        $id = intval($param['id']);
        if(!$id){
            return $this->error('参数错误');
        }
        $chaoda_id=intval($id);
        //潮搭详情
        $list = Db::view('chaoda','id,cover,description,title,address,fb_user_id,status,reason,type')
            ->view('user','nickname,avatar','user.user_id = chaoda.fb_user_id','left')
            ->where('chaoda.id',$chaoda_id)
            ->find();
        if(!$list){
            return \json(self::callback(0,'没有找到该条信息！'));
        }
        //查询所有潮搭图片数量
        $chaoda_img= Db::name('chaoda_img')->field('id,img_url,type,cover')->where('chaoda_id',$chaoda_id)->select();
//                //查询所有图片的tag
        foreach ($chaoda_img as $k=>$v){
            $chaoda_img[$k]['tags']= Db::name('chaoda_tag')->field('tag_name,x_postion,y_postion,direction')->where('img_id',$v['id'])->select();
        };
        $data['images']=$chaoda_img;
        $data['list'] = $list;
        $this->assign('data',$data);
        $this->assign('param',$param);
        return $this->fetch();
    }

    /*
    * 待审核详情
    * */
    public function dsh_publish(){
        $param = $this->request->param();

        $id = intval($param['id']);
        if(!$id){
            return $this->error('参数错误');
        }
        $chaoda_id=intval($id);
        //潮搭详情
        $list = Db::view('chaoda','id,cover,description,title,address,fb_user_id,status,reason,type')
            ->view('user','nickname,avatar','user.user_id = chaoda.fb_user_id','left')
            ->where('chaoda.id',$chaoda_id)
            ->find();
        if(!$list){
            return \json(self::callback(0,'没有找到该条信息！'));
        }
        //查询所有潮搭图片数量
        $chaoda_img= Db::name('chaoda_img')->field('id,img_url,type,cover')->where('chaoda_id',$chaoda_id)->select();
//                //查询所有图片的tag
        foreach ($chaoda_img as $k=>$v){
            $chaoda_img[$k]['tags']= Db::name('chaoda_tag')->field('tag_name,x_postion,y_postion,direction')->where('img_id',$v['id'])->select();
        };
        $data['images']=$chaoda_img;
        $data['list'] = $list;
        $this->assign('data',$data);
        $this->assign('param',$param);
        return $this->fetch();
    }

    /*
    * 通过审核
    * */
    public function tgsh(){
        $param = $this->request->param();
        $id = $param['id'];
        $title = $param['title'];
        $status = $param['status'];
        $page = $param['page'];
        if(!$id){
            return $this->error('参数错误');
        }
        $genxin = [
            'is_delete' => 0,
            'status' => 2,
            'sh_time' => time()
        ];
        $rst = Db::name('chaoda')->where('id',$id)->update($genxin);
        if($rst===false){
            return $this->error('审核失败');
        }else{
            ##获取潮搭信息
            $info = Db::name('chaoda')->where(['id'=>$id])->field('fb_user_id,title')->find();
            if($info['fb_user_id']){  //如果是个人潮搭
                $user_id = $info['fb_user_id'];
                $type = 'audit_notice';

                ##获取openid
                $open_id = UserLogic::getUserOpenId($user_id);
                if(!$open_id)return $this->success('操作成功,小程序消息通知发送失败[用户不存在]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取access_token
                $access_token = Weixin::getAccessToken();
                if(!$access_token)return $this->success('操作成功,小程序消息通知发送失败[获取access_token失败]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取用户的form_id
                $form_id = UserLogic::getUserFormId($user_id);
                if(!$form_id)return $this->success('操作成功,小程序消息通知发送失败[没有可用form_id]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取模板id
                $data = [
                    'title' => $info['title'],
                    'status' => '审核通过',
                ];

                $templateInfo = UserLogic::getTemplateInfo($type, $data);
                $templateInfo['page'] .= "?id={$id}";

                ##更新模板信息的状态
                Db::startTrans();
                $res = UserLogic::useFormId($form_id['id']);
                if($res === false)return $this->success('操作成功,小程序消息通知发送失败[模板信息更新失败]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##发送消息
                $res = CreateTemplate::sendTemplateMsg($open_id, $templateInfo, $form_id, $access_token);
                $result = json_decode($res, true);
                if($result && isset($result['errcode'])){
                    $errCode = $result['errcode'];
                    if($errCode > 0){
                        Db::rollback();
                        return $this->success("操作成功,小程序消息通知发送失败[模板消息发送失败,错误码{$errCode}]",'admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
                    }
                    Db::commit();
                }else{
                    Db::rollback();
                    return $this->success("操作成功,小程序消息通知发送失败[模板消息发送失败]",'admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
                }
            }
            return $this->success('操作成功','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
        }

    }
    /*
* 审核拒绝
* */
    public function shrefuse(){
        $param = $this->request->param();
        $id = $param['id'];
        $title = $param['title'];
        $status = $param['status'];
        $page = $param['page'];
        $reason = trim($this->request->post('reason'));
        if(!$id){
            return $this->error('参数错误');
        }
        $genxin = [
            'is_delete' => 1,
            'status' => -1,
            'sh_time' => time(),
            'reason' => $reason
        ];
        $rst = Db::name('chaoda')->where('id',$id)->update($genxin);
        if($rst===false){
            return $this->error('审核不通过写入失败');
        }else{
            $data=[
                'code'=>1,
                'msg'=>'拒绝成功'
            ];

            ##获取潮搭信息
            $info = Db::name('chaoda')->where(['id'=>$id])->field('fb_user_id,title')->find();
            if($info['fb_user_id']){  //如果是个人潮搭
                $user_id = $info['fb_user_id'];
                $type = 'audit_notice';

                ##获取openid
                $open_id = UserLogic::getUserOpenId($user_id);
                if(!$open_id)return $this->success('操作成功,小程序消息通知发送失败[用户不存在]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取access_token
                $access_token = Weixin::getAccessToken();
                if(!$access_token)return $this->success('操作成功,小程序消息通知发送失败[获取access_token失败]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取用户的form_id
                $form_id = UserLogic::getUserFormId($user_id);
                if(!$form_id)return $this->success('操作成功,小程序消息通知发送失败[没有可用form_id]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##获取模板id
                $data = [
                    'title' => $info['title'],
                    'status' => '审核未通过',
                ];

                $templateInfo = UserLogic::getTemplateInfo($type, $data);
                $templateInfo['page'] .= "?id={$id}";

                ##更新模板信息的状态
                Db::startTrans();
                $res = UserLogic::useFormId($form_id['id']);
                if($res === false)return $this->success('操作成功,小程序消息通知发送失败[模板信息更新失败]','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);

                ##发送消息
                $res = CreateTemplate::sendTemplateMsg($open_id, $templateInfo, $form_id, $access_token);
                $result = json_decode($res, true);
                if($result && isset($result['errcode'])){
                    $errCode = $result['errcode'];
                    if($errCode > 0){
                        Db::rollback();
                        return $this->success("操作成功,小程序消息通知发送失败[模板消息发送失败,错误码{$errCode}]",'admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
                    }
                    Db::commit();
                }else{
                    Db::rollback();
                    return $this->success("操作成功,小程序消息通知发送失败[模板消息发送失败]",'admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
                }
            }

            return $this->success('操作成功','admin/chaoda/dsh_list?title='.$title.'&status='.$status.'&page='.$page);
        }
    }
    /*
    **下架
    * */
    public function xiajia(){
        $id = input('id') ? intval(input('id')) : 0 ;

        if(!$id){
            return $this->error('参数错误');
        }
        $genxin = [
            'is_delete' => 1,
            'status' => -1
        ];
        $rst = Db::name('chaoda')->where('id',$id)->update($genxin);
        if($rst===false){
            return $this->error('下架失败');
        }else{
            return $this->success('操作成功','admin/chaoda/dsh_list');
        }
    }
    /*
**上架
* */
    public function shangjia(){
        $id = input('id') ? intval(input('id')) : 0 ;
        if(!$id){
            return $this->error('参数错误');
        }
        $genxin = [
            'is_delete' => 0,
            'status' => 2
        ];
        $rst = Db::name('chaoda')->where('id',$id)->update($genxin);
        if($rst===false){
            return $this->error('审核失败');
        }else{
            return $this->success('操作成功','admin/chaoda/dsh_list');
        }
    }
//风格详情
    public function style_publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new styleModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();

                //验证菜单是否存在
                $city = $model->where('id',$id)->find();
                if(empty($city)) {
                    return $this->error('id不正确');
                }
                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/chaoda/style');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();


                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑风格');
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

                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/chaoda/style');
                }
            } else {
                //非提交操作

                $this->assign('title','新增风格');
                return $this->fetch();
            }
        }

    }
//删除
    public function style_delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;


            if(false == Db::name('product_style')->where('id',$id)->setField('is_delete',1)) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/chaoda/style');
            }
        }
    }
    //删除话题
    public function topic_delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('topic')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/chaoda/topic_list');
            }
        }
    }
    //删除标签
    public function tag_delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('tag')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/chaoda/tag_list');
            }
        }
    }
    /*
     * 启用/禁用
     * */
    public function style_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('product_style')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/chaoda/style');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
    /*
 * 话题启用/禁用
 * */
    public function topic_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('topic')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/chaoda/topic_list');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
    /*
* 标签启用/禁用
* */
    public function tag_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('tag')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/chaoda/tag_list');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }
}