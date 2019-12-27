<?php


namespace app\user_v6\model;

use think\Model;
use think\Validate;

class DynamicDianzanModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic_dianzan';
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
    public static function DynamicDianzanOrCancel($param){
        //status 1:点赞 ，-1取消点赞
        $rule = [
            'dynamic_id' => 'require|number|egt:0',
            'status'       => 'require|number|in:-1,1',
            'user_id'    => 'require|number|gt:0',
            'token'      => 'require',
        ];

        $msg = [
            'dynamic_id.require' => '缺少必要参数',
            'token.require'      => '缺少必要参数',
            'status.require'       => '缺少必要参数',
            'user_id.require'    => '缺少必要参数',
            'dynamic_id.number'  => '参数格式不正确',
            'status.number'        => '参数格式不正确',
            'user_id.number'     => '参数格式不正确',
            'user_id.gt'         => '参数范围错误',
            'dynamic_id.egt'     => '参数范围错误',
            'status.in'            => '参数不在接收范围',

        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }
        // 查询数据
        $dynamic=DynamicModel::where(['id' => $param['dynamic_id'], 'status' => 1])
            -> field('id,store_id,like_number')-> find();
        if(!$dynamic){return '没有找到该动态!';}
        $info = DynamicDianzanModel::where(['dynamic_id' => $param['dynamic_id'], 'user_id' => $param['user_id']])
            -> field('id,dynamic_id,user_id')->find();
            if($param['status']==1){
                //点赞
            if($info){
                return '请勿重复点赞!';
            }else{
                $insertData = [
                    'dynamic_id'  => $param['dynamic_id'],
                    'user_id'     => $param['user_id'],
                    'create_time' => time(),
                ];
                $result = DynamicDianzanModel::insertGetId($insertData);
                DynamicModel::where('id', $dynamic['id']) -> setInc('like_number', 1);

            }
            }elseif ($param['status']==-1){
                //取消点赞
                if(!$info){
                    return '已取消!';
                }else{
                    $result = DynamicDianzanModel::destroy($info['id']);
                    if($dynamic['like_number']>0){
                        DynamicModel::where('id', $dynamic['id']) -> setDec('like_number', 1);
                    }
                }
            }else{
                //报错
                return '状态错误!';
            }
        if ($result) return true;
        return false;
    }

}