<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/25
 * Time: 16:56
 */

namespace app\admin\model;


use think\Model;

class Topic extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    /**
     * 获取话题列表
     * @return array
     */
    public function getList(){
        return $this->where(['client_type'=>2,'status'=>1])->field('id,title')->select()->toArray();
    }

}