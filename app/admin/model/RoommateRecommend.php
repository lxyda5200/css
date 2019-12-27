<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use traits\model\SoftDelete;

class RoommateRecommend extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time', 'sort'];

    protected function setCreateTimeAttr(){
        return time();
    }

    public function setSortAttr(){
        return $this->order('sort','desc')->value('sort') + 1;
    }

    /**
     * 获取宿友推荐列表
     * @return array
     */
    public function getList(){
        $page = input('post.page',1,'intval');
        $list = $this->field('id,title,visit_number,status,sort')->order('sort','asc')->paginate(15,false,['page'=>$page])->toArray();
        $list['max_page'] = ceil($list['total']/$list['per_page']);
        return $list;
    }

    /**
     * 添加宿友推荐
     * @param $post
     * @return string
     */
    public function add($post){

        $title = trimStr($post['title']);
        $description = trimStr($post['description']);
        $bg_cover = trimStr($post['bg_cover']);

        $data = compact('title','description','bg_cover');

        $res = $this->isUpdate(false)->save($data);
        if($res === false)throw new Exception('添加失败');

        return $this->getLastInsID();
    }

    /**
     * 更新宿友推荐
     * @param $post
     * @throws Exception
     */
    public function edit($post){

        $id = intval($post['id']);
        $title = trimStr($post['title']);
        $description = trimStr($post['description']);
        $bg_cover = trimStr($post['bg_cover']);

        $data = compact('title','description','bg_cover');

        $res = $this->save($data,['id'=>$id]);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 宿友推荐详情
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo(){
        $id = input('post.id',0,'intval');
        $info = $this->where(['id'=>$id])->field('id,title,description,bg_cover')->with(['details'])->find();
        if(!$info)throw new Exception('数据不存在或已删除');
        return $info;
    }

    /**
     * 一对多 宿友推荐店铺列表
     * @return \think\model\relation\HasMany
     */
    public function details(){
        return $this->hasMany('RoommateRecommendDetail','roommate_recommend_id','id')->alias('rrd')
            ->join('store s','s.id = rrd.store_id','LEFT')
            ->field('
                rrd.id,rrd.roommate_recommend_id,rrd.store_id,rrd.cover,rrd.title,rrd.recommended_reason,rrd.star,
                s.store_name,s.mobile,s.address,s.real_read_number as read_number,s.cover as store_img
            ')
            ->order('rrd.sort','asc');
    }

    /**
     * 修改状态
     * @throws Exception
     */
    public function editStatus(){
        $id = input('post.id',0,'intval');
        $status = input('post.status',-1,'intval');
        $status = $status==-1?1:-1;
        $res = $this->save(compact('status'),compact('id'));
        if($res === false)throw new Exception('操作失败');
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
     * 置顶宿友推荐
     * @throws Exception
     */
    public function toTop(){
        $id = input('post.id',0,'intval');
        $cur_sort = $this->where(['id'=>$id])->value('sort');

        ##之前的排序都+1
        $res = $this->where(['sort'=>['LT',$cur_sort]])->setInc('sort',1);
        if($res === false)throw new Exception('操作失败');
        ##置顶当前数据
        $res = $this->where(['id'=>$id])->setField('sort',1);
        if($res === false)throw new Exception('操作失败');
    }

}