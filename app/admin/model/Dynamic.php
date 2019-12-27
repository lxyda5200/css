<?php


namespace app\admin\model;


use think\Db;
use think\Exception;
use think\Model;
use traits\model\SoftDelete;

class Dynamic extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $delete_time = 'delete_time';

    protected $dateFormat = false;

    use SoftDelete;

    /**
     * 获取推荐动态列表
     * @return array
     * @throws Exception
     * @throws \think\exception\DbException
     */
    public function getRecommendList(){
        $page = input('post.page',1,'intval');
        $dynamic_id = input('post.dynamic_id',0,'intval');
//        $is_recommend = input('post.is_recommend',-1,'intval');
        $status = input('post.status',0,'intval');
        $recom_handler = input('post.handler','','trimStr');
        $user_type = input('post.user_type',0,'intval');
        $type = input('post.type',0,'intval');

        $where = [];
        if($dynamic_id)$where['d.id'] = $dynamic_id;
        if($recom_handler){
            ##获取操作者id
            $recom_handler_id = Db::name('admin')->where(['nickname'=>['LIKE',"%{$recom_handler}%"]])->column('id');
            if(!$recom_handler_id)throw new Exception('没有相关操作人');
            $where['d.recom_handler_id'] = ['IN', $recom_handler_id];
        }
        if($status > 0){
            switch($status){
                case 1:   //生效中[开启并且在生效时间中]
                    $where['d.recom_start_time'] = ['ELT', time()];
                    $where['d.recom_end_time'] = ['GT', time()];
                    $where['d.is_recommend'] = 1;
                    break;
                case 2:   //已失效[开启并且已超过生效时间]
                    $where['d.recom_end_time'] = ['EGT', time()];
                    $where['d.is_recommend'] = 1;
                    break;
                case 3:  //开启
                    $where['d.is_recommend'] = 1;
                    break;
                case 4:
                    $where['d.is_recommend'] = 0;
                    break;
                case 5:  //未生效 [开启但是还未到生效时间]
                    $where['d.is_recommend'] = 1;
                    $where['d.recom_start_time'] = ['GT', time()];
                    break;
                default:
                    throw new Exception('状态值错误');
                    break;
            }
        }
        if($user_type > 0)$where['d.user_type'] = $user_type;
        if($type > 0)$where['d.type'] = $type;

        $data = $this->alias('d')
            ->join('store s','s.id = d.store_id','LEFT')
            ->join('user u','u.user_id = d.user_id','LEFT')
            ->join('admin a','a.id = d.recom_handler_id','LEFT')
            ->where($where)
            ->with(['storeBrand'])
            ->field([
                'd.id', "d.recom_sort", 'd.recom_start_time', 'd.recom_end_time', 'd.recom_update_time', 'd.is_recommend', 'd.store_id', 'd.recom_end_time as recom_end_time_int', 'd.recom_start_time as recom_start_time_int', 'd.recom_remark', 'd.type',
//                "IF(d.recom_sort > 0 , recom_sort, '未设置') recom_sort",
                's.store_name',
                'a.nickname as recom_handler',
                'u.nickname, u.nickname as authentication'
            ])
            ->order("is_recommend","desc")
            ->order("field(recom_sort,0) asc")
            ->order('recom_sort','asc')
            ->paginate(15,false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total']/$data['per_page']);
        return $data;
    }

    /**
     * 处理开始时间
     * @param $value
     * @return false|string
     */
    public function getRecomStartTimeAttr($value){
        return $value?date("Y-m-d H:i:s",$value):"无";
    }

    /**
     * 处理结束时间
     * @param $value
     * @return false|string
     */
    public function getRecomEndTimeAttr($value){
        return $value?date("Y-m-d H:i:s",$value):"无";
    }

    /**
     * 处理操作人
     * @param $value
     * @return string
     */
    public function getRecomHandlerAttr($value){
        return $value?:"无";
    }

    /**
     * 处理最新推荐更新时间
     * @param $value
     * @return false|string
     */
    public function getRecomUpdateTimeAttr($value){
        return $value?date("Y-m-d H:i:s",$value):"无";
    }

    /**
     * 处理认证标识
     * @return string
     */
    public function getAuthenticationAttr(){
        return '无';
    }

    /**
     * 一对一 获取店铺品牌信息
     * @return \think\model\relation\HasOne
     */
    public function storeBrand(){
        return $this->hasOne('BrandStore','store_id','store_id')->alias('bs')
            ->join('brand b','b.id = bs.brand_id','LEFT')
            ->where(['bs.is_selected'=>1])
            ->field('
                bs.store_id,b.brand_name
            ');
    }

    /**
     * 更新动态推荐状态
     * @throws Exception
     */
    public function editIsRecommend(){
        $dynamic_id = input('post.dynamic_id',0,'intval');
        $is_recommend = input('post.is_recommend',0,'intval');

        $res = $this->where(['id'=>$dynamic_id])->update(['is_recommend'=>$is_recommend]);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 修改动态推荐信息
     * @throws Exception
     */
    public function editRecommendInfo(){
        $dynamic_id = input('post.dynamic_id',0,'intval');
        $recom_start_time = input('post.recom_start_time','','trimStr,strtotime');
        $recom_end_time = input('post.recom_end_time','','trimStr,strtotime');
        $recom_sort = input('post.recom_sort',0,'intval');
        $is_recommend = input('post.is_recommend',0,'intval');
        $recom_remark = input('post.recom_remark','','trimStr');

        if(!$recom_start_time || !$recom_end_time){
            $recom_start_time = $recom_end_time = 0;
        }
        if($recom_start_time && ($recom_end_time <= $recom_start_time))throw new Exception('开始时间应小于结束时间');

        ##更新基础信息
        $data = compact('recom_start_time','recom_end_time','is_recommend','recom_remark');
        $res = $this->where(['id'=>$dynamic_id])->update($data);
        if($res === false)throw new Exception('操作失败');

        ##操作排序
        $prev_sort = $this->where(['id'=>$dynamic_id])->value('recom_sort');
        if($prev_sort == $recom_sort)return;

        ##更新
        if($recom_sort == 0){
            if($prev_sort > 0){
                $this->where(['recom_sort'=>['GT', $prev_sort]])->setDec('recom_sort',1);
            }
        }else{
            if($prev_sort > $recom_sort){
                $ids = $this->where(['recom_sort'=>['BETWEEN',[$recom_sort,$prev_sort]]])->column('id');
                foreach($ids as $v){$this->where(['id'=>$v])->setInc('recom_sort',1);}
            }else{
                if($prev_sort == 0){
                    $this->where(['recom_sort'=>['EGT', $recom_sort]])->setInc('recom_sort',1);
                }else{
                    $ids = $this->where(['recom_sort'=>['BETWEEN',[$prev_sort,$recom_sort]]])->column('id');
                    foreach($ids as $v){$this->where(['id'=>$v])->setDec('recom_sort',1);}
                }

            }
        }

        $res = $this->where(['id'=>$dynamic_id])->setField('recom_sort', $recom_sort);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 获取列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getList(){
        $page = input('post.page',1,'intval');
        $keywords = input('post.keywords','','trimStr');
        $dynamic_id = input('post.dynamic_id',0,'intval');
        $start_time = input('post.start_time','','trimStr,strtotime');
        $end_time = input('post.end_time','','trimStr,strtotime');
        $status = input('post.status',0,'intval');
        $user_type = input('post.user_type',0,'intval');
        $type = input('post.type',0,'intval');

        $where = [];
        if($keywords)$where['s.store_name|u.nickname'] = ['LIKE', "%{$keywords}%"];
        if($dynamic_id)$where['d.id'] = $dynamic_id;
        if($user_type > 0)$where['user_type'] = $user_type;
        if($type > 0)$where['type'] = $type;
        if($start_time || $end_time)$where['d.create_time'] = ['BETWEEN', [$start_time, $end_time]];
        switch($status){
            case 0:
                $where['d.status'] = ['IN', [1, -1]];
                break;
            case 1:
                $where['d.status'] = 1;
                break;
            case -1:
                $where['d.status'] = -1;
                break;
            default:
                break;
        }

        $data = $this->alias('d')
            ->join('store s','s.id = d.store_id','LEFT')
            ->join('topic t','t.id = d.topic_id','LEFT')
            ->join('user u','u.user_id = d.user_id','LEFT')
            ->where($where)
            ->with(['storeBrand'])
            ->field('
                d.id,d.cover,d.title,d.description,d.create_time,d.status,d.type,d.user_type,d.exam_time,d.exam_type,d.is_select,d.is_recommend,
                s.store_name,s.address,s.id as brand_name,
                t.title as topic,
                u.nickname, u.nickname as authentication
            ')
            ->paginate(15,false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total']/$data['per_page']);

        return $data;
    }

    /**
     * 优化主题数据
     * @param $value
     * @return string
     */
    public function getTopicAttr($value){
        return $value?:"无";
    }

    /**
     * 获取店铺品牌名
     * @param $value
     * @return string
     */
    public function getBrandNameAttr($value){
        return BrandStore::getStoreBrandName($value);
    }

    /**
     * 编辑状态
     * @throws Exception
     */
    public function editStatus(){
        $dynamic_id = input('post.dynamic_id',0,'intval');
        $status = input('post.status',-1,'intval');
        $res = $this->where(['id'=>$dynamic_id])->setField('status',$status);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 获取动态详情
     * @return array
     */
    public function getInfo(){
        $id = input('post.dynamic_id',0,'intval');
        $info = $this->alias('d')
            ->join('store s','s.id = d.store_id','LEFT')
            ->join('topic t','t.id = d.topic_id','LEFT')
            ->where(['d.id'=>$id])
            ->with(['dynamicImgs', 'dynamicVideos'])
            ->field('
                d.id,d.title,d.store_id,d.description,d.create_time,
                s.store_name,s.id as brand_name,
                t.title as topic_title
            ')
            ->find();
        if(!$info)throw new Exception('动态不存在或者已删除');

        $today_start_time = strtotime(date('Y-m-d') . ' 00:00:00');
        $today_end_time = $today_start_time + 24 * 60 * 60;

        ##今日曝光量
        $today_look = DynamicLookRecord::countByTime($id, $today_start_time, $today_end_time);
        ##总曝光量
        $total_look = DynamicLookRecord::countAll($id);
        ##今日浏览量
        $today_visit = DynamicVisitRecord::countByTime($id, $today_start_time, $today_end_time);
        ##总浏览量
        $total_visit = DynamicVisitRecord::countAll($id);
        ##今日转发量
        $today_share = DynamicShareRecord::countByTime($id, $today_start_time, $today_end_time);
        ##总转发量
        $total_share = DynamicShareRecord::countAll($id);
        ##今日点赞量
        $today_praise = DynamicDianzan::countByTime($id, $today_start_time, $today_end_time);
        ##总点赞量
        $total_praise = DynamicDianzan::countAll($id);
        ##今日导航探店量
        $today_navigation = DynamicNavigationRecord::countByTime($id, $today_start_time, $today_end_time);
        ##总导航探店量
        $total_navigation = DynamicNavigationRecord::countAll($id);
        ##今日评论数
        $today_comment = DynamicComment::countByTime($id, $today_start_time, $today_end_time);
        ##总评论数
        $total_comment = DynamicComment::countAll($id);

        $dynamic_info = $info->toArray();

        $today_data = compact('today_look','today_navigation','today_praise','today_share','today_visit','today_comment');

        $total_data = compact('total_look','total_navigation','total_praise','total_share','total_visit','total_comment');

        return compact('dynamic_info','today_data','total_data');
    }

    /**
     * 一对多 动态图片
     * @return \think\model\relation\HasMany
     */
    public function dynamicImgs(){
        return $this->hasMany('DynamicImg','dynamic_id','id')->field('img_url,dynamic_id')->where(['type'=>1]);
    }

    /**
     * 一对多 动态视频
     * @return \think\model\relation\HasMany
     */
    public function dynamicVideos(){
        return $this->hasMany('DynamicImg','dynamic_id','id')->field('img_url,cover,dynamic_id')->where(['type'=>2]);
    }

    /**
     * 获取动态时间段内的浏览量、导航探店量、转发量
     * @return array
     * @throws Exception
     */
    public function getDynamicData(){
        $dynamic_id = input('post.dynamic_id',0,'intval');
        $type = input('post.type',1,'intval');  //类型：1.天数；2.时间区间
        $days = input('post.days',1,'intval');
        $start_time = input('post.start_time','','trimStr');
        $end_time = input('post.end_time','','trimStr');
        if($type == 2 && (!$start_time || !$end_time))throw new Exception('时间格式错误');
        if($type == 1 && $days > 30)throw new Exception('最多查询最近30天的数据');

        ##生成开始结束时间
        if($type == 1){
            ##结束时间为昨天的23:59:59
            $end_time = strtotime(date('Y-m-d') . " 23:59:59") - 24 * 60 * 60;
            $start_time = $end_time - $days * 24 * 60 * 60 + 1;
            $start_time = date('Y-m-d',$start_time);
            $end_time = date('Y-m-d',$end_time);
        }
        $limit_time = $this->createTimeLimit($start_time, $end_time);
        ##浏览量
        $total_visit = $type==2?(DynamicVisitRecord::countByDays($dynamic_id, $days)):(DynamicVisitRecord::countByTime($dynamic_id, $start_time, $end_time));
        $visit_list = DynamicVisitRecord::countByTimeList($dynamic_id, $limit_time);
        ##导航探店量
        $total_navigation = $type==2?(DynamicNavigationRecord::countByDays($dynamic_id, $days)):(DynamicNavigationRecord::countByTime($dynamic_id, $start_time, $end_time));
        $navigation_list = DynamicNavigationRecord::countByTimeList($dynamic_id, $limit_time);
        ##转发量
        $total_share = $type==2?(DynamicShareRecord::countByDays($dynamic_id, $days)):(DynamicShareRecord::countByTime($dynamic_id, $start_time, $end_time));
        $share_list = DynamicShareRecord::countByTimeList($dynamic_id, $limit_time);

        $data_list = [];
        foreach($visit_list as $k => $v){
            $data_list[$k]['visit_num'] = $v['num'];
            $data_list[$k]['navigation_num'] = $navigation_list[$k]['num'];
            $data_list[$k]['share_num'] = $share_list[$k]['num'];
        }

        return compact('total_visit','total_navigation','total_share','data_list');
    }

    /**
     * 生成时间列表
     * @param $start_time
     * @param $end_time
     * @return array
     */
    public function createTimeLimit($start_time, $end_time){
        $start_time = strtotime($start_time . " 00:00:00");
        $end_time = strtotime($end_time . " 23:59:59");
        $max = ceil(($end_time - $start_time) / (24 * 60 * 60));
        $arr = [];
        for($i=0;$i<$max;$i++){
            $per_start_time = $start_time + $i * 24 * 60 * 60;
            $per_end_time = $per_start_time + 23 * 60 * 60 + 59 * 60 + 59;
            $per_date = date('m-d',$per_start_time);
            $arr[$per_date]['start_time'] = $per_start_time;
            $arr[$per_date]['end_time'] = $per_end_time;
        }
        return $arr;
    }

    /**
     * 更新精选状态
     * @return bool|string
     */
    public static function editIsSelect(){
        try{
            $dynamic_id = input('post.dynamic_id',0,'intval');
            $is_select = input('post.is_select',0,'intval');
            if($is_select){
                ##检查动态状态
                if(!self::checkIsCanSelect($dynamic_id))throw new Exception('动态未审核通过');
            }
            Db::startTrans();
            ##修改精选状态
            $res = (new self())->where(['id'=>$dynamic_id])->setField('is_select', $is_select);
            if($res === false)throw new Exception('操作失败');
            ##增加操作记录
            $event = $is_select==1?3:4;
            $res = DynamicHandleLog::addLog($event,$dynamic_id);
            if(!is_bool($res))throw new Exception($res);

            Db::commit();
            return true;
        }catch(Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 检查是否已审核通过
     * @param $dynamic_id
     * @return bool
     */
    public static function checkIsCanSelect($dynamic_id){
        $status = (new self())->where(['id'=>$dynamic_id])->value('status');
        return ($status==1 || $status==-1)?true:false;
    }

}