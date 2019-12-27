<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/3
 * Time: 10:14
 */

namespace app\admin\controller;

use app\admin\model\MemberStoreBanner;
use app\admin\model\StoreCategory;
use app\admin\model\StoreCategoryImg;
use think\Db;
use app\admin\model\MemberStoreCategory as mCategoryModel;
use app\admin\model\StoreCategory as categoryModel;
use app\admin\model\ProductCategory as pCategoryModel;
class StoreType extends Admin
{
    /*
     * 普通商城分类
     * */
    public function store_category(){
        $model = new categoryModel();
        $lists = $model->order(['client_type'=>'desc','is_show'=>'desc','paixu'=>'asc','create_time'=>'desc'])->paginate(15,false);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
     * 添加分类
     * */
    public function publish1()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new categoryModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['category_name', 'require', '分类名称不能为空']
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
                    return $this->success('修改成功','admin/store_type/store_category');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑分类');
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
                    ['category_name', 'require', '分类名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/store_type/store_category');
                }
            } else {
                //非提交操作
                $this->assign('title','新增分类');
                return $this->fetch();
            }
        }

    }


    /*
     * 商品分类
     * */
    public function product_category(){
        $model = new pCategoryModel();
        $lists = $model->order(['paixu'=>'asc','create_time'=>'desc'])->paginate(15,false);
        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /*
     * 添加分类
     * */
    public function publish3()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new pCategoryModel();

        //是正常添加操作
        if($id > 0) {
            if ($id == 2){
                return $this->error('预告分类不支持编辑');
            }
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作



                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['category_name', 'require', '分类名称不能为空']
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
                    return $this->success('修改成功','admin/store_type/product_category');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑商品分类');
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
                    ['category_name', 'require', '分类名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/store_type/product_category');
                }
            } else {
                //非提交操作
                $this->assign('title','新增分类');
                return $this->fetch();
            }
        }

    }


    /*
     * 是否显示商品分类
     * */
    public function is_show3()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('product_category')->where('id',$post['id'])->update(['is_show'=>$post['is_show']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store_type/product_category');
            }
        }
    }


    /*
     * 商品分类排序
     * */
    public function paixu3()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('product_category')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('product_category')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/store_type/product_category');
        }
    }

    /*
     * 分类轮播图
     * */
    public function index(){
        $lists = Db::view('store_category_img')
            ->view('store_category','category_name,client_type','store_category.id = store_category_img.category_id','left')
            ->order(['client_type'=>'desc','id'=>'asc'])
            ->paginate(15,false);

        $this->assign('lists',$lists);
        return $this->fetch();
    }


    /*
     * 添加分类轮播图
     * */
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new StoreCategoryImg();

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
                $data = $model->where('id',$id)->find();
                if(empty($data)) {
                    return $this->error('id不正确');
                }

                $count = Db::name('store_category_img')->where('category_id',$post['category_id'])->count();

                if ($count >= 5) {
                    return $this->error('每个分类最多5张轮播图');
                }

                if ($post['store_id']) {
                    $type = Db::name('store')->where('id',$post['store_id'])->value('type');

                    if(!$type) {
                        return $this->error('店铺ID不正确');
                    }
                    if ($type == 2){
                        return $this->error('请填写普通店铺ID');
                    }
                }

                if(false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/store_type/index');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                $category = Db::name('store_category')->field('id,category_name,paixu,type,client_type')->where('(is_show =1) OR (id IN (18,19))')->order(['client_type'=>'desc','paixu'=>'asc'])->select();
                $this->assign('category',$category);
                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑轮播图');
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
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/store_type/index');
                }
            } else {
                //非提交操作
                $category = Db::name('store_category')->field('id,category_name,paixu,type,client_type')->where('(is_show =1) OR (id IN (18,19))')->order(['client_type'=>'asc','paixu'=>'asc'])->select();
                foreach ($category as $k=>&$v){
                    if($v['client_type']==1){
                        $v['category_name']=$v['category_name']."(小程序)";
                    }elseif ($v['client_type']==2){
                        $v['category_name']=$v['category_name']."(app)";
                    }
                }
                $this->assign('category',$category);

                $this->assign('title','新增轮播图');

            }
        }

        # 获取可使用的所有抽奖活动
        $lottery = Db::table('draw_lottery')
            ->where(['delete_time' => null, 'active_status' => ['in', [1,2]], 'status' => 1])
            ->field('id, title, client')->select();
        $client = [
            1 => 'APP',
            2 => '小程序',
            3 => 'APP&小程序'
        ];
        foreach ($lottery as $k => $v)
            $lottery[$k]['title'] = $v['title']."({$client[$v['client']]})";

        $this->assign(compact('lottery'));
        return $this->fetch();

    }

    /*
     * 删除
     * */
    public function delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('store_category_img')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/store_type/index');
            }
        }
    }

    /*
     * 是否显示普通商城分类
     * */
    public function is_show1()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('store_category')->where('id',$post['id'])->update(['is_show'=>$post['is_show']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store_type/store_category');
            }
        }
    }

    /*
     * 普通商城分类排序
     * */
    public function paixu1()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('store_category')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false === Db::name('store_category')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/store_type/store_category');
        }
    }


    /*
     * 会员商城分类
     * */
    public function member_store_category(){
        $model = new mCategoryModel();
        $lists = $model->order(['paixu'=>'asc','create_time'=>'desc'])->paginate(15,false);;
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
     * 添加分类
     * */
    public function publish2()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new mCategoryModel();

        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['category_name', 'require', '分类名称不能为空']
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
                    return $this->success('修改成功','admin/store_type/member_store_category');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑分类');
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
                    ['category_name', 'require', '分类名称不能为空'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/store_type/member_store_category');
                }
            } else {
                //非提交操作
                $this->assign('title','新增分类');
                return $this->fetch();
            }
        }

    }

    public function is_show2()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('member_store_category')->where('id',$post['id'])->update(['is_show'=>$post['is_show']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/store_type/member_store_category');
            }
        }
    }

    public function paixu2()
    {
        if($this->request->isPost()) {
            $post = $this->request->post();
            $i = 0;
            foreach ($post['id'] as $k => $val) {
                $order = Db::name('member_store_category')->where('id',$val)->value('paixu');
                if($order != $post['paixu'][$k]) {
                    if(false == Db::name('member_store_category')->where('id',$val)->update(['paixu'=>$post['paixu'][$k]])) {
                        return $this->error('更新失败');
                    } else {
                        $i++;
                    }
                }
            }
            addlog();//写入日志
            return $this->success('成功更新'.$i.'个数据','admin/store_type/member_store_category');
        }
    }

    /*
     * 分类轮播图
     * */
    public function ms_banner_index(){
        $lists = Db::view('member_store_banner')
            ->order(['sort'=>'asc'])
            ->paginate(15,false);

        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 修改banner排序
     */
    public function edit_banner_sort(){
        $sort = input('post.sort',9999,'intval');
        $banner_id = input('post.id',0,'intval');
        if(!$sort || !$banner_id)return $this->error('参数缺失');

        ##修改排序
        $res = MemberStoreBanner::editSort($banner_id, $sort);
        if($res === false)return $this->error('操作失败');
        return $this->success('操作成功');
    }

    /*
     * 添加分类轮播图
     * */
    public function ms_banner_publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new MemberStoreBanner();

        //是正常添加操作
        if ($id > 0) {
            //是修改操作
            if ($this->request->isPost()) {
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
                $data = $model->where('id',$id)->find();
                if (empty($data)) {
                    return $this->error('id不正确');
                }

                $count = Db::name('member_store_banner')->count();

                if ($count >= 5) {
                    return $this->error('最多5张轮播图');
                }

                if ($post['store_id']) {
                    $type = Db::name('store')->where('id',$post['store_id'])->value('type');

                    if(!$type) {
                        return $this->error('店铺ID不正确');
                    }
                    if ($type == 1){
                        return $this->error('请填写会员店铺ID');
                    }
                }

                if (false == $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($operation_id=$id);//写入日志
                    return $this->success('修改成功','admin/store_type/ms_banner_index');
                }
            } else {
                //非提交操作
                $data = $model->where('id',$id)->find();
                if (!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑轮播图');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            //是新增操作
            if ($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                $post['create_time'] = time();
                if (false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/store_type/ms_banner_index');
                }
            } else {
                //非提交操作
                $this->assign('title','新增轮播图');
                return $this->fetch();
            }
        }

    }

    /*
     * 删除
     * */
    public function ms_banner_delete(){
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == Db::name('member_store_banner')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                addlog($id);//写入日志
                return $this->success('删除成功','admin/store_type/ms_banner_index');
            }
        }
    }
}