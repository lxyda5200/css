<?php


namespace app\wxapi_test\model;


use think\Model;

class TagFollowModel extends Model
{
    protected $pk = 'id';

    protected $table = 'tag_follow';

    public static function getUserFollowNoSort($uid,$page = 1,$size = 20,$fb_user_id = 0){
        $other = $fb_user_id == 0 ? $uid : $fb_user_id;

        $data =self::where(['f.user_id' => $other])
            -> alias('f')
            -> join('tag t', 'f.tag_id = t.id and t.status = 1')
            -> join('tag_follow tf', 'tf.tag_id = f.tag_id and tf.user_id = '. $uid,'left')
            -> field(['f.tag_id,f.id,t.title,t.description,IF(tf.create_time>0,1,0) is_follow'])
            -> page($page,$size)
            -> order('id desc') -> select();
        $count = self::where(['f.user_id' => $other])
            -> alias('f')
            -> join('tag t', 'f.tag_id = t.id and t.status = 1')
            -> field(['f.tag_id,f.id,t.title,t.description,IF(1=1,1,0) is_follow'])->count();
        $return = [
            'total' => $count,
            'max_page' => ceil(($count)/$size),
            'list' => $data,
        ];
        return $return;
    }

    /**
     *  获取用户关注标签动态数据 并排序
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserFollowData($uid,$page,$size){
        // 获取用户关注的标签数据 并组合成一维数组
        $data = self::where(['user_id' => $uid])-> field(['tag_id']) -> select();
        $followIds = array_column($data, 'tag_id');

        $temp = ''; // 组装正则查询条件
        foreach ($followIds as $k => $v){
            $temp .= "[{$v}]*,*";
        }
        $exp = 'REGEXP \''."(".rtrim($temp, ",*").")+".'\'';

        // 正则查询匹配的动态数据
        $model = ChaoDaModel::where(['tag_ids' => ['exp', $exp]])
            -> alias('c')
            -> join('user u', 'u.user_id = c.fb_user_id','left')
            -> field([
                'c.id', 'c.description','c.dianzan_number','c.cover','c.topic_id','c.create_time','c.comment_num','c.collect_num','c.tag_ids',
                'u.nickname','u.avatar'
            ]);
        $chaoda = $model->page($page,$size)-> select();
        $total = $model->count();
        // 动态数据排序
        $sort_result = [];
        foreach ($chaoda as $k => $v){
            // 动态分数得分
            $hours = (time() - $v['create_time'])/3600;
            $score = $v['dianzan_number']*0.5+$v['comment_num']*2+(($v['collect_num']*5)/sqrt($hours+2));
            // 话题得分
            $sort_result[] = $score;
            unset($v['tag_ids']);
        }
        array_multisort($sort_result, SORT_DESC, $chaoda);
        return ['data' => $chaoda, 'total' => $total];
    }
}