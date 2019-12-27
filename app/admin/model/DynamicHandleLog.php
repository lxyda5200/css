<?php


namespace app\admin\model;


use think\Exception;
use think\Model;
use think\Session;

class DynamicHandleLog extends Model
{

    protected $autoWriteTimestamp = false;

    protected $resultSetType = '\think\Collection';

    protected $insert = ['create_time'];

    /**
     * 自动完成 创建时间
     * @return int
     */
    public function setCreateTimeAttr(){
        return time();
    }

    /**
     * 增加操作记录
     * @param $event
     * @param $dynamic_id
     * @param string $note
     * @return bool|string
     */
    public static function addLog($event, $dynamic_id, $note=""){
        try{
            $data = compact('event','dynamic_id','note');
            $data['handle_id'] = (int)Session::get('admin');
            $res = (new self())->isUpdate(false)->save($data);
            if($res === false)throw new Exception('添加操作纪录失败');
            return true;
        }catch(Exception $e){
            return $e->getMessage();
        }
    }

}