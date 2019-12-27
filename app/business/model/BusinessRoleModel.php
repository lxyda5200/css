<?php


namespace app\business\model;


use think\Model;

class BusinessRoleModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_role';

    /**
     *  获取角色数据列表
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getRoleData(){
        $list = self::where(['status' => 1])->field(['id','role_name'])->select();

        return $list;
    }
}