<?php


namespace app\admin\model;


use think\Exception;
use think\Model;

class BusinessCircleImg extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    /**
     * 添加商圈图片
     * @param $circle_id
     * @param $imgs
     * @throws Exception
     */
    public function addCircleImg($circle_id, $imgs){
        if(empty($imgs))throw new Exception('请上传商圈封面图');
        $data = [];
        foreach($imgs as $v){
            #验证
            if(!$v['img_url'])throw new Exception('图片地址不能为空');
            $data[] = [
                'business_circle_id' => $circle_id,
                'img_url' => trimStr($v['img_url'])
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('商圈封面图添加失败');
    }

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 删除商圈封面
     * @param $circle_id
     */
    public function delCircleImgByCircleId($circle_id){
        $res = $this->where(['business_circle_id'=>$circle_id])->delete();
        if($res === false)throw new Exception('删除失败');
    }

}