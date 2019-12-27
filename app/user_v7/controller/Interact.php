<?php


namespace app\user_v7\controller;

use app\user_v7\common\Base;
use app\user_v7\common\User as UserFunc;
use app\user_v7\common\UserLogic;
use app\user_v7\model\CommentModel;
use my_redis\MRedis;
use think\Db;
use jiguang\JiG;
use think\Exception;
use think\response\Json;
use think\Validate;
use app\user_v7\model\User;

class Interact extends Base
{

    /**
     * 获取客服
     * @param $user_id
     * @param $store_id
     * @return array
     */
    public function getStoreService($user_id, $store_id){

        $userInfo = Db::name('user')->where(['user_id'=>$user_id])->field('user_id,jig_id,jig_pwd')->find();
        if(!$userInfo['jig_id']){  //注册
            $jig_info = JiG::registerUser($userInfo['user_id']);
            if($jig_info['status'] == 0){
                addErrLog($jig_info,'注册极光用户失败');
                return ['status'=>0,'err'=>$jig_info['err']];
            }
            $jig_id = $jig_info['jig_id'];
            $jig_pwd = $jig_info['jig_pwd'];
        }else{
            $jig_id = $userInfo['jig_id'];
            $jig_pwd = $userInfo['jig_pwd'];
        }

        ##获取客服
        $service_info = JiG::getCustomerServiceInfo($userInfo['user_id'],$store_id);
        if($service_info['status'] == 0)return ['status'=>0,'err'=>$service_info['err']];
        $store_jig_id = $service_info['data']['jig_id'];

        return ['status'=>1,'data'=>compact('jig_id','jig_pwd','store_jig_id')];

    }

    /**
     * 获取互动信息
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function interactData(){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #逻辑
            $user_id = $userInfo['user_id'];
            ##获取关注数
            $follow_num = UserLogic::userFollowNum($user_id);
            ##获取收藏数
            $collect_num = User::getUserCollectNum($user_id);
            ##获取评论数
            $comment_num = User::getCommentNum($user_id);
            ##判断用户有没有待查看评论数
            $new_comment = User::newCommentNum($user_id);
            ##获取未读系统消息数
            $system_num = User::userNewSystemMsgNum($user_id);
            ##最近一条未读系统消息时间
            $system_msg_time = $system_num?((int)User::userNewSystemMsgTime($user_id)):0;
            #返回
            return json(self::callback(1,'',compact('follow_num','collect_num','comment_num','new_comment','system_num','system_msg_time')));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 动态消息回复列表
     * @param Validate $validate
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function dynamicComments(Validate $validate){
        try{
            #验证
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            #验证参数
            $rule = [
                'page' => 'number|>=:1',
                'size' => 'number|>=:1|<=:20'
            ];
            $res = $validate->rule($rule)->check(input());
            if(!$res)throw new Exception($validate->getError());
            #逻辑
            $data = CommentModel::userCommentRecoveryList($userInfo['user_id']);
            ##更新用户查看评论时间
            User::updateUserScanCommentTime($userInfo['user_id']);
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户主抽奖活动信息
     * @return Json
     */
    public function getMainDrawLottery(){
        try{
            $redis = new MRedis();
            $redis = $redis->getRedis();
            ##获取首页抽奖活动id
            $key = getMainDrawKey();
            $mainDraw = $redis->get($key);
            if(!$mainDraw)throw new Exception('暂无主抽奖活动');
            ##获取首页抽奖活动信息
            $draw_key = getDrawKey($mainDraw);
            $data = $redis->get($draw_key);
            if(!$data)throw new Exception('暂无主抽奖活动');
            $data = json_decode($data,true);
            if(!is_array($data) && !$data)throw new Exception('暂无主抽奖活动');
            if($data['end_time']<=time())throw new Exception('暂无主抽奖活动');
            if($data['status'] != 1)throw new Exception('暂无主抽奖活动');
            return json(self::callback(1,'',['icon'=>$data['icon'],'title'=>'抽奖','state'=>1,'draw_id'=>(int)$mainDraw]));
        }catch(Exception $e){
            return json(self::callback(1,$e->getMessage(),['icon'=>'','title'=>'','state'=>0,'draw_id'=>0]));
        }
    }

    /**
     * 获取活动信息
     * @return Json
     */
    public function getDrawLotteryData(){
        try{
            $draw_id = input('post.draw_id',0,'intval');
            if(!$draw_id)throw new Exception('参数错误');
            $key = getDrawKey($draw_id);
            $redis = new MRedis();
            $redis = $redis->getRedis();
            $data = $redis->get($key);
            if(!$data)throw new Exception('活动不存在或者已下架');
            $data = json_decode($data,true);
            if(!is_array($data) || !$data)throw new Exception('活动不存在或者已下架');
            $reward = $data['reward_for_web'];
            unset($data['reward_for_web']);
            $data['reward'] = $reward;
            return json(self::callback(1,'',$data));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取抽奖实时数据【中奖记录，用户可抽奖次数，活动状态】
     * @param MRedis $MRedis
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getOtherInfo(MRedis $MRedis){
        try{
            $draw_id = input('post.draw_id',0,'intval');
            $user_id = input('post.user_id',0,'intval');
            if($user_id){
                $userInfo = UserFunc::checkToken();
                if ($userInfo instanceof Json){
                    return $userInfo;
                }
            }
            if(!$draw_id)throw new Exception('参数缺失');

            $redis = $MRedis->getRedis();

            ##获取抽奖信息(状态)
            $key = getDrawKey($draw_id);
            $draw_info = $redis->get($key);
            if(!$draw_info)throw new Exception('活动不存在或者已下架');
            $draw_info = json_decode($draw_info,true);
            if(!is_array($draw_info) || !$draw_info)throw new Exception('活动异常');
            if($draw_info['status'] != 1)throw new Exception('活动已下架');
            $start_time = (int)$draw_info['start_time'];
            $end_time = $draw_info['end_time'];
            $cur_time = time();
            $status = 2;  //默认活动已开始
            if(time() < $start_time){  //活动未开始
                $status = 1;
            }
            if(time() >= $end_time){   //活动已结束||已抽完
                $status = 3;
            }
            ##获取抽奖记录
            $draw_record_key = getDrawRecordKey($draw_id);
            $draw_record = $redis->lLen($draw_record_key);
            if($draw_record >= $draw_info['draw_num']){  //已抽完
                $status = 3;
            }
            ##获取中奖纪录
            $record_key = getDrawWithFakeRecordKey($draw_id);
            $get_lottery_num = (int)($redis->lLen($record_key));
            $start = 0;
            if($get_lottery_num > 20){
                $start = $get_lottery_num - 20;
            }
            ##获取最新的20条记录
            $reward_record = $redis->lGetRange($record_key,$start,$start + 19);
            $now = time();
            foreach($reward_record as $k => $v){
                if($v['draw_time'] > $now){  ##抽奖时间大于当前时间,删除
                    unset($reward_record[$k]);
                }else{
                    $reward_record[$k] = json_decode($v,true);
                }
            }
            ##将最新的中奖记录展示在最前
            $reward_record = array_reverse($reward_record);

            ##获取用户可抽奖次数
            if($user_id){
                $user_num_key = getUserDrawNumKey($draw_id);
                $draw_num = intval($redis->hGet($user_num_key, $user_id));
            }else{
                $draw_num = 0;
            }

            ##活动信息
            $reward = $draw_info['reward_for_web'];
            unset($draw_info['reward_for_web']);
            $draw_info['reward'] = $reward;
            #返回
            return json(self::callback(1,'',compact('status','cur_time','start_time','draw_num','get_lottery_num','reward_record','draw_info')));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 用户抽奖逻辑
     * @param MRedis $MRedis
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function drawLottery(MRedis $MRedis){
        ##state   1.正常;2.活动已下架;3.活动未开始;4.活动已结束;5.奖品已抽完
        try{
            ##验证用户登录
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $draw_id = input('post.draw_id',0,'intval');
            if(!$draw_id)throw new Exception('参数缺失');

            $redis = $MRedis->getRedis();

            ##获取活动信息
            $draw_key = getDrawKey($draw_id);
            $draw_info = $redis->get($draw_key);
            if(!$draw_info)throw new Exception('活动不存在');
            $draw_info = json_decode($draw_info,true);
            if(!is_array($draw_info) || !$draw_info)throw new Exception('活动信息异常');

            $reward_list = $draw_info['reward'];

            $coupon_info = ['user_coupon_id'=>'0', 'is_solo'=>'0', 'type'=>'0', 'store_id'=>"0", 'is_online'=>'0'];

            $state = 1;

            if($draw_info['status'] != 1){
                $state = 2;
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }
            if($draw_info['start_time'] > time()){ ##未开始
                $state = 3;
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }
            if($draw_info['end_time'] <= time()){  ##已结束
                $state = 4;
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }

            ##获取活动的抽奖记录
            $draw_record_key = getDrawRecordKey($draw_id);
            $draw_record = $redis->lLen($draw_record_key);
            ##奖品已抽完
            if($draw_record >= $draw_info['draw_num']){
                $state = 5;
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }

            ##获取用户可抽奖次数
            $draw_num_key = getUserDrawNumKey($draw_id);
            $did_draw_num_key = getUserDidDrawNumKey($draw_id);
            $draw_num = intval($redis->hGet($draw_num_key, $userInfo['user_id']));
            $did_draw_num = intval($redis->hGet($did_draw_num_key, $userInfo['user_id']));
            if(!$draw_num || $draw_num<=0){
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }

            ##判断用户抽奖达到限制次数
            if($did_draw_num && $did_draw_num >= $draw_info['per_user_max_number'] && $draw_info['per_user_max_number'] != -1){

                ##更新用户抽奖次数[可抽奖|已抽奖]
                $redis->hSet($draw_num_key, $userInfo['user_id'],$draw_num-1);
                $redis->hSet($did_draw_num_key, $userInfo['user_id'], $did_draw_num+1);

                ##获取第一个无奖的id
                $reward_id = getDefaultRewardId($reward_list);
                return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
            }

            ##中奖的key
            $reward_record_key = getDrawWithFakeRecordKey($draw_id);

            ##获取用于导入的抽奖记录键
            $reward_record_import_key = getDrawImportRecordKey();

            ##redis队列
            $key = getDrawLockKey($draw_id);

            do{
                $rand = rand(10000,99999);
                $isLock = $redis->set($key, $rand, ['nx', 'ex'=>5]);
                if($isLock){

                    ##重新获取抽奖信息
                    $draw_info = $redis->get($draw_key);
                    $draw_info = json_decode($draw_info,true);
                    $reward_list = $draw_info['reward'];

                    ##再次判断抽奖次数
                    $draw_record = $redis->lLen($draw_record_key);
                    if($draw_record >= $draw_info['draw_num']){
                        $state = 5;
                        $reward_id = getDefaultRewardId($reward_list);
                        return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
                    }

                    ##更新用户抽奖次数[可抽奖|已抽奖]
                    $redis->hSet($draw_num_key, $userInfo['user_id'],$draw_num-1);
                    $redis->hSet($did_draw_num_key, $userInfo['user_id'], $did_draw_num+1);

                    if($draw_info['type'] == 1){  //随机模式
                        ##获取抽奖的列表
                        $draw_random_key = getDrawRandomKey($draw_id);
                        $reward = (int)($redis->lPop($draw_random_key));

                        $data = [
                            'user_id' => $userInfo['user_id'],
                            'draw_time' => time(),
                            'reward_id' => $reward,
                            'is_reward' => 0
                        ];
                        if(!$reward){  //未中奖
                            ##增加抽奖记录
                            $redis->rPush($draw_record_key, json_encode($data));
                            $data['draw_id'] = $draw_id;
                            $redis->rPush($reward_record_import_key, json_encode($data));
                            if($redis->get($key) == $rand)$redis->del($key);
                            $reward_id = getDefaultRewardId($reward_list);
                            return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
                        }
                        ##中奖但是是无奖品
                        $reward_info = $reward_list[$reward];
                        if($reward_info['type'] != 1){
                            ##增加抽奖记录
                            $redis->rPush($draw_record_key, json_encode($data));
                            $data['draw_id'] = $draw_id;
                            $redis->rPush($reward_record_import_key, json_encode($data));
                            if($redis->get($key) == $rand)$redis->del($key);
                            return json(self::callback(1,'',['reward_id'=>$reward_info['id'],'coupon_info'=>$coupon_info,'state'=>$state]));
                        }
                        ##中奖且有奖品
                        ###增加抽奖记录
                        $data['is_reward'] = 1;
                        $redis->rPush($draw_record_key, json_encode($data));
                        $data['draw_id'] = $draw_id;
                        $redis->rPush($reward_record_import_key, json_encode($data));
                        if($redis->get($key) == $rand)$redis->del($key);
                        ###增加中奖记录
                        $data_reward = [
                            'avatar' => $userInfo['avatar'],
                            'user_name' => $userInfo['nickname'],
                            'draw_time' => time(),
                            'gift_title' => $reward_list[$reward]['coupon_name']
                        ];
                        $redis->rPush($reward_record_key, json_encode($data_reward));
                        ###发放优惠券
                        $coupon_info = UserLogic::userDrawGetCoupon($userInfo['user_id'], $reward_info['id'], $draw_id);
                        #返回
                        return json(self::callback(1,'',['reward_id'=>$reward_info['id'], 'coupon_info'=>$coupon_info,'state'=>$state]));

                    }else{  //概率模式
                        $draw_manic_key = getDrawManicKey($draw_id);
                        $len = $redis->lLen($draw_manic_key);
                        ##摇色子
                        $index = mt_rand(0, $len -1);
                        ##获取色子的结果
                        $reward_info = $redis->lRange($draw_manic_key,$index,$index);  //下标从0开始
                        $reward = (int)($reward_info[0]);

                        $data = [
                            'user_id' => $userInfo['user_id'],
                            'draw_time' => time(),
                            'reward_id' => $reward,
                            'is_reward' => 0
                        ];
                        if(!$reward){  //未中奖
                            ##增加抽奖记录
                            $redis->rPush($draw_record_key, json_encode($data));
                            $data['draw_id'] = $draw_id;
                            $redis->rPush($reward_record_import_key, json_encode($data));
                            if($redis->get($key) == $rand)$redis->del($key);
                            $reward_id = getDefaultRewardId($reward_list);
                            return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
                        }
//                        ##更新奖池的当前下标为0
//                        $redis->lSet($draw_manic_key,$index,0);


                        $reward_info = $reward_list[$reward];
                        ##中奖但是奖品已抽完
                        if($reward_info['remain'] <= 0){
                            $data['reward_id'] = 0;
                            ##增加抽奖记录
                            $redis->rPush($draw_record_key, json_encode($data));
                            $data['draw_id'] = $draw_id;
                            $redis->rPush($reward_record_import_key, json_encode($data));
                            if($redis->get($key) == $rand)$redis->del($key);
                            $reward_id = getDefaultRewardId($reward_list);
                            return json(self::callback(1,'',compact('reward_id','coupon_info','state')));
                        }
                        ##中奖但是是无奖品
                        if($reward_info['type'] != 1){
                            ##增加抽奖记录
                            $redis->rPush($draw_record_key, json_encode($data));
                            $data['draw_id'] = $draw_id;
                            $redis->rPush($reward_record_import_key, json_encode($data));
                            ##更新奖品数
                            $draw_info['reward'][$reward]['remain'] = (string)($reward_info['remain'] - 1);
                            $redis->set($draw_key,json_encode($draw_info),$draw_info['end_time'] + 10 * 24 * 60 * 60);
                            if($redis->get($key) == $rand)$redis->del($key);
                            return json(self::callback(1,'',['reward_id'=>$reward_info['id'],'coupon_info'=>$coupon_info,'state'=>$state]));
                        }

                        ##中奖且有奖品
                        ###增加抽奖记录
                        $data['is_reward'] = 1;
                        $redis->rPush($draw_record_key, json_encode($data));
                        $data['draw_id'] = $draw_id;
                        $redis->rPush($reward_record_import_key, json_encode($data));
                        ##更新奖品数
                        $draw_info['reward'][$reward]['remain'] = (string)($reward_info['remain'] - 1);
                        $redis->set($draw_key,json_encode($draw_info),$draw_info['end_time'] + 10 * 24 * 60 * 60);
                        if($redis->get($key) == $rand)$redis->del($key);
                        ###增加中奖记录
                        $data_reward = [
                            'avatar' => $userInfo['avatar'],
                            'user_name' => $userInfo['nickname'],
                            'draw_time' => time(),
                            'gift_title' => $reward_list[$reward]['coupon_name']
                        ];
                        $redis->rPush($reward_record_key, json_encode($data_reward));
                        ###发放优惠券
                        $coupon_info = UserLogic::userDrawGetCoupon($userInfo['user_id'], $reward_info['id'], $draw_id);
                        #返回
                        return json(self::callback(1,'',['reward_id'=>$reward_info['id'], 'coupon_info'=>$coupon_info,'state'=>$state]));
                    }
                }else{
                    usleep(5000);  //休息5毫秒
                }
            }while(!$isLock);
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 获取用户抽中奖品列表
     * @return array|false|\PDOStatement|string|\think\Model|Json
     */
    public function getUserDrawLotteryReward(){
        try{
            ##验证用户登录
            $userInfo = UserFunc::checkToken();
            if ($userInfo instanceof Json){
                return $userInfo;
            }
            $draw_id = input('post.draw_id',0,'intval');
            if(!$draw_id)throw new Exception('参数缺失');
            ##逻辑
            $coupon_list = UserLogic::getUserDrawCouponList($userInfo['user_id'], $draw_id);
            ##返回
            return json(self::callback(1,'',$coupon_list));
        }catch(Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}