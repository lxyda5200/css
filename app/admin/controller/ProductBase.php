<?php


namespace app\admin\controller;

use app\admin\model\CateProduct;
use app\admin\model\ProductStyleProduct;
use app\admin\model\StyleProduct;
use app\admin\validate\ProductBase as ProductBaseValidate;
use think\Db;
use think\Exception;
use app\admin\model\Product;


class ProductBase extends ApiBase
{

    /**
     * 商品分类--列表
     * @param ProductBaseValidate $productBase
     * @param CateProduct $cateProduct
     * @return \think\response\Json
     */
    public function cateProductList(ProductBaseValidate $productBase, CateProduct $cateProduct){
        try{

            ##验证
            $res = $productBase->scene('cate_product_list')->check(input());
            if(!$res)throw new Exception($productBase->getError());

            ##逻辑
            $page = input('post.page',1,'intval');
            $data = $cateProduct->field('id,title,suit')->paginate(15,false,['page'=>$page])->toArray();

            ##返回
            $data['max_page'] = ceil($data['total']/$data['per_page']);
            return json(self::callback(1,'',$data));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 商品分类--新增
     * @param ProductBaseValidate $productBase
     * @param CateProduct $cateProduct
     * @return \think\response\Json
     */
    public function addCateProduct(ProductBaseValidate $productBase, CateProduct $cateProduct){
        try{

            ##验证
            $res = $productBase->scene('add_cate_product')->check(input());
            if(!$res)throw new Exception($productBase->getError());

            ##逻辑
            $title = input('post.title','','addslashes,strip_tags,trim');
            $suit = input('post.suit',0,'intval');

            $res = $cateProduct->add(compact('title','suit'));
            if($res === false)throw new Exception('添加失败');

            return json(self::callback(1,'添加成功'));

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }

    }

    /**
     * 商品分类--修改
     * @param ProductBaseValidate $productBase
     * @param CateProduct $cateProduct
     * @return \think\response\Json
     */
    public function editCateProduct(ProductBaseValidate $productBase, CateProduct $cateProduct){
        try{

            $id = input('id',0,'intval');

            $type = input('type',0,'intval');  ##类型：0.查询；1.更新

            if($type){##处置
                ##验证
                $rule = [
                    'title|商品分类' => "require|max:6|min:1|unique:cate_product,title^suit,{$id}",
                    'suit|适用人群' => "require|number|>=:1|<=:4|unique:cate_product,suit^title,{$id}",
                ];
                $res = $productBase->scene('edit_cate_product')->rule($rule)->check(input());
                if(!$res)throw new Exception($productBase->getError());

                ##逻辑
                $title = input('post.title','','addslashes,strip_tags,trim');
                $suit = input('post.suit',0,'intval');

                $res = $cateProduct->edit($id,compact('title','suit'));
                if($res === false)throw new Exception('修改失败');

                return json(self::callback(1,'修改成功'));
            }else{##查询
                ##验证
                $res = $productBase->scene('edit_cate_product_info')->check(input());
                if(!$res)throw new Exception($productBase->getError());

                ##逻辑
                $info = $cateProduct->getInfo($id);
                if(!$info)throw new Exception('数据不存在或已删除');

                return json(self::callback(1,'',$info));
            }

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 商品分类--删除
     * @param ProductBaseValidate $productBase
     * @param CateProduct $cateProduct
     * @return \think\response\Json
     */
    public function delCateProduct(ProductBaseValidate $productBase, CateProduct $cateProduct){
        ##验证
        $res = $productBase->scene('del_cate_product')->check(input());
        if(!$res)return json(self::callback(0,$productBase->getError()));

        ##逻辑
        $id = input('post.id',0,'intval');
        try{
            Db::startTrans();
            ##删除商品分类
            $res = $cateProduct->delCateProduct($id);
            if($res === false)throw new Exception('删除失败');
            ##清除商品的分类绑定
            $res = Product::updateProCate($id);
            if($res === false)throw new Exception('商品分类更新失败');

            Db::commit();
            return json(self::callback(1,'删除成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品风格--列表
     * @param ProductBaseValidate $productBase
     * @param StyleProduct $styleProduct
     * @return \think\response\Json
     */
    public function styleProductList(ProductBaseValidate $productBase, StyleProduct $styleProduct){
        try{
            ##验证
            $res = $productBase->scene('style_product_list')->check(input());
            if(!$res)throw new Exception($productBase->getError());

            ##逻辑
            $page = input('post.page',1,'intval');
            $data = $styleProduct->field('id,title')->paginate(15,false,['page'=>$page])->toArray();

            ##返回
            $data['max_page'] = ceil($data['total']/$data['per_page']);
            return json(self::callback(1,'',$data));

        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品风格--新增
     * @param ProductBaseValidate $productBase
     * @param StyleProduct $styleProduct
     * @return \think\response\Json
     */
    public function addStyleProduct(ProductBaseValidate $productBase, StyleProduct $styleProduct){
        try{
            ##验证
            $rule = [
                'title|商品风格' => "require|max:6|min:1|unique:style_product,title"
            ];
            $res = $productBase->scene('add_style_product')->rule($rule)->check(input());
            if(!$res)throw new Exception($productBase->getError());

            ##逻辑
            $title = input('post.title','','addslashes,strip_tags,trim');
            $res = $styleProduct->add(compact('title'));
            if(!$res)throw new Exception('添加失败');

            return json(self::callback(1,'添加成功'));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品风格--修改
     * @param ProductBaseValidate $productBase
     * @param StyleProduct $styleProduct
     * @return \think\response\Json
     */
    public function editStyleProduct(ProductBaseValidate $productBase, StyleProduct $styleProduct){
        try{

            $id = input('id',0,'intval');

            $type = input('type',0,'intval');  ##类型：0.查询；1.更新

            if($type){##处置
                ##验证
                $rule = [
                    'title|商品风格' => "require|max:6|min:1|unique:style_product,title,{$id}"
                ];
                $res = $productBase->scene('edit_style_product')->rule($rule)->check(input());
                if(!$res)throw new Exception($productBase->getError());

                ##逻辑
                $title = input('post.title','','addslashes,strip_tags,trim');

                $res = $styleProduct->edit($id,compact('title'));
                if($res === false)throw new Exception('修改失败');

                return json(self::callback(1,'修改成功'));
            }else{##查询
                ##验证
                $res = $productBase->scene('edit_cate_product_info')->check(input());
                if(!$res)throw new Exception($productBase->getError());

                ##逻辑
                $info = $styleProduct->getInfo($id);
                if(!$info)throw new Exception('数据不存在或已删除');

                return json(self::callback(1,'',$info));
            }

        }catch(Exception $e){

            return json(self::callback(0,$e->getMessage()));

        }
    }

    /**
     * 商品风格--删除
     * @param ProductBaseValidate $productBase
     * @param StyleProduct $styleProduct
     * @param ProductStyleProduct $productStyleProduct
     * @return \think\response\Json
     */
    public function delStyleProduct(ProductBaseValidate $productBase, StyleProduct $styleProduct, ProductStyleProduct $productStyleProduct){
        ##验证
        $res = $productBase->scene('del_style_product')->check(input());
        if(!$res)return json(self::callback(0,$productBase->getError()));

        ##逻辑
        $id = input('post.id',0,'intval');
        try{
            Db::startTrans();
            ##删除商品分类
            $res = $styleProduct->delCateProduct($id);
            if($res === false)throw new Exception('删除失败');
            ##清除商品的风格绑定
            $res = $productStyleProduct->delStyleProduct($id);
            if($res === false)throw new Exception('商品分类更新失败');

            Db::commit();
            return json(self::callback(1,'删除成功'));
        }catch(Exception $e){
            Db::rollback();
            return json(self::callback(0,$e->getMessage()));
        }
    }

}