<?php


namespace app\business\model;


use think\Model;
use jiguang\JiG;
use think\Db;
class BusinessModel extends Model
{
    protected $pk = 'id';

    protected $table = 'business';

    /**
     *  获取员工信息数据
     * @param $business_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBusinessInfoData($business_id){
        $info = self::where(['b.id' => $business_id])
            ->alias('b')
            ->join('store s', 's.id = b.store_id')
            ->field([
                'b.business_name', 'b.money', 'b.mobile','b.avatar',
                's.store_name'
            ])
            ->find();
        //头像添加测试地址
        //$info['avatar'] = 'http://121.196.214.146/csswx/css/public'.$info['avatar'];

        return $info;
    }


    /**
     *  用户修改头像
     * @param $file
     * @param $user_id
     * @return array
     */
    public static function businessEditAvatar($file,$user_id){
        $info = $file->validate(['size'=>3145728,'ext'=>'jpg,png,gif,jpeg'])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads/busisess/avatar/',true,true);
        if($info){
            //缩略图
            // 输出 jpg
            //$info->getExtension();
            $photo = $info->getSaveName();
            //$photo = $info->getFilename();
            $imgurl = ROOT_PATH .'public/uploads/busisess/avatar/'.$photo;//原大图路径
            $image = \think\Image::open($imgurl);
            $image->thumb(200, 200,1)->save($imgurl);//生成缩略图、删除原图

            // 成功上传后 获取上传信息
            $avatar = '/uploads/busisess/avatar/'.str_replace("\\", '/', $photo);
            //$avatar = '/uploads/busisess/avatar/'.str_replace("\\", '/', $info->getSaveName());
            $return = self::where('id' , $user_id) -> update(['avatar' => $avatar]);

            //修改极光头像
            /*$jig_id = Db::name('business')->where(['id'=>$user_id])->value('jig_id');
            if($jig_id){
                $path = __FILE__;
                $host = strstr($path,'csswx')?"wx.supersg.cn":"appwx.supersg.cn";
                $data = [
                    'avatar'=>"http://" . $host .$avatar
                ];
                JiG::editServiceData($jig_id,$data);
            }*/

            if ($return){
                return ['code' => 0, 'msg'=>'成功', 'data' => $avatar];
            }
            return ['code' => 1, 'msg'=>'失败','data' => ''];
        }else{
            // 上传失败获取错误信息
            return ['code' => 1, 'msg'=>$file->getError(),'data' => ''];
        }
    }

    /**
     *  获取员工账号列表
     * @param $business_id  父级员工ID
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getStaffAccount($business_id,$user_info){
        $where['b.store_id'] = $user_info['store_id'];
        $where['b.business_status'] = 1;
        $where['b.pid'] = ['neq','0'];
        if($user_info['is_main_user'] == 0){
            $where['b.id'] = $business_id;
        }
        $data = self::where($where)
            ->alias('b')
            ->join('business_role r', 'b.role_id = r.id', 'left')
            ->field(['b.id', 'b.business_name', 'b.mobile', 'r.role_name as role'])
            ->select();
        return $data;
    }

    /**
     * 员工钱包
     * @param $business_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getMyWallet($business_id,$type,$page,$limit){
        $pre = ($page-1)*$limit;
        $wallet = self::where('id', $business_id)
            ->field(['withdraw_money','money as not_withdraw_money'])->find();
        if($type == 1){ //收入
            $wallet['list'] = self::incomes($business_id,$pre,$limit);
        }else{  //支出
            $wallet['list'] = self::inouts($business_id,$pre,$limit);
        }
        $wallet['money'] = '0';
        $money = BusinessMoneyDetailsModel::where(['money' => ['>', 0],'user_id'=>$business_id])->field('sum(money) as money')->find();
        if(isset($money['money']) && $money['money'] > 0) $wallet['money'] = $money['money'];

        return $wallet;
    }

    /**
     *  用户支出记录
     * @return \think\model\relation\HasMany
     */
    public function inouts($business_id,$pre,$limit){
        /*return $this -> hasMany('BusinessMoneyDetailsModel', 'user_id', 'id')->where(['money' => ['<', 0]])
            -> field('note,money,balance,create_time,order_id,order_detail_id')->limit($pre,$limit);*/
        $re =  BusinessMoneyDetailsModel::where(['money' => ['<', 0],'user_id'=>$business_id])
            ->field('type,money,order_id,FROM_UNIXTIME(create_time, \'%Y/%m/%d\') as ymd')->order('create_time desc')->limit($pre,$limit)->select();
        foreach ($re as &$v){
            $v['money'] = (string)abs($v['money']);
            $v['kind'] = '2';
            $v['title'] = '提现';
            $v['note'] = '提现单号：'.$v['order_id'];
            unset($v['order_id']);
        }
        return $re;
    }

    /**
     *  用户收入记录
     * @return \think\model\relation\HasMany
     */
    public function incomes($business_id,$pre,$limit){
        /*return $this -> hasMany('BusinessMoneyDetailsModel', 'user_id', 'id')->where(['money' => ['>', 0]])
            -> field('note,money,balance,create_time,order_id,order_detail_id')->limit($pre,$limit);*/
        $re =  BusinessMoneyDetailsModel::where(['bmd.money' => ['>', 0],'bmd.user_id'=>$business_id])
            ->alias('bmd')
            ->join('bussiness_profit bp', 'bp.id = bmd.profit_id', 'left')
            ->field('bmd.type,bmd.money,bp.maidan_order_id,FROM_UNIXTIME(bmd.create_time, \'%Y/%m/%d\') as ymd,bmd.profit_id,FROM_UNIXTIME(bp.create_time, \'%Y%m%d\') as profit_create_time')
            ->order('bmd.create_time desc')
            ->limit($pre,$limit)
            ->select();
        foreach ($re as &$v){
            $v['kind'] = '1';
            if($v['type'] == 1){
                $v['note'] = '订单号：'.$v['maidan_order_id'];
            }else{
                $v['note'] = "日期: " .$v['profit_create_time'];
            }
            if($v['type'] == 1){
                $v['title'] = '销售提成';
            }elseif ($v['type'] == 2){
                $v['title'] = '销售总额阶梯奖励';
            } elseif ($v['type'] == 3){
                $v['title'] = '新用户推广奖励';
            } elseif ($v['type'] == 4){
                $v['title'] = '首个用户额外奖励';
            } elseif ($v['type'] == 5){
                $v['title'] = '新用户推广阶梯奖励';
            }

            unset($v['profit_create_time']);
            unset($v['maidan_order_id']);
        }
        return $re;
    }

}