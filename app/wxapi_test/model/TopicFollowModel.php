<?php


namespace app\wxapi_test\model;


use think\Db;
use think\Model;
use app\wxapi_test\model\TopicModel;
use think\db\Query;

class TopicFollowModel extends Model
{
    protected $pk = 'id';

    protected $table = 'topic_follow';

    /**
     *  获取用户关注点话题
     * @param $uid
     * @param int $page
     * @param int $size
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserFollowNoSort($uid,$page = 1,$size = 20,$fb_user_id = 0){
        $other = $fb_user_id == 0 ? $uid : $fb_user_id;
        $topic = self::where(['f.user_id' => $other])
            -> alias('f')
            -> join('topic t', 'f.topic_id = t.id and t.status = 1')
            -> join('topic_follow tf', 'tf.topic_id = f.topic_id and tf.user_id = '. $uid,'left')
            -> field(['f.id,f.topic_id,t.title,IF(tf.create_time>0,1,0) is_follow'])

            -> page($page,$size)-> order('id desc')-> select();
        $count = self::where(['f.user_id' => $other])
            -> alias('f')
            -> join('topic t', 'f.topic_id = t.id and t.status = 1')
            -> field(['f.id,f.topic_id,t.title,IF(1=1,1,0) is_follow'])->count();
        $return = [
            'total' => $count,
            'max_page' => ceil(($count)/$size),
            'list' => $topic,
        ];
        return $return;
    }

    /**
     *  获取用户关注话题动态数据  并排序
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserFollowByUid($uid,$page = 1,$size = 20){
        // 获取关注话题ID 并形成一维数组
        $temp = self::where(['user_id' => $uid]) -> field('topic_id') -> select();
        $topicIds = array_column($temp,'topic_id');

        // 根据获取的数组话题ID查询 动态
        $model = ChaoDaModel::where(['topic_id' => ['in', $topicIds]])
            -> alias('c')
            -> join('topic t', 'c.topic_id = t.id','left')
            -> join('user u', 'u.user_id = c.fb_user_id','left')
            -> field([
                'c.id', 'c.description','c.dianzan_number','c.cover','c.topic_id','c.create_time','c.comment_num','c.collect_num',
                't.title',
                'u.nickname','u.avatar'
            ]);
        $chaoda = $model->page($page,$size)-> select();
        $total  = $model->count();
        // 动态排序计算
        $sort_result = [];
        foreach ($chaoda as $k => $v){
            // 动态分数得分
            $hours = (time() - $v['create_time'])/3600;
            $score = $v['dianzan_number']*0.5+$v['comment_num']*2+(($v['collect_num']*5)/sqrt($hours+2));
            // 话题得分
            $sort_result[] = $score;
        }
        array_multisort($sort_result, SORT_DESC, $chaoda);

        $totalNum = count($chaoda);
        $ids = array_column($chaoda, 'id');
        // 在数据大于10的情况下 随机插入一条用户为查看的动态 每10条添加一条
        if ($totalNum>=10){
            $insertNum = floor($totalNum/10);
            $insertData = ChaoDaModel::where(['c.id' => ['not in', $ids]])
                -> alias('c')
//                -> join('topic t', 'c.topic_id = t.id', 'left')
                -> join('user u', 'u.user_id = c.fb_user_id','left')
                -> field([
                    'c.id', 'c.description','c.dianzan_number','c.cover','c.topic_id','c.create_time','c.comment_num','c.collect_num',
//                    't.title',
                    'u.nickname','u.avatar'
                ])-> order('rand()') -> limit($insertNum) -> select();

            for ($i = 1 ; $i <= $insertNum ; $i++){
                array_splice($chaoda, $i*10, 0, array($insertData[$i-1]));
            }
        }
        return ['data' => $chaoda, 'total' => $total];
    }

    /**
     *  一对多关联图片 / 视频
     * @return \think\model\relation\HasMany
     */
    public function chaodata(){
        return $this -> hasMany('ChaoDaModel', 'topic_id', 'id') -> field('cover,id,topic_id,description');
    }
}