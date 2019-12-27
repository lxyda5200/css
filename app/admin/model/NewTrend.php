<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use traits\model\SoftDelete;
use app\admin\model\NewTrendStyle;

class NewTrend extends Model
{

    protected $autoWriteTimestamp = false;

    use SoftDelete;

    protected $deleteTime = 'delete_time';

    protected $insert = ['create_time', 'sort'];

    protected $resultSetType = '\think\Collection';

    public function setCreateTimeAttr(){
        return time();
    }

    public function setSortAttr(){
        ##获取当前排序
        $sort = $this->order('sort','desc')->value('sort') + 1;
        return $sort;
    }

    /**
     * 新增时尚新潮
     * @param $data
     * @return string
     * @throws Exception
     */
    public function add($data){
        $title = trimStr($data['title']);
        $cover = trimStr($data['cover']);
        $content = htmlspecialchars(addslashes($data['content']));
        $topic_id = intval($data['topic_id']);

        $data2 = compact('title','cover','content','topic_id');
        $res = $this->isUpdate(false)->save($data2);
        if($res === false)throw new Exception('添加失败');
        return $this->getLastInsID();
    }

    /**
     * 时尚新潮详情放开html
     * @param $value
     * @return string
     */
    public function getContentAttr($value){
        return stripslashes(htmlspecialchars_decode($value));
    }

    /**
     * 获取时尚新潮信息
     * @return array|false|\PDOStatement|string|Model
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo(){
        $id = input('post.id',0,'intval');
        $info = $this->alias('nt')
            ->join('topic t','t.id = nt.topic_id','LEFT')
            ->where(['nt.id'=>$id])
            ->field('
                nt.id,nt.title,nt.topic_id,nt.cover,nt.content,nt.cover,
                t.title as topic_title
            ')
            ->with(['storeList','productList'])
            ->find();
        if(!$info)throw new Exception('数据不存在或已删除');

        ##获取风格
        $styles = NewTrendStyle::getNewTrendStyleList($id);
        $info['style_list'] = $styles;
        return $info;
    }

    /**
     * 一对多 推荐店铺
     * @return \think\model\relation\HasMany
     */
    public function storeList(){
        return $this->hasMany('NewTrendStore','new_trend_id','id')->alias('nts')
            ->join('store s','s.id = nts.store_id','LEFT')
            ->field('
                s.store_name,s.address,s.mobile,s.id as store_id,
                nts.id,nts.new_trend_id
            ')
            ->order('nts.sort','asc');
    }

    /**
     * 一对多 推荐商品
     * @return \think\model\relation\HasMany
     */
    public function productList(){
        return $this->hasMany('NewTrendProduct','new_trend_id','id')->alias('ntp')
            ->join('product p','p.id = ntp.product_id','LEFT')
            ->join('store s','s.id = p.store_id','LEFT')
            ->join('product_specs ps','ps.product_id = p.id','LEFT')
            ->group('ntp.product_id')
            ->field('
                s.store_name,s.address,s.mobile,
                p.product_name,
                ps.cover,
                ntp.id,ntp.product_id,ntp.new_trend_id
            ')
            ->order('ntp.sort','asc');
    }

    /**
     * 编辑时尚新潮
     * @param $data
     * @throws Exception
     */
    public function edit($data){
        $id = intval($data['id']);

        $title = trimStr($data['title']);
        $cover = trimStr($data['cover']);
        $content = htmlspecialchars(addslashes($data['content']));
        $topic_id = intval($data['topic_id']);

        $data2 = compact('title','cover','content','topic_id');
        $res = $this->save($data2,['id'=>$id]);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 时尚新潮列表
     * @return array
     * @throws \think\exception\DbException
     */
    public function getList(){
        $page = input('post.page',1,'intval');
        $list = $this->order('sort','asc')->field('id,title,visit_number,status,cover,sort')->paginate(10,false,['page'=>$page])->toArray();
        $list['max_page'] = ceil($list['total']/$list['per_page']);
        return $list;
    }

    /**
     * 修改状态
     * @throws Exception
     */
    public function editStatus(){
        $id = input('post.id',0,'intval');
        $status = input('post.status',1,'intval');
        $status = $status%2+1;
        $res = $this->where(['id'=>$id])->setField('status',$status);
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
     * 置顶
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