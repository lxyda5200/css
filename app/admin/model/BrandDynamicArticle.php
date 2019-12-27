<?php


namespace app\admin\model;


use app\admin\model\BrandDynamicPicture;
use think\Exception;
use think\Model;
use traits\model\SoftDelete;
use app\admin\validate\Brand;

class BrandDynamicArticle extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time', 'sort'];

    use SoftDelete;

    protected $delete_time = 'delete_time';

    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 自动添加排序
     * @param $value
     * @param $data
     * @return int|mixed
     */
    public function setSortAttr($value, $data){
        $brand_dynamic_id = intval($data['brand_dynamic_id']);
        return $this->where(['brand_dynamic_id'=>$brand_dynamic_id])->order('sort','desc')->value('sort') + 1;
    }

    /**
     * 新增时尚动态资讯
     * @param $brand_dynamic_id
     * @param $post
     * @throws Exception
     */
    public function add($brand_dynamic_id, $post){
        $articles = $post['articles'];
        $brand = new Brand();
        foreach($articles as $v){
            #验证
            $type = intval($v['type']);
            $check = $brand->scene("add_brand_dynamic_article_{$type}")->check($v);
            if(!$check)throw new Exception($brand->getError());
            $data = [
                'brand_dynamic_id' => $brand_dynamic_id,
                'title' => trimStr($v['title']),
                'cover' => trimStr($v['cover']),
                'type' => intval($v['type']),
                'create_time' => time(),
                'sort' => intval($v['sort'])
            ];
            if($type == 1){  //视频
                $data['video_url'] = trimStr($v['video_url']);
                $data['video_cover'] = trimStr($v['video_cover']);
                $data['media_id'] = trimStr($v['media_id']);
            }
            if($type == 3){  //news
                $data['content'] = htmlspecialchars(addslashes($v['content']));
            }

            $article_id = $this->insertGetId($data);
            if($article_id === false)throw new Exception('时尚动态资讯添加失败');

            if($type == 2){  //影集
                ##增加影集信息
                BrandDynamicPicture::add($article_id, $v['imgs']);
            }
            if($type == 3){  //news
                ##增加news banner
                BrandDynamicNewsImgs::add($article_id, $v['imgs']);
            }
        }
    }

    /**
     * 增加一条时尚动态资讯
     * @param $post
     * @throws Exception
     */
    public function addOne($post){
        $type = intval($post['type']);
        $data = [
            'brand_dynamic_id' => intval($post['brand_dynamic_id']),
            'title' => trimStr($post['title']),
            'cover' => trimStr($post['cover']),
            'type' => $type,
            'create_time' => time(),
            'sort' => $this->autoSort(intval($post['brand_dynamic_id']))
        ];
        if($type == 1){  //视频
            $data['video_url'] = trimStr($post['video_url']);
            $data['video_cover'] = trimStr($post['video_cover']);
            $data['media_id'] = trimStr($post['media_id']);
            $data['media_desc'] = trimStr($post['media_desc']);
        }
        if($type == 3){  //news
            $data['content'] = htmlspecialchars(addslashes($post['content']));
        }

        $article_id = $this->insertGetId($data);
        if($article_id === false)throw new Exception('时尚动态资讯添加失败');

        if($type == 2){  //影集
            ##增加影集信息
            BrandDynamicPicture::add($article_id, $post['imgs']);
        }
        if($type == 3){  //news
            ##增加news banner
            BrandDynamicNewsImgs::add($article_id, $post['imgs']);
        }
    }

    /**
     * 获取资讯排序
     * @param $brand_dynamic_id
     * @return int|mixed
     */
    public function autoSort($brand_dynamic_id){
        return $this->where(['brand_dynamic_id'=>$brand_dynamic_id])->order('sort','desc')->value('sort') + 1;
    }

    /**
     * 更新时尚动态资讯
     * @param $post
     * @throws Exception
     */
    public function edit($post){
        $type = intval($post['type']);
        $data = [
            'title' => trimStr($post['title']),
            'cover' => trimStr($post['cover']),
            'type' => $type
        ];
        if($type == 1){  //视频
            $data['video_url'] = trimStr($post['video_url']);
            $data['video_cover'] = trimStr($post['video_cover']);
            $data['media_id'] = trimStr($post['media_id']);
            $data['media_desc'] = trimStr($post['media_desc']);
        }
        if($type == 3){  //news
            $data['content'] = htmlspecialchars(addslashes($post['content']));
        }

        $article_id = intval($post['id']);
        $res = $this->where(['id'=>$article_id])->update($data);
        if($res === false)throw new Exception('时尚动态资讯修改失败');

        if($type == 2){  //影集
            ##删除以前的影集
            BrandDynamicPicture::del($article_id);
            ##增加影集信息
            BrandDynamicPicture::add($article_id, $post['imgs']);
        }
        if($type == 3){  //news
            ##删除以前的new banner
            BrandDynamicNewsImgs::del($article_id);
            ##增加news banner
            BrandDynamicNewsImgs::add($article_id, $post['imgs']);
        }
    }

    /**
     * 排序
     * @throws Exception
     */
    public function sort(){
        $id = input('post.id',0,'intval');
        $brand_dynamic_id = input('post.brand_dynamic_id',0,'intval');
        $sort = input('post.sort',1,'intval');

        ##获取以前的排序
        $prev_sort = $this->where(['id'=>$id])->value('sort');
        if($prev_sort == $sort)return;
        ##更新
        if($prev_sort > $sort){
            $ids = $this->where(['sort'=>['BETWEEN',[$sort,$prev_sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
            foreach($ids as $v){$this->where(['id'=>$v])->setInc('sort',1);}
        }else{
            $ids = $this->where(['sort'=>['BETWEEN',[$prev_sort,$sort]],'brand_dynamic_id'=>$brand_dynamic_id])->column('id');
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
        $brand_dynamic_id = input('post.brand_dynamic_id',0,'intval');
        ##获取当前的排序
        $cur_sort = $this->where(['id'=>$id,'brand_dynamic_id'=>$brand_dynamic_id])->value('sort');
        ##每一个排序递加1
        $res = $this->where(['sort'=>['LT',$cur_sort],'brand_dynamic_id'=>$brand_dynamic_id])->setInc('sort',1);
        if($res === false)throw new Exception('操作失败');
        ##当前的单品排序置为1
        $res = $this->where(['id'=>$id])->setField('sort',1);
        if($res === false)throw new Exception('操作失败');
    }

    /**
     * 获取动态资讯列表
     * @return array
     * @throws \think\exception\DbException
     */
    public static function getList(){
        $brand_id = input('post.brand_id',0,'intval');
        $page = input('post.page',1,'intval');
        ##获取动态id
        $brand_dynamic_id = BrandDynamic::getIdByBrandId($brand_id);
        ##获取列表
        $data = (new self())->where(['brand_dynamic_id'=>$brand_dynamic_id])->field('id,title,visit_number,status,type,sort')->order('sort','asc')->paginate(5,false,['page'=>$page])->toArray();
        $data['max_page'] = ceil($data['total']/$data['per_page']);
        return $data;
    }

    /**
     * 获取动态资讯详情
     * @return array|false|\PDOStatement|string|Model
     */
    public static function getInfo(){
        $article_id = input('post.article_id',0,'intval');
        #获取信息
        $info = (new self())->where(['id'=>$article_id])->field('id,title,cover,type,video_url,video_cover,media_id,media_desc,content,media_desc')->with(['newsImgs','pictures'])->find();
        return $info;
    }

    /**
     * 获取
     * @param $val
     * @return string
     */
    public function getContentAttr($val){
        return stripslashes(htmlspecialchars_decode(htmlspecialchars_decode(stripslashes($val))));
    }

    /**
     * 动态news imgs
     * @return \think\model\relation\HasMany
     */
    public function newsImgs(){
        return $this->hasMany('BrandDynamicNewsImgs','dynamic_news_id','id')->field('img,is_cover,sort,dynamic_news_id')->order('sort','asc');
    }

    /**
     * 动态影集 pictures
     * @return \think\model\relation\HasMany
     */
    public function pictures(){
        return $this->hasMany('BrandDynamicPicture','dynamic_article_id','id')->field('url,desc,is_cover,sort,dynamic_article_id')->order('sort','asc');
    }

    /**
     * 修改状态
     * @throws Exception
     */
    public function editStatus(){
        $id = input('post.id',0,'intval');
        $status = input('post.status',1,'intval');

        $res = $this->where(['id'=>$id])->setField('status',$status);
        if($res === false)throw new Exception('操作失败');
    }

}