<?php


namespace app\store\model;


use think\Exception;
use think\Model;
use app\store\validate\Dynamic;

class DynamicStyle extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    public $dynamic_id;

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 新增活动风格绑定
     * @param $dynamic_id
     * @param $styles
     * @return array|false
     * @throws Exception
     */
    public function add($dynamic_id, $styles){
        $this->dynamic_id = $dynamic_id;
        $dynamic = new Dynamic();
        $data = [];
        foreach($styles as $v){
            $check = $dynamic->scene('add_dynamic_style')->check($v);
            if(!$check)throw new Exception($dynamic->getError());
            $data[] = [
                'style_id' => intval($v['style_id']),
                'type' => intval($v['type']),
                'dynamic_id' => $dynamic_id
            ];
        }

        $res = $this->isUpdate(false)->saveAll($data);
        return $res;
    }

    /**
     * 获取动态的风格
     * @param $dynamic_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getDynamicStyle($dynamic_id){
        return (new self())->where(compact('dynamic_id'))->field('id,style_id,type')->select();
    }

    /**
     * 删除动态下的风格
     * @param $dynamic_id
     */
    public function del($dynamic_id){
        $res = $this->where(compact('dynamic_id'))->delete();
        if($res === false)throw new Exception('操作失败');
    }

}