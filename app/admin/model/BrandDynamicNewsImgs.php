<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use app\admin\validate\Brand;

class BrandDynamicNewsImgs extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增时尚动态new banner
     * @param $dynamic_news_id
     * @param $imgs
     * @throws Exception
     */
    public static function add($dynamic_news_id, $imgs){
        $brand = new Brand();
        $img_data = [];
        foreach($imgs as $v){
            #验证
            $check = $brand->scene('add_brand_dynamic_news_imgs')->check($v);
            if(!$check)throw new Exception($brand->getError());
            $img_data[] = [
                'dynamic_news_id' => $dynamic_news_id,
                'img' => trimStr($v['url']),
                'is_cover' => intval($v['is_cover']),
                'sort' => intval($v['sort'])
            ];
        }
        $res = (new self())->isUpdate(false)->saveAll($img_data);
        if($res === false)throw new Exception('时尚动态news广告图添加失败');
    }

    /**
     * 删除时尚动态news banner
     * @param $dynamic_news_id
     * @return int
     */
    public static function del($dynamic_news_id){
        return (new self())->where(['dynamic_news_id'=>$dynamic_news_id])->delete();
    }

}