<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/4
 * Time: 10:37
 */

namespace app\admin\model;


use think\Exception;
use think\Model;

class Store extends Model
{

    protected $resultSetType = '\think\Collection';

    /**
     * 商家类型
     * @param $val
     * @return mixed
     */
    public function getIsBrandAttr($val) {
        $is_brand = [
            1 => '普通店铺',
            2 => '会员店铺'
        ];
        return $is_brand[$val];
    }

    /**
     * 商家类型
     * @param $val
     * @return mixed
     */
//    public function getTypeAttr($val) {
//        $type = [
//            1 => '普通店铺',
//            2 => '会员店铺'
//        ];
//        return $type[$val];
//    }


    /**
     * 审核状态
     * @param $val
     * @return mixed
     */
    public function getShStatusAttr($val) {
        $status = [
            0 => '待审核',
            1 => '通过',
            -1 => '拒绝',
            -2 => '删除'
        ];
        return $status[$val];
    }

    public static function getStoreListByKeywords($keywords){
        $list = (new self())->where(['store_name'=>['LIKE',"%{$keywords}%"],'store_type'=>0,'store_status'=>1])->field('id,store_name')->select();
        //addErrLog((new self())->getLastSql());
        return $list;
    }

//    public function products(){
//        return $this->hasMany('app\admin\model\Product','store_id')->field('id,product_name');
//    }

    /**
     * 设置人工干预得分
     * @param $id
     * @param $score_meddle
     * @return int
     */
    public static function setStoreScoreMeddle($id, $score_meddle){
        return (new self())->where(['id'=>$id])->setField('score_meddle',$score_meddle);
    }

    /**
     * 设置平台买单提成比
     * @param $id
     * @param $maidan_deduct
     * @return int
     */
    public static function setStoreMaidanDeduct($id, $maidan_deduct){
        return (new self())->where(['id'=>$id])->setField('maidan_deduct',$maidan_deduct);
    }

    /**
     * 获取室友推荐店铺列表
     * @return \think\Paginator
     */
    public function roommateStoreList(){
        $page = input('post.page',1,'intval');
        $store_ids = input('post.store_ids','','trimStr');
        $cate_id = input('post.cate_id',0,'intval');
        $style_id = input('post.style_id',0,'intval');
        $keywords = input('post.keywords','','trimStr');
        $store_ids = explode(',',trim($store_ids,','));
        $where = [
            's.store_status' => 1,
            's.sh_status' => 1,
            's.type' => 1,
            's.id' => ['NOT IN', $store_ids]
        ];
        if($cate_id)$where['scs.cate_store_id'] = $cate_id;
        if($style_id)$where['sss.style_store_id'] = $style_id;
        if($keywords)$where['s.store_name|s.mobile'] = ['LIKE',"%{$keywords}%"];

        $list = $this->alias('s')
            ->join('store_cate_store scs','scs.store_id = s.id','LEFT')
            ->join('store_style_store sss','sss.store_id = s.id','LEFT')
            ->group('s.id')
            ->where($where)
            ->field('
                s.id,s.cover,s.store_name,s.mobile,s.address,s.real_read_number as read_number
            ')
            ->with(['styles'])
            ->paginate(12,false,['page'=>$page])
            ->toArray();

        $list['max_page'] = ceil($list['total']/$list['per_page']);
        return $list;
    }

    /**
     * 店铺风格
     * @return \think\model\relation\HasMany
     */
    public function styles(){
        return $this->hasMany('StoreStyleStore','store_id','id')->alias('sss')
            ->join('style_store ss','sss.style_store_id = ss.id','LEFT')
            ->where(['ss.delete_time'=>null])
            ->field('
                ss.id as style_id,ss.title,store_style_store.id,store_style_store.store_id
            ');
    }

}