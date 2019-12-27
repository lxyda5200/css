<?php


namespace app\admin\controller;


use app\admin\model\StoreCateStore;
use app\admin\model\StoreStyleStore;
use app\admin\model\StyleStore;
use app\admin\validate\StoreBase as StoreBaseValidate;
use app\admin\model\CateStore;
use think\Db;
use think\Exception;

class StoreBase extends ApiBase
{

    /**
     * 门店主营分类--列表
     * @param StoreBaseValidate $storeBase
     * @param CateStore $cateStore
     * @return \think\response\Json\
     */
    public function cateStoreList(StoreBaseValidate $storeBase, CateStore $cateStore){
        try{

            ##验证
            $res = $storeBase->scene('cate_store_list')->check(input());
            if(!$res)throw new Exception($storeBase->getError());

            ##逻辑
            $page = input('post.page',1,'intval');
            $data = $cateStore->field('id,title')->paginate(15,false,['page'=>$page])->toArray();

            ##返回
            $data['max_page'] = ceil($data['total']/$data['per_page']);
            return json(self::callback(1,'',$data));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }

    }

    /**
     * 门店主营分类--添加
     * @param StoreBaseValidate $storeBase
     * @param CateStore $cateStore
     * @return \think\response\Json
     */
    public function addCateStore(StoreBaseValidate $storeBase, CateStore $cateStore){
        try{

            ##验证
            $res = $storeBase->scene('add_cate_store')->check(input());
            if(!$res)throw new Exception($storeBase->getError());

            ##逻辑
            $title = input('post.title','','addslashes,strip_tags,trim');

            $res = $cateStore->add(compact('title'));
            if($res === false)throw new Exception('新增失败');

            return json(self::callback(1,'新增成功'));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营分类--修改
     * @param StoreBaseValidate $storeBase
     * @param CateStore $cateStore
     * @return \think\response\Json
     */
    public function editCateStore(StoreBaseValidate $storeBase, CateStore $cateStore){
        try{

            $id = input('id',0,'intval');

            $type = input('type',0,'intval');  ##类型：0.查询；1.更新

            if($type){##处置
                ##验证
                $rule = [
                    'title|主营分类' => "require|max:6|min:1|unique:cate_store,title,{$id}"
                ];
                $res = $storeBase->scene('edit_cate_store')->rule($rule)->check(input());
                if(!$res)throw new Exception($storeBase->getError());

                ##逻辑
                $title = input('post.title','','addslashes,strip_tags,trim');

                $res = $cateStore->edit($id,$title);
                if($res === false)throw new Exception('修改失败');

                return json(self::callback(1,'修改成功'));
            }else{##查询
                ##验证
                $res = $storeBase->scene('edit_cate_store_info')->check(input());
                if(!$res)throw new Exception($storeBase->getError());

                ##逻辑
                $info = $cateStore->getInfo($id);
                if(!$info)throw new Exception('数据不存在或已删除');

                return json(self::callback(1,'',$info));
            }

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营分类--删除
     * @param StoreBaseValidate $storeBase
     * @param StoreCateStore $storeCateStore
     * @return \think\response\Json
     */
    public function delCateStore(StoreBaseValidate $storeBase, StoreCateStore $storeCateStore){

        ##验证
        $res = $storeBase->scene('del_cate_store')->check(input());
        if(!$res)return json(self::callback(0,$storeBase->getError()));

        ##逻辑
        $id = input('post.id',0,'intval');
        try{
            Db::startTrans();
            ##删除分类
            $res = CateStore::destroy($id);
            if($res === false)throw new Exception('删除失败');
            ##删除店铺与分类的关系
            $res = $storeCateStore->delByCateStore($id);
            if($res === false)throw new Exception('删除失败--店铺主营分类取消失败');

            Db::commit();

            return json(self::callback(1,'删除成功'));

        }catch(Exception $e){

            Db::rollback();
            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营风格--列表
     * @param StoreBaseValidate $storeBase
     * @param StyleStore $styleStore
     * @return \think\response\Json
     */
    public function styleStoreList(StoreBaseValidate $storeBase, StyleStore $styleStore){
        try{

            ##验证
            $res = $storeBase->scene('style_store_list')->check(input());
            if(!$res)throw new Exception($storeBase->getError());

            ##逻辑
            $page = input('post.page',1,'intval');
            $data = $styleStore->field('id,title')->paginate(15,false,['page'=>$page])->toArray();

            ##返回
            $data['max_page'] = ceil($data['total']/$data['per_page']);
            return json(self::callback(1,'',$data));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营风格--新增
     * @param StoreBaseValidate $storeBase
     * @param StyleStore $styleStore
     * @return \think\response\Json
     */
    public function addStyleStore(StoreBaseValidate $storeBase, StyleStore $styleStore){
        try{

            ##验证
            $rule = [
                'title' => "require|max:6|min:1|unique:style_store,title",
            ];
            $res = $storeBase->scene('add_style_store')->rule($rule)->check(input());
            if(!$res)throw new Exception($storeBase->getError());

            ##逻辑
            $title = input('post.title','','addslashes,strip_tags,trim');

            $res = $styleStore->add(compact('title'));
            if($res === false)throw new Exception('新增失败');

            return json(self::callback(1,'新增成功'));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营风格--修改
     * @param StoreBaseValidate $storeBase
     * @param StyleStore $styleStore
     * @return \think\response\Json
     */
    public function editStyleStore(StoreBaseValidate $storeBase, StyleStore $styleStore){
        try{

            $id = input('id',0,'intval');

            $type = input('type',0,'intval');  ##类型：0.查询；1.更新

            if($type){##处置
                ##验证
                $rule = [
                    'title|主营风格' => "require|max:6|min:1|unique:style_store,title,{$id}"
                ];
                $res = $storeBase->scene('edit_style_store')->rule($rule)->check(input());
                if(!$res)throw new Exception($storeBase->getError());

                ##逻辑
                $title = input('post.title','','addslashes,strip_tags,trim');

                $res = $styleStore->edit($id,$title);
                if($res === false)throw new Exception('修改失败');

                return json(self::callback(1,'修改成功'));
            }else{##查询
                ##验证
                $res = $storeBase->scene('edit_style_store_info')->check(input());
                if(!$res)throw new Exception($storeBase->getError());

                ##逻辑
                $info = $styleStore->getInfo($id);
                if(!$info)throw new Exception('数据不存在或已删除');

                return json(self::callback(1,'',$info));
            }

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 门店主营风格--删除
     * @param StoreBaseValidate $storeBase
     * @param StoreStyleStore $storeStyleStore
     * @return \think\response\Json
     */
    public function delStyleStore(StoreBaseValidate $storeBase, StoreStyleStore $storeStyleStore){
        ##验证
        $res = $storeBase->scene('del_style_store')->check(input());
        if(!$res)return json(self::callback(0,$storeBase->getError()));

        ##逻辑
        $id = input('post.id',0,'intval');
        try{
            Db::startTrans();
            ##删除分类
            $res = StyleStore::destroy($id);
            if($res === false)throw new Exception('删除失败');
            ##删除店铺与分类的关系
            $res = $storeStyleStore->delByCateStore($id);
            if($res === false)throw new Exception('删除失败--店铺主营风格取消失败');

            Db::commit();

            return json(self::callback(1,'删除成功'));

        }catch(Exception $e){

            Db::rollback();
            return json(self::callback(0,$e->getMessage()));

        }
    }

}