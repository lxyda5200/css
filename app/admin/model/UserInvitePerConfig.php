<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use think\Validate;

class UserInvitePerConfig extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    protected static $min_condition = 0;

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 增加阶梯奖励
     * @param $config_id
     * @param $list
     * @throws Exception
     */
    public static function addRule($config_id, $list){
        $data = [];
        foreach($list as $v){
            ##验证
            $check = self::checkRule($v);
            if(!is_bool($check))throw new Exception($check);
            if(self::$min_condition >= $v['condition'])throw new Exception('阶梯奖励条件人数应从小到大');
            self::$min_condition = (int)$v['condition'];
            $data[] = [
                'condition' => (int)$v['condition'],
                'price' => (float)$v['price'],
                'config_id' => $config_id
            ];
        }
        $res = (new self())->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('阶梯奖励更新失败');
    }

    /**
     * 验证参数
     * @param $data
     * @return array|bool
     */
    public static function checkRule($data){
        $validate = new Validate();
        $rule = [
            'condition|人数' => 'require|number|>=:1',
            'price|奖励金额' => 'require|float|>=:0.01'
        ];
        $res = $validate->rule($rule)->check($data);
        if(!$res)return $validate->getError();
        return $res;
    }

}