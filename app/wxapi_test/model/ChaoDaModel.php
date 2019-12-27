<?php


namespace app\wxapi_test\model;


use think\Model;
use think\db\Query;
use think\Validate;
use think\Db;

class ChaoDaModel extends Model
{
    protected $pk = 'id';

    protected $table = 'chaoda';

    /**
     *  获取动态详情数据
     * @param $id  动态ID
     * @param $user_id   用户ID
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getDetailsById($id, $user_id){

        // 查询动态详情数据(关联查询)
        $info = self::where(['c.id' => $id])
            -> alias('c')
            -> join('user u', 'c.fb_user_id = u.user_id and u.user_status != -1','left')
            -> join('topic t', 'c.topic_id = t.id', 'left')
            -> join('store s', 'c.store_id = s.id and s.store_status = 1', 'left')
            -> join('store rs', 'c.recommend_store_id = rs.id and rs.store_status = 1', 'left')
            -> join('store_follow sf', 'sf.store_id = s.id and sf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            -> join('store_follow uf', 'uf.fb_user_id = c.fb_user_id and uf.user_id = '. $user_id, 'left')  // 查看用户是否关注店铺
            -> join('chaoda_dianzan d', 'd.chaoda_id = c.id and d.user_id = '.$user_id, 'left')  //  关联点赞表
            -> join('chaoda_collection sc', 'sc.chaoda_id = c.id and sc.user_id = '.$user_id, 'left')  //  关联点赞表
            -> field([
                'c.is_group','c.product_ids','c.store_id as chaoda_store_id','c.share_number','c.collect_number','c.comment_number','c.dianzan_number','c.fb_user_id','c.title','c.id as chaoda_id','c.latitude','c.longitude','c.is_group','c.cover as chaoda_cover','c.topic_id', 'c.tag_ids', 'c.name', 'c.fb_user_id', 'c.address', 'c.create_time', 'c.description', 'c.store_id', 'c.cover_thumb', 'c.id', 'c.comment_number as total_comment_number','c.type',
                'u.nickname', 'u.avatar','c.recommend_store_id','u.user_status',
                't.title as topic_title',
                's.id as store_id','s.store_name', 's.cover','s.lng','s.lat','s.address as store_address',
                'rs.store_name as recommend_store_name', 'rs.cover as recommend_cover','rs.lng as recommend_lng','rs.lat as recommend_lat','rs.address as recommend_address',
                'IF(d.create_time > 0, 1, 0)  is_dianzan',
                'IF(sf.create_time > 0 ,1 ,0) is_follow_store',
                'IF(uf.create_time > 0 ,1 ,0) is_follow_user',
                'IF(sc.create_time > 0 ,1 ,0) is_collect',
            ])
            -> with(['imgurl'=>function(Query $query){
                return $query->where('can_use',1);
            }])
//            -> with(['comments' => function (Query $query) use ($id) {
//                return $query -> where(['pid' => 0, 'chaoda_id' => $id]) ->with(['reply' => function(Query $query){
//                    return $query -> order('id asc'); // 统计回复评论总数
//                }]) -> limit(4) -> order('id desc');
//            },
//                'imgurl'])
            -> find();

        //判断是否是多图
        if($info['type']=='' || $info['type']=='image'){
            $num= Db::name('chaoda_img')->where('chaoda_id',$info['id'])->where('type','image')->count();
            $num2= Db::name('chaoda_img')->where('chaoda_id',$info['id'])->where('type','video')->count();
            if($num2<=0){
                if($num>=2){$info['type'] ='images';}elseif ($num<=1){
                    $info['type'] ='image';
                }
            }else{
                $info['type'] ='video';
            }
        }

        //  计算主评论的回复评论数
//        foreach ($info['comments'] as $ke => $va){
//            $info['comments'][$ke]['totalReply'] = count($va['reply']);
//        }
        // 处理标签数据 以便查询
        $tag = explode(',', $info['tag_ids']);
        if ($tag != ''){
            foreach ($tag as $k => $v){
                $va = substr($v, 1, strlen($v)-2);
                $tag[$k] = $va;
            }
            $where = ['id' => ['in', $tag], 'status' => 1];
        }else{
            $where = ['status' => 1];
        }
        $label = TagModel::where($where) -> field(['id', 'title']) -> select();
        $info['tag_list'] = $label;
        // 主评论总数
        $comment_number = CommentModel::where(['chaoda_id' => $id, 'pid' => 0]) -> count();
        $total_group_buy_price=0;//计算总团购价格
        $total_price=0;//计算总划线价格
        if ($info['chaoda_store_id'] > 0){
            //潮搭商品tag信息
            $product_info = Db::view('chaoda_tag')
                ->view('product','freight,is_buy,status','product.id = chaoda_tag.product_id','left')
                ->where('chaoda_id',$id)
                ->where('product.status',1)
                ->select();

            foreach ($product_info as $k2=>$v2){
                $product = Db::name('product_specs')->field('id,cover,product_specs,product_name,price,group_buy_price,huaxian_price,stock,share_img,platform_price')->where('product_id',$v2['product_id'])->find();
                $product_info[$k2]['specs_id'] = $product['id'];
                $product_info[$k2]['is_buy'] = $v2['is_buy'];
                $product_info[$k2]['cover'] = $product['cover'];
                $product_info[$k2]['product_specs'] = $product['product_specs'];
                $product_info[$k2]['product_name'] = $product['product_name'];
                $product_info[$k2]['price'] = $info['is_group']==1?$v2['price']:$product['price'];
                $product_info[$k2]['group_buy_price'] = $product['group_buy_price'];
                $product_info[$k2]['stock'] = $product['stock'];
                $product_info[$k2]['huaxian_price'] = $product['price'];
                $product_info[$k2]['share_img'] = $product['share_img'];
                $product_info[$k2]['platform_price'] = $product['platform_price'];
                if($info['is_group']==1){
                    $total_group_buy_price+=$v2['price'];
                }else{
                    $total_group_buy_price+=$product['price'];
                }
                // $total_group_buy_price+=$v2['price'];//计算总团购价格
                $total_price+=$product['price'];//计算总划线价格
            }

        }elseif($info['fb_user_id'] > 0){
            if($info['product_ids']){
                $productIdsArray = explode(',',$info['product_ids']);
                $tempIds = [];
                // TODO 判断product_ids数据是否为'[]'  是表示为空则不处理
                if ($info['product_ids'] != "[]" || $info['product_ids'] != '[]' ){
                    foreach ($productIdsArray as $k=>$v1){
                        $tempIds[$k] = substr($v1, 1, strlen($v1)-2);
                    }
                    foreach ($tempIds as $k => $v){
                        $product_info[$k] = Db::table('product_specs')
                            ->field([
                                'id as specs_id,cover,product_specs,product_name,price,group_buy_price,huaxian_price,stock,share_img,platform_price',
                            ])
                            -> where(['product_id' => $v]) -> find();
                        $product_info[$k]['product_id'] = $v;
                    }
                    foreach ($product_info as $k2=>$v2){
                        $total_group_buy_price+=$v2['price'];
                    }
                }else{
                    $product_info=[];
                }

            }else{
                $product_info=[];
            }

        }
        // 获取推荐数据
        $recommendData = self::ChaoDaDetailsRecommendDataPage($info['tag_ids'], $info['topic_id']);
        if($recommendData['data']){
            foreach ($recommendData['data'] as $k6=>&$v6){
                if(isset($user_id) && $user_id>0){
                    //判断是否点赞
                    $v6['is_dianzan'] = Db::name('chaoda_dianzan')->where('user_id',$user_id)->where('chaoda_id',$v6['id'])->count();
                }else{
                    $v6['is_dianzan'] = 0;
                }
            }
        }
        $info['total_huaxian_price']=$total_price;//总划线金额
        $info['total_money']=$total_group_buy_price;//总团购金额
        $info['comment_number'] = $comment_number;
        $info['totalRecommend'] = $recommendData['total'];
        $info['recommend'] = $recommendData['data'];
        $info['product_info'] = $product_info;
        return $info;
    }


    /**
     *  查询推荐数据 带分页
     * @param $tag_ids 标签
     * @param $topic 话题
     * @param int $page 页数
     * @param int $size  每页条数
     * @return array  返回查询数据总计  及分页数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function ChaoDaDetailsRecommendDataPage($tag_ids, $topic, $page = 1, $size = 8){
        // 处理标签数据 以便查询
        $tag = [];
        $where = ['c.is_delete' => ['=',0],'c.status' => ['=',2]];
        if($tag_ids != "[]" && $tag_ids != ""){
            $tag = explode(',', $tag_ids);
            $temp = ''; // 组装正则查询条件
            foreach ($tag as $k => $v){
                $va = substr($v, 1, strlen($v)-2);
                $tag[$k] = $va;
                $temp .= "[{$va}]*,";
            }
            $exp = 'REGEXP \''."(".rtrim($temp, ",").")+".'\'';
//            if (!empty($tag)) $where = ['c.tag_ids' => ['exp', $exp],'c.is_delete' => ['=',0],'c.status' => ['=',2]];
//            if (!empty($tag)) $where = "(c.tag_ids {$exp} and c.is_delete = 0 and c.status = 0) and (u.user_status != -1 or  s.store_status = 1)";
            if (!empty($tag)) $where = "(c.tag_ids {$exp} and c.is_delete = 0 and c.status = 0)";
        }
//        $where1 = "(c.topic_id = {$topic} and c.tag_ids != '' and c.is_delete = 0 and c.status = 2) and (u.user_status != -1 or  s.store_status = 1)";
        $where1 = "(c.topic_id = {$topic} and c.tag_ids != '' and c.is_delete = 0 and c.status = 2)";
        // 查询推荐数据
//        $recommend = ChaoDaModel::where(['c.topic_id' => $topic, 'c.tag_ids' => ['<>', ""],'c.is_delete' => ['=',0],'c.status' => ['=',2]]) ->whereOr($where)
//        $recommend = ChaoDaModel::where($where1) ->whereOr($where)
//
//            -> alias('c')
//            -> join('topic t', 'c.topic_id = t.id', 'left')
//            -> join('user u', 'c.fb_user_id = u.user_id', 'left')
//            -> join('store s', 'c.store_id = s.id', 'left')
//            -> field([
//                'c.id','c.cover_thumb','c.store_id','c.fb_user_id','s.store_name','s.cover', 'c.topic_id','c.description','c.title','c.dianzan_number','c.comment_number','c.collect_number','c.create_time','c.tag_ids','c.cover','c.type','c.status',
//                'u.nickname', 'u.avatar',
//                't.title as topic_title'
//            ])
//            -> select();
        $recommend = ChaoDaModel::where($where1) ->whereOr($where)

            -> alias('c')
            -> join('topic t', 'c.topic_id = t.id', 'left')
            -> field([
                'c.id','c.cover_thumb','c.store_id','c.fb_user_id', 'c.topic_id','c.description','c.title','c.dianzan_number','c.comment_number','c.collect_number','c.create_time','c.tag_ids','c.cover','c.type',
                't.title as topic_title'
            ])
            -> select();

//        $thumb_conf = config('config_common.compress_config');
//        $thumb_mark = "_{$thumb_conf['chaoda'][0]}X{$thumb_conf['chaoda'][1]}";
        foreach ($recommend as $k => $v){
            if ($v['store_id']){
                $store_info = Db::table('store')->where('id',$v['store_id'])->field('store_name,cover,store_status')->find();
                if ($store_info && $store_info['store_status'] == 1){
                    $recommend[$k]['store_logo'] = $store_info['cover'];
                    $recommend[$k]['store_name'] = $store_info['store_name'];
                    $recommend[$k]['store_status'] = $store_info['store_status'];
                }else{
                    unset($recommend[$k]);
                }
            }
            if ($v['fb_user_id']){
                $user_info = Db::table('user')->where('user_id', $v['fb_user_id'])->field('nickname,avatar,user_status')->find();
                if ($user_info && $user_info['user_status'] != -1){
                    $recommend[$k]['nickname'] = $user_info['nickname'];
                    $recommend[$k]['avatar'] = $user_info['avatar'];
                    $recommend[$k]['user_status'] = $user_info['user_status'];
                }else{
                    unset($recommend[$k]);
                }
            }

//            if(!$v['cover_thumb'] || !strstr($v['cover_thumb'], $thumb_mark)){  //不是视频封面且没有生成缩略图
//                ##生成缩略图
//                $path = createThumb($v['cover'],'uploads/product/thumb/', 'chaoda');
//                if(file_exists(trim($path,'/'))){ //修改cover_thumb字段
//                    Db::name('chaoda')->where(['id'=>$v['id']])->setField('cover_thumb',$path);
//                    $list[$k]['cover'] = $path;
//                }
//            }else{
//                $list[$k]['cover'] = $v['cover_thumb'];
//            }

        }
        // 获取数组标签及话题两列数据并组装重复值形成新数组
        $topic_tmp = array_column($recommend, 'topic_id');
        $taget_tmp = array_column($recommend, 'tag_ids');
        $topic = array_count_values($topic_tmp);
        $taget = array_count_values($taget_tmp);

        // 存放推荐排序得分数组
        $sort_result = [];
        foreach ($recommend as $ke => $vl){
            // 动态分数得分
            $hours = (time() - $vl['create_time'])/3600;
            $ts_num = $vl['dianzan_number']*0.5+$vl['comment_number']*2+(($vl['collect_number']*5)/sqrt($hours+2));
            // 推荐排序得分
            $recommend_score = $ts_num*$topic[$vl['topic_id']] + $ts_num*$taget[$vl['tag_ids']]*0.2;

            $sort_result[] = $recommend_score;
        }
        // 降值排序
        array_multisort($sort_result, SORT_DESC, $recommend);
        // 计算查询数据总数
        $totalRecommend = count($recommend);
        // 分页读取开始位置
        $start=($page-1)*$size;
        // 读取分页数据
        $recommend = array_slice($recommend,$start,$size);

        return ['total' => $totalRecommend, 'data' => $recommend];
    }

    /**
     *  用户动态点赞 取消点赞
     * @param $param
     * @return array|bool|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function userSupportPost($param){
        $rule = [
            'chaoda_id'  => 'require|number|gt:0',
            'user_id'    => 'require|number|gt:0',
            'kind'       => 'require|in:0,1',
            'token'      => 'require',
        ];

        $msg = [
            'chaoda_id.require' => '缺少必要参数',
            'token.require'     => '缺少必要参数',
            'chaoda_id.number'  => '参数格式不正确',
            'chaoda_id.gt'      => '参数范围错误',
            'user_id.require'   => '缺少必要参数',
            'user_id.number'    => '参数格式不正确',
            'user_id.gt'        => '参数范围错误',
            'kind.require'      => '缺少必要参数',
            'kind.in'           => '参数不在接收范围',
        ];

        $validate = new Validate($rule, $msg);
        if (!$validate->check($param)) {
            return $validate->getError();
        }

        $info = self::where(['c.id' => $param['chaoda_id']])
            -> alias('c')
            -> join('chaoda_dianzan d' ,'c.id = d.chaoda_id and d.user_id = '.$param['user_id'], 'left')
            -> field(['c.id', 'c.dianzan_number', 'IF(d.id>0,1,0) is_support', 'c.fb_user_id', 'c.store_id', 'd.id did']) -> find();
        if (!$info) return '数据检索失败';

        if ($param['kind'] == 1){
            if ($info['is_support'] == 1) return '已点赞';
            $insertData = [
                'chaoda_id'   => $param['chaoda_id'],
                'store_id'    => $info['store_id'],
                'user_id'     => $param['user_id'],
                'fb_user_id'  => $info['fb_user_id'],
                'create_time' => time(),
            ];
            $result = ChaoDaSupportModel::insertGetId($insertData);
            if ($result) self::where('id', $param['chaoda_id']) -> setInc('dianzan_number', 1);
        }else{
            if ($info['is_support'] == 0) return '无法取消';
            $result = ChaoDaSupportModel::destroy($info['did']);
            if ($result && $info['dianzan_number'] > 0) self::where('id', $param['chaoda_id']) -> setDec('dianzan_number', 1);
        }

        return $result ? true : false;
    }

    /**
     *  一对多关联评论
     *  关联用户表 查询评论用户昵称
     * @return \think\model\relation\HasMany
     */
    public function comments()
    {
        return $this->hasMany('CommentModel', 'chaoda_id', 'id')
            -> join('user', 'user.user_id = chaoda_comment.user_id')
            -> field('chaoda_comment.support,chaoda_comment.hate,chaoda_comment.create_time,chaoda_comment.user_id,chaoda_comment.content,chaoda_comment.chaoda_id,chaoda_comment.pid,chaoda_comment.id,user.nickname,user.avatar');
    }

    /**
     *  一对多关联图片 / 视频
     * @return \think\model\relation\HasMany
     */
    public function imgurl(){
        return $this -> hasMany('ImagesModel', 'chaoda_id', 'id') -> field('img_url,type,chaoda_id,cover');
    }
}