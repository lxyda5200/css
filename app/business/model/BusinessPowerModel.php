<?php


namespace app\business\model;


use think\Model;
use think\Db;
class BusinessPowerModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_power';

    /**
     *  获取权限列表【全部】
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessAllPower($business_id){
        $list = self::where(['pid'=>0,'status'=>1]) -> with('powers') -> field(['id,pid,name'])->order('sort ASC')->select();
        foreach ($list as $k => $v){
            $v['status'] = 1;
            foreach ($v['powers'] as $kp => $vp){
                $vp['status'] = 0;
                $power = Db::name('business_power_details')->where(['business_id'=>$business_id,'power_id'=>$vp['id']])->value('id');
                if($power){
                    $vp['status'] = 1;
                }else{
                    $v['status'] = 0;
                }
            }
        }
        return $list;
    }


    /**
     *  关联查询
     * @return \think\model\relation\HasMany
     */
    public function powers(){
        return $this -> hasMany('BusinessPowerModel', 'pid', 'id')
            -> field('id,pid,name')->order('sort ASC');
    }
}