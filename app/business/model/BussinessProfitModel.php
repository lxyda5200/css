<?php


namespace app\business\model;


use think\Model;
use think\Db;
class BussinessProfitModel extends Model
{

    protected $pk = 'id';

    protected $table = 'bussiness_profit';

    /**
     *  获取员工推广数据 type1  15天内  type 2   总计
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessTotal($where,$type,$start_time,$end_time){
        $where['status'] = 3;
        if($start_time > 0 && $end_time > 0){
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }else{
            if($type == 1){ //半个月内
                $where['create_time'] = ['egt', strtotime(date("Y-m-d", strtotime("-15 day")))];
            }
        }

        $total = self::where($where)->field('sum(price_profit) as profit')->find();
        return $total['profit'];
    }

    /**
     * 获取推广列表  1未付   2已付    3已结转
     * @param $where
     * @param $status
     */
    public static function getBusinessRecommendList($where,$status,$page,$limit){
        $pre = ($page-1)*$limit;
        $where['status'] = $status;
        $where['price_profit'] = ['gt',0];
        $list = self::where($where)->field(['SUM(price_profit) AS profit',
            'IF(FROM_UNIXTIME(create_time, \'%d\') <= 15, FROM_UNIXTIME(create_time, \'%Y/%m/01\'), FROM_UNIXTIME(create_time, \'%Y/%m/16\')) as date_range'
            ])
            ->group('date_range')
            ->order('date_range desc')
            ->limit($pre,$limit)
            ->select();
        foreach ($list as &$v){
            if(substr($v['date_range'], -1) == 1){
                $v['start_time'] = strtotime($v['date_range']);
                $new = substr($v['date_range'],0,8).'15';
                $v['date_range'].=' - '.$new;
                $v['end_time'] = strtotime($new) + 86399;
            }else{
                $v['start_time'] = strtotime($v['date_range']);
                $firstday = date('Y-m-01', strtotime($v['date_range']));
                $lastday = date('Y/m/d', strtotime("$firstday +1 month -1 day"));
                $v['date_range'].=' - '.$lastday;
                $v['end_time'] = strtotime($lastday) + 86399;
            }
            $v['status'] = $status -1;
        }
        return $list;
    }

    /**
     * 计算首个用户奖励
     * @param $where
     * @param $type
     */
    public function getBusinessOne($where,$type){
        $where['type'] = $type;
        $where['price_profit'] = ['gt',0];
        $list = self::where($where)->field(['create_time','(status - 1) as status','price_profit'])->select();
        foreach ($list as &$v){
            $v['title'] = '首个用户额外奖励';
            $v['note'] = '';
        }
        return $list;
    }

    /**
     * 计算平台推广用户个数阶梯奖励
     * @param $where
     * @param $type
     */
    public static function getBusinessJieti($where,$type){
        $where['type'] = $type;
        $where['price_profit'] = ['gt',0];
        $list = self::where($where)->field(['create_time','(status - 1) as status','price_profit','achievement_user_num','min_maidan_price'])->select();
        foreach ($list as &$v){
            $achievement_user_num = $v['achievement_user_num'];
            $min_maidan_price = $v['min_maidan_price'];
            $v['title'] = '达成'.$achievement_user_num.'个用户阶梯奖励';
            $v['note'] = '备注：新用户首次买单金额超过￥'.$min_maidan_price;
            unset($v['achievement_user_num']);
            unset($v['min_maidan_price']);
        }
        return $list;
    }

    /**
     * 单个新用户奖励 总计与次数
     * @param $where
     * @param $type
     */
    public static function getBusinessRecommend($where,$type,$status,$start_time,$end_tim){
        $where['type'] = $type;
        $where['price_profit'] = ['gt',0];
        $count = self::where($where)->field(['create_time','status','price_profit'])->count();//次数
        $total = self::where($where)->field('SUM(price_profit) AS profit')->find();
        $data = [];
        if($count > 0){
            $arr = array(
                "create_time"=> date('Y/m/d',$start_time).'-'.date('Y/m/d',$end_tim),
                "status"=> strval($status -1),
                "price_profit"=> $total['profit'],
                "title"=> "新用户数：".$count."个",
                "note"=> ""
            );
            array_push($data,$arr);
        }
        return $data;
    }

    /**
     * 获取销售提成详情
     * @param $profit_id
     */
    public static function getSalesDetail($profit_id){
        $list = self::where(['bp.id'=>$profit_id,'bp.status'=>3,'bp.type'=>1])
            ->alias('bp')
            ->join('maidan_order mo', 'bp.maidan_order_id = mo.id', 'left')
            ->field(['mo.order_sn','bp.create_time','mo.price_maidan','bp.price_profit','bp.type'])
            ->find();
        if($list){
            $list['source'] = '买单提成';
        }
        return $list;
    }

    /**
     * 员工其他收入详情  2.销售总额阶梯奖励；3.新用户推广奖励；4.首个用户额外奖励；5.新用户推广阶梯奖励
     ** @param $profit_id
     */
    public static function getIncomesDetail($profit_id){
        $list = self::where(['id'=>$profit_id,'status'=>3])
            ->field('achievement_price,min_maidan_price,achievement_user_num,price_profit,type,(status - 1) as status,create_time')
            ->find();
        if($list){
            switch ($list['type']){
                case 2:
                    $achievement_price = $list['achievement_price'];
                    $list['title'] = '销售总额达到'.$achievement_price.'阶梯奖励';
                    $list['note'] = '';
                    break;
                case 3:
                    $list['title'] = '新用户推广奖励';
                    $list['note'] = '';
                    break;
                case 4:
                    $list['title'] = '首个用户额外奖励';
                    $list['note'] = '';
                    break;
                case 5:
                    $achievement_user_num = $list['achievement_user_num'];
                    $min_maidan_price = $list['min_maidan_price'];
                    $list['title'] = '达成'.$achievement_user_num.'个用户阶梯奖励';
                    $list['note'] = '备注：新用户首次买单金额超过￥'.$min_maidan_price;
                    break;
            }

            unset($list['achievement_price']);
            unset($list['achievement_user_num']);
            unset($list['min_maidan_price']);
        }


        return $list;
    }




}