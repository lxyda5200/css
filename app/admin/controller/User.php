<?php

namespace app\admin\controller;

use \app\admin\model\User as userModel;
use \app\admin\model\UserShow as usershowModel;
use think\Db;
use think\Request;
use think\Session;
use think\Exception;
class User extends Permissions
{
    /*
     * 普通用户列表
     * */
    public function index(){
        $model = new userModel();
        $post= Request::instance()->request();
        $day=intval($post['day']);
        //判断是否是天数快捷查询
        if($day==1){
            //今天
            $begin=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }elseif($day==2){
            //昨天
            $begin=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
            $end=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }elseif($day==3){
            //前天
            $begin=mktime(0,0,0,date('m'),date('d')-2,date('Y'));
            $end=mktime(0,0,0,date('m'),date('d')-1,date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }else{
            if (!empty($post['create_time'])) {
                $where["FROM_UNIXTIME(create_time,'%Y-%m-%d')"] = ['eq',$post['create_time']];
            }
        }
        if (!empty($post['keywords'])) {
            $where['nickname|mobile|invitation_code'] = ['like', '%' . $post['keywords'] . '%'];
        }

        if (isset($post['user_status']) and $post['user_status'] != '') {
            $where['user_status'] = ['eq',$post['user_status']];
        }
        if (!empty($post['type'])) {
            $where['type'] = ['eq',$post['type']];
        }
        //来源
        if (!empty($post['source'])) {
            $where['source'] = ['eq',$post['source']];
        }
        //是否授权
        if (!empty($post['authorize_time'])) {
            $authorize_time=$post['authorize_time'];
     if($authorize_time==1){
         //已授权
         $where['authorize_time'] = ['gt',0];
     }else{
         //未授权
         $where['authorize_time'] = ['eq',0];
     }
        }
        $users = $model->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);

//        foreach ($users as $k=>$v){
//            if($v['mobile']==''){
//                $user_mobile = Db::name('user') ->where('wx_openid',$v['wx_openid'])->where('mobile','neq','')->value('mobile');
//                $users[$k]['mobile']=$user_mobile;
//            }
//        }
        $sum_money = $model->where($where)->sum('money');
        $number = $model->where($where)->count('user_id');
        $this->assign('sum_money',$sum_money);
        $this->assign('number',$number);
        $this->assign('users',$users);
        $this->assign('param',$post);
        return $this->fetch();
    }
    /*
     * 普通用户列表（演示）
     * */
    public function user_show(){
        $model = new userModel();
        $model2 = new usershowModel();
        $post= Request::instance()->request();
        $day=intval($post['day']);
        //判断是否是天数快捷查询
        if($day==1){
            //今天
            $begin=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }elseif($day==2){
            //昨天
            $begin=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
            $end=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }elseif($day==3){
            //前天
            $begin=mktime(0,0,0,date('m'),date('d')-2,date('Y'));
            $end=mktime(0,0,0,date('m'),date('d')-1,date('Y'))-1;
            $where['create_time'] = ['between',[$begin,$end] ];
        }else{
            if (!empty($post['create_time'])) {
                $where["FROM_UNIXTIME(create_time,'%Y-%m-%d')"] = ['eq',$post['create_time']];
            }
        }
        if (!empty($post['keywords'])) {
            $where['nickname|mobile|invitation_code'] = ['like', '%' . $post['keywords'] . '%'];
        }

        if (isset($post['user_status']) and $post['user_status'] != '') {
            $where['user_status'] = ['eq',$post['user_status']];
        }
        if (!empty($post['type'])) {
            $where['type'] = ['eq',$post['type']];
        }
        //来源
        if (!empty($post['source'])) {
            $where['source'] = ['eq',$post['source']];
        }
        //是否授权
        if (!empty($post['authorize_time'])) {
            $authorize_time=$post['authorize_time'];
            if($authorize_time==1){
                //已授权
                $where['authorize_time'] = ['gt',0];
            }else{
                //未授权
                $where['authorize_time'] = ['eq',0];
            }
        }

        if (!empty($post['page'])) {
            $page=$post['page'];
        }else{
            $page=1;
        }
        $size=15;
        //查询语句1
        $matField = '*';
        $Sql1 = Db::name('user')
            ->field($matField)
            ->where($where)
            ->order('create_time desc')
            ->buildSql();
        $actField = '*';
        //查询语句2
        $Sql2 = Db::field($actField)
            ->name('user_show')
            ->where($where)
            ->union($Sql1, true)
            ->select(false);

        //查询结果
        $sql = "($Sql2)";
        $users = Db::table($sql.' as a')->field($matField) // 这里xx与$matField 和$actField 字段名称要一致
        ->order('a.create_time DESC')
        ->paginate(15,false,['query'=>$this->request->param()]);


//        $users = $model2->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);

//        if(count($users1)<15){
//            $p=15-$users1;
//            $users2 = $model2->order('create_time desc')->where($where)->limit();
//        }

//        var_dump($users);
        foreach ($users as $k=>&$v){
            $v['authorize_time']=date("Y-m-d H:i:s",$v['authorize_time']);
            if($v['mobile']==''){
                $user_mobile = Db::name('user') ->where('wx_openid',$v['wx_openid'])->where('mobile','neq','')->value('mobile');
                $users[$k]['mobile']=$user_mobile;
            }
        }
        $sum_money = $model->where($where)->sum('money');
        $number1 = $model->where($where)->count('user_id');
        $number2 = $model2->where($where)->count('user_id');
        $number=$number1+$number2;
        $this->assign('sum_money',$sum_money);
        $this->assign('number',$number);
        $this->assign('users',$users);
        $this->assign('param',$post);
        return $this->fetch();
    }
    /*
     * 编辑
     * */
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new userModel();
        #$cateModel = new cateModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['nickname', 'require', '昵称不能为空'],
                    ['avatar', 'require', '请上传头像'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $article = $model->where('user_id',$id)->find();
                if(empty($article)) {
                    return $this->error('id不正确');
                }
                //设置修改人
                $post['edit_admin_id'] = Session::get('admin');
                if(false == $model->allowField(true)->save($post,['user_id'=>$id])) {
                    return $this->error('修改失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('修改成功','admin/user/index');
                }
            } else {
                //非提交操作
                $user = $model->where('user_id',$id)->find();

                if(!empty($user)) {
                    $this->assign('user',$user);
                    $this->assign('title','编辑');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            //是新增操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([
                    ['nickname', 'require', '昵称不能为空'],
                    ['avatar', 'require', '请上传头像'],
                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //设置创建人
                $post['admin_id'] = Session::get('admin');
                //设置修改人
                $post['edit_admin_id'] = $post['admin_id'];
                if(false == $model->allowField(true)->save($post)) {
                    return $this->error('添加失败');
                } else {
                    addlog($model->id);//写入日志
                    return $this->success('添加成功','admin/user/index');
                }
            } else {
                //非提交操作
                #$cate = $cateModel->select();
                #$cates = $cateModel->catelist($cate);
                #$this->assign('cates',$cates);
                $this->assign('title','新增');
                return $this->fetch();
            }
        }

    }

    /*
     * 启用/禁用
     * */
    public function user_status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $user_status = $post['user_status'];
                if(false == Db::name('user')->where('user_id',$id)->update(['user_status'=>$user_status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('设置成功','admin/user/index');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 提现记录
     * */
    public function tixian_record(){
        $param = $this->request->param();

        if (!empty($param['order_no'])) {
            $where['user_tixian_record.order_no'] = ['like', "%{$param['order_no']}%"];
        }

        if (!empty($param['user_id'])) {
            $where['user_tixian_record.user_id'] = ['eq', $param['user_id']];
        }

        if(!empty($param['start_create_at'])){
            $where['user_tixian_record.create_at'] = array('egt',"{$param['start_create_at']} 00:00:00");
        }

        if(!empty($param['end_create_at'])){
            if(!empty($param['start_create_at'])) {
                $where['user_tixian_record.create_at'] = array(array('egt',"{$param['start_create_at']} 00:00:00"),array('elt',"{$param['end_create_at']} 23:59:59")) ;

            }else{
                $where['user_tixian_record.create_at'] = array('elt',"{$param['end_create_at']} 23:59:59");
            }
        }

        try{
            $lists = Db::view('user_tixian_record')
                ->view('user','nickname','user.user_id = user_tixian_record.user_id','left')
                ->where($where)
                ->order(['user_tixian_record.create_at'=>'desc'])
                ->paginate(15,false);

        }catch (\Exception $e){
            return $e->getMessage();
        }

        $sum_tixian_money = Db::view('user_tixian_record')
            ->view('user','nickname','user.user_id = user_tixian_record.user_id','left')
            ->where($where)
            ->where('user_tixian_record.code',10000)
            ->sum('user_tixian_record.money');

        $this->assign('sum_tixian_money',$sum_tixian_money);
        $this->assign('param',$this->request->param());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /*
 * 推广统计列表
 * */
    public function statistics(){
        $model = new userModel();
        $post= Request::instance()->request();
        $mobile=trim($post['keywords']);
        if($mobile){
            $pattern = "/^1(3[0-9]|4[0-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])\\d{8}$/";
            if (!preg_match($pattern, $mobile)) {
                return $this->error('手机号格式错误','admin/user/statistics','',1);
            }
            $user = Db::name('user')->where('mobile',$mobile)->find();
            if($user){
                $source=$post['source'];

                if($source>0){
                    $where['source'] = ['eq',$source];

                }
                $begin=strtotime($post['authorize_time']);
                $en=$post['authorize_time2'];
                $end=strtotime(" $en+1 day ")-1;
                //开始时间
                if (!empty($post['authorize_time']) && empty($post['authorize_time2'])){
                    $where['authorize_time'] = ['gt',$begin ];
                }
                //结束时间
                if (!empty($post['authorize_time2']) && empty($post['authorize_time'])) {
                    $where['authorize_time'] = ['lt',$end];
                }
                if (!empty($post['authorize_time2']) && !empty($post['authorize_time'])) {
                    $where['authorize_time'] = ['between',[$begin,$end]];
                }
                $where['invitation_user_id'] = ['eq',$user['user_id']];
                $users = $model->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);
                $total_number=$model->where($where)->count();
                foreach ($users as $k=>$v){
                    if($v['mobile']==''){
                        $user_mobile = Db::name('user') ->where('wx_openid',$v['wx_openid'])->where('mobile','neq','')->value('mobile');
                        $users[$k]['mobile']=$user_mobile;
                    }
                }
                $today = mktime(0,0,0,date('m'),date('d'),date('Y'));
                $today_end = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
                $where1['authorize_time'] = ['between',[$today,$today_end] ];
                $curr = date("Y-m-d");
                $w=date('w');//获取当前周的第几天 周日是 0 周一到周六是1-6  
                $beginLastweek=strtotime("$curr -".($w ? $w-1 : $w-6).' days');//获取本周开始日期，如果$w是0是周日:-6天;其它:-1天    
                $s=date('Y-m-d 00:00:00',$beginLastweek);
                $e=date('Y-m-d 23:59:59',strtotime("$s +6 days"));

                $where2["FROM_UNIXTIME(authorize_time,'%Y-%m-%d')"]=['between',[$s,$e] ];
                $today_number = Db::name('user')->where($where)->where($where1)->count();
                $week_number = Db::name('user')->where($where)->where($where2)->count();
            }else{
                return $this->error('未查询到该推广用户','admin/user/statistics','',1);
            }
        }else{
            $where['user_id'] = ['eq',0];
            $users = $model->order('create_time desc')->where($where)->paginate(15,false,['query'=>$this->request->param()]);
            $today_number=0;
            $total_number=0;
            $week_number=0;
        }
        $this->assign('total_number',$total_number);
        $this->assign('user',$user);
        $this->assign('users',$users);
        $this->assign('today_number',$today_number);
        $this->assign('week_number',$week_number);
        $this->assign('param',$post);
        return $this->fetch();
    }

}