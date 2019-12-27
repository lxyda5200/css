<?php


namespace app\admin\controller;


use app\admin\model\IndustryCategory;

class IndustryCate extends ApiBase
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
     * 获取行业分类列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getIndustryCateList() {
        $params = input('post.');
        $where = ['status' => 1];
        if(isset($params['cate_name']) && !empty($params['cate_name']))
            $where['cate_name'] = ['like', "%{$params['cate_name']}%"];
        if(isset($params['id']) && !empty($params['id']))
            $where['pid'] = $params['id'];
        if($params['type'] == 1) {
            $list = IndustryCategory::where($where)->field('id, pid, cate_name, level')
                ->order('sort')->paginate(10);
        }else {
            $list = IndustryCategory::where(['status' => 1, 'level' => 1])->field('id, pid, cate_name, level')
                ->order('sort')->select();
        }

        if(!$list)
            return json(self::callback(0, '暂无数据'));

        return json(self::callback(1, 'success', $list));
    }


    /**
     * 添加行业分类
     * @return \think\response\Json
     */
    public function addIndustryCate() {
        $params = input('post.');
        # 数据验证
        $validate = new \app\admin\validate\IndustryCategory();
        if(!$validate->scene('add')->check($params))
            return json(self::callback(0, $validate->getError()));

        $insert_data = [];
        foreach ($params['cate_name'] as $k => $v) {
            $insert_data[$k]['pid'] = $params['pid'];
            $insert_data[$k]['cate_name'] = $v;
            $insert_data[$k]['level'] = $params['level'];
            $insert_data[$k]['create_time'] = time();
        }

        $industry_category = new IndustryCategory();
        $res = $industry_category->insertAll($insert_data);
        if(!$res)
            return json(self::callback(0, '添加失败'));

        return json(self::callback(1, '添加成功'));
    }


    /**
     * 修改行业分类信息
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editIndustryCate() {
        $id = input('id', 0, 'intval');
        if(!$id)
            return json(self::callback(0, '缺少参数id'));

        if(request()->isPost()) {
            $params = input('post.');
            # 数据验证
            $validate = new \app\admin\validate\IndustryCategory();
            if(!$validate->scene('add')->check($params))
                return json(self::callback(0, $validate->getError()));

            unset($params['id']);
            $res = IndustryCategory::update($params, ['id' => $id]);
            if($res === false)
                return json(self::callback(0, '修改失败'));

            return json(self::callback(1, '修改成功'));
        }else {
            $detail = IndustryCategory::where(['id' => $id])->field('id, pid, cate_name, level, sort')->find();
            if(!$detail)
                return json(self::callback(0, '未查询到数据'));

            return json(self::callback(1, 'success', $detail));
        }
    }


    /**
     * 删除行业分类
     * @return \think\response\Json
     */
    public function delIndustryCate() {
        $params = input('post.');
        # 数据验证
        $validate = new \app\admin\validate\IndustryCategory();
        if(!$validate->scene('del')->check($params))
            return json(self::callback(0, $validate->getError()));

        # 查询删除分类含有的子分类
        $ids = IndustryCategory::where(['pid' => $params['id']])->column('id');
        $ids[] = $params['id'];
        $res = IndustryCategory::where(['id' => ['in', $ids]])->update(['status' => 0]);
        if($res === false)
            return json(self::callback(0, '删除失败'));

        return json(self::callback(1, '删除成功'));
    }
}