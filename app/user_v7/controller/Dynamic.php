<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/12/5
 * Time: 13:47
 */

namespace app\user_v7\controller;

use app\common\controller\Base;
use app\user_v7\common\Logic;
use app\user_v7\common\User as UserFunc;
use app\user_v7\common\UserLogic;
use app\user_v7\model\CommentModel;
use app\user_v7\model\DynamicCommentDianzanModel;
use app\user_v7\model\DynamicModel;
use app\user_v7\model\DynamicDianzanModel;
use app\user_v7\model\DynamicCollectionModel;
use app\user_v7\model\NewTrendModel;
use app\user_v7\model\NewTrendDianzanModel;
use app\user_v7\model\NewTrendCollectionModel;
use app\user_v7\model\NewTrendCommentModel;
use app\user_v7\model\Scene;
use app\user_v7\model\Topic;
use think\Cache;
use think\Db;
use app\user_v7\common\User;
use think\Exception;
use think\Log;
use think\response\Json;
use app\user_v7\model\User as UserModel;
use think\Validate;

class Dynamic extends Base
{
    /**
     * 场景标记
     */
    public function GetSceneList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $user_scene =$userInfo['user_scene'] ? $userInfo['user_scene'] : 0 ;
            if($user_id && $user_scene){
                //用户已经选择过
                $user_scene=$userInfo['user_scene'];
            }else{
                //随机生成三个
                $pid = Db::name('scene')
                    ->field('id')
                    ->where('status',1)
                    ->where('level',1)
                    ->order('RAND()')
                    ->limit(3)
                    ->select();
                $user_scene='';
                foreach ($pid as $k=>&$v){
                    $scene_id = Db::name('scene')
                        ->where('status',1)
                        ->where('p_id',$v['id'])
                        ->where('level',2)
                        ->order('RAND()')
                        ->limit(1)
                        ->value('id');
                    $user_scene.=$scene_id.',';
                }
                $user_scene=rtrim($user_scene, ',');
            }
            $list = Db::name('scene')
                ->field('id,title')
                ->where('status',1)
                ->where('level',1)
                ->order('sort desc')
                ->select();
            foreach ($list as $k1=>&$v1){
                $list[$k1]['category_info'] = Db::name('scene')
                    ->field([
                        'id','title',
                        "IF(id in ($user_scene), 1, 0)  is_select"
                    ])
                    ->where('status',1)
                    ->where('p_id',$v1['id'])
                    ->order('sort desc')
                    ->select();
            }
            return json(self::callback(1,'返回成功!',$list));
        }catch (\Exception $e) {
            return \json(self::callback(0, $e->getMessage()));
        }
    }
    /**
     * 生活剪影
     **/
    public function LifeSilhouette(){
        try{
            $gender = input('gender') ? intval(input('gender')) : 0 ;
            $age_range = input('age_range') ? intval(input('age_range')) : 0 ;
            $scene_ids = input('scene_ids','','addslashes,strip_tags,trim');
            $user_id = input('user_id') ? intval(input('user_id')) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            //验证参数
            if (!$gender || !$age_range || !$scene_ids ) {throw new \Exception('参数错误');}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
                $gender_array=array("0","1","2");
                if(!in_array($gender,$gender_array)){throw new \Exception('性别错误');}
                $new_info=[
                    'gender'=>$gender,
                    'age_range_id'=>$age_range
                ];
                $info= Db::name('user')->where('user_id',$user_id)->update($new_info);
            }
            //年龄段加1
            $range= Db::name('age_range')->where('id',$age_range)->setInc('number');
            $scene_ids=explode(",",$scene_ids);

            $data=[];
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 探店（关注/未关注）
     */
    public function StoreDynamic(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
                $num=DynamicModel::GetStoreFollow($user_id);
                if($num>0){
                    //返回店铺动态
                    $data=DynamicModel::GetStoreFollowDynamic($user_id,$page,$size,$lng,$lat);
                    return \json(self::callback(1,'返回成功!',$data,true));
                }
            }
            //type 1:有关注，2：未关注
            //返回热门动态
            $size=3;
            $data=DynamicModel::GetHotDynamic($user_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '返回失败!'), 400);
            return \json(self::callback(1,'返回成功!',$data,true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 探店（未关注，未登录）热门趣店
     */
    public function GetRecommendStoreFollow(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $page = input('post.page',1,'intval');
            $size = input('post.size',8,'intval');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回推荐关注店铺
            $data=DynamicModel::GetRecommendStoreFollow($user_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '返回失败'), 400);
            return \json(self::callback(1,'成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 探店推荐
     */
    public function RecommendDynamic(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $category=input('post.category/a','');
            $sort = input("sort") ? trim(input("sort")) : 0 ;//排序
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //推荐
            $data=DynamicModel::GetRecommendDynamic($user_id,$page,$size,$lat,$lng,$category,$sort);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '返回失败'), 400);

            ##执行任务插入抽奖信息
            //UserLogic::executeDrawTask();

            return \json(self::callback(1,'返回成功!',$data,true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 探店阅读人
     */
    public function DynamicReader(){
        try{
            $id = input("id") ? intval(input("id")) : 0 ;
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //推荐
            $data=DynamicModel::GetRecommendDynamic($user_id,$page,$size);
            return \json(self::callback(1,'返回成功!',$data,true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 店铺动态详情
     */
    public function DynamicDetail(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//动态id
            if(!$id){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回推荐关注店铺
            $data=DynamicModel::GetDynamicDetail($id,$user_id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * （店铺动态详情页）动态推荐
     */
    public function DynamicRecommend(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input('post.page',1,'intval');
            $size = input('post.size',12,'intval');
            $scene_id = input("scene_id") ? intval(input("scene_id")) : 0 ;//场景id
            if(!$scene_id){return \json(self::callback(0,'还没有传场景id哦',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回店铺动态详情推荐
            $data=DynamicModel::GetDynamicDetailRecommend($user_id,$page,$size,$lat,$lng,$scene_id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 探店附近
     */
    public function NearbyDynamic(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            $category=input('post.category/a','');
            $sort = 4;//附近
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //推荐
            $data=DynamicModel::GetRecommendDynamic($user_id,$page,$size,$lat,$lng,$category,$sort);
            return \json(self::callback(1,'返回成功!',$data,true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 搜索动态
     */
    public function DynamicSearch(){
        try{
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            $user_id = input('user_id',0,'intval') ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input('post.page',1,'intval');
            $size = input('post.size',15,'intval');
            if (!$keywords) return \json(self::callback(0,'参数错误'),400);
            if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
            if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
            if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回推荐关注店铺
            $data=DynamicModel::GetDynamicSearch($user_id,$keywords,$lat,$lng,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 时尚新潮列表
     */
    public function NewTrendList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $page = input('post.page',1,'intval');
            $size = input('post.size',10,'intval');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回推荐关注店铺
            $data=DynamicModel::GetNewTrendList($user_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 时尚新潮详情
     */
    public function NewTrendDetail(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//时尚新潮id
            if(!$id){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回推荐关注店铺
            $data=DynamicModel::GetNewTrendDetail($user_id,$id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 时尚新潮收藏/取消收藏
     */
    public function NewTrendColletOrCancel(){
        try{
            $params = $this -> request -> only(['new_trend_id','user_id', 'status','token']);
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $result = NewTrendCollectionModel::NewTrendColletOrCancel($params);
            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);
            if ($result === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 时尚新潮点赞/取消点赞
     */
    public function NewTrendDianzanOrCancel(){
        try{
            $params = $this -> request -> only(['new_trend_id','user_id', 'status','token']);
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $result = NewTrendDianzanModel::NewTrendDianzanOrCancel($params);
            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);
            if ($result === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 时尚新潮评论
     */
    public function NewTrendComment(){
        try{
            $params = $this -> request -> only(['new_trend_id', 'user_id', 'pid','rid', 'content','token','b_user_id']);
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if(($params['pid'] && $params['pid']>0 && $params['rid']<=0) || ($params['rid'] && $params['rid']>0 && $params['pid']<=0)){return \json(self::callback(0, '请传正确的pid或rid', false));}
            //返回评论
            $data=DynamicModel::GetNewTrendComment($params);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '评论失败'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     *  TODO zd
     *  时尚新潮评论列表数据分页
     * @return Json
     */
    public function NewTrendCommentList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $new_trend_id = input("new_trend_id") ? intval(input("new_trend_id")) : 0 ;//时尚新潮id
            $page = input("page") ? intval(input("page")) : 1 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$new_trend_id){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = NewTrendCommentModel::detailsCommentPage($user_id,$new_trend_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     *  TODO zd
     *  时尚新潮主评论子评论列表数据分页
     * @return Json
     */
    public function NewTrendMainCommentList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $new_trend_id = input("new_trend_id") ? intval(input("new_trend_id")) : 0 ;//时尚新潮id
            $pid = input("pid") ? intval(input("pid")) : 0 ;//主评论id
            $page = input("page") ? intval(input("page")) : 1 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$new_trend_id || !$pid){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = NewTrendCommentModel::GetNewTrendMainCommentList($new_trend_id,$pid,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 时尚新潮分享
     */
    public function NewTrendShare(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $new_trend_id = input("id") ? intval(input("id")) : 0 ;
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $new_trend=NewTrendModel::where(['id' => $new_trend_id, 'status' => 1])-> field('id')-> find();
            if(!$new_trend){return \json(self::callback(0, '没有找到该条动态!', false,true));}
            $result=NewTrendModel::where('id', $new_trend_id) -> setInc('share_number', 1);
            if($result===false){return \json(self::callback(0, '分享统计失败!', false,true));}
            return \json(self::callback(1, '成功!', true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 宿友推荐
     */
    public function RoommateRecommend(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//宿友推荐id
            $derect = input("derect") ? intval(input("derect")) : 0 ;//1:上一期，2：下一期
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回宿友推荐
            $data=DynamicModel::RoommateRecommend($id,$derect);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1,'成功!',$data));

        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 推荐页筛选
     */
    public function RecommendSelect(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $type = input("type") ? intval(input("type")) : 0 ;
            if(!$type){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //返回筛选数据
            $data=DynamicModel::GetSelectData($type);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1,'成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 动态热门搜索词
     */
    public function HotSearchKeywords(){
        try{
            $data['search_words'] = Db::name('search_keywords')->where('client_type',2)->where('type',2)->column('search_keywords');
            $data['search_list'] = Db::name('search_dynamic_record')->where('client_type',2)->order('search_number','desc')->limit('0,12')->column('search_keywords');
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 动态评论
     */
    public function DynamicComment(){
        try{
            $params = $this -> request -> only(['dynamic_id', 'user_id', 'pid', 'rid', 'content','token','b_user_id']);
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if(($params['pid'] && $params['pid']>0 && $params['rid']<=0) || ($params['rid'] && $params['rid']>0 && $params['pid']<=0)){return \json(self::callback(0, '请传正确的pid或rid', false));}
            //返回评论
            $data=DynamicModel::GetDynamicComment($params);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '评论失败'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 删除评论
     */
    public function DynamicCommentDelete(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//时尚动态id
            return '本次产品未设计删除评论！';
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if($data===false){ return \json(self::callback(0,'失败',false,true));}
            return \json(self::callback(1,'成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     *  TODO zd
     *  动态评论列表数据分页
     * @return Json
     */
    public function DynamicCommentList(){
        try{

            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? intval(input("dynamic_id")) : 0 ;//动态id
            $page = input("page") ? intval(input("page")) : 1 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$dynamic_id){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = CommentModel::detailsCommentPage($user_id,$dynamic_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     *  TODO zd
     *  动态评论列表主评论子评论数据分页
     * @return Json
     */
    public function DynamicMainCommentList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? intval(input("dynamic_id")) : 0 ;//动态id
            $pid = input("pid") ? intval(input("pid")) : 0 ;//主评论id
            $page = input("page") ? intval(input("page")) : 1 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$dynamic_id || !$pid){return \json(self::callback(0,'参数错误',false));}
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = CommentModel::MainCommentList($dynamic_id,$pid,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 动态评论点赞/取消点赞
     */
    public function DynamicCommentDianzanOrCancel(){
        try{
            $params = $this -> request -> only(['comment_id','user_id', 'status','token']);
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $result = DynamicCommentDianzanModel::CommentDianzanOrCancel($params);
            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);
            if ($result === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 动态收藏/取消收藏
     */
    public function DynamicColletOrCancel(){
            try{
                $params = $this -> request -> only(['dynamic_id','user_id', 'status','token']);
                // 用户登录TOKEN验证
                $userInfo = User::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
                $result = DynamicCollectionModel::DynamicColletOrCancel($params);
                // 返回错误 或 失败 或 成功提示
                if (is_string($result)) return json(self::callback(0, $result), 400);
                if ($result === false) return json(self::callback(0, '失败'), 400);
                return \json(self::callback(1, '成功', []));
            }catch (\Exception $e){
                return \json(self::callback(0,$e->getMessage()));
            }
        }
    /**
     * 动态点赞/取消点赞
     */
    public function DynamicDianzanOrCancel(){
        try{
            $params = $this -> request -> only(['dynamic_id','user_id', 'status','token']);
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $result = DynamicDianzanModel::DynamicDianzanOrCancel($params);
            // 返回错误 或 失败 或 成功提示
            if (is_string($result)) return json(self::callback(0, $result), 400);
            if ($result === false) return json(self::callback(0, '失败'), 400);
            return \json(self::callback(1, '成功', []));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 导航记录
     */
    public function NavigationRecord(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? intval(input("dynamic_id")) : 0 ;
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $dynamic=DynamicModel::where(['id' => $dynamic_id, 'status' => 1])
                -> field('id,store_id')-> find();
            if(!$dynamic){return \json(self::callback(0, '没有找到该条动态!', false,true));}

            $dynamic_navigation_record=Db::name('dynamic_navigation_record')->where('user_id',$user_id)->where('dynamic_id',$dynamic_id)-> find();
            if(!$dynamic_navigation_record){
                //新增
                $data2=[
                    'user_id'=>$userInfo['user_id'],
                    'dynamic_id'=>$dynamic_id,
                    'create_time'=>time()
                ];
                $rst=Db::name('dynamic_navigation_record')->insert($data2);
                $result=DynamicModel::where('id', $dynamic_id) -> setInc('navigation_number', 1);
            }
            $dynamic_user_record=Db::name('dynamic_user_record')->where('user_id',$user_id)->where('dynamic_id',$dynamic_id)-> find();
            if($dynamic_user_record){
                Db::name('dynamic_user_record')->where('user_id',$user_id)->where('id', $dynamic_id) -> setInc('share_number', 1);
            }else{
                $data=[
                    'user_id'=>$userInfo['user_id'],
                    'dynamic_id'=>$dynamic_id,
                    'share_number'=>1,
                    'create_time'=>time()
                ];
                $rst=Db::name('dynamic_user_record')->insert($data);
                if($rst===false){return \json(self::callback(0, '写入用户分享失败!', false,true));}
            }
            return \json(self::callback(1, '成功!', true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 动态分享
     */
    public function DynamicShare(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? intval(input("dynamic_id")) : 0 ;
            // 用户登录TOKEN验证
            $userInfo = User::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $dynamic=DynamicModel::where(['id' => $dynamic_id, 'status' => 1])
                -> field('id,store_id')-> find();
            if(!$dynamic){return \json(self::callback(0, '没有找到该条动态!', false,true));}
            $result=DynamicModel::where('id', $dynamic_id) -> setInc('share_number', 1);
            if($result===false){return \json(self::callback(0, '分享统计失败!', false,true));}
            $data=[
                'user_id'=>$userInfo['user_id'],
                'dynamic_id'=>$dynamic_id,
                'create_time'=>time()
            ];
            $rst=Db::name('dynamic_share_record')->insert($data);
            $dynamic_user_record=Db::name('dynamic_user_record')->where('user_id',$user_id)->where('dynamic_id',$dynamic_id)-> find();
            if($dynamic_user_record){
                Db::name('dynamic_user_record')->where('user_id',$user_id)->where('dynamic_id', $dynamic_id) -> setInc('share_number', 1);
            }else{
                $data2=[
                    'user_id'=>$userInfo['user_id'],
                    'dynamic_id'=>$dynamic_id,
                    'share_number'=>1,
                    'create_time'=>time()
                ];
                $rst=Db::name('dynamic_user_record')->insert($data2);
            }
            if($rst===false){return \json(self::callback(0, '写入用户分享失败!', false,true));}
            return \json(self::callback(1, '成功!', true));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * turtle购筛选
     */
    public function TurtleSelect(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::GetTurtleSelect();
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 人气单品
     */
    public function PopularProducts(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
          //  $id = input("id") ? intval(input("id")) : 0 ;//人气单品id
           // $derect = input("derect") ? intval(input("derect")) : 0 ;//1:上一条，2：下一条

            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::GetPopularProducts();
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data,true,true));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 商圈（商圈信息）
     */
    public function BusinessCircle(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//商圈id
            if(!$id) return json(self::callback(0, '参数错误'), 400);
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::GetBusinessCircleDynamic($id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 商圈动态列表
     */
    public function BusinessCircleDynamicList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//商圈id
            $lat = input("lat") ? trim(input("lat")) : 0;//纬度
            $lng = input("lng") ? trim(input("lng")) : 0 ;//经度
            $page = input("page") ? intval(input("page")) : 0 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$id) return json(self::callback(0, '参数错误'), 400);
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::GetBusinessCircleDynamicList($user_id,$id,$page,$size,$lng,$lat);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 动态详情评论
     */
    public function DynamicDetailComment(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//动态id
            $page = input("page") ? intval(input("page")) : 0 ;
            $size = input("size") ? intval(input("size")) : 15 ;
            if(!$id) return json(self::callback(0, '参数错误'), 400);
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::GetDynamicDetailComment($user_id,$id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }











    //---------todo---------//
    /**
     * 品牌故事
     */
    public function BrandStory(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//品牌故事id
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::BrandStory($id);

            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 时尚动态
     */
    public function FashionTrends(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//时尚动态id
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::FashionTrends($id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }
    /**
     * 时尚动态详情
     */
    public function FashionTrendsDetail(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//时尚动态id
            //$type = input("type") ? intval(input("type")) : 1 ;//1.视频；2.影集；3.news
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = [];
            $data['video'] = DynamicModel::FashionTrendsDetail($id,1);
            $data['pic'] = DynamicModel::FashionTrendsDetail($id,2);
            $data['news'] = 'www.baidu.com';

            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 时尚动态news详情
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function FashionTrendsNewsDetail(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $id = input("id") ? intval(input("id")) : 0 ;//时尚动态id
            //$type = input("type") ? intval(input("type")) : 1 ;//1.视频；2.影集；3.news
//            if (isset($user_id) && $user_id>0) {
//                //token 验证
//                $userInfo = UserFunc::checkToken();
//                if ($userInfo instanceof Json){
//                    return $userInfo;
//                }
//            }
            $data =DynamicModel::FashionTrendsDetail($id,3);

            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 收藏 生活动态列表
     */
    public function liveCollectionList(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $lng = input("lng") ? strval(input("lng")) : 0 ;//经度
            $lat = input("lat") ? strval(input("lat")) : 0 ;//纬度
            $page = input("page") ? intval(input("page")) : 1 ;//页码
            $size = input("size") ? intval(input("size")) : 10 ;//每页数量
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::liveCollectionList($user_id,$lng,$lat,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 店铺详情 动态列表
     */
    public function storeDynamicLits(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $store_id = input("store_id") ? intval(input("store_id")) : 0 ;//店铺id
            $lng = input("lng") ? strval(input("lng")) : 0 ;//经度
            $lat = input("lat") ? strval(input("lat")) : 0 ;//纬度
            $page = input("page") ? intval(input("page")) : 1 ;//页码
            $size = input("size") ? intval(input("size")) : 10 ;//每页数量
            if(!$store_id){return json(self::callback(0, '参数错误'), 400);}

            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::storeDynamicLits($store_id,$lng,$lat,$user_id,$page,$size);

            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 获取生成的生活剪影数量
     */
    public function getRecommendedAmount(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $scene_id = input("scene_id") ? strval(input("scene_id")) : 0 ;//场景id   1,2,3
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if($scene_id == 0){//场景id为0，查看用户记录的场景id
                if($user_id == 0){//用户id为0，未登录
                    $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
                }else{//查找用户记录的scene_id
                    $user_scene_id = Db::name('user')->where(['user_id'=>$user_id])->value('user_scene');
                    if (!empty($user_scene_id) && $user_scene_id != '0'){//用户场景id存在
                        $scene_arr = explode(",",$user_scene_id);
                        $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
                    }else{//用户场景id不存在，随机生成三个大类
                        $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
                    }
                }
            }else{
                $scene_arr = explode(",",$scene_id);
                $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
            }
            $pids = array_map('array_shift', $pid);

            //找出每个大类的动态数量
            $scene_main= Db::name('dynamic')
                ->where(['scene_main_id'=>['in',$pids],'status'=>1])
                ->field(['scene_main_id','count(*) as num'])
                ->group('scene_main_id')
                ->select();
            $recommended_amount = 1;
            foreach ($scene_main as $ks => $vs){
                $recommended_amount  *= $vs['num'];
            }
            if($recommended_amount > 50){
                $recommended_amount = rand(20,50);
            }
            return \json(self::callback(1, '成功', $recommended_amount));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 生活剪影列表
     */
    public function lifeSilhouetteList(){
        try{
        $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
        $token = input('token','','addslashes,strip_tags,trim');
        $scene_id = input("scene_id") ? strval(input("scene_id")) : 0 ;//场景id   1,2,3
        $lng = input("lng") ? strval(input("lng")) : 0 ;//经度
        $lat = input("lat") ? strval(input("lat")) : 0 ;//纬度
        $group_id = input("group_id") ? strval(input("group_id")) : 0 ;//组id
        if (isset($user_id) && $user_id>0) {
            //token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
        }
        if($group_id > 0){//通过组id 查找生活剪影
            $data = DynamicModel::LifeSilhouetteDetails($user_id,$group_id,[],$lng,$lat);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }
        $scene_arr = [];
        if($scene_id == 0){//场景id为0，查看用户记录的场景id
            if($user_id == 0){//用户id为0，未登录
                $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
            }else{//查找用户记录的scene_id
                $user_scene_id = Db::name('user')->where(['user_id'=>$user_id])->value('user_scene');
                if (!empty($user_scene_id) && $user_scene_id != '0'){//用户场景id存在
                    $scene_arr = explode(",",$user_scene_id);
                    $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
                }else{//用户场景id不存在，随机生成三个大类
                    $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
                }
            }
        }else{
            //对scene_id 进行排序
            $scene_arr = explode(",",$scene_id);
            asort($scene_arr);
            $scene_id = implode(",",$scene_arr);
            if($user_id > 0){//用户登录,记录scene_id
                $user_scene_id = Db::name('user')->where(['user_id'=>$user_id])->value('user_scene');
                if($user_scene_id != $scene_id){
                    Db::name('user')->where(['user_id' => $user_id]) -> update(['user_scene' => $scene_id]);
                }
            }
            $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
        }
        $pids = array_map('array_shift', $pid);
        $data = DynamicModel::lifeSilhouetteList($user_id,$scene_arr,$pids,$lat,$lng);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 生活剪影列表-多套
     */
    public function lifeSilhouetteListMore(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $scene_id = input("scene_id") ? strval(input("scene_id")) : 0 ;//场景id   1,2,3
            $lng = input("lng") ? strval(input("lng")) : 0 ;//经度
            $lat = input("lat") ? strval(input("lat")) : 0 ;//纬度
            $size = input("size") ? strval(input("size")) : 1 ;//纬度
            $group_id = input("group_id") ? strval(input("group_id")) : 0 ;//组id
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $scene_arr = [];
            if($scene_id == 0){//场景id为0，查看用户记录的场景id
                if($user_id == 0){//用户id为0，未登录
                    $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
                }else{//查找用户记录的scene_id
                    $user_scene_id = Db::name('user')->where(['user_id'=>$user_id])->value('user_scene');
                    if (!empty($user_scene_id) && $user_scene_id != '0'){//用户场景id存在
                        $scene_arr = explode(",",$user_scene_id);
                        $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
                    }else{//用户场景id不存在，随机生成三个大类
                        $pid = Db::name('scene')->field('id')->where(['status'=>1,'level'=>1])->order('RAND()')->limit(3)->select();
                    }
                }
            }else{
                //对scene_id 进行排序
                $scene_arr = explode(",",$scene_id);
                asort($scene_arr);
                $scene_id = implode(",",$scene_arr);
                if($user_id > 0){//用户登录,记录scene_id
                    $user_scene_id = Db::name('user')->where(['user_id'=>$user_id])->value('user_scene');
                    if($user_scene_id != $scene_id){
                        Db::name('user')->where(['user_id' => $user_id]) -> update(['user_scene' => $scene_id]);
                    }
                }

                $pid = Db::name('scene')->field('p_id')->where(['id'=>['in',$scene_arr],'status'=>1,'p_id'=>['<>',0]])->group('p_id')->select();
            }
            $pids = array_map('array_shift', $pid);

            $data = array();
            if($group_id > 0){
                $arr = DynamicModel::LifeSilhouetteDetails($user_id,$group_id,$pids,$lng,$lat);
                array_push($data,$arr);
            }else{
                for ($i=0; $i<$size; $i++){
                    $arr = DynamicModel::lifeSilhouetteList($user_id,$scene_arr,$pids,$lat,$lng);
                    array_push($data,$arr);
                }
            }

            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 生活剪影  收藏，点赞，浏览，分享
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function lifeSilhouetteCollect(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $group_id = input("group_id") ? strval(input("group_id")) : 0 ;//生活剪影groupID
            $type = input("type") ? intval(input("type")) : 0 ;  //1收藏，2点赞  3浏览，4分享
            $status = input("status") ? intval(input("status")) : 0 ;  //1添加,2删除
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            //添加收藏/点赞/分享/浏览次数，以及收藏表/点赞表/浏览表用户记录

            $data = DynamicModel::lifeSilhouetteCollect($user_id,$group_id,$type,$status);
            if(is_string($data)) return json(self::callback(0, $data,false), 400);
            if ($data === false) return json(self::callback(0, '失败!',false), 400);
            return \json(self::callback(1, '成功', true));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }

    }

    /**
     * 生活剪影列表-点击商品
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function lifeSilhouetteProduct(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? strval(input("dynamic_id")) : 0 ;//生活动态ID
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::lifeSilhouetteProduct($dynamic_id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 生活剪影列表-点击商铺
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function lifeSilhouetteStore(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $dynamic_id = input("dynamic_id") ? strval(input("dynamic_id")) : 0 ;//生活动态ID
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::lifeSilhouetteStore($dynamic_id);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }


    /**
     * 更新年龄以及性别
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function updateUserAge(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $gender = input("gender") ? intval(input("gender")) : 0 ;//1男  2女
            $age_range_id = input("age_range_id") ? intval(input("age_range_id")) : 0 ;//1男  2女
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::updateUserAge($user_id,$gender,$age_range_id);
            if(is_string($data)) return json(self::callback(0, $data,false), 400);
            if ($data === false) return json(self::callback(0, '失败!',false), 400);
            return \json(self::callback(1, '成功', true));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 我的收藏 生活剪影列表
     */
    public function myLifeSilhouette(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $page = input("page") ? intval(input("page")) : 1 ;//页码
            $size = input("size") ? intval(input("size")) : 10 ;//每页数量
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::myLifeSilhouette($user_id,$page,$size);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    /**
     * 生活剪影组详情
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function LifeSilhouetteDetails(){
        try{
            $user_id = input("user_id") ? intval(input("user_id")) : 0 ;
            $token = input('token','','addslashes,strip_tags,trim');
            $group_id = input("group_id") ? intval(input("group_id")) : 0 ;//生活剪影组id
            $lng = input("lng") ? strval(input("lng")) : 0 ;//经度
            $lat = input("lat") ? strval(input("lat")) : 0 ;//纬度
            if (isset($user_id) && $user_id>0) {
                //token 验证
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            $data = DynamicModel::LifeSilhouetteDetails($user_id,$group_id,[],$lng,$lat);
            if(is_string($data)) return json(self::callback(0, $data), 400);
            if ($data === false) return json(self::callback(0, '失败!'), 400);
            return \json(self::callback(1, '成功', $data));
        }catch (\Exception $e){
            return \json(self::callback(0, $e -> getMessage()));
        }
    }

    public function dynamicSceneList(Scene $scene){
        try{
            $list = $scene->getSceneTree();
            return json(self::callback(1,'', $list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户动态可推荐店铺列表
     * @param UserModel $user
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function userDynamicStoreList(UserModel $user){
        try{
            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            ##逻辑
            $list = $user->getUserDynamicStoreList();
            ##返回
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 获取用户动态可推荐商品列表
     * @param UserModel $user
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function userDynamicProductList(UserModel $user){
        try{
            ##token 验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            ##逻辑
            $list = $user->getUserDynamicProductList();
            ##返回
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

    /**
     * 获取动态话题列表
     * @param Validate $validate
     * @param Topic $topic
     * @return Json
     */
    public function dynamicTopicList(Validate $validate, Topic $topic){
        try{
            ##验证
            $rule = [
                'page' => 'number|>=:1',
                'size' => 'number|>=:1'
            ];
            $res = $validate->rule($rule)->check(input());
            if(!$res)throw new Exception($validate->getError());
            ##逻辑
            $list = $topic->getUserDynamicTopic();
            ##返回
            return json(self::callback(1,'',$list));
        }catch(Exception $e){
            return json(self::callback(0, $e->getMessage()));
        }
    }

}