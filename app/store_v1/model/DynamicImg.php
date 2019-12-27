<?php


namespace app\store_v1\model;


use think\Exception;
use think\Model;
use app\store_v1\validate\Dynamic;

class DynamicImg extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 添加动态图片
     * @param $dynamic_id
     * @param $data
     * @return array|false
     */
    public function add($dynamic_id, $data){
        $type = $data['type'];
        $imgs = $data['imgs'];
        $dynamic = new Dynamic();
        $img_ids = [];
        if($type == 1){  //图片
            foreach($imgs as $k => $v){
                ##验证
                $check = $dynamic->scene('add_dynamic_img')->check($v);
                if(!$check)throw new Exception($dynamic->getError());
                $data = [
                    'img_url' => trimStr($v['src']),
                    'is_cover' => intval($v['is_cover']),
                    'dynamic_id' => $dynamic_id,
                    'type' => 1
                ];
                $idx = $this->isUpdate(false)->insertGetId($data);
                if($idx === false)throw new Exception('图片新增失败');
                $img_ids[$k] = $idx;
            }
        }else{  //视频
            foreach($imgs as $k=> $v){
                ##验证
                $check = $dynamic->scene('add_dynamic_video')->check($v);
                if(!$check)throw new Exception($dynamic->getError());
                $data = [
                    'img_url' => trimStr($v['src']),
                    'is_cover' => intval($v['is_cover']),
                    'cover' => trimStr($v['cover']),
                    'media_id' => trimStr($v['video_id']),
                    'cover_status' => 2,
                    'video_type' => 2,
                    'dynamic_id' => $dynamic_id,
                    'type' => 2
                ];
                $res = $this->isUpdate(false)->save($data);
                if($res === false)throw new Exception('视频新增失败');
                $img_ids[$k] = $this->getLastInsID();
            }
        }
        return $img_ids;
    }

    /**
     * 删除动态下的图片
     * @param $dynamic_id
     */
    public function del($dynamic_id){
        $res = $this->where(compact('dynamic_id'))->delete();
        if($res === false)throw new Exception('操作失败');
    }

}