<?php


namespace app\user_v6\model;

use think\Model;
use think\Validate;

class DynamicCommentDianzanModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic_comment_dianzan';
    protected $dateFormat=false;
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
    public static function CommentDianzanOrCancel($param){
        //status 1:点赞 ，-1取消点赞
        $rule = [
            'comment_id' => 'require|number|egt:0',
            'status'       => 'require|number|in:-1,1',
            'user_id'    => 'require|number|gt:0',
            'token'      => 'require',
        ];
        $msg = [
            'comment_id.require' => '缺少必要参数',
            'token.require'      => '缺少必要参数',
            'status.require'       => '缺少必要参数',
            'user_id.require'    => '缺少必要参数',
            'comment_id.number'  => '参数格式不正确',
            'status.number'        => '参数格式不正确',
            'user_id.number'     => '参数格式不正确',
            'user_id.gt'         => '参数范围错误',
            'comment_id.egt'     => '参数范围错误',
            'status.in'            => '参数不在接收范围',

        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }
        // 查询数据
        $info = DynamicCommentDianzanModel::where(['co.comment_id' => $param['comment_id'], 'co.user_id' => $param['user_id']])
            -> alias('co')
            -> field([
                'co.id','co.comment_id','co.id', 'ch.dynamic_id'
            ])
            -> join('dynamic_comment ch', 'co.comment_id = ch.id')
            -> find();
            if($param['status']==1){
                //点赞
            if($info){
                return '请勿重复点赞!';
            }else{
                $insertData = [
                    'comment_id'  => $param['comment_id'],
                    'type'        => 1,
                    'user_id'     => $param['user_id'],
                    'create_time' => time(),
                ];
                $result = DynamicCommentDianzanModel::insertGetId($insertData);
//                DynamicModel::where('id', $info['dynamic_id']) -> setInc('like_number', 1);

            }
            }elseif ($param['status']==-1){
                //取消点赞
                if(!$info){
                    return '已取消!';
                }else{
                    $result = DynamicCommentDianzanModel::destroy($info['id']);
//                    DynamicModel::where('id', $info['dynamic_id']) -> setDec('like_number', 1);
                }

            }else{
                //报错
                return '状态错误!';
            }
        if ($result) return true;
        return false;
    }

}