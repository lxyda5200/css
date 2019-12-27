<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use traits\model\SoftDelete;
use app\admin\model\BrandStore;

class Brand extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    protected $insert = ['create_time', 'type'];

    public function setCreateTimeAttr(){
        return time();
    }

    public function setTypeAttr(){
        return 1;
    }

    /**
     * 添加品牌主信息
     * @throws Exception
     */
    public function add(){
        $brand_name = input('post.brand_name','','trimStr');
        $cate_id = input('post.cate_id',0,'intval');
        $logo = input('post.logo','','trimStr');
        $is_open = input('post.is_open',0,'intval');

        $data = compact('brand_name','cate_id','logo','is_open');
        $res = $this->isUpdate(false)->save($data);
        if($res === false)throw new Exception('添加失败');

        $brand_id = $this->getLastInsID();
        ##增加品牌故事
        BrandStory::autoAdd($brand_id);
        ##增加品牌动态
        BrandDynamic::autoAdd($brand_id);
    }

    /**
     * 获取品牌信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo(){
        $brand_id = input('post.id',0,'intval');
        $info = $this->alias('b')
            ->join('brand_cate bc','bc.id = b.cate_id','LEFT')
            ->where(['b.id'=>$brand_id])
            ->field('
                b.id,b.brand_name,b.cate_id,b.logo,b.is_open,
                bc.title as brand_cate_name
            ')
            ->find();
        if(!$info)throw new Exception('数据不存在或已删除');
        return $info;
    }

    /**
     * 更新品牌信息
     * @throws Exception
     */
    public function edit(){
        $id = input('post.id',0,'intval');
        $brand_name = input('post.brand_name','','trimStr');
        $cate_id = input('post.cate_id',0,'intval');
        $logo = input('post.logo','','trimStr');
        $is_open = input('post.is_open',0,'intval');

        $data = compact('brand_name','cate_id','logo','is_open');
        $res = $this->save($data,['id'=>$id]);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 获取品牌列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getList(){
        $page = input('post.id',1,'intval');
        $data = $this->alias('b')
            ->join('brand_story bs','bs.brand_id = b.id','LEFT')
            ->join('brand_dynamic bd','bd.brand_id = b.id','LEFT')
            ->join('brand_cate bc','bc.id = b.cate_id','LEFT')
            ->where([
                'b.type' => 1
            ])
            ->field('
                b.id,b.brand_name,b.logo,b.is_open,b.status,
                bc.title as cate_name,
                bs.id as brand_story_id,
                bd.id as brand_dynamic_id
            ')
            ->order('b.id','asc')
            ->paginate(10,false,['page'=>$page])
            ->toArray();
        $data['max_page'] = ceil($data['total']/$data['per_page']);
        return $data;
    }

    /**
     * 删除品牌
     * @throws Exception
     */
    public function del(){
        $id = input('post.id',0,'intval');
        #删除商家绑定的记录
        $res = BrandStore::delByBrandId($id);
        if($res === false)throw new Exception('操作失败');
        #删除品牌
        $res = Brand::destroy($id);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 修改品牌开放状态
     * @throws Exception
     */
    public function editIsOpen(){
        $id = input('post.id',0,'intval');
        $is_open = input('post.is_open',1,'intval');
        #更新
        $res = $this->where(['id'=>$id])->setField('is_open',$is_open);
        if($res === false)throw new Exception('操作失败');
    }

}