<?php


namespace app\admin\model;


use app\admin\validate\Operate;
use think\Exception;
use think\Model;
use traits\model\SoftDelete;

class PopularProducts extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time','update_time', 'sort'];

    protected $update = ['update_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    protected function setUpdateTimeAttr(){
        return time();
    }

    /**
     * 新增时生成排序
     * @return int|mixed
     */
    protected function setSortAttr(){
        return $this->order('sort','desc')->value('sort') + 1;
    }

    /**
     * 设置
     * @param $data
     * @return false|int
     */
    public function add($data){
        ##获取当前的排序值
        $sort = $this->order('sort','desc')->value('sort') + 1;
        $data['sort'] = $sort;
        return $this->isUpdate(false)->save($data);
    }

    /**
     * 检查数据是否存在
     * @param $id
     * @return int|string
     */
    public function check($id){
        return $this->where(compact('id'))->count('id');
    }

    /**
     * 更新
     * @param $id
     * @param $data
     * @return false|int
     */
    public function edit($id, $data){
        return $this->save($data,compact('id'));
    }

    /**
     * 删除
     * @param $id
     * @return false|int
     */
    public function del($id){
        return $this->save(['delete_time'=>time()],compact('id'));
    }

    /**
     * 更新状态
     * @param $id
     * @param $status
     * @return false|int
     */
    public function editStatus($id, $status){
        $status = $status%2 + 1;
        return $this->save(compact('status'),compact('id'));
    }

    /**
     * 获取人气单品信息
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo(){
        $id = input('post.id',0,'intval');
        $info = $this->where(compact('id'))->field('id,title,bg_img')->with(['productList'])->find();
        return $info;
    }

    /**
     * 一对多 获取人气单品商品列表
     * @return \think\model\relation\HasMany
     */
    public function productList(){
        return $this->hasMany('PopularProductsDetails','pop_pro_id','id')
            ->join('product p','p.id = popular_products_details.product_id','LEFT')
            ->join('store s','p.store_id = s.id','LEFT')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->group('p.id')
            ->field('
                popular_products_details.id,popular_products_details.cover,popular_products_details.title,popular_products_details.desc,popular_products_details.product_id,popular_products_details.pop_pro_id,
                p.product_name,p.read_number,
                s.store_name,s.address,s.mobile,
                ps.cover as product_img
            ')
            ->order('sort','asc');
    }

    /**
     * 修改排序
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

    /**
     * 置顶
     * @throws Exception
     */
    public function toTop(){
        $id = input('post.id',0,'intval');
        ##每一个排序递加1
        $res = $this->where(['id'=>['LT',$id]])->setInc('sort',1);
        if($res === false)throw new Exception('操作失败');
        ##当前的单品排序置为1
        $res = $this->where(['id'=>$id])->setField('sort',1);
        if($res === false)throw new Exception('操作失败');
    }

}