<?php


namespace app\store\model;


use think\Db;
use think\Exception;
use think\Model;

class Brand extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = [
        'create_time'
    ];

    /**
     * 通过brand_name获取品牌信息
     * @param $brand_name
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getBrandInfoByBrandName($brand_name){
        return (new self())->where(['brand_name'=>$brand_name])->field('id')->find();
    }


    /**
     * 获取知名品牌列表
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function getFamousBrandList() {
        $page = input('page',1,'intval');
        $data = $this->alias('b')
            ->join(['brand_cate' => 'bc'], 'b.cate_id=bc.id')
            ->where(['b.status' => 1, 'b.is_open' => 1, 'b.type' => 1, 'bc.status' => 1])
            ->where('b.delete_time', 'null')
            ->field('b.brand_name, b.logo, bc.title, b.id as brand_id')
            ->order('b.sort desc')
            ->paginate(10, false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total'] / $data['per_page']);
        return $data;
    }

    /**
     * 增加品牌
     * @param $data
     * @return int|string
     */
    public static function add($data){
        return (new self())->insertGetId($data);
    }

    /**
     * 设置默认创建时间
     * @return int
     */
    protected function setCreateTimeAttr(){
        return time();
    }


    /**
     * 查询是否存在品牌名
     * @param $brand_name
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exitsBrandName($brand_name, $store_id) {
        $brand_id = Db::table('brand_store')->where(['store_id' => $store_id, 'type' => 2])->value('brand_id');
        return $this->where(['brand_name' => $brand_name, 'id' => ['neq', $brand_id]])->find();
    }


    /**
     * 添加自有品牌品牌故事
     * @param $data
     * @return string
     * @throws \think\exception\PDOException
     */
    public function insertSelfBrand($data) {
        $this->startTrans();
        try{
            # 添加品牌基本信息
            $baseInfo = [
                'brand_name' => $data['brand_name'],
                'cate_id' => $data['cate_id'],
                'logo' => $data['logo'],
                'type' => 2,
                'create_time' => time(),
//                'status' => $data['status']
                'status' => 1
            ];
            $res = $this->insertGetId($baseInfo);
            if(!$res)
                throw new Exception('操作失败3');

            # 添加品牌故事
            $brand_story = [
                'brand_id' => $res,
                'history' => $data['history'],
                'notion' => $data['notion'],
                'create_time' => time()
            ];

            $brand_story_id = BrandStory::addBrandStory($brand_story);
            if(!$brand_story_id)
                throw new Exception('操作失败4');

            # 添加品牌故事广告
            foreach ($data['ads'] as $k => $v) {
                $data['ads'][$k]['brand_story_id'] = $brand_story_id;
                $data['ads'][$k]['brand_id'] = $res;
            }

            $res1 = BrandStoryAds::addBrandStoryAds($data['ads']);
            if(!$res1)
                throw new Exception('操作失败5');

            # 品牌经典款设置
            foreach ($data['goods'] as $k => $v) {
                $data['goods'][$k]['brand_id'] = $res;
                $data['goods'][$k]['create_time'] = time();
            }
            $res2 = BrandProduct::addGoods($data['goods']);
            if(!$res2)
                throw new Exception('操作失败6');

            # 添加品牌店铺关系
            $data['brand_id'] = $res;
            $res3 = BrandStore::addRelation($data, 2);
            if(!$res3)
                throw new Exception('操作失败7');

            $this->commit();
            return true;
        }catch (Exception $exception) {
            $this->rollback();

            return $exception->getMessage();
        }
    }


    /**
     * 修改自有品牌品牌故事
     * @param $data
     * @return bool
     * @throws \think\exception\PDOException
     */
    public function updateSelfBrand($data) {
        $this->startTrans();
        try{
            # 修改品牌基本信息
            $baseInfo = [
                'brand_name' => $data['brand_name'],
                'cate_id' => $data['cate_id'],
                'logo' => $data['logo'],
                'type' => 2,
//                'status' => $data['status']
                'status' => 1
            ];
            $res = $this->where(['id' => $data['brand_id']])->update($baseInfo);
            if($res === false)
                throw new Exception('操作失败');

            # 修改品牌故事
            $brand_story = [
                'history' => $data['history'],
                'notion' => $data['notion']
            ];

            $brand_story_id = BrandStory::updateBrandStory($data['brand_id'], $brand_story);
            if($brand_story_id === false)
                throw new Exception('操作失败');

            # 修改品牌故事广告
            $brand_story_id = BrandStory::getStoryId($data['brand_id']);
            $res3 = BrandStoryAds::delBrandStoryAds($data['brand_id']);
            if(!$res3)
                throw new Exception('操作失败');

            foreach ($data['ads'] as $k => $v) {
                $data['ads'][$k]['brand_story_id'] = $brand_story_id;
                $data['ads'][$k]['brand_id'] = $data['brand_id'];
            }

            $res1 = BrandStoryAds::addBrandStoryAds($data['ads']);
            if(!$res1)
                throw new Exception('操作失败');

            # 品牌经典款设置修改
            $res4 = BrandProduct::delGoods($data['brand_id']);
            if(!$res4)
                throw new Exception('操作失败');

            foreach ($data['goods'] as $k => $v) {
                $data['goods'][$k]['brand_id'] = $data['brand_id'];
                $data['goods'][$k]['create_time'] = time();
            }
            $res2 = BrandProduct::addGoods($data['goods']);
            if(!$res2)
                throw new Exception('操作失败');


            # 更新品牌店铺关系
            $res3 = BrandStore::updateRelation($data, 2);
            if(!$res3)
                throw new Exception('操作失败');

            $this->commit();
            return true;
        }catch (Exception $exception) {
            $this->rollback();

//            return $exception->getMessage();
            return false;
        }
    }


    /**
     * 品牌基本信息
     * @param $brand_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function brandBaseInfo($brand_id) {
        return $this->alias('b')
            ->join(['brand_cate' => 'bc'], 'b.cate_id=bc.id')
            ->join(['brand_store' => 'bs'], 'bs.brand_id=b.id')
            ->where(['b.id' => $brand_id])
            ->field('b.brand_name, bc.title, bc.id as cate_id, b.logo, bs.is_show_story, bs.is_selected')
            ->find();
    }

}