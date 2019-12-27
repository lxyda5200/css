<?php
/**
 * Created by PhpStorm.
 * User: win 10
 * Date: 2018/8/2
 * Time: 20:17
 */

namespace app\user_v5\controller;

use app\common\controller\Base;
use app\user_v5\common\Logic;
use \app\user_v5\model\Goods as GoodsModel;
use app\user_v5\model\GoodsComment;
use app\user_v5\validate\HouseEntrust;
use think\Db;
use think\response\Json;
class Goods extends Base
{

    /**
     * 商品分类
     */
    public function getGoodsClass(){
        $data = Db::name('goods_class')->field('id,class_name')->where('is_show',1)->order(['paixu'=>'asc','id'=>'asc'])->select();
        return json(self::callback(1,'',$data));
    }


    /**
     * 商品列表
     */
    public function goodsList(){
        try{
            $class_id = input('?post.class_id') ? intval(input('class_id')) : 0 ;
            $paixu = input('?post.paixu') ? intval(input('paixu')) : 0 ;   //排序 0默认排序 1销量排序 2价格排序
            $order = input('?post.order') ? intval(input('order')) : 0 ;  //规则 1升序 2倒序
            $page = input('?post.page') ? intval(input('page')) : 1 ;
            $size = input('?post.size') ? intval(input('size')) : 10 ;

            if ($class_id){
                $where['class_id'] = ['eq',$class_id];
            }

            switch ($paixu){
                case 1:
                    $field = 'sales';
                    break;
                case 2:
                    $field = 'price';
                    break;
                default:
                    $field = 'create_time';
                    break;
            }

            $order = $order==1 ? 'asc' : 'desc';

            $order = [$field=>$order];
            #dump($order);

            $where['status'] = ['eq',1];
            $where['is_delete'] = ['eq',0];

            $count = GoodsModel::where($where)->count();

            $list = GoodsModel::getGoodsList($page,$size,$where,$order);

            $data['max_page'] = ceil($count/$size);
            $data['total'] = $count;
            $data['list'] = $list;

            return json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 商品详情
     */
    public function goodsDetail(){
        try{
            $goods_id = input('?post.goods_id') ? intval(input('goods_id')) : 0 ;

            if (!$goods_id){
                return json(self::callback(0,'参数错误'),400);
            }

            $goodsInfo = GoodsModel::getGoodsDetail($goods_id);

            if (!$goodsInfo){
                throw new \Exception('商品不存在');
            }

            $total_comment = GoodsComment::where('goods_id',$goods_id)->count();
            $avg_score = GoodsComment::where('goods_id',$goods_id)->avg('score');

            $data = $goodsInfo->toArray();
            $data['avg_score'] = round($avg_score,1);
            $data['total_comment'] = $total_comment;
            $data['mobile'] = "15881050771";

            return json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 商品评论列表
     */
    public function goodsCommentList(){
        try{

            $goods_id = input('?post.goods_id') ? intval(input('goods_id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            if (!$goods_id){
                return json(self::callback(0,'参数错误'),400);
            }

            $goods = GoodsModel::get($goods_id);
            if (!$goods){
                return json(self::callback(0,'商品不存在'));
            }

            $where['goods_id'] = ['eq',$goods_id];

            $total = GoodsComment::where($where)->count();

            $list = GoodsComment::getCommentList($where,$page,$size,['create_time'=>'desc']);

            foreach ($list as $k=>$v){
                $total_score += $v->score;

                ##获取评论图片
                $list[$k]['imgs'] = Logic::getProCommentImages($v['id']);
            }


            $data['max_page'] = ceil($total/$size);
            $data['total'] = $total;
            $data['avg_score'] = round($total_score/$total,1);
            $data['list'] = $list;

            return json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

}