<?php


namespace app\store\model;


use think\Db;
use think\Exception;
use think\Model;

class BrandDynamicArticle extends Model
{
    protected $table = 'brand_dynamic_article';

    /**
     * 添加新闻news
     * @param $data
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function addNews($data) {
        self::startTrans();
        try{
            $data1 = [
                'title' => $data['title'],
                'cover' => $data['cover'],
                'content' => $data['content'],
                'brand_dynamic_id' => $data['brand_dynamic_id'],
                'type' => $data['type'],
                'create_time' => time()
            ];
            $dynamic_news_id = self::insertGetId($data1);
            if(!$dynamic_news_id)
                throw new Exception(false);

            foreach ($data['imgs'] as $k => $v) {
                $data['imgs'][$k]['dynamic_news_id'] = $dynamic_news_id;
                $data['imgs'][$k]['create_time'] = time();
            }

            $res = self::addNewsImg($data['imgs']);
            if(!$res)
                throw new Exception(false);

            self::commit();
            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }

    }


    /**
     * 更新新闻
     * @param $id
     * @param $data
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function updateNews($id, $data) {
        self::startTrans();
        try{
            $data1 = [
                'title' => $data['title'],
                'cover' => $data['cover'],
                'content' => $data['content'],
                'brand_dynamic_id' => $data['brand_dynamic_id'],
                'type' => $data['type']
            ];
            $dynamic_news_id = self::updateVideo($id, $data1);
            if($dynamic_news_id === false)
                throw new Exception(false);

            foreach ($data['imgs'] as $k => $v) {
                $data['imgs'][$k]['dynamic_news_id'] = $id;
                $data['imgs'][$k]['create_time'] = time();
            }

            $res1 = self::delNewsImg($id);
            if(!$res1)
                throw new Exception(false);

            $res = self::addNewsImg($data['imgs']);
            if(!$res)
                throw new Exception(false);

            self::commit();
            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 添加影片集合
     * @param $data
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function addVideos($data) {
        self::startTrans();
        try{
            $data1 = [
                'title' => $data['title'],
                'cover' => $data['cover'],
                'brand_dynamic_id' => $data['brand_dynamic_id'],
                'type' => $data['type'],
                'create_time' => time()
            ];
            $dynamic_article_id = self::insertGetId($data1);
            if(!$dynamic_article_id)
                throw new Exception(false);

            foreach ($data['imgs'] as $k => $v) {
                $data['imgs'][$k]['dynamic_article_id'] = $dynamic_article_id;
                $data['imgs'][$k]['create_time'] = time();
            }

            $res = self::addVideoImgs($data['imgs']);
            if(!$res)
                throw new Exception(false);

            self::commit();
            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 更新影集信息
     * @param $id
     * @param $data
     * @return bool|string
     * @throws \think\exception\PDOException
     */
    public static function updateVideos($id, $data) {
        self::startTrans();
        try{
            $data1 = [
                'title' => $data['title'],
                'cover' => $data['cover'],
                'brand_dynamic_id' => $data['brand_dynamic_id'],
                'type' => $data['type']
            ];
            $dynamic_article_id = self::updateVideo($id, $data1);
            if($dynamic_article_id === false)
                throw new Exception(false);

            foreach ($data['imgs'] as $k => $v) {
                $data['imgs'][$k]['dynamic_article_id'] = $id;
                $data['imgs'][$k]['create_time'] = time();
            }

            $res1 = self::delPictureImg($id);
            if(!$res1)
                throw new Exception(false);

            $res = self::addVideoImgs($data['imgs']);
            if(!$res)
                throw new Exception(false);

            self::commit();
            return true;
        }catch (Exception $exception) {
            self::rollback();
            return $exception->getMessage();
        }
    }


    /**
     * 添加视频
     * @param $data
     * @return int|string
     */
    public static function addVideo($data) {
        return self::insert($data);
    }


    /**
     * 修改视频
     * @param $id
     * @param $data
     * @return BrandDynamicArticle
     */
    public static function updateVideo($id, $data) {
        return self::where(['id' => $id])->update($data);
    }


    /**
     * 添加新闻news图片
     * @param $data
     * @return int|string
     */
    public function addNewsImg($data) {
        return Db::table('brand_dynamic_news_imgs')
            ->insertAll($data);
    }



    /**
     * 添加影片集
     * @param $data
     * @return int|string
     */
    public function addVideoImgs($data) {
        return Db::table('brand_dynamic_picture')
            ->insertAll($data);
    }


    /**
     * 删除动态
     * @param $id
     * @return bool
     * @throws \think\exception\PDOException
     */
    public function delDynamic($id) {
        $this->startTrans();
        try{
            $param = $this->where(['id' => $id])->field('id, type')->find();
            if($param['type'] == 3) {
                $res = $this->delNewsImg($param['id']);
                if(!$res)
                    throw new Exception(false);
            }else if ($param['type'] == 2) {
                $res = $this->delPictureImg($param['id']);
                if(!$res)
                    throw new Exception(false);
            }

            $res1 = $this->where(['id' => $id])->delete();
            if(!$res1)
                throw new Exception(false);

            $this->commit();
            return true;
        }catch (Exception $exception) {
            $this->rollback();
            return true;
        }

    }


    /**
     * 删除新闻相关图片
     * @param $dynamic_news_id
     * @return int
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function delNewsImg($dynamic_news_id) {
        return Db::table('brand_dynamic_news_imgs')->where(['dynamic_news_id' => $dynamic_news_id])->delete();
    }


    /**
     * 删除影集相关图片
     * @param $dynamic_article_id
     * @return int
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function delPictureImg($dynamic_article_id) {
        return Db::table('brand_dynamic_picture')->where(['dynamic_article_id' => $dynamic_article_id])->delete();
    }


    /**
     * 更改显示状态
     * @param $id
     * @param $status
     * @return BrandDynamicArticle
     */
    public function changeView($id, $status) {
        return $this->where(['id' => $id])->update(['status' => $status]);
    }


    /**
     * 获取咨询列表
     * @param $brand_dynamic_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList($brand_dynamic_id) {
        return $this->where(['brand_dynamic_id' => $brand_dynamic_id])->paginate(3);
    }


    /**
     * 获取信息
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo($id) {
        $base_info = $this->where(['id' => $id])
            ->field('id, title, cover, type, content, video_url, video_cover, media_id, media_desc')->find();
        switch ($base_info['type']) {
            case 2:
                $imgs = $this->getPictureImg($base_info['id']);
                $base_info['imgs'] = $imgs;
                break;
            case 3:
                $imgs = $this->getNewsImg($base_info['id']);
                $base_info['imgs'] = $imgs;
                break;
            default:
                break;
        }
        return $base_info;
    }


    /**
     * 获取影集照片
     * @param $dynamic_article_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPictureImg($dynamic_article_id) {
        return Db::table('brand_dynamic_picture')->where(['dynamic_article_id' => $dynamic_article_id])
            ->field('create_time', true)->order('sort asc')->select();
    }


    /**
     * 获取新闻news照片
     * @param $dynamic_news_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getNewsImg($dynamic_news_id) {
        return Db::table('brand_dynamic_news_imgs')->where(['dynamic_news_id' => $dynamic_news_id])
            ->field('create_time', true)->order('sort asc')->select();
    }


    /**
     * 更改咨询集排序
     * @param $id
     * @param $store_id
     * @param $sort
     * @return bool
     * @throws Exception
     */
    public static function changeSort($id, $store_id, $sort) {
        $brand_dynamic_id = Db::table('brand_store')->alias('bs')
            ->join(['brand_dynamic' => 'bsy'], 'bs.brand_id=bsy.brand_id')
            ->where(['bs.store_id' => $store_id, 'type' => 2])->value('bsy.id');
        if(!$brand_dynamic_id)
            return false;

        ##获取以前的排序
        $prev_sort = self::where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)
            return true;
        ##更新
        if($prev_sort > $sort){
            $ids = self::where(['sort'=>['BETWEEN',[$sort,$prev_sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setInc('sort',1);
        }else{
            $ids = self::where(['sort'=>['BETWEEN',[$prev_sort,$sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
            foreach($ids as $v)
                self::where(['id'=>$v])->setDec('sort',1);
        }
        $res = self::where(['id'=>$id])->setField('sort', $sort);
        if($res === false)
            return false;
        return true;
    }
}