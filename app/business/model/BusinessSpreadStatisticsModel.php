<?php


namespace app\business\model;


use think\Model;
use think\Db;
class BusinessSpreadStatisticsModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_spread_statistics';

    /**
     *  获取推广统计数据
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessSpreadList($where = []){
        $list = self::where($where)->field(['status', 'total_money', 'status', 'FROM_UNIXTIME(start_time,\'%Y-%m-%d\') as start_time', 'FROM_UNIXTIME(end_time,\'%Y-%m-%d\') as end_time', 'create_time'])->select();
        $totalMoney = array_sum(array_column($list, 'total_money'));
        return compact('list', 'totalMoney');
    }

    /**
     *  获取推广详情
     * @param $spread_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getSpreadDetails($spread_id){
        $info = self::where('id',  $spread_id)->field(['start_time', 'end_time', 'business_id'])->find();

        if (!$info) return '数据检索失败';

        $list = BusinessSpreadModel::where(['create_time' => ['between', [$info['start_time'], $info['end_time']]], 'business_id' => $info['business_id']])->field([
            'id','order_id','status','money','create_time','statements_time','note'
        ])->select();

        return $list;
    }









    //--todu v6--//测试
    public static function lifeSilhouetteList($user_id,$scene_arr,$pids,$lat,$lng){
        $order = ['d.is_recommend'=>'DESC','weight'=>'DESC','d.create_time'=>'DESC'];
        $distance='IF('.$lat.' > 0 OR '.$lng.'>0, lat_lng_distance('.$lat.','.$lng.',st.lat,st.lng), 0)';
        $subQuery = Db::name('dynamic')->alias('d')
            ->join('store st','d.store_id = st.id','LEFT')
            //->join('scene sc','sc.id = d.scene_id','LEFT')
            ->join('business_circle_store bcs','bcs.store_id = st.id','LEFT')
            ->where(['scene_main_id'=>['in',$pids],'d.status'=>1])
            ->field(['d.id','st.cover as store_logo','st.store_name','st.id as store_id','d.description','d.cover','d.type','d.is_group_buy',
                'd.look_number','d.like_number','d.share_number','d.collect_number','d.comment_number','d.scene_main_id as p_id','d.scene_id as sc_id',
                '(d.look_number+d.like_number+d.collect_number+d.comment_number+d.share_number) as hot','st.signature','bcs.business_circle_id',
                'ROUND('.$distance.',2) as distance',
                'ROUND((look_number*0.1+d.like_number*0.3+d.collect_number*0.25+d.comment_number*0.1+d.share_number*0.15+'.$distance.'*0.1),2) as weight'])
            ->order($order)
            ->select();
        //对查询出的数据 做p_id分类
        $result = array();
        foreach ($subQuery as $key => $value) {
            $result[$value['p_id']][] = $value;
        }
        $ret = array();
        foreach ($result as $key => $value) {
            array_push($ret, $value);
        }
        //取出每组中匹配度高的动态
        $list = [];
        foreach ($ret as $k => $v){//遍历分类
            $d_arr = [];
            foreach ($v as $kv =>$vv){//遍历分类中每组的动态
                if(in_array($vv['sc_id'],$scene_arr)){
                    //如果浏览过，则不推荐
                    if($user_id > 0){
                        $user_records = Db::name('dynamic_user_record')->where(['user_id'=>$user_id,'dynamic_id'=>$vv['id']])->find();
                        if(!$user_records){
                            array_push($d_arr,$vv);
                        }
                    }else{
                        array_push($d_arr,$vv);
                    }
                }
            }
            if(count($d_arr) > 0){//在用户所选的scene中，有动态
                array_push($list,$d_arr['0']);//根据排序，取出第一个
            }else{//在用户所选的scene中，无动态，根据相识度获取动态
                array_push($list,$v['0']);//根据排序，暂时取出所选分类中的第一个
            }
        }

        $dynamic_ids = [];
        $business_circle_ids = [];
        foreach ($list as &$v){
            switch ($v['p_id']){
                case 1:
                    $v['scene'] = '聚会';
                    $v['scene_desc'] = '拒绝”社交恐惧“，轻松hold住全场';
                    break;
                case 7:
                    $v['scene'] = '运动';
                    $v['scene_desc'] = '不止好看的衣服，还有好棒的身材';
                    break;
                case 12:
                    $v['scene'] = '出行';
                    $v['scene_desc'] = '魅力四射，自然融入街边风景';
                    break;
                case 17:
                    $v['scene'] = '约会';
                    $v['scene_desc'] = '不一样的风格，一样的心动';
                    break;
                case 21:
                    $v['scene'] = 'Show';
                    $v['scene_desc'] = '点亮世界的色彩';
                    break;
            }
            //获取动态关联商品
            $v['dynamic_product'] = Db::name('dynamic_product')
                ->where('dynamic_id','EQ',$v['id'])
                ->count();
            //获取动态图片或者视频
            $v['dynamic_img'] = Db::name('dynamic_img')
                ->where('dynamic_id','EQ',$v['id'])
                ->field('img_url,cover,type')
                ->select();
            //获取生成的动态id组
            array_push($dynamic_ids,$v['id']);
            //获取动态的商圈
            if(!empty($v['business_circle_id'])){
                array_push($business_circle_ids,$v['business_circle_id']);
            }

        }
        $data['list'] = $list;
        //$data['cover'] = $data['list']['0'];
        //将生成的动态id，保存到dynamic_user_record，如果该浏览动态已被记录，则曝光加一，没有则添加
        if($user_id > 0){
            foreach ($dynamic_ids as $kd => $vd){
                $dynamic_user_record = Db::name('dynamic_user_record')->where(['user_id'=>$user_id,'dynamic_id'=>$vd])->find();
                if($dynamic_user_record){
                    Db::name('dynamic_user_record')->where('id',$dynamic_user_record['id'])->setInc('look_number',1);
                }else{
                    $dynamic_user_record_datas = [
                        'dynamic_id' => $vd,
                        'user_id' => $user_id,
                        'look_number' => 1,//曝光次数
                        'visit_number' => 0,//访问次数
                        'create_time' => time()
                    ];
                    Db::table('dynamic_user_record')->insert($dynamic_user_record_datas);
                }
            }
        }
        //将生成的动态id保存到dynamic_group，如果存在则不用添加
        asort($dynamic_ids);
        $dynamic_ids = implode(",",$dynamic_ids);
        $group_data = Db::name('dynamic_group')->where('dynamic_ids',$dynamic_ids)->find();
        if($group_data){
            $data['group_data'] =$group_data;
            Db::name('dynamic_group')->where('id',$group_data['id'])->setInc('visit_num',1);
        }else{
            $dynamic_group_data = [
                'dynamic_ids' => $dynamic_ids,
                'collect_num' => 0,
                'praise_num' => 0,
                'share_num' => 0,
                'create_time' => time(),
                'visit_num' => 1,//总浏览数
            ];
            $groupId = Db::table('dynamic_group')->insertGetId($dynamic_group_data);
            $dynamic_group_data['id'] = $groupId;
            $data['group_data'] =$dynamic_group_data;
        }

        //去掉重复的商圈
        $business_circle_ids = array_unique($business_circle_ids);
        //获取商圈
        $data['dynamic_circle'] = Db::name('business_circle')
            ->alias('bc')
            ->join('business_circle_img bci','bci.business_circle_id = bc.id','LEFT')
            ->where(['bc.id'=>['in',$business_circle_ids],'bc.status'=>1])
            ->field(['bc.id as id','circle_name','address','img_url','visit_number'])
            ->select();


        return $data;
    }

}