<?php


namespace app\user_v6\model;

use think\Model;
use think\Validate;

class NewTrendDianzanModel extends Model
{
    protected $pk = 'id';
    protected $table = 'new_trend_dianzan';
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
    public static function NewTrendDianzanOrCancel($param){
        //status 1:点赞 ，-1取消点赞
        $rule = [
            'new_trend_id' => 'require|number|egt:0',
            'status'       => 'require|number|in:-1,1',
            'user_id'    => 'require|number|gt:0',
            'token'      => 'require',
        ];

        $msg = [
            'new_trend_id.require' => '缺少必要参数',
            'token.require'      => '缺少必要参数',
            'status.require'       => '缺少必要参数',
            'user_id.require'    => '缺少必要参数',
            'new_trend_id.number'  => '参数格式不正确',
            'status.number'        => '参数格式不正确',
            'user_id.number'     => '参数格式不正确',
            'user_id.gt'         => '参数范围错误',
            'new_trend_id.egt'     => '参数范围错误',
            'status.in'            => '参数不在接收范围',

        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }
        // 查询数据
        $newtrend=NewTrendModel::where(['id' => $param['new_trend_id'], 'status' => 1])-> field('id,like_number,comment_number,collect_number')-> find();
        if(!$newtrend){return '没有找到该时尚新潮!';}
        $info = NewTrendDianzanModel::where(['new_trend_id' => $param['new_trend_id'], 'user_id' => $param['user_id']])
            -> field('id,new_trend_id,user_id')->find();
            if($param['status']==1){
                //点赞
            if($info){
                return '请勿重复点赞!';
            }else{
                $insertData = [
                    'new_trend_id'  => $param['new_trend_id'],
                    'user_id'     => $param['user_id'],
                    'create_time' => time(),
                ];
                $result = NewTrendDianzanModel::insertGetId($insertData);
                NewTrendModel::where('id', $newtrend['id']) -> setInc('like_number', 1);
            }
            }elseif ($param['status']==-1){
                //取消点赞
                if(!$info){
                    return '已取消!';
                }else{
                    $result = NewTrendDianzanModel::destroy($info['id']);
                    if($newtrend['like_number']>0){
                        NewTrendModel::where('id', $newtrend['id']) -> setDec('like_number', 1);
                    }
                    if($result===false){ return '取消失败!';}
                    return true;
                }
            }else{
                //报错
                return '状态错误!';
            }
    }

}