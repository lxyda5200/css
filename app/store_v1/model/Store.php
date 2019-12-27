<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/7
 * Time: 14:56
 */

namespace app\store_v1\model;
use think\Db;
use think\Model;

class Store extends Model
{

    /**
     * 获取全部店铺ID
     * @param $id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_id($id){
        $ids =  Db::table('store')->where(['p_id'=>$id])->field('id')->select();
        if($ids){
            $info= array_column($ids,'id');
        }
        $info[] = $id;
        return $info;
    }


    /**
     * 店铺状态修改
     */
    public function edit_status($store_id,$status){
        $where['id'] = $store_id;
        $data = Db::table('store')->where($where)->find();
        if($data){
            if($data['store_status'] !=1){
                return false;
            }
            if($data['status'] === $status){
                return true;
            }
            $res = Db::table('store')->where($where)->update(['status'=>$status]);
            return $res;
        }else{
            return false;
        }
    }


    /**
     * 获取店铺列表
     */
    public function get_list($user,$status=false,$store_name=''){
        if($user['main_id'] !=$user['id']){
            $ids =  $user['store_pid'];
        }else{
            $ids =  (new \app\store_v1\model\Store())->get_id($user['main_id']);
        }
        $page = intval(input('page')) ? intval(input('page')) : 1 ;
        $size = intval(input('size')) ? intval(input('size')) : 10 ;
        $where['a.id'] = ['in',$ids];
        if(!empty($store_name)){
            $where['a.store_name'] = ['like','%'.$store_name.'%'];
        }
        if($status ==1){
            $where['a.store_status'] = 1;
            $where['a.sh_status'] =1;
            $where['a.status'] =1;
        }
        $total = Db::table('store')
            ->alias('a')
            ->join('business_circle_store b','b.store_id=a.id','left')
            ->join('business_circle c','c.id=b.business_circle_id and c.status=1','left')
            ->where($where)->count();
        $data['list'] = Db::table('store')
            ->alias('a')
            ->join('business_circle_store b','b.store_id=a.id','left')
            ->join('business_circle c','c.id=b.business_circle_id and c.status=1','left')
            ->where($where)
            ->page($page,$size)
            ->field('a.id,a.store_name,a.cover,a.mobile,a.money,a.type,a.see_type,a.sh_status,a.sh_type,a.store_status,a.lng,a.lat,a.province,a.city,a.area,a.address,a.create_time,a.store_type,a.status,c.circle_name')
            ->order('store_type desc')
            ->select();
        $data['total'] =$total;
        $data['max_page'] = ceil($total/$size);
        return $data;
    }

    /**
     * 店铺详情
     */
    public function get_details($store_id){

        $where['id']= $store_id;
        $data = Db::table('store')->where($where)
            ->field('id,p_id,store_name,nickname,cover,mobile,money,telephone,business_img,description,is_ziqu,province,city,area,address,platform_ticheng_old,type,see_type,sh_status,sh_type,buy_type,store_status,lng,lat,create_time,store_type,opening_type')
            ->find();
        if($data){
            $data['province'] = NewArea::get_name(intval($data['province']));
            $data['city'] = NewArea::get_name(intval($data['city']));
            $data['area'] = NewArea::get_name(intval($data['area']));
            //店铺主图
            $data['store_img'] = Store::get_img($store_id);
            //店铺线下店铺照片
            $data['store_imgs'] = Store::get_imgs($store_id);
            //店铺品牌
            $data['brand'] = Store::get_brands($store_id);
            //店铺风格
            $data['stylestore'] = Store::get_StyleStore($store_id);
            //店铺分类
            $data['storecatestore'] = Store::get_StoreCateStore($store_id);
            //店铺开通记录
            $data['storelog'] = Store::get_Storelog($store_id);
             //店铺商圈
            $data['circle_name'] = null;
            $circle_name = Db::table('business_circle_store')
                ->alias('a')
                ->join('business_circle b','b.id=a.business_circle_id')
                ->where(['a.store_id'=>$store_id])->field('b.id,b.circle_name')->find();
            if(!empty($circle_name['circle_name'])){
                $data['circle_name'] = $circle_name['circle_name'];
                $data['circle_name_id'] = $circle_name['id'];
            }
        }
        return $data;
    }

    /**
     * 店铺主图
     */
    protected function get_img($store_id){
        return Db::name('store_img')->field('id,img_url')->where('store_id',$store_id)->order('paixu','asc')->select();
    }

    /**
     * 店铺线下图片图
     */
    protected function get_imgs($store_id){
        return Db::name('store_imgs')->field('id,img_url')->where('store_id',$store_id)->order('paixu','asc')->select();
    }

    /**
     * 获取店铺开通记录
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_Storelog($store_id){
        return Db::name('store_status_log')->field('id,status,create_time')->where('store_id',$store_id)->order('create_time','asc')->select();
    }

    /**
     * 店铺品牌列表
     */
    public function get_brand($main_id){
        return Db::table('brand_company')
            ->alias('a')
            ->join('brand b','b.id=a.brand_id and b.status=1')
            ->where(['a.company_id'=>$main_id])
            ->field('b.id,b.brand_name,b.logo')
            ->order('sort asc')
            ->select();
    }

    /**
     * 店铺本店关联品牌列表
     */
    public function get_brands($store_id){
        return Db::table('brand_store')
            ->alias('a')
            ->join('brand b','b.id=a.brand_id and b.status=1')
            ->where(['a.store_id'=>$store_id])
            ->field('b.id,b.brand_name,b.logo')
            ->order('sort asc')
            ->select();
    }



    /**
     * 获取店铺分类列表
     */
    public function get_StoreCateStore($store_id){
        return Db::table('store_cate_store')
            ->alias('a')
            ->join('cate_store b','b.id=a.cate_store_id')
            ->where(['a.store_id'=>$store_id])->field('b.id,b.title')
            ->select();
    }

    /**
     * 获取店铺分类列表
     */
    public function get_StyleStore($store_id){
        return Db::table('store_style_store')
            ->alias('a')
            ->join('style_store b','b.id=a.style_store_id')
            ->where(['a.store_id'=>$store_id])->field('b.id,b.title')
            ->select();
    }


    /**
     * 添加店铺实景图片
     */
    public function add_Store_imgs($data){
        return Db::table('store_imgs')->insertAll($data);
    }

    /**
     * 添加店铺主图
     */
    public function add_Store_img($data){
        return Db::table('store_img')->insertAll($data);
    }

    /**
     * 关联商圈
     */
    public function Store_circle($data){
        return Db::table('business_circle_store')->insertGetId([
            'store_id'=>$data['store_id'],
            'business_circle_id'=>$data['circle_id'],
            'create_time'=>time(),
        ]);
    }

    /**
     * 关联品牌
     * @return int|string
     */
    public function addBrandStoreRelation($data){
        $info_data = [];
        if(empty($data['brand_id'])){
            return false;
        }
        $store_array = explode(',',$data['brand_id']);
        if(empty($store_array)){
            return false;
        }
        foreach ($store_array as $k=>$v){
            $info_data[$k]['store_id'] = intval($data['store_id']);
            $info_data[$k]['brand_id'] = $v;
            $info_data[$k]['company_id'] =intval($data['main_id']);
        }
        return Db::table('brand_store')->insertAll($info_data);
    }

    /**
     * 获取品牌信息
     * @param $data
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getBrand($data){
        if(empty($data['brand_id'])){
            return false;
        }
        $store_array = explode(',',$data['brand_id']);
        if(empty($store_array)){
            return false;
        }
        //获取品牌信息
        return Db::table('brand')->where(['id'=>intval($store_array['0']),'status'=>1])->find();
    }

    /**
     * 关联店铺分类
     */
    public function addStoreCateRelation($data){
        $info_data = [];
        if(empty($data['cate_id'])){
            return false;
        }
        $store_array = explode(',',$data['cate_id']);
        $time = time();
        foreach ($store_array as $k=>$v){
            $info_data[$k]['store_id'] = intval($data['store_id']);
            $info_data[$k]['cate_store_id'] = $v;
            $info_data[$k]['create_time'] = $time;
        }
        return Db::table('store_cate_store')->insertAll($info_data);
    }

    /**
     * 关联店铺主营风格
     */
    public function addStoreStyle($data){
        $info_data = [];
        if(empty($data['style_id'])){
            return false;
        }
        $store_array = explode(',',$data['style_id']);
        $time = time();
        foreach ($store_array as $k=>$v){
            $info_data[$k]['store_id'] = intval($data['store_id']);
            $info_data[$k]['style_store_id'] = $v;
            $info_data[$k]['create_time'] = $time;
        }
        return Db::table('store_style_store')->insertAll($info_data);
    }


    /**
     * 写入店铺状态记录
     * @param $store_id
     * @param $status
     * @return StoreStatusLog
     */
    public function writeStoreStatusLog($store_id, $status) {
        return StoreStatusLog::create([
            'store_id' => $store_id,
            'status' => $status,
            'create_time'=>time()
        ]);
    }


    /**
     * 获取一个店铺的坐标(经纬度)
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getStorePosition($id){
        return (new self())->where(compact('id'))->field('lng,lat')->find();
    }


//    public function bussinessReward() {
//        return $this->hasMany(BussinessReward::class, 'store_id', 'id');
//    }


    /**
     * 修改到店买单员工提成比例
     * @param $store_id
     * @param $deduct
     * @return Store
     */
    public function changeDeduct($store_id, $deduct) {
        return $this->where(['id' => $store_id])->update(['bussiness_deduct' => $deduct]);
    }


    /**
     * 获取到店买单员工提成比例
     * @param $store_id
     * @return mixed
     */
    public function getDeduct($store_id) {
        return $this->where(['id' => $store_id])->value('bussiness_deduct');
    }


//    public function trademarkCert() {
//        return $this->hasMany(TrademarkCert::class, 'store_id', 'id');
//    }
//
//
//    public function brandLink() {
//        return $this->hasMany(BrandLink::class, 'store_id', 'id');
//    }
}