<?php


namespace app\admin\model;


use think\Model;
use traits\model\SoftDelete;

class CouponUseRule extends Model
{

    protected $autoWriteTimestamp = false;
    use SoftDelete;

    /**
     * 获取
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCanUseLists(){
        return $this->where(['status'=>1,'is_common'=>1])->field('id,title')->select();
    }

    /**
     * 添加规则
     * @param $data
     * @return int|string
     */
    public function add($data){
        $data['create_time'] = time();
        return $this->insertGetId($data);
    }

    /**
     * 更新规则
     * @param $id
     * @param $data
     * @return CouponUseRule
     */
    public function edit($id, $data){
        return $this->where(['id'=>$id])->update($data);
    }

    /**
     * 更新字段
     * @param $id
     * @param $field
     * @param $value
     * @return int
     */
    public function updateField($id, $field, $value){
        return $this->where(['id'=>$id])->setField($field,$value);
    }

    /**
     * 获取公共模板id
     * @return array
     */
    public function getCanUseIds(){
        return $this->where(['status'=>1,'is_common'=>1])->column('id');
    }

    /**
     *
     * @param $id_str
     * @return array
     */
    public static function getCouponRules($id_str){
        $rule_model_id = explode(',',$id_str);
        $rules = (new self())->where(['id'=>['IN',$rule_model_id],'status'=>1])->column('title');
        return $rules;
    }

}