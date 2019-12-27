<?php


namespace app\admin\model;


use app\admin\validate\Operate;
use think\Exception;
use think\Model;
use traits\model\SoftDelete;

class RoommateRecommendDetail extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 添加推荐商品
     * @param $store_list
     * @param $roommate_recommend_id
     * @throws Exception
     */
    public function add($store_list, $roommate_recommend_id){

        $operate = new Operate();
        $rule = [
            'title' => "require|max:30|min:2",
            'cover|宿友推荐店铺封面图' => 'require'
        ];

        $data = [];
        foreach($store_list as $v){
            ##验证
            $check = $operate->scene('roommate_recom_detail')->rule($rule)->check($v);
            if(!$check)throw new Exception($operate->getError());
            $data[] = [
                'roommate_recommend_id' => $roommate_recommend_id,
                'store_id' => intval($v['store_id']),
                'star' => floatval($v['star']),
                'cover' => trimStr($v['cover']),
                'title' => trimStr($v['title']),
                'recommended_reason' => trimStr($v['recommended_reason']),
                'sort' => intval($v['sort'])
            ];
        }
        ##操作添加
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('推荐店铺添加失败');

    }

    /**
     * 删除推荐商品
     * @param $roommate_recommend_id
     * @throws Exception
     */
    public function del($roommate_recommend_id){
        $res = $this->save(['delete_time'=>time()],compact('roommate_recommend_id'));
        if($res === false)throw new Exception('操作失败');
    }

}