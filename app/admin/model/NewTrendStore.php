<?php


namespace app\admin\model;


use app\admin\validate\Operate;
use think\Exception;
use think\Model;

class NewTrendStore extends Model
{

    protected $autoWriteTimestamp = false;

    protected $insert = ['create_time', 'sort'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 添加时尚新潮店铺推荐
     * @param $stores
     * @param $new_trend_id
     * @throws Exception
     */
    public function add($stores, $new_trend_id){
        $data = [];
        $operate = new Operate();
        foreach($stores as $v){
            ##验证
            $check = $operate->scene('add_new_trend_store')->check($v);
            if(!$check)throw new Exception($operate->getError());
            $data[] = [
                'new_trend_id' => $new_trend_id,
                'store_id' => (int)$v['store_id'],
                'sort' => (int)$v['sort']
            ];
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('店铺绑定失败');
    }

    /**
     *
     * @param $new_trend_id
     * @throws Exception
     */
    public function del($new_trend_id){
        $res = $this->where(compact('new_trend_id'))->delete();
        if($res === false)throw new Exception('时尚新潮推荐店铺更新失败');
    }

}