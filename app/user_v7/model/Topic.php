<?php


namespace app\user_v7\model;


use think\Model;

class Topic extends Model
{

    protected $autoWriteTimestamp = false;

    protected $dateFormat = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取APP话题列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getUserDynamicTopic(){
        $page = input('post.page',1,'intval');
        $size = input('post.size',10,'intval');

        $where = [
            'status' => 1,
            'client_type' => 2,
            'user_id' => 0
        ];
        ##【参与讨论数组成】
        ## 浏览量（话题本身+其他动态）+点赞人数(关联动态)+分享数（话题本身+其他动态）+收藏数(关联动态)+评论数(关联动态)
        $list = $this->where($where)
            ->field('id,title,description,list_bg_cover,(total_read_number+read_number+praise_number+share_number+total_share_number+total_comment_number) as join_number')
            ->order('join_number','desc')
            ->paginate($size,false,['page'=>$page])
            ->toArray();

        $list['max_page'] = ceil($list['total']/$list['per_page']);

        return $list;
    }

    /**
     * 处理话题标题
     * @param $val
     * @return string
     */
    public function getTitleAttr($val){
        return $val?"#{$val}#":"";
    }

}