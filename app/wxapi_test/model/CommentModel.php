<?php


namespace app\wxapi_test\model;


use think\Db;
use think\Model;
use think\Validate;
use think\db\Query;
use app\wxapi_test\common\User;

class CommentModel extends Model
{
    protected $pk = 'id';

    protected $table = 'chaoda_comment';

    /**
     *  一对多关联自身查找子评论数据
     *  关联查询用户表  查询评论用户昵称
     * @return \think\model\relation\HasMany
     */
    public function reply(){
        return $this->hasMany('CommentModel', 'pid', 'id') -> join('user', 'chaoda_comment.user_id = user.user_id')
            -> field('chaoda_comment.content,chaoda_comment.chaoda_id,chaoda_comment.pid,chaoda_comment.id,chaoda_comment.user_id,user.nickname');
    }

    public static function userDelComment($param){
        $rule = [
            'user_id'    => 'require|number',
            'cid'        => 'require|number',
            'token'      => 'require',
        ];

        $msg = [
            'user_id.require'   => '缺少必要参数',
            'user_id.number'    => '参数格式不正确',
            'cid.require'       => '缺少必要参数',
            'require.require'   => '缺少必要参数',
            'cid.number'        => '参数格式不正确',
        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }

        // 查询评论信息
        $commentInfo = CommentModel::where('m.id', $param['cid'])
            -> alias('m')
            -> join('chaoda c', 'm.chaoda_id = c.id')
            -> field(['m.id','m.chaoda_id','m.user_id','c.comment_number']) -> find();
        if (!$commentInfo) return '检索数据失败';
        // 判断评论来源是否是自己
        if ($commentInfo['user_id'] != $param['user_id']) return "只能删除自己的评论";

        // 删除评论
        $comDel = CommentModel::destroy($param['cid']);
        if ($comDel){
            // 动态评论数减1
            if ($commentInfo['comment_num'] > 0){
                ChaoDaModel::where('id', $commentInfo['chaoda_id']) -> setDec('comment_number',1);
            }
            return true;
        }
        return false;
    }

    /**
     *  动态详情页 评论数据分页
     * @param $param
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function detailsCommentPage($param){
        $rule = [
            'chaoda_id'  => 'require|number',
            // 'user_id'    => 'require|number',
            'page'       => 'require|number|egt:1',
            'size'       => 'require|number|egt:1',
        ];

        $msg = [
            'chaoda_id.require' => '缺少必要参数',
            'chaoda_id.number'  => '参数格式不正确',
            //'user_id.require'   => '缺少必要参数',
            //'user_id.number'    => '参数格式不正确',
            'page.require'      => '缺少必要参数',
            'page.egt'          => '参数范围错误',
            'size.require'      => '缺少必要参数',
            'page.number'       => '参数格式不正确',
            'size.number'       => '参数格式不正确',
            'size.egt'          => '参数范围错误',
        ];
        $user_id = isset($param['user_id']) ? $param['user_id'] : 0;
        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }
        $page = $param['page'] ? $param['page'] : 1;
        $size = $param['size'] ? $param['size'] : 8;
        // 分页查询评论数据
        $data = self::where(['c.chaoda_id' => $param['chaoda_id'], 'c.pid' => 0])
            -> alias('c')
            -> field([
                'c.id', 'c.pid', 'c.create_time', 'c.user_id', 'c.content', 'c.chaoda_id', 'c.support', 'c.hate',
                'u.nickname', 'u.avatar',
                'IF(cd.type = 1,1,0) is_support','IF(cd.type = 2, 1, 0) is_hate'
            ])
            -> join('user u', 'c.user_id = u.user_id', 'left')
            -> join('chaoda_comment_dianzan cd', 'cd.comment_id = c.id and cd.user_id = ' . $user_id, 'left')
            -> with(['reply' => function(Query $query){
                return $query -> order('id asc'); // 统计回复评论总数
            }])
            -> page("{$page},{$size}")
            -> order('c.id desc')
            -> select();

        // 计算主评论的回复评论数量
        foreach ($data as $ke => $va){
            $data[$ke]['totalReply'] = count($va['reply']);
        }

        // 计算总的主评论数
        $total_comment = self::where(['chaoda_id' => $param['chaoda_id'],'pid' => 0]) -> count();
        $returnData = [
            'list' => $data,
            'total_comment' =>$total_comment
        ];
        return $returnData;
    }
    /**
     *  动态详情评论页 评论数据分页
     * @param $param
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function detailsCommentList($param){

        $rule = [
            'chaoda_id'  => 'require|number',
            'comment_id'    => 'require|number',
            'page'       => 'require|number|egt:1',
            'size'       => 'require|number|egt:1',
        ];

        $msg = [
            'chaoda_id.require' => '缺少必要参数',
            'chaoda_id.number'  => '参数格式不正确',
            'comment_id.require'   => '缺少必要参数',
            'comment_id.number'    => '参数格式不正确',
            'page.require'      => '缺少必要参数',
            'page.egt'          => '参数范围错误',
            'size.require'      => '缺少必要参数',
            'page.number'       => '参数格式不正确',
            'size.number'       => '参数格式不正确',
            'size.egt'          => '参数范围错误',
        ];
        $user_id = isset($param['user_id']) ? $param['user_id'] : 0;
        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }
        $page = $param['page'] ? $param['page'] : 1;
        $size = $param['size'] ? $param['size'] : 8;
        $comment_id=$param['comment_id'];

        // 分页查询评论数据
        $data = self::where(['c.chaoda_id' => $param['chaoda_id'], 'c.pid' => $comment_id])
            -> alias('c')
            -> field([
                'c.id', 'c.pid', 'c.create_time', 'c.user_id', 'c.content', 'c.chaoda_id', 'c.support', 'c.hate',
                'u.nickname', 'u.avatar'
            ])
            -> join('user u', 'c.user_id = u.user_id', 'left')
            -> page("{$page},{$size}")
            -> order('c.id desc')
            -> select();

        // 计算主评论的回复评论数量
        foreach ($data as $ke => $va){
            $data[$ke]['totalReply'] = count($va['reply']);
        }

        // 计算总的主评论数
        $total_comment = self::where(['chaoda_id' => $param['chaoda_id'],'pid' =>$param['comment_id']]) -> count();
        $returnData = [
            'list' => $data,
            'total_comment' =>$total_comment
        ];
        return $returnData;
    }
    /**
     *  添加评论或回复评论数据 及 传入参数验证
     * @param $param
     * @return array|bool
     * @throws \think\Exception
     */
    public static function insertData($param){
        $rule = [
            'chaoda_id'  => 'require|number',
            'user_id'    => 'require|number',
            'content'    => 'require',
            'token'    => 'require',
        ];

        $msg = [
            'chaoda_id.require' => '缺少必要参数',
            'chaoda_id.number'  => '参数格式不正确',
            'user_id.require'   => '缺少必要参数',
            'user_id.number'    => '参数格式不正确',
            'content.require'   => '缺少必要参数',
            'token.require'     => '缺少必要参数',
        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }


        //  动态数据是否存在
        $chaodaInfo = ChaoDaModel::where(['id' => intval($param['chaoda_id'])]) -> count();
        if ($chaodaInfo != 1) return json(self::callback(0, "数据检索失败"), 400);

        $insertData = [
            'chaoda_id'   => $param['chaoda_id'],
            'user_id'     => $param['user_id'],
            'content'     => htmlspecialchars($param['content']),
            'create_time' => time(),
            'pid'         => (isset($param['pid']) && is_numeric($param['pid']) && $param['pid'] > 0) ? $param['pid'] : 0,
            'support'     => 0,
            'hate'        => 0,
        ];

        $result = CommentModel::insertGetId($insertData);
        if ($result){
            ChaoDaModel::where('id', $param['chaoda_id']) -> setInc('comment_number',1);
        }
        return $result ? true : false;
    }

}