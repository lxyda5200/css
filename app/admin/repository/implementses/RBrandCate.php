<?php
namespace app\admin\repository\implementses;

use app\admin\model\BrandCate;
use app\admin\model\GoodsCategory;
use app\admin\repository\interfaces\IBrandCate;

class RBrandCate implements IBrandCate
{

    public function getList($where, $type)
    {
        // TODO: Implement getList() method.
        if($type == 2) {
            return GoodsCategory::where($where)->field('id, cate_name, level')
                ->select();
        }else {
            return GoodsCategory::where($where)->field('id, cate_name, level')
                ->paginate(10);
        }
    }

    public function addCate($pid, $title, $level)
    {
        // TODO: Implement addCate() method.
        $data = [];
        foreach ($title as $k => $v) {
            $data[$k]['cate_name'] = $v;
            $data[$k]['pid'] = $pid;
            $data[$k]['level'] = $level;
            $data[$k]['create_time'] = time();
        }
        $goodsCategory = new GoodsCategory();
        return $goodsCategory->insertAll($data);
    }

    public function editCate($id)
    {
        // TODO: Implement editCate() method.
        return GoodsCategory::get($id);
    }

    public function updateCate($pid, $title, $id, $level)
    {
        // TODO: Implement updateCate() method.
        return GoodsCategory::where(['id' => $id])->update(['pid' => $pid, 'cate_name' => $title, 'level' => $level]);
    }

    public function delCate($id_arr)
    {
        // TODO: Implement delCate() method.
        return GoodsCategory::where(['id' => ['in', $id_arr]])->update(['status' => 0]);
    }

    public function getChildren($id)
    {
        // TODO: Implement getChildren() method.
        return GoodsCategory::where(['pid' => ['in', $id]])->field('id, cate_name, pid')->select();
    }
}