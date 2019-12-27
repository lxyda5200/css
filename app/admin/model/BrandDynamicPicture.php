<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use app\admin\validate\Brand;

class BrandDynamicPicture extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增时尚动态影集
     * @param $dynamic_article_id
     * @param $imgs
     * @throws Exception
     */
    public static function add($dynamic_article_id, $imgs){
        $img_data = [];
        $brand = new Brand();
        foreach($imgs as $vv){
            $check = $brand->scene('add_brand_dynamic_picture')->check($vv);
            if(!$check)throw new Exception($brand->getError());
            $img_data[] = [
                'dynamic_article_id' => $dynamic_article_id,
                'url' => trimStr($vv['url']),
                'desc' => trimStr($vv['desc']),
                'is_cover' => intval($vv['is_cover']),
                'sort' => intval($vv['sort'])
            ];
        }
        $res = (new self())->isUpdate(false)->saveAll($img_data);
        if($res === false)throw new Exception('时尚动态影集添加失败');
    }

    /**
     * 删除动态资讯影集图片
     * @param $dynamic_article_id
     * @return int
     */
    public static function del($dynamic_article_id){
        return (new self())->where(['dynamic_article_id'=>$dynamic_article_id])->delete();
    }

}