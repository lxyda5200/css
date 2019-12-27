<?php


namespace app\user_v7\model;


use think\Model;

class Scene extends Model
{

    protected $autoWriteTimestamp = false;

    protected $dateFormat = false;

    protected $resultSetType = '\think\Collection';

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

}