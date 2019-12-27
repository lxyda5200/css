<?php


namespace app\admin\model;


use app\admin\validate\Operate;
use think\Exception;
use think\Model;

class NewTrendStyle extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增时尚新潮关联风格
     * @param $styles
     * @param $new_trend_id
     * @throws Exception
     */
    public function add($styles, $new_trend_id){
        $data = [];
        $operate = new Operate();
        foreach($styles as $v){
            $res = $operate->scene('add_new_trend_style')->check($v);
            if(!$res)throw new Exception($operate->getError());
            $data[]= [
                'style_id' => (int)$v['style_id'],
                'new_trend_id' => $new_trend_id,
                'type' => (int)$v['type']
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('关联风格添加失败');
    }

    /**
     * 获取风格名
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getTitleAttr($value, $data){
        return $data['type'] == 1?StyleStore::getStyleTitle($data['style_id']):StyleProduct::getStyleTitle($data['style_id']);
    }

    /**
     * 获取时尚新潮的风格
     * @param $new_trend_id
     * @return array
     */
    public static function getNewTrendStyleList($new_trend_id){
        return (new self())->where(['new_trend_id'=>$new_trend_id])->field('style_id,type,create_time as title')->select()->toArray();
    }

    /**
     * 删除时尚新潮关联风格
     * @param $new_trend_id
     * @throws Exception
     */
    public function del($new_trend_id){
        $res = $this->where(compact('new_trend_id'))->delete();
        if($res === false)throw new Exception('时尚新潮关联风格更新失败');
    }

}