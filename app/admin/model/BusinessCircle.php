<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use think\model\relation\HasMany;
use traits\model\SoftDelete;

class BusinessCircle extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected  $dateFormat = false;

    protected $insert = ['create_time'];

    use SoftDelete;

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取省市区列表
     * @return array
     * @throws Exception
     */
    public function getRegion(){
        $id = input('post.id',0,'intval');
        $level = input('post.level',0,'intval');
        if(!$level>1 && !$id)throw new Exception('参数错误');

        $province_list = $city_list = $county_list = [];

        switch($level){
            case 1:  //省市县
                $province_list = NewArea::getProvinceList();
                $city_list = NewArea::getCityList($province_list[0]['id']);
                $county_list = NewArea::getCountyList($city_list[0]['id']);
                break;
            case 2:  //市县
                $city_list = NewArea::getCityList($id);
                $county_list = NewArea::getCountyList($city_list[0]['id']);
                break;
            case 3:  //县
                $county_list = NewArea::getCountyList($id);
                break;
            default:
                break;
        }
        return compact('province_list','city_list','county_list');
    }

    /**
     * 添加商圈主信息
     * @param $post
     * @return string
     * @throws Exception
     */
    public function add($post){
        $circle_name = trimStr($post['circle_name']);
        $address = trimStr($post['address']);
        $description = trimStr($post['description']);
        $province = intval($post['province']);
        $city = intval($post['city']);
        $area = intval($post['area']);
        $status = intval($post['status']);
        $lng = floatval($post['lng']);
        $lat = floatval($post['lat']);
        $province_name = NewArea::getRegionName($province);
        $city_name = NewArea::getRegionName($city);
        $area_name = NewArea::getRegionName($area);

        $res = $this->isUpdate(false)->save(compact('circle_name','address','description','province','city','area','status','lng','lat','province_name','city_name','area_name'));
        if($res === false)throw new Exception('添加失败');
        ##返回商圈id
        return $this->getLastInsID();
    }

    /**
     * 获取商圈列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getList(){
        $keywords = input('post.keywords','','trimStr');
        $circle_id = input('post.circle_id',0,'intval');
        $province = input('post.province',0,'intval');
        $city = input('post.city',0,'intval');
        $area = input('post.area',0,'intval');
        $page = input('post.page',1,'intval');

        $where = [];
        if($keywords)$where['circle_name'] = ['LIKE', "%{$keywords}%"];
        if($circle_id)$where['id'] = $circle_id;
        if($province)$where['province'] = $province;
        if($city)$where['city'] = $city;
        if($area)$where['area'] = $area;

        $data = $this
            ->where($where)
            ->field('id,circle_name,city_name,area_name,address,visit_number,create_time,status')
            ->with(['cover'=>function(HasMany $hasMany){
                $hasMany->field('id,business_circle_id,img_url');
            }])
            ->paginate(15,false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total'] / $data['per_page']);

        return $data;
    }

    /**
     * 获取商圈详情
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo(){
        $id = input('post.id',0,'intval');
        $data = $this
            ->where(['id'=>$id])
            ->field('id,circle_name,province_name,city_name,area_name,address,visit_number,create_time,status,description')
            ->with(['cover' => function(HasMany $hasMany){
                $hasMany->field('img_url, business_circle_id');
            }])
            ->find();
        if(!$data)throw new Exception('商圈已删除或不存在');
        $data = $data->toArray();

        return $data;
    }

    public function getCreateTimeAttr($value){
        return date('Y-m-d H:i:s', $value);
    }

    /**
     * 一对多 商圈封面
     * @return HasMany
     */
    public function cover(){
        return $this->hasMany('BusinessCircleImg','business_circle_id','id');
    }

    /**
     * 编辑商圈状态
     * @throws Exception
     */
    public function editStatus(){
        $id = input('post.id',0,'intval');
        $status = input('post.status',1,'intval');

        $res = $this->where(['id'=>$id])->setField('status',$status);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 删除商圈以及商圈下的店铺
     * @throws Exception
     */
    public function del(){
        $id = input('post.id',0,'intval');
        ##删除商圈
        $res = BusinessCircle::destroy($id);
        if($res === false)throw new Exception('删除失败');
        ##删除商圈下的店铺
        BusinessCircleStore::delByCircleId($id);
    }

    /**
     * 获取商圈编辑页信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getEditInfo(){
        $id = input('post.id',0,'intval');
        $data = $this
            ->where(['id'=>$id])
            ->with([
                'cover' => function(HasMany $hasMany){
                    $hasMany->field('img_url, business_circle_id');
                },
                'stores' => function(HasMany $hasMany){
                    $hasMany->field('store_id, business_circle_id');
                }
            ])
            ->field('id,circle_name,province,city,area,address,status,description,lng,lat,province as province_list,province as city_list,city as area_list')
            ->find();
        if(!$data)throw new Exception('商圈不存在或已删除');
        $data = $data->toArray();
        return $data;
    }

    /**
     * 获取省列表
     * @return array
     */
    public function getProvinceListAttr(){
        return NewArea::getProvinceList();
    }

    /**
     * 获取市列表
     * @param $pid
     * @return array
     */
    public function getCityListAttr($pid){
        return NewArea::getCityList($pid);
    }

    /**
     * 获取区县列表
     * @param $pid
     * @return array
     */
    public function getAreaListAttr($pid){
        return NewArea::getCountyList($pid);
    }

    /**
     * 一对多 商圈店铺列表
     * @return HasMany
     */
    public function stores(){
        return $this->hasMany('BusinessCircleStore','business_circle_id','id');
    }

    /**
     * 编辑商圈信息
     * @param $post
     * @return mixed
     * @throws Exception
     */
    public function edit($post){
        $id = intval($post['id']);
        $circle_name = trimStr($post['circle_name']);
        $address = trimStr($post['address']);
        $description = trimStr($post['description']);
        $province = intval($post['province']);
        $city = intval($post['city']);
        $area = intval($post['area']);
        $status = intval($post['status']);
        $lng = floatval($post['lng']);
        $lat = floatval($post['lat']);
        $province_name = NewArea::getRegionName($province);
        $city_name = NewArea::getRegionName($city);
        $area_name = NewArea::getRegionName($area);

        $data = compact('circle_name','address','description','province','city','area','status','lng','lat','province_name','city_name','area_name');
        $res = $this->where(['id'=>$id])->update($data);
        if($res === false)throw new Exception('修改失败');

        return $id;
    }

}