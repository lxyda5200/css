<?php


namespace app\admin\controller;


use app\admin\model\GoodsCategory;
use app\admin\repository\implementses\RBrandCate;
use app\user\common\Base;
use app\admin\validate\BrandCate as BrandCateValidate;

class BrandCate extends Base
{
    /**
     * 构建子孙树
     * @param $a
     * @param int $pid
     * @return array
     */
    private function makeTree($a,$pid = 0){
        $tree = array();
        foreach ($a as $v) {
            if ($v['pid'] == $pid) {
                $v['children'] = $this->makeTree($a, $v['id']);
                if ($v['children'] == null) {
                    unset($v['children']);
                }

                $tree[] = $v;
            }
        }
        return $tree;
    }


    /**
     * 获取分类列表
     * @param RBrandCate $brandCate
     */
    public function cateList(RBrandCate $brandCate) {
        $params = input('param.');
        $where = ['status' => 1];
        if(isset($params['title']) && !empty($params['title']))
            $where['cate_name'] = ['like', "%{$params['title']}%"];
        if(isset($params['level']) && !empty($params['level']))
            $where['level'] = $params['level'];
        if(isset($params['pid']) && !empty($params['pid']))
            $where['pid'] = $params['pid'];
        $data = $brandCate->getList($where, isset($params['type'])?$params['type']:1);
        return json(['status' => 1, 'msg' => 'success', 'data' => $data]);
    }


    /**
     * 添加分类
     * @param RBrandCate $brandCate
     */
    public function addCate(RBrandCate $brandCate) {
        $params = input('post.');
        # 数据验证
        $validate = new BrandCateValidate();
        if(!$validate->scene('save')->check($params))
            return json(['status' => 0, 'msg' => $validate->getError(), 'data' => null]);

        $res = $brandCate->addCate(intval($params['pid']), $params['title'], intval($params['level']));
        if(!$res)
            return json(['status' => 0, 'msg' => '添加失败', 'data' => null]);

        return json(['status' => 1, 'msg' => '添加成功', 'data' => null]);
    }


    /**
     * 获取修改分类信息
     * @param RBrandCate $brandCate
     */
    public function editCate(RBrandCate $brandCate) {
        $params = input('post.');
        # 数据验证
        $validate = new BrandCateValidate();
        if(!$validate->scene('edit')->check($params))
            $this->ajaxReturn(0, $validate->getError());

        $data = $brandCate->editCate(intval($params['id']));
        if(!$data)
            return json(['status' => 0, 'msg' => '参数错误', 'data' => null]);

        return json(['status' => 1, 'msg' => 'success', 'data' => $data]);
    }


    /**
     * 修改分类信息
     * @param RBrandCate $brandCate
     */
    public function updateCate(RBrandCate $brandCate) {
        $params = input('post.');
        # 数据验证
        $validate = new BrandCateValidate();
        if(!$validate->scene('save')->check($params))
            return json(['status' => 0, 'msg' => $validate->getError(), 'data' => null]);

        $res = $brandCate->updateCate(intval($params['pid']), trimStr($params['title'][0]), intval($params['id']), intval($params['level']));
        if($res === false)
            return json(['status' => 0, 'msg' => '修改失败', 'data' => null]);

        return json(['status' => 1, 'msg' => '修改成功', 'data' => null]);
    }


    /**
     * 删除分类信息
     * @param RBrandCate $brandCate
     */
    public function delCate(RBrandCate $brandCate) {
        $params = input('post.');
        # 数据验证
        $validate = new BrandCateValidate();
        if(!$validate->scene('del')->check($params))
            $this->ajaxReturn(0, $validate->getError());

        # 查找下级
        $level = GoodsCategory::get(intval($params['id']))['level'];
        $id_arr = [$params['id']];
        while ($level > 1) {
            $ids = $brandCate->getChildren($id_arr);
            $id_arr1 = array_column($ids, 'id');
            $id_arr = array_merge($id_arr, $id_arr1);
            $level--;
        }

        $res = $brandCate->delCate($id_arr);
        if(!$res)
            $this->ajaxReturn(0, '删除失败');

        $this->ajaxReturn(1, '删除成功');
    }
}