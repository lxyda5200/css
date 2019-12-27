<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;

class Scene extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取场景数
     * @return array
     */
    public function getSceneTree(){
        $list = $this->getSceneList();
        $tree = $this->getTree($list);
        return $tree;
    }

    /**
     * 获取场景列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getSceneList(){
        $list =  $this->where(['status'=>1])->field('id,title,level,p_id')->select();
        return empty($list)?$list:json_decode(json_encode($list),true);
    }

    /**
     * 生成场景树
     * @param $list
     * @return array
     */
    public function getTree($list){
        $tree = [];
        foreach($list as $v){
            if($v['level'] == 1){
                $tree[] = $v;
            }
        }
        foreach($tree as &$v){
            foreach($list as $vv){
                if($vv['level'] == 2 && $vv['p_id'] == $v['id'])$v['child'][] = $vv;
            }
        }
        return $tree;
    }

    /**
     * 增加场景使用数
     * @param $id
     * @return int|true
     */
    public function incUseNumber($id){
        return $this->where(compact('id'))->setInc('use_number');
    }

    /**
     * 减少场景使用数
     * @param $id
     * @return int|true
     */
    public function decUseNumber($id){
        return $this->where(compact('id'))->setDec('use_number');
    }

    /**
     * 获取主题主id
     * @param $id
     * @return mixed
     */
    public static function getMainId($id){
        return (new self())->where(['id'=>$id])->value('p_id');
    }

}