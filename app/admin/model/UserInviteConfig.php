<?php


namespace app\admin\model;


use think\Db;
use think\Exception;
use think\Model;
use think\model\relation\HasMany;
use traits\model\SoftDelete;

class UserInviteConfig extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取信息
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getInfo(){
        $data = $this
            ->field('id,per_price,max_invite_num,first_invite_price')
            ->order('id','desc')
            ->with([
                'perConfig' => function(HasMany $hasMany){
                    $hasMany->order('condition','asc')->field('id,condition,price,config_id');
                }
            ])
            ->find();
        $data = json_decode(json_encode($data), true);
        return $data;
    }

    /**
     * 一对多  -- 获取当前阶梯奖励
     * @return HasMany
     */
    public function perConfig(){
        return $this->hasMany('UserInvitePerConfig','config_id','id');
    }

    /**
     * 编辑
     * @param $data
     * @return bool|string
     */
    public function edit($data){
        $insert_data = [
            'per_price' => floatval($data['per_price']),
            'max_invite_num' => intval($data['max_invite_num']),
            'first_invite_price' => floatval($data['first_invite_price']),
        ];
        $id = intval($data['id']);
        Db::startTrans();
        try{
            ##删除旧数据
            if($id){
                $res = UserInviteConfig::destroy($id);
                if($res === false)throw new Exception('删除旧数据失败');
            }
            ##增加新数据
            $res = $this->isUpdate(false)->save($insert_data);
            if($res === false)throw new Exception('插入数据失败');

            ##插入新的阶梯奖励规则
            if(isset($data['rules']) && is_array($data['rules'])){
                $config_id = $this->getLastInsID();
                UserInvitePerConfig::addRule($config_id, $data['rules']);
            }
            Db::commit();

            ##返回
            return true;
        }catch(Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }

}