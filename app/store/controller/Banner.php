<?php


namespace app\store\controller;

use app\store\repository\implementses\StoreBanner;
use app\store\repository\interfaces\IStoreBanner;
use \app\store\validate\StoreBanner as StoreBannerValidate;

class Banner extends Base
{

    /**
     * 添加banner广告列表
     * @return \think\response\Json
     */
    public function addBanner()
    {
        $params = input('post.');
        # 数据验证
        $validate = new StoreBannerValidate();
        if(!$validate->scene('addBanner')->check($params))
            return json(self::callback(0, $validate->getError()));

        unset($params['token']);
        $res = StoreBanner::addBanner($params);
        if(!$res)
            return json(self::callback(0, '添加失败'));

        return json(self::callback(1, '添加成功'));
    }

    /**
     * 获取banner广告列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBannerList()
    {
        $params = input('post.');
        $data = StoreBanner::getList($params['store_id']);
        if(!$data)
            return json(self::callback(0, '暂无数据'));

        return json(self::callback(1, 'success', $data));
    }


    /**
     * 删除banner广告
     * @return \think\response\Json
     */
    public function delBanner()
    {
        $params = input('post.');
        # 数据验证
        $validate = new StoreBannerValidate();
        if(!$validate->scene('delBanner')->check($params))
            return json(self::callback(0, $validate->getError()));

        $res = StoreBanner::delBanner($params['id']);
        if(!$res)
            return json(self::callback(0, '删除失败'));

        return json(self::callback(1, '删除成功'));
    }


    /**
     * 编辑banner广告信息
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function editBanner()
    {
        $id = input('id', 0);
        if(!$id)
            return json(self::callback(0, '参数缺失'));

        if(request()->isPost()) {
            $params = input('post.');
            if(isset($params['id']))
                unset($params['id']);
            # 数据验证
            $validate = new StoreBannerValidate();
            if(!$validate->scene('addBanner')->check($params))
                return json(self::callback(0, $validate->getError()));

            # 修改数据
            unset($params['token']);
            unset($params['store_id']);
            switch ($params['type']) {
                case 2:
                    $params['content'] = '';
                    break;
                case 3:
                    $params['link'] = '';
                    break;
                default:
                    $params['link'] = '';
                    $params['content'] = '';
            }

            $res = StoreBanner::updateBannerInfo($id, $params);
            if($res === false)
                return json(self::callback(0, '修改失败'));

            return json(self::callback(1,'修改成功'));
        }else {
            # 获取单个banner广告信息
            $data = StoreBanner::getBannerInfo($id);
            if(!$data)
                return json(self::callback(0, '数据丢失'));

            return json(self::callback(1, 'success', $data));
        }
    }


    /**
     * 更改排序
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function sortBanner()
    {
        $params = input('post.');
        # 数据验证
        $validate = new StoreBannerValidate();
        if(!$validate->scene('sort')->check($params))
            return json(self::callback(0, $validate->getError()));

        # 排序
        $res = StoreBanner::changeSort($params['store_id'], $params['id'], $params['sort']);
        if(!$res)
            return json(self::callback(0, '操作失败'));

        return json(self::callback(1, '操作成功'));
    }
}