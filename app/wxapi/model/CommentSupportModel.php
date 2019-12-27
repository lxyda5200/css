<?php


namespace app\wxapi\model;


use think\Model;
use think\Validate;
class CommentSupportModel extends Model
{
    protected $pk = 'id';

    protected $table = 'chaoda_comment_dianzan';


    /**
     *  评论点赞  取消点赞
     *  评论点踩  取消点踩
     * @param $param
     * @return array|bool|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function supportComment($param){
        $rule = [
            'comment_id' => 'require|number|egt:0',
            'support'    => 'require|number|in:0,1',
            'type'       => 'require|number|in:1,2',
            'user_id'    => 'require|number|gt:0',
            'token'      => 'require',
        ];

        $msg = [
            'comment_id.require' => '缺少必要参数',
            'token.require'      => '缺少必要参数',
            'type.require'       => '缺少必要参数',
            'user_id.require'    => '缺少必要参数',
            'support.require'    => '缺少必要参数',
            'comment_id.number'  => '参数格式不正确',
            'type.number'        => '参数格式不正确',
            'user_id.number'     => '参数格式不正确',
            'support.number'     => '参数格式不正确',
            'user_id.gt'         => '参数范围错误',
            'comment_id.egt'     => '参数范围错误',
            'support.in'         => '参数不在接收范围',
            'type.in'            => '参数不在接收范围',

        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }

        // 查询数据
        $info = CommentSupportModel::where(['co.comment_id' => $param['comment_id'], 'co.user_id' => $param['user_id'], 'co.type' => $param['type']])
            -> alias('co')
            -> field([
                'co.comment_id','ch.support','co.id', 'ch.hate'
            ])
            -> join('chaoda_comment ch', 'co.comment_id = ch.id')
            -> find();
        $returnWord = $param['type'] == 1 ? '点赞' : '点踩';

        $setName = $param['type'] ==1 ? 'support' : 'hate';

        if ($param['support'] == 1){
            if ($info) return '重复'.$returnWord;

            $insertData = [
                'comment_id'  => $param['comment_id'],
                'type'        => $param['type'],
                'user_id'     => $param['user_id'],
                'create_time' => time(),
            ];

            $result = CommentSupportModel::insertGetId($insertData);

            CommentModel::where('id', $param['comment_id']) -> setInc($setName, 1);
        }else{
            if (!$info) return '无法取消'.$returnWord;
            $result = CommentSupportModel::destroy($info['id']);
            if ($info[$setName] >= 1){
                CommentModel::where('id', $param['comment_id']) -> setDec($setName, 1);
            }
        }

        if ($result) return true;

        return false;
    }

}