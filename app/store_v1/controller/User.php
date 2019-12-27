<?php


namespace app\store_v1\controller;
use app\store_v1\model\Business;
use think\Db;
use think\Request;
use think\response\Json;

class User extends Base
{
    protected $noNeedRight = '*';

    protected $model = [];

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }


    /**
     * 商户管理员列表
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function data(){
        try{
            $name = trim(input('store_name')); //店铺名称
            $group_id = intval(input('group_id')); //角色ID
            $user_name = trim(input('user_name')); //账号
            $where['a.main_id'] = $this->store_info['main_id'];
            $where['a.business_status'] = ['>',0];
//            $where['a.id'] = ['neq',$this->store_info['user_id']];
            if($name){
                $where['s.store_name']=['like','%'.$name.'%'];
            }
            if($group_id){
                $where['a.group_id']=$group_id;
            }
            if($user_name){
                $where['a.user_name'] =  ['like','%'.$user_name.'%'];
            }
            $data['total'] = Db::table('business')
                ->alias('a')
                ->join(' store s','s.id=a.store_id','left')
                ->join('store_group g','g.id=a.group_id and g.store_id=a.main_id','left')
                ->where($where)
                ->count();
            $data['list'] = Db::table('business')
                ->alias('a')
                ->join('store s','s.id=a.store_id','left')
                ->join('store_group g','g.id=a.group_id and g.store_id=a.main_id','left')
                ->where($where)
                ->field('a.id,a.mobile,a.user_name,a.business_name,a.avatar,a.create_time,a.email,a.business_status,a.store_id,a.pid,a.group_id,a.main_id,s.store_name,g.name')
                ->page($this->page,$this->size)
                ->order('pid asc')
                ->select();
            $data['max_page'] = ceil($data['total']/$this->size);
            return json(['status'=>1,'msg'=>'返回成功','data'=>$data]);
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 新增管理员
     */
    public function UserAdd(){
        try{
            $user_name = trim(input('user_name'));  //账号
            $nickname = trim(input('nickname'));
            $password = trim(input('password'));
            $status = intval(input('status')); //状态
            $store_id = intval(input('store_id'));
            $group_id = intval(input('group_id'));
            if(empty($user_name) || empty($password) || empty($nickname) || empty($store_id) || empty($group_id)){
                return json(['status'=>0,'msg'=>'信息错误','data'=>'']);
            }
            //查询是否已存在该账号名
            $id = Db::table('business')->where(['user_name'=>$user_name])->field('id')->find();
            if($id){
                return json(['status'=>0,'msg'=>'该账号已被使用','data'=>'']);
            }
            //检查关联店铺是否正常
            $store_ids = Db::table('store')->where(['id'=>$store_id,'sh_status'=>1])->field('id')->find();
            if(empty($store_ids)){
                return json(['status'=>0,'msg'=>'该店铺不存在或被关闭','data'=>'']);
            }
            //检查角色ID是否正常
            $ids = Db::table('store_group')->where(['id'=>$group_id,'status'=>1])->field('id')->find();
            if(empty($ids)){
                return json(['status'=>0,'msg'=>'该角色已被删除或禁用','data'=>'']);
            }
            $info_list = [
                'user_name'=>$user_name,
                'business_name'=>$nickname,
                'password'=>password_hash($password, PASSWORD_DEFAULT),
                'store_id'=>$store_id,
                'main_id'=>$this->store_info['main_id'],
                'group_id'=>$group_id,
                'create_time'=>time(),
                'pid'=>$this->store_info['user_id'],
            ];

            if($status ==1){
                $info_list['business_status'] = 1;
            }else{
                $info_list['business_status'] = 2;
            }
            $model = new Business();
            $res = $model->allowField(true)->save($info_list);
            if($res){
                return json(['status'=>1,'msg'=>'添加成功','data'=>'']);
            }
            return json(['status'=>0,'msg'=>'添加失败','data'=>'']);
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改管理员信息
     */
    public function UserEdit(){
        try{
            $user_id = intval(input('user_id')); //管理员ID
            if(empty($user_id)){
                return json(['status'=>0,'msg'=>'管理员不存在','data'=>'']);
            }
            $status = intval(input('status')); //状态 1正常 2禁用
            $nickname = trim(input('nickname'));
            $password = trim(input('password'));
            $store_id = intval(input('store_id'));
            $group_id = intval(input('group_id'));
            $info_list =[];
            if(!empty($nickname)){
                $info_list['business_name'] = $nickname;
            }
            //关联店铺ID 0为主店铺
            if(!empty($store_id)){
                //检查关联店铺是否正常
                $store_ids = Db::table('store')->where(['id'=>$store_id,'status'=>1,'sh_status'=>1,'store_status'=>1])->field('id')->find();
                if(empty($store_ids)){
                    return json(['status'=>0,'msg'=>'该店铺不存在或被关闭','data'=>'']);
                }
                $info_list['store_id'] = $store_id;
            }

            if(!empty($group_id)){
                //检查角色ID是否正常
                $ids = Db::table('store_group')->where(['id'=>$group_id,'status'=>1])->field('id')->find();
                if(empty($ids)){
                    return json(['status'=>0,'msg'=>'该角色已被删除或禁用','data'=>'']);
                }
                $info_list['group_id'] = $group_id;
            }
            if(!empty($password)){
                $info_list['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            if(!empty($status) && in_array($status,[1,2])){
                if($status ==1){
                    $info_list['business_status'] = 1;
                }else{
                    $info_list['business_status'] = 2;
                }
            }
            $model = new Business();
            $res = $model->allowField(true)->save($info_list,['id'=>$user_id]);
            if($res){
                return json(['status'=>1,'msg'=>'更新成功','data'=>'']);
            }
            return json(['status'=>0,'msg'=>'更新失败','data'=>'']);
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改密码
     */
    public function modifyPassword(){
        try{
            $store_info = $this->store_info;
            $old_password = trim(input('old_password'));
            $new_password = trim(input('new_password'));
            if (!$old_password || !$new_password){
                return \json(self::callback(0,'参数错误'));
            }
            if (!password_verify($old_password, $store_info['password'])) {
                // Pass
                throw new \Exception('旧密码错误');
            }
            if (password_verify($new_password, $store_info['password'])) {
                // Pass
                throw new \Exception('新密码不能和原密码相同');
            }
            $data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            Db::name('business')->where('id',$store_info['user_id'])->update($data);
            return \json(self::callback(1,'修改成功'));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 修改状态
     */
    public function Userstatus(){
        try{
            $type = intval(input('type'));  //类型 1正常 2禁用
            $user_id = intval(input('user_id')); //管理员ID
            if(empty($user_id)){
                return json(['status'=>0,'msg'=>'请求错误','data'=>'']);
            }
            if(in_array($type,[1,2])){
                $status = intval($type);
                $model = new Business();
                $res = $model->allowField(true)->save(['business_status'=>$status],['id'=>$user_id]);
                if($res){
                    return json(['status'=>1,'msg'=>'更新成功','data'=>'']);
                }
                return json(['status'=>0,'msg'=>'更新失败','data'=>'']);
            }else{
                return json(['status'=>0,'msg'=>'请求类型错误','data'=>'']);
            }
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 查看信息
     */
    public function Userche(){
        try{
            $user_id = intval(input('user_id')); //管理员ID
            if(empty($user_id)){
                return json(['status'=>0,'msg'=>'请求信息不正确','data'=>'']);
            }
            $data = Db::table('business')
                ->alias('a')
                ->join('store s','s.id=a.store_id','left')
                ->join('store_group g','g.id=a.group_id and g.store_id=a.main_id','left')
                ->where(['a.id'=>$user_id,'main_id'=>$this->store_info['main_id']])
                ->field('a.id,a.mobile,a.user_name,a.business_name,a.avatar,a.create_time,a.email,a.business_status,a.store_id,a.pid,a.group_id,a.main_id,s.store_name,g.name')
                ->find();
            if($data){
                return json(['status'=>1,'msg'=>'返回成功','data'=>$data]);
            }
            return json(['status'=>0,'msg'=>'用户不存在','data'=>'']);
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
}