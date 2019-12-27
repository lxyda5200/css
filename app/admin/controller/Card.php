<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/4/26
 * Time: 10:55
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\GiftpackCard as cardModel;
class Card extends Admin
{
    public function index()
    {
        $model = new cardModel();
        $data = $model->order(['create_time'=>'desc','id'=>'asc'])->paginate(15,false);

        foreach ($data as $k=>$v){
            $v->name = Db::name('giftpack')->where('id',$v->giftpack_id)->value('name');
        }

        $this->assign('data',$data);
        return $this->fetch();
    }

    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new cardModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    #['city_name', 'require', '城市名称不能为空']
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $city = $model->where('id',$id)->find();
                if(empty($city)) {
                    return $this->error('id不正确');
                }
                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/card/index');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                $giftpack = Db::name('giftpack')->where('is_del',0)->select();

                $this->assign('giftpack',$giftpack);

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑城市');
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
                    #['coupon_name', 'require', '卡券名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/card/index');
                }
            } else {
                //非提交操作

                $giftpack = Db::name('giftpack')->where('is_del',0)->select();

                $this->assign('giftpack',$giftpack);

                $this->assign('title','新增卡券');
                return $this->fetch();
            }
        }

    }

    public function delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            Db::name('giftpack_card_store')->where('card_id',$id)->delete();

            if(false == Db::name('giftpack_card')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/card/index');
            }
        }
    }


    /*
     * 商家列表
     * */
    public function store_list(){
        $param = $this->request->param();


        if (!empty($param['id'])) {
            $where['giftpack_card_store.card_id'] = ['eq', $param['id']];
        }


        $lists = Db::view('giftpack_card_store')
            ->view('store','store_name','store.id = giftpack_card_store.store_id','left')
            ->where($where)
            ->order(['giftpack_card_store.id'=>'desc'])->paginate(15,false);


        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /*
     * 新增店铺
     * */
    public function store_publish(){
        //获取菜单id
        #$id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        #$model = new cardModel();

        //是新增操作
        if($this->request->isPost()) {
            //是提交操作
            $post = $this->request->post();
            //验证  唯一规则： 表名，字段名，排除主键值，主键名
            $validate = new \think\Validate([
                #['city_name', 'require', '城市名称不能为空'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($post)) {
                $this->error('提交失败：' . $validate->getError());
            }

            if (!Db::name('store')->where('sh_status',1)->where('id',$post['store_id'])->count()){
                $this->error('店铺不存在');
            }

            if (Db::name('giftpack_card_store')->where('card_id',$post['card_id'])->where('store_id',$post['store_id'])->count()){
                $this->error('该店铺已绑定');
            }


            if(false == Db::name('giftpack_card_store')->strict(false)->insert($post)) {
                return $this->error('添加失败');
            } else {

                return $this->success('添加成功',url('admin/card/store_list',['id'=>$post['card_id']]));
            }
        } else {
            //非提交操作
            $card_id = input('card_id');

            $this->assign('card_id',$card_id);
            return $this->fetch();
        }
    }

    //删除相关店铺
    public function store_delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

            $card_id = Db::name('giftpack_card_store')->where('id',$id)->value('card_id');

            if(false == Db::name('giftpack_card_store')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                #addlog($id);//写入日志
                return $this->success('删除成功',url('admin/card/store_list',['id'=>$card_id]));
            }
        }
    }

}