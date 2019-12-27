<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/3/4
 * Time: 11:26
 */

namespace app\admin\model;


use think\Model;

class MemberStoreBanner extends Model
{

    protected $autoWriteTimestamp = false;

    /**
     * 修改排序
     * @param $id
     * @param $sort
     * @return int
     */
    public static function editSort($id, $sort){
        return (new self())->where(['id'=>$id])->setField('sort', $sort);
    }

}