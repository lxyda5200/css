<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class BrandCate extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    use SoftDelete;

    /**
     * 获取品牌分类列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList(){
        return $this->where(['status'=>1])->field('id,title')->select()->toArray();
    }

}