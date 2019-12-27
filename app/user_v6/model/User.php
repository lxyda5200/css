<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/16
 * Time: 15:02
 */

namespace app\user_v6\model;

use app\user_v6\model\ChaodaCollection;
use app\user_v6\model\CommentModel;
use app\user_v6\model\DynamicCollectionModel;
use app\user_v6\model\DynamicGroupCollection;
use app\user_v6\model\ProductCollection;
use app\user_v6\model\StoreCollection;
use app\user_v6\model\UserMsgLink;
use app\user_v6\common\User as UserFunc;
use think\Model;
use think\response\Json;
use think\Validate;

class User extends Model
{

    protected $name = 'user';
    protected $table = 'user';
    protected $pk = 'user_id';
    protected $insert = ['create_time','login_time'];
    protected $dateFormat=false;

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
     * 通过第三方登录的微信openid是否注册过并且绑定手机号
     * @param $openid
     * @return mixed
     */
    public function getUserInfoMobile($unionid){
        return $this->field('user_id,mobile')->where('user_status IN (1,3)')->where(['wx_unionid'=>$unionid])->find();
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
        $info = $this->where(['mobile'=>$mobile])->field('user_id,password,mobile')->find();
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

    /**
     * 获取用户收藏总数
     * @param $user_id
     * @return int|string
     */
    public static function getUserCollectNum($user_id){
        ##商品收藏数
        $product_num = ProductCollection::userCollectNum($user_id);
        ##店铺收藏数
        $store_num = StoreCollection::userCollectNum($user_id);
        ##潮搭收藏数
        $chaoda_num = ChaodaCollection::userCollectNum($user_id);
        ##店铺动态收藏数
        $dynamic_num = DynamicCollectionModel::userCollectNum($user_id);
        ##生活剪影收藏数
        $dynamic_group_num = DynamicGroupCollection::userCollectNum($user_id);
//        $dynamic_group_num = SceneGroupCollection::userCollectNum($user_id);

        ##总收藏数
        $collect_num = $product_num + $store_num + $chaoda_num + $dynamic_num + $dynamic_group_num;
        return $collect_num;
    }

    /**
     * 获取用户评论回复数
     * @param $user_id
     * @return int|string
     */
    public static function getCommentNum($user_id){
        ##获取评价
        $num = CommentModel::userByCommentNum($user_id);
        return $num;
    }

    /**
     * 获取用户的评论新回复数
     * @param $user_id
     * @return int|string
     */
    public static function newCommentNum($user_id){
        return CommentModel::userNewByCommentNum($user_id);
    }

    /**
     * 获取用户的上次查看动态评论列表的时间
     * @param $user_id
     * @return mixed
     */
    public static function getCommentScanTime($user_id){
        return (new self())->where(['user_id'=>$user_id])->value('dynamic_comment_scan_time');
    }

    /**
     * 获取用户待查看系统消息数
     * @param $user_id
     * @return int|string
     */
    public static function userNewSystemMsgNum($user_id){
        return UserMsgLink::userNewSystemMsgNum($user_id);
    }

    /**
     * 获取用户上次查看系统消息的时间
     * @param $user_id
     * @return mixed
     */
    public static function getSystemMsgScanTime($user_id){
        return (new self())->where(['user_id'=>$user_id])->value('system_scan_time');
    }

    /**
     * 获取用户带查看系统消息的最新时间
     * @param $user_id
     * @return mixed
     */
    public static function userNewSystemMsgTime($user_id){
        return UserMsgLink::userNewSystemMsgTime($user_id);
    }

    /**
     * 获取用户的昵称与头像
     * @param $user_id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getUserNameAndLogo($user_id){
        $info = (new self())->where(['user_id'=>$user_id])->field('nickname,avatar')->find();
        if(!$info){
            $info = [];
            $info['nickname'] = $info['avatar'] = "";
        }
        if(!$info['nickname'])$info['nickname'] = "";
        if(!$info['avatar'])$info['avatar'] = "";
        return $info;
    }

    /**
     * 更新用户查看评论时间
     * @param $user_id
     * @return int
     */
    public static function updateUserScanCommentTime($user_id){
        return (new self())->where(['user_id'=>$user_id])->setField('dynamic_comment_scan_time',time());
    }

    /**
     * 更新用户查看系统消失时间
     * @param $user_id
     * @return int
     */
    public static function updateUserScanSystemTime($user_id){
        return (new self())->where(['user_id'=>$user_id])->setField('system_scan_time',time());
    }

    /**
     * 更新手机号
     * @param $user_id
     * @param $mobile
     * @return int
     */
    public function bindMobile($user_id, $mobile){
        return $this->where(['user_id'=>$user_id])->setField('mobile', $mobile);
    }

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setLoginTime(){
        return time();
    }

}