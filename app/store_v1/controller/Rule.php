<?php


namespace app\store_v1\controller;
use app\store_v1\model\StoreGroup;
use think\Db;
use think\Request;
use think\response\Json;
header("content-type:text/html;charset=utf-8");
class Rule extends Base
{
    protected $noNeedRight = '*';
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    /**
     * 全部角色列表
     */
    public function Rulelist(){
        $status = intval(input('type')); //状态
        $where['store_id'] = $this->store_info['main_id'];
        if(in_array($status,[1,2])){
            $where['status'] = $status;
        }
        $data['total'] = Db::table('store_group')
            ->where($where)
            ->count();
        $data['list'] = Db::table('store_group')
            ->where($where)
            ->order('status asc')
            ->page($this->page,$this->size)
            ->select();
        $data['max_page'] = ceil($data['total']/$this->size);
        return json(['status'=>1,'msg'=>'返回成功','data'=>$data]);
    }

    /**
     * 新增角色
     */
    public function RuleAdd(){
        $name = trim(input('name'));  //角色名
        $desc = trim(input('desc'));
        $prom = input('post.');
        if(empty($prom['rules'])){
            return json(['status'=>0,'msg'=>'请求信息错误','data'=>'']);
        }
        $rules =$prom['rules'];
        $status = intval(input('status')); //状态 1正常 2禁用
        if(empty($name) || empty($rules)){
            return json(['status'=>0,'msg'=>'请求信息错误','data'=>'']);
        }
        if(!in_array($status,[1,2])){
            return json(['status'=>0,'msg'=>'状态不正常','data'=>'']);
        }
        $rules = json_encode($rules,JSON_UNESCAPED_UNICODE);
        $info_list = [
            'name'=>$name,
            'rules'=>$rules,
            'status'=>$status,
            'create_time'=>time(),
            'store_id'=>$this->store_info['main_id'],
        ];
        if($desc){
            $info_list['desc'] = $desc;
        }
        $model = new StoreGroup();
        $res = $model->allowField(true)->save($info_list);
        if($res){
            return json(['status'=>1,'msg'=>'添加角色成功','data'=>'']);
        }
        return json(['status'=>0,'msg'=>'添加角色失败','data'=>'']);
    }

    /**
     * 修改角色信息
     */
    public function RuleEdit(){
        $ids = intval(input('ids'));  //角色ID
        $name = trim(input('name'));  //角色名
        $desc = trim(input('desc'));
        $prom = input('post.');

        $status = intval(input('status')); //状态 1正常 2禁用
        if(empty($ids)){
            return json(['status'=>0,'msg'=>'请求信息错误','data'=>'']);
        }
        $info_list = [];
        if(!empty($status)){
            if(!in_array($status,[1,2])){
                return json(['status'=>0,'msg'=>'状态不正常','data'=>'']);
            }
            $info_list['status'] = $status;
        }
        if($name){
            $info_list['name'] = $name;
        }
        if(!empty($prom['rules'])){
            $rules = json_encode($prom['rules'],JSON_UNESCAPED_UNICODE);
            $info_list['rules'] = $rules;
        }
        if($desc){
            $info_list['desc'] = $desc;
        }
        if(empty($info_list)){
            return json(['status'=>0,'msg'=>'修改信息未填写','data'=>'']);
        }
        $info_list['update_time'] = time();
        $model = new StoreGroup();
        $res = $model->allowField(true)->save($info_list,['id'=>$ids]);
        if($res){
            return json(['status'=>1,'msg'=>'修改成功','data'=>'']);
        }
        return json(['status'=>0,'msg'=>'修改失败','data'=>'']);
    }


    /**
     * 修改状态
     */
    public function Userstatus(){
        $type = intval(input('type'));  //类型 1正常 2禁用
        $ids = intval(input('ids'));  //角色ID
        if(empty($ids)){
//            $this->error('请求错误');
            return json(['status'=>0,'msg'=>'请求错误','data'=>'']);
        }
        if(in_array($type,[1,2])){
            $status = $type;
            $model = new StoreGroup();
            $res = $model->allowField(true)->save(['status'=>$status],['id'=>$ids]);
            if($res){
//                $this->success('更新成功');
                return json(['status'=>1,'msg'=>'更新成功','data'=>'']);
            }
//            $this->error('更新失败');
            return json(['status'=>0,'msg'=>'更新失败','data'=>'']);
        }else{
//            $this->error('请求类型错误');
            return json(['status'=>0,'msg'=>'请求类型错误','data'=>'']);
        }
    }

    /**
     * 查看角色信息
     */
    public function Ruleche(){
        $ids = intval(input('ids'));
        if(empty($ids)){
            return json(['status'=>0,'msg'=>'请求信息错误','data'=>'']);
        }
        $data = Db::table('store_group')->where(['id'=>$ids,'store_id'=>$this->store_info['main_id']])->find();
        if(empty($data)){
            return json(['status'=>0,'msg'=>'角色不存在','data'=>'']);
        }
        $data['rules'] = json_decode($data['rules'],true);
        return json(['status'=>1,'msg'=>'返回成功','data'=>$data]);
    }

}