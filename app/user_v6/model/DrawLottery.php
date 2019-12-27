<?php


namespace app\user_v6\model;


use think\Model;
use think\model\relation\HasMany;
use traits\model\SoftDelete;
use my_redis\MRedis;

class DrawLottery extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    /**
     * 获取有效的抽奖
     * @return array
     */
    public static function getValidList(){
        return (new self())
            ->where(['status'=>1, 'end_time'=>['GT', time()], 'client'=>['IN', [1,3]]])
            ->field('id,end_time')
            ->with([
                'rules' => function(HasMany $hasMany){
                    $hasMany->field('conditions,lottery_id,id');
                }
            ])
            ->select()
            ->toArray();
    }

    /**
     * 一对多  抽奖次数规则
     * @return HasMany
     */
    public function rules(){
        return $this->hasMany('TacticsLottery','lottery_id','id');
    }

    /**
     * 获取抽奖活动信息
     * @param $draw_id
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public static function makeDrawLotteryRedisData($draw_id){
        $data = (new self())
            ->where(['id'=>$draw_id])
            ->with([
                'reward' => function(HasMany $hasMany){
                    $hasMany->alias('gl')
                        ->join('coupon_rule cr','cr.id = gl.gift_id','LEFT')
                        ->field('
                            gl.id,gl.gift_type as type,gl.gift_desc,gl.gift_name,gl.icon,gl.lottery_id,gl.actual_gift_count,gl.gift_count,gl.remain,
                            cr.coupon_money,cr.satisfy_money,cr.coupon_name
                        ')->order('gl.sort','asc');
                }
            ])
            ->field('id,title,description,start_time,end_time,rule,number as draw_num,type,status,per_user_max_number,icon,bg_img,fake_user')
            ->find();
        $data = json_decode(json_encode($data),true);
        $reward = $data['reward'];
        $new_reward = [];

        ##计算总奖品数
        $reward_num = 0;
        foreach($reward as &$v){
            $v['coupon_money'] = (string)floatval($v['coupon_money']);
            $v['satisfy_money'] = (string)floatval($v['satisfy_money']);

            if($v['type'] == 1){
                $reward_num += ($data['type'] == 1?$v['gift_count']:$v['actual_gift_count']);
                $v['gift_desc'] = $v['coupon_name'];
            }

            $new_reward[$v['id']] = $v;
        }
        $data['reward'] = $new_reward;
        $data['reward_for_web'] = $reward;
        $data['rule'] = explode('|',$data['rule']);
        $data['reward_num'] = $reward_num;

        return $data;
    }

    /**
     * 一对多获取抽奖奖品列表
     * @return HasMany
     */
    public function reward(){
        return $this->hasMany('GiftLottery','lottery_id','id');
    }

    /**
     * 将抽奖数据更新至redis
     * @param $draw_id
     * @return bool
     */
    public static function addDrawRedisData($draw_id){
        $data = self::makeDrawLotteryRedisData($draw_id);
        $timeout = $data['end_time'] - time() + 10 * 24 * 60 * 60;
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();
        $draw_key = getDrawKey($draw_id);
        $res = $redis->set($draw_key,json_encode($data),$timeout);
        if(!$res)return false;
        ##获取奖池的键
        $lottery_key = $data['type'] ==1 ?getDrawRandomKey($draw_id):getDrawManicKey($draw_id);
//        $lottery_data = $redis->keys($lottery_key);
        ##删除键
        $redis->del($lottery_key);
        $lottery_list = self::makeLotteryList($draw_id);
        $params = array_merge([$lottery_key],$lottery_list);
        $res = call_user_func_array([$redis, 'lPush'],$params);
        if(!$res)return false;

        ##获取获奖记录的键
        $record_key = getDrawWithFakeRecordKey($draw_id);
        $redis->del($record_key);
        if($data['fake_user'] > 0){
            $record_list = createFakeRewardRecord($data['fake_user'],$data['reward_for_web'],$data['start_time']);
            $params = array_merge([$record_key], $record_list);
            $res = call_user_func_array([$redis, 'lPush'],$params);
        }
        return $res;
    }

    /**
     * 修改抽奖状态更新redis
     * @param $draw_id
     * @return bool
     */
    public static function editStatusEditDrawRedisData($draw_id){
        $data = self::makeDrawLotteryRedisData($draw_id);
        $timeout = $data['end_time'] - time() + 10 * 24 * 60 * 60;
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();
        $draw_key = getDrawKey($draw_id);
        $res = $redis->set($draw_key,json_encode($data),$timeout);
        return $res;
    }

    /**
     *  将浮动入口抽奖数据更新至redis
     * @return bool
     */
    public static function addMainDrawRedisData(){
        ##获取浮动入口抽奖活动id
        $draw_id = self::where(['icon_status'=>1])->value('id');
        if(!$draw_id)return true;
        $data = self::makeDrawLotteryRedisData($draw_id);
        $timeout = $data['end_time'] - time() + 10 * 24 * 60 * 60;
        $MRedis = new MRedis();
        $redis = $MRedis->getRedis();
        $draw_key = getMainDrawKey();
        return $redis->set($draw_key,$draw_id,$timeout);
    }

    /**
     * 获取奖池
     * @param $draw_id
     * @return array
     */
    public static function makeLotteryList($draw_id){
        $draw_info = self::where(['id'=>$draw_id])->field('type,number')->find();
        $reward = GiftLottery::getLotteryData($draw_id);
        $lottery_list = [];
        foreach($reward as $v){
            if($draw_info['type'] == 1){  ##随机模式
                for($i=$v['actual_gift_count'];$i>0;$i--){
                    array_push($lottery_list,$v['id']);
                }
            }else {  ##概率模式
                for ($i = $v['manic']; $i > 0; $i--) {
                    array_push($lottery_list, $v['id']);
                }
            }
        }
        if($draw_info['type'] == 1){
            $max = $draw_info['number'] - count($lottery_list);
            for($i=$max;$i>0;$i--){
                array_push($lottery_list,0);
            }
        }
        ##打乱顺序
        shuffle($lottery_list);
        return $lottery_list;
    }

    /**
     * 判断活动状态
     * @param $draw_id
     * @return bool
     */
    public static function checkDrawLottery($draw_id){
        $info = self::where(['id'=>$draw_id])->field('end_time,status')->find();
        ##活动不存在
        if(!$info)return false;
        ##活动已结束
        if($info['end_time'] <= time())return false;
        ##活动下架
        if($info['status'] != 1)return false;
        return true;
    }

}