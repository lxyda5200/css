<?php


namespace app\admin\model;

use app\admin\validate\Brand;

use think\Exception;
use think\Model;

class BrandDynamicAds extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time', 'sort'];

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 自动生成排序
     * @param $value
     * @param $data
     * @return int|mixed
     */
    public function setSortAttr($value, $data){
        $brand_dynamic_id = $data['brand_dynamic_id'];
        return $this->where(['brand_dynamic_id'=>$brand_dynamic_id])->order('sort','desc')->value('sort') + 1;
    }

    /**
     * 新增时尚动态
     * @param $brand_dynamic_id
     * @param $post
     * @throws Exception
     */
    public function add($brand_dynamic_id, $post){
        $banners = $post['banners'];
        $brand = new Brand();
        $data = [];
        foreach($banners as $v){
            #验证
            $check = $brand->scene('add_brand_dynamic_ads')->check($v);
            if(!$check)throw new Exception($brand->getError());

            $item = [
                'brand_dynamic_id' => $brand_dynamic_id,
                'title' => trimStr($v['title']),
                'url' => trimStr($v['url']),
                'type' => intval($v['type']),
                'link_type' => intval($v['link_type']),
                'link_url' => trimStr($v['link_url']),
                'sort' => intval($v['sort'])
            ];

            if($v['type'] == 2){
                $item['cover'] = trimStr($v['cover']);
                $item['media_id'] = trimStr($v['media_id']);
            }
            $data[] = $item;
        }
        $res = $this->isUpdate(false)->saveAll($data);
        if($res === false)throw new Exception('时尚动态广告添加失败');
    }

    /**
     * 新增一条动态广告
     * @throws Exception
     */
    public function addOne(){
        $param = input('post.');
        $data = [
            'brand_dynamic_id' => intval($param['brand_dynamic_id']),
            'title' => trimStr($param['title']),
            'url' => trimStr($param['url']),
            'type' => intval($param['type']),
            'link_type' => intval($param['link_type']),
            'link_url' => trimStr($param['link_url']),
            'cover' => trimStr($param['cover'])
        ];
        $res = $this->isUpdate(false)->save($data);
        if($res === false)throw new Exception('时尚动态广告添加失败');
    }

    /**
     * 编辑动态广告
     * @throws Exception
     */
    public function edit(){
        $param = input('post.');
        $data = [
            'title' => trimStr($param['title']),
            'url' => trimStr($param['url']),
            'type' => intval($param['type']),
            'link_type' => intval($param['link_type']),
            'link_url' => trimStr($param['link_url'])
        ];
        $res = $this->save($data,['id'=>intval($param['id'])]);
        if($res === false)throw new Exception('时尚动态广告修改失败');
    }

    /**
     * 获取动态广告列表
     * @return array
     */
    public function getList(){
        ##获取动态id
        $brand_dynamic_id = input('post.brand_dynamic_id',0,'intval');
        ##获取列表
        $list = (new self())->where(['brand_dynamic_id'=>$brand_dynamic_id,'status'=>1])->field('id,title,type,cover,link_type,link_url,media_id,sort,url')->order('sort','asc')->select()->toArray();
        return $list;
    }

    /**
     * 删除动态广告
     * @throws Exception
     */
    public function del(){
        ##获取id
        $id = input('post.id',0,'intval');
        $res = $this->where(['id'=>$id])->delete();
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 排序
     * @throws Exception
     */
    public function sort(){
        $id = input('post.id',0,'intval');
        $sort = input('post.sort',1,'intval');

        ##获取以前的排序
        $prev_sort = $this->where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)return;
        ##更新
        if($prev_sort > $sort){
            $ids = $this->where(['sort'=>['BETWEEN',[$sort,$prev_sort]]])->column('id');
            foreach($ids as $v){$this->where(['id'=>$v])->setInc('sort',1);}
        }else{
            $ids = $this->where(['sort'=>['BETWEEN',[$prev_sort,$sort]]])->column('id');
            foreach($ids as $v){$this->where(['id'=>$v])->setDec('sort',1);}
        }
        $res = $this->where(['id'=>$id])->setField('sort', $sort);
        if($res === false)throw new Exception('操作失败');
    }

}