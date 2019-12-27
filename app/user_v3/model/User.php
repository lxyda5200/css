<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/16
 * Time: 15:02
 */

namespace app\user_v3\model;

use think\Model;
class User extends Model
{

    protected $name = 'user';

    protected $insert = ['create_time','login_time'];

    protected $autoWriteTimestamp = false;

    public function goodsComment()
    {
        return $this->hasMany('GoodsComment','user_id','user_id');
    }

    /**
     * 通过第三方登录的微信openid获取user_id
     * @param $openid
     * @return mixed
     */
    public function getUserInfoByUnionid($unionid){
        return $this->where(['wx_unionid'=>$unionid])->value('user_id');
    }

    /**
     * 通过第三方登录的qq_openid获取user_id
     * @param $qq_openid
     * @return mixed
     */
    public function getUserInfoByQQOpenid($qq_openid){
        return $this->where(['qq_openid'=>$qq_openid])->value('user_id');
    }

    /**
     * 通过第三方登录的sina_id获取user_id
     * @param $sinaId
     * @return mixed
     */
    public function getUserInfoBySinaId($sinaId){
        return $this->where(['sina_id'=>$sinaId])->value('user_id');
    }

    /**
     * 添加用户
     * @param $data
     * @return false|int
     */
    public function addUser($data){
        $data['create_time'] = time();
        $data['login_time'] = time();
        return $this->insertGetId($data);
    }

    /**
     * 更新token相关信息
     * @param $user_id
     * @param $data
     * @return User
     */
    public static function updateToken($user_id, $data){
        return self::update($data,['user_id'=>$user_id]);
    }

    /**
     * 通过电话号码获取用户信息
     * @param $mobile
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserInfoByMobile($mobile){
        $info = $this->where(['mobile'=>$mobile])->field('user_id,password')->find();
        if($info)$info = $info->toArray();
        return $info;
    }

    /**
     * 修改用户信息
     * @param $user_id
     * @param $data
     * @return User
     */
    public function edit($user_id, $data){
        return $this->where(['user_id'=>$user_id])->update($data);
    }

    /**
     * 获取用户基本信息
     * @param $user_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserBaseInfo($user_id){
        return $this->where(['user_id'=>$user_id])->field('id,nickname,token,avatar')->find();
    }

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setLoginTime(){
        return time();
    }

}