<?php


namespace app\business\model;


use think\Model;

class BusinessPowerDetailsModel extends Model
{

    protected $pk = 'id';

    protected $table = 'business_power_details';


    /**
     *  获取员工权限列表
     * @param $business_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPowerListByBusinessId($business_id){
        $list = self::where(['business_id' => $business_id])
            ->field(['id','power_id'])
            ->select();

        return $list;
    }

    /**
     *  删除或添加员工权限
     * @param $business_id  员工ID
     * @param $power_id     权限ID
     * @param $type         操作类型 0-删除 1-添加
     * @return int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function editBusinessPower($business_id, $power_id, $status){
        //查询权限信息
        $power_info = BusinessPowerModel::where(['id' => $power_id,'status'=>1])->field(['id','pid','name','type'])->find();
        if (!$power_info) return false;
        //判断父权限   与子权限
        if($power_info['pid'] == 0 && $power_info['type']  == 1){//父-单选
            return false;//此操作无效
        }elseif($power_info['pid'] == 0 && $power_info['type']  == 2){//父 - 复选
            $powers = BusinessPowerModel::where(['pid' => $power_id,'status'=>1])->field(['id'])->select();
            switch ($status) {
                case 0: //权限全部删除
                    foreach ($powers as $k =>$v){
                        $info = self::where(['business_id' => $business_id,'power_id'=>$v['id']])->field('id')->find();
                        if($info){
                            self::where('id', $info['id']) -> delete();
                        }
                    }
                    break;
                case 1: //权限全部添加
                    foreach ($powers as $k =>$v){
                        $info = self::where(['business_id' => $business_id,'power_id'=>$v['id']])->field('id')->find();
                        if(!$info){
                            self::insert(['business_id' => $business_id, 'power_id' => $v['id']]);
                        }
                    }
                    break;
            }
        }elseif ($power_info['pid'] > 0 && $power_info['type']  == 1){//子-单选
            $info = self::where(['business_id' => $business_id,'power_id'=>$power_id])->field('id')->find();
            if(!$info){ //此权限不存在，需要添加，另外一个删除
                self::insert(['business_id' => $business_id, 'power_id' => $power_id]);
                //删除其他子权限 //通过子id   找出其余 子id
                $other_powers = BusinessPowerModel::where(['pid' => $power_info['pid'],'status'=>1,'id'=>['neq',$power_id]])->field(['id'])->select();
                foreach ($other_powers as $k => $v){
                    $infos = self::where(['business_id' => $business_id,'power_id'=>$v['id']])->field('id')->find();
                    if($infos){
                        self::where('id', $infos['id']) -> delete();
                    }
                }
            }
        }else{//子-复选
            switch ($status) {
                case 0: //权限删除
                    $info = self::where(['business_id' => $business_id,'power_id'=>$power_id])->field('id')->find();
                    if($info){
                        self::where('id', $info['id'])->delete();
                    }
                    break;
                case 1: //添加权限
                    $info = self::where(['business_id' => $business_id,'power_id'=>$power_id])->field('id')->find();
                    if(!$info){
                        self::insert(['business_id' => $business_id, 'power_id' => $power_id]);
                    }
                    break;
            }
        }

        return true;
    }
}