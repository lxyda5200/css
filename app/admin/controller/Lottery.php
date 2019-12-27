<?php


namespace app\admin\controller;


use app\admin\model\CouponRule;
use app\admin\model\DrawLottery;
use app\admin\model\DrawLotteryRecord;
use app\admin\model\GiftLottery;
use app\admin\model\TacticsLottery;
use app\admin\validate\LotteryValidate;
use my_redis\MRedis;
use think\Db;
use think\Exception;
use think\Loader;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

class Lottery extends Admin
{
    /**
     * 更新活动状态
     */
    private function updateActiveStatus() {
        DrawLottery::update(['active_status' => 2], ['start_time' => ['elt', time()], 'end_time' => ['gt', time()]]);
        DrawLottery::update(['active_status' => 3], ['end_time' => ['elt', time()], 'status' => 1]);
        DrawLottery::update(['active_status' => 4], ['end_time' => ['elt', time()], 'status' => -1]);
    }


    /**
     * 活动列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function lotteryList()
    {
        $params = input('param.');
        $where = ['delete_time' => null];
        $title = '';
        $active_status_ = 0;
        $status = 0;
        if(isset($params['title']) && !empty($params['title'])) {
            $where['dl.title'] = ['like', "%{$params['title']}%"];
            $title = $params['title'];
        }
        if(isset($params["active_status"]) && !empty($params["active_status"])) {
            $where["dl.active_status"] = $params["active_status"];
            $active_status_ = $params['active_status'];
        }
        if(isset($params["status"]) && !empty($params["status"])) {
            $where["dl.status"] = $params["status"];
            $status = $params['status'];
        }
        # 更新活动状态
        $this->updateActiveStatus();
        # 查询以上线活动
        $online = DrawLottery::where(['active_status' => 2])->count();
        # 查询待上线活动
        $offline = DrawLottery::where(['active_status' => 1])->count();
        # 查询本月已完成活动
        $current_month = DrawLottery::where(['active_status' => 3, 'start_time' => ['egt', strtotime(date('Y-m-1'))], 'end_time' => ['elt', time()]])->count();

        # 获取活动列表
        $active_status = [
            1 => "未开始",
            2 => "进行中",
            3 => "已完成",
            4 => "已失效"
        ];

        $draw_lottery_model = new DrawLottery();
        $list = $draw_lottery_model->alias('dl')
            ->join(['admin' => 'a'], 'a.id=dl.create_user')
            ->where($where)
            ->field('dl.*, a.name')->paginate(10);

        $this->assign(compact('online', 'offline', 'current_month', "list", "active_status", 'title', 'active_status_', 'status'));
        return $this->fetch();
    }


    /**
     * 添加活动
     * @return mixed|\think\response\Json
     */
    public function addLottery() {
        if(request()->isAjax()) {
            $params = input('post.');
            # 数据验证
            $validate = new LotteryValidate();
            if(!$validate->scene('save')->check($params))
                return json(['code' => 1, 'msg' => $validate->getError()]);

            Db::startTrans();
            try {
                # 写入活动
                $draw_lottery_data = [
                    'title' => trimStr($params['title']),
                    'description' => trimStr($params['description']),
                    'start_time' => strtotime($params['start_time']),
                    'end_time' => strtotime($params['end_time']),
                    'rule' => trimStr($params['rule']),
                    'client' => intval($params['client']),
                    'number' => intval($params['number']),
                    'fake_user' => intval($params['fake_user']),
                    'type' => intval($params['type']),
                    'per_user_max_number' => intval($params['per_user_max_number']),
                    'bg_img' => strip_tags(trim($params['bg_img'])),
                    'icon' => strip_tags(trim($params['icon'])),
                    'create_time' => time(),
                    'create_user' => session('admin')
                ];
                $draw_lottery_model = new DrawLottery();
                $lottery_id = $draw_lottery_model->insertGetId($draw_lottery_data);
                if(!$lottery_id)
                    throw new Exception('添加失败');


                # 修改优惠券状态
                $id_arr = array_column($params['coupon_data'], 'coupon_id');
                $res4 = CouponRule::where(['id' => ['in',$id_arr]])->update(['is_lottery' => 1]);
                if($res4 === false)
                    throw new Exception('添加失败！！！');

                # 写入奖品设置
                $coupon_data = $params['coupon_data'];
                foreach ($coupon_data as $k => $value) {
                    $coupon_data[$k]['remain'] = $value['actual_gift_count'];
                    unset($coupon_data[$k]['gl']);
                    unset($coupon_data[$k]['coupon_name']);
                    $coupon_data[$k]['gift_id'] = $value['coupon_id'];
                    unset($coupon_data[$k]['coupon_id']);
                    unset($coupon_data[$k]['surplus_number']);
                    $coupon_data[$k]['lottery_id'] = $lottery_id;
                }

                $gift_lottery_model = new GiftLottery();
                $res1 = $gift_lottery_model->insertAll($coupon_data);
                if(!$res1)
                    throw new Exception('添加失败啦');

                # 写入活动策略
                $tactics_data = $params['tactics_data'];
                foreach ($tactics_data as $key => $val) {
                    $tactics_data[$key]['lottery_id'] = $lottery_id;
                    $tactics_data[$key]['create_time'] = time();
                }
                $tactics_lottery_model = new TacticsLottery();
                $res2 = $tactics_lottery_model->insertAll($tactics_data);
                if(!$res2)
                    throw new Exception('添加失败！');

                $res3 = \app\user_v6\model\DrawLottery::addDrawRedisData($lottery_id);
                if(!$res3)
                    throw new Exception('添加失败！！！！！');

                Db::commit();
                return json(['code' => 0, 'msg' => '添加成功']);
            }catch (Exception $exception) {
                Db::rollback();
                return json(['code' => 1, 'msg' => $exception->getMessage()]);
            }
        }
        return $this->fetch();
    }


    /**
     * 编辑
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editLottery() {
        $id = input('param.id');
        if(request()->isAjax()) {
            $params = input('post.');

            # 数据验证
            $validate = new LotteryValidate();
            if(!$validate->scene('save')->check($params))
                return json(['code' => 1, 'msg' => $validate->getError()]);

            Db::startTrans();
            try {
                # 更新活动
                $draw_lottery_data = [
                    'title' => trimStr($params['title']),
                    'description' => trimStr($params['description']),
                    'start_time' => strtotime($params['start_time']),
                    'end_time' => strtotime($params['end_time']),
                    'rule' => trimStr($params['rule']),
                    'client' => intval($params['client']),
                    'number' => intval($params['number']),
                    'fake_user' => intval($params['fake_user']),
                    'type' => intval($params['type']),
                    'per_user_max_number' => intval($params['per_user_max_number']),
                    'bg_img' => strip_tags(trim($params['bg_img'])),
                    'icon' => strip_tags(trim($params['icon'])),
                    'create_time' => time(),
                    'create_user' => session('admin')
                ];
                $res = DrawLottery::where(['id' => intval($params['id'])])->update($draw_lottery_data);
                if($res === false)
                    throw new Exception('更新失败');


                # 更新奖品设置
                $id_arr1 = GiftLottery::where(['lottery_id' => intval($params['id']), 'gift_type' => 1])
                    ->column('gift_id');

                $res5 = CouponRule::where(['id' => ['in', $id_arr1]])->update(['is_lottery' => 0]);
                if($res5 === false)
                    throw new Exception('更新失败啦！！');

                $res1 = GiftLottery::where(['lottery_id' => intval($params['id'])])->delete();
                if($res1 === false)
                    throw new Exception('更新失败啦');

                # 修改优惠券状态
                $id_arr = array_column($params['coupon_data'], 'coupon_id');
                $res4 = CouponRule::where(['id' => ['in',$id_arr]])->update(['is_lottery' => 1]);
                if($res4 === false)
                    throw new Exception('修改失败！！！');


                $coupon_data = $params['coupon_data'];
                foreach ($coupon_data as $k => $v) {
                    $coupon_data[$k]['remain'] = $v['actual_gift_count'];
                    unset($coupon_data[$k]['gl']);
                    unset($coupon_data[$k]['coupon_name']);
                    $coupon_data[$k]['gift_id'] = $v['coupon_id'];
                    unset($coupon_data[$k]['coupon_id']);
                    unset($coupon_data[$k]['surplus_number']);
                    $coupon_data[$k]['lottery_id'] = intval($params['id']);
                }

                $gift_lottery_model = new GiftLottery();
                $res1 = $gift_lottery_model->insertAll($coupon_data);
                if(!$res1)
                    throw new Exception('更新失败啦！');


                # 更新活动策略
                $res3 = TacticsLottery::where(['lottery_id' => intval($params['id'])])->delete();
                if($res3 === false)
                    throw new Exception('更新失败啦！！！');
                $tactics_data = $params['tactics_data'];
                foreach ($tactics_data as $key => $val1) {
                    $tactics_data[$key]['lottery_id'] = intval($params['id']);
                    $tactics_data[$key]['create_time'] = time();
                }
                $tactics_lottery_model = new TacticsLottery();
                $res2 = $tactics_lottery_model->insertAll($tactics_data);
                if(!$res2)
                    throw new Exception('更新失败！！');

                $res4 = \app\user_v6\model\DrawLottery::addDrawRedisData($params['id']);
                if(!$res4)
                    throw new Exception('更新失败！！！');

                Db::commit();
                return json(['code' => 0, 'msg' => '更新成功']);
            }catch (Exception $exception) {
                Db::rollback();
                return json(['code' => 1, 'msg' => $exception->getMessage()]);
            }
        }
        $info = DrawLottery::get($id);
        $tactics_list = TacticsLottery::where(['lottery_id' => $id])->field('conditions')->select();
        $this->assign(compact('info', 'id', 'tactics_list'));
        return $this->fetch();
    }


    /**
     * 获取奖品列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGiftList() {
        $id = input('param.lottery_id');
        $gift_lottery_model = new GiftLottery();
        $gift_list = $gift_lottery_model->alias('gl')
            ->join(['draw_lottery' => 'dl'], 'dl.id=gl.lottery_id')
            ->join(['coupon_rule' => 'cr'], 'cr.id=gl.gift_id', 'left')
            ->where(['gl.lottery_id' => $id])
            ->field('gl.manic, cr.coupon_name, gl.gift_id as coupon_id, gl.gift_type, gl.gift_count, gl.actual_gift_count, gl.sort, gl.icon, gl.gift_name, gl.remain, dl.number')
            ->select();
//        $gift_list = GiftLottery::where(['lottery_id' => $id])
//            ->field('gift_id as coupon_id, gift_type, gift_count, actual_gift_count, sort, icon, gift_name, remain')
//            ->select();

        foreach ($gift_list as $k => $item) {
            $gift_list[$k]['gl'] = (string)(round($item['remain'] / $item['number'] * 100, 2));
            unset($gift_list[$k]['number']);
            unset($gift_list[$k]['remain']);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $gift_list]);
    }




    /**
     * 获取优惠券
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCoupon() {
        $params = input('post.');

        $where1 = ['is_lottery' => 0,'is_open' => 1, 'start_time' => ['elt', time()], 'end_time' => ['egt', time()], 'days' => 0, 'coupon_type' => 11, 'kind' => 2];
        $where2 = ['is_lottery' => 0,'is_open' => 1, 'start_time' => ['elt', time()], 'end_time' => ['egt', time()], 'days' => 0, 'coupon_type' => 12];

        $where3 = ['is_lottery' => 0,'is_open' => 1, 'days' => ['gt', 0], 'coupon_type' => 11, 'kind' => 2];
        $where4 = ['is_lottery' => 0,'is_open' => 1, 'days' => ['gt', 0], 'coupon_type' => 12];

        $where = [];
        if(isset($params['coupon_name']) && !empty($params['coupon_name']))
            $where['coupon_name'] = ['like', "%{$params['coupon_name']}%"];
        if(isset($params['type']) && !empty($params['type']))
            $where['type'] = $params['type'];
        $where1 = array_merge($where1, $where);
        $where2 = array_merge($where2, $where);
        $where3 = array_merge($where3, $where);
        $where4 = array_merge($where4, $where);

        $list = CouponRule::where($where1)
            ->whereOr(function ($query) use ($where2) {
                $query->where($where2);
            })
            ->whereOr(function ($query) use ($where3) {
                $query->where($where3);
            })->whereOr(function ($query) use ($where4) {
                $query->where($where4);
            })
            ->field('id, coupon_name, type as new_type, coupon_type as new_coupon_type, surplus_number')
            ->paginate(10);

        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }


    /**
     * 修改icon显示状态
     * @return \think\response\Json
     */
    public function changeStatus() {
        $params = input('post.');
        Db::startTrans();
        try {
            if(intval($params['icon_status']) == 1) {
                $res1 = DrawLottery::where(['id' => ['gt', 0]])->update(['icon_status' => 0]);
                if($res1 === false)
                    throw new Exception('修改失败');
            }
            $res = DrawLottery::where(['id' => intval($params['id'])])->update(['icon_status' => $params['icon_status']]);
            if($res === false)
                throw new Exception('修改失败！');

            $res2 = \app\user_v6\model\DrawLottery::addMainDrawRedisData();
            if($res2 === false)
                throw new Exception('修改失败！！');

            Db::commit();
            return json(['code' => 0, 'msg' => '修改成功']);
        }catch (Exception $exception) {
            Db::rollback();
            return json(['code' => 1, 'msg' => $exception->getMessage()]);
        }
    }


    /**
     * 开启/禁用
     * @return \think\response\Json
     */
    public function forbid() {
        $params = input('post.');
        Db::startTrans();
        try {
            $res = DrawLottery::where(['id' => $params['id']])->update(['status' => $params['status']]);
            if($res === false)
                throw new Exception('操作失败');

            $res1 = \app\user_v6\model\DrawLottery::editStatusEditDrawRedisData($params['id']);
            if(!$res1)
                throw new Exception('操作失败！');

            Db::commit();
            return json(['code' => 0, 'msg' => '操作成功']);
        }catch (Exception $exception) {
            Db::rollback();
            return json(['code' => 1, 'msg' => $exception->getMessage()]);
        }

    }


    /**
     * 删除
     * @return \think\response\Json
     */
    public function del() {
        $params = input('post.');
        $res = DrawLottery::where(['id' => $params['id']])->update(['delete_time' => time()]);
        if($res === false)
            return json(['code' => 1, 'msg' => '删除失败']);

        return json(['code' => 0, 'msg' => '删除成功']);
    }


    /**
     * 详情
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function detailLottery() {
        $id = input('param.id');
        $info = DrawLottery::get($id);
        $tactics_list = TacticsLottery::where(['lottery_id' => $id])->field('conditions')->select();

        $gift_lottery_model = new GiftLottery();
        $gift_list = $gift_lottery_model->alias('gl')
            ->join(['draw_lottery' => 'dl'], 'dl.id=gl.lottery_id')
            ->join(['coupon_rule' => 'cr'], 'cr.id=gl.gift_id', 'left')
            ->where(['gl.lottery_id' => $id])
            ->field('gl.manic, cr.coupon_name, gl.gift_id as coupon_id, gl.gift_type, gl.gift_count, gl.actual_gift_count, gl.sort, gl.icon, gl.gift_name, gl.remain, dl.number')
            ->select();

        foreach ($gift_list as $k => $item) {
            $gift_list[$k]['gl'] = (string)(round($item['remain'] / $item['number'] * 100, 2)) . '%';
            unset($gift_list[$k]['number']);
//            unset($gift_list[$k]['remain']);
        }

        $this->assign(compact('info', 'id', 'tactics_list', 'gift_list'));
        return $this->fetch();
    }


    /**
     * 抽奖记录统计信息
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lotteryLog() {
        $id = input('param.id');
        $active_data = Db::table('draw_lottery')->where(['id' => $id])->field('title, start_time, end_time')->find();
        $active_data['start_time'] = date('Y-m-d H:i:s', $active_data['start_time']);
        $active_data['end_time'] = date('Y-m-d H:i:s', $active_data['end_time']);
        $time = date('Y-m-d H:i:s', time());

        $today['cy'] = DrawLotteryRecord::where(['draw_lottery_id' => $id,
            'create_time' => ['elt', time()]])
            ->where(['create_time' => ['egt', strtotime(date('Y-m-d', time()))]])
            ->group('user_id')
            ->count(1);
        $today['zj'] = DrawLotteryRecord::where(['draw_lottery_id' => $id,
            'create_time' => ['elt', time()], 'is_reward' => 1])
            ->where(['create_time' => ['egt', strtotime(date('Y-m-d', time()))]])
            ->group('user_id')
            ->count(1);

        $lj['cy'] = DrawLotteryRecord::where(['draw_lottery_id' => $id])->group('user_id')->count(1);
        $lj['zj'] = DrawLotteryRecord::where(['draw_lottery_id' => $id, 'is_reward' => 1])->group('user_id')->count(1);

        $this->assign(compact('id', 'active_data', 'time', 'today', 'lj'));
        return $this->fetch();
    }


    /**
     * 累计中奖情况统计数据
     * @return \think\response\Json
     */
    public function accumulative() {
        $id = input('param.lottery_id');
        $yes_list = DrawLotteryRecord::where(['is_reward' => 1, 'draw_lottery_id' => $id])
            ->group('user_id')->column('user_id');
        $yes = count($yes_list);
        $no = DrawLotteryRecord::where(['is_reward' => 0, 'draw_lottery_id' => $id, 'user_id' => ['notin', $yes_list]])
            ->group('user_id')->count(1);
        return json(['code' => 0, 'msg' => 'success', 'data' => compact('no', 'yes')]);
    }


    /**
     * 中奖次数统计
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lotteryCount() {
        $id = input('param.lottery_id');
        $per_user_max_number = DrawLottery::where(['id' => $id])->value('per_user_max_number');
        $count_list = Db::table('draw_lottery_record')->where(['draw_lottery_id' => $id, 'is_reward' => 1])
            ->field('count(user_id) as user_id_c, user_id')
            ->group('user_id')->select();

        $zj_count = array_column($count_list, 'user_id_c');
        rsort($zj_count);
        if($per_user_max_number == -1)
            $per_user_max_number = isset($zj_count[0])?$zj_count[0]:0;

        $zj_count = array_count_values($zj_count);
        $count = count($zj_count);

        return json(['code' => 0, 'msg' => 'success', 'data' => compact('zj_count', 'per_user_max_number', 'count')]);
    }


    /**
     * 获取抽奖记录列表
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function getRecord() {
        $params = input('post.');
        $where['draw_lottery_id'] = $params['id'];
        if(isset($params['start_time']) && !empty($params['start_time']))
            $where['lr.create_time'] = ['egt', strtotime($params['start_time'])];
        if(isset($params['end_time']) && !empty($params['end_time']))
            $where['lr.create_time'] = ['elt', strtotime($params['end_time'])];
        if(isset($params['mobile']) && !empty($params['mobile']))
            $where['u.mobile'] = ['like', "%{$params['mobile']}%"];
        if(isset($params['gift_name']) && !empty($params['gift_name']))
            $where['gl.gift_name'] = ['like', "%{$params['gift_name']}%"];
        if(isset($params['is_reward']) && $params['is_reward']!='')
            $where['lr.is_reward'] = $params['is_reward'];

        $draw_lottery_record = new DrawLotteryRecord();
        $list = $draw_lottery_record->alias('lr')
            ->join(['user' => 'u'], 'u.user_id=lr.user_id', 'left')
            ->join(['gift_lottery' => 'gl'], 'gl.id=lr.reward_id', 'left')
            ->where($where)
            ->field('lr.draw_time, lr.is_reward, gl.gift_name, u.nickname, u.mobile')
            ->paginate(10);

        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }


    /**
     * 导出excel
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exportExcel() {
        $params = input('post.');

        # 查询数据
        $where['draw_lottery_id'] = $params['id'];
        if(isset($params['start_time']) && !empty($params['start_time']))
            $where['lr.create_time'] = ['egt', strtotime($params['start_time'])];
        if(isset($params['end_time']) && !empty($params['end_time']))
            $where['lr.create_time'] = ['elt', strtotime($params['end_time'])];
        if(isset($params['mobile']) && !empty($params['mobile']))
            $where['u.mobile'] = ['like', "%{$params['mobile']}%"];
        if(isset($params['gift_name']) && !empty($params['gift_name']))
            $where['gl.gift_name'] = ['like', "%{$params['gift_name']}%"];
        if(isset($params['is_reward']) && !empty($params['is_reward']))
            $where['lr.is_reward'] = $params['is_reward'];

        $draw_lottery_record = new DrawLotteryRecord();
        $data = $draw_lottery_record->alias('lr')
            ->join(['user' => 'u'], 'u.user_id=lr.user_id', 'left')
            ->join(['gift_lottery' => 'gl'], 'gl.id=lr.reward_id', 'left')
            ->where($where)
            ->field('lr.create_time, lr.is_reward, gl.gift_name, u.nickname, u.mobile')
            ->select();

        Loader::import('PHPExcel.Classes.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objSheet = $objPHPExcel->getActiveSheet();
        $objSheet->setTitle("中奖记录");

        $objSheet->setCellValue("A1","用户名称")
            ->setCellValue("B1","手机号")
            ->setCellValue("C1","抽奖时间")
            ->setCellValue("D1","是否中奖")
            ->setCellValue("E1","奖品名称");


        foreach ($data as $k => $item) {
            $objSheet->setCellValue("A".($k+2),$item['nickname']);
            $objSheet->setCellValue("B".($k+2),$item['mobile']);
            $objSheet->setCellValue("C".($k+2),$item['create_time']);
            $objSheet->setCellValue("D".($k+2),$item['is_reward']==1?"中奖":"未中奖");
            $objSheet->setCellValue("E".($k+2),$item['gift_name']);
        }


        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');

        /*浏览器查看，浏览器保存*/
        browser_excel('Excel2007',date('y-m-d/H:i:s', time()).'.xlsx');
        $objWriter->save("php://output");
    }


    /**
     * 获取优惠券数量
     * @return \think\response\Json
     */
    public function getCouponCount() {
        $id = input('param.id');
        $count = CouponRule::where(['id' => $id])->value('surplus_number');
        return json(['code' => 0, 'msg' => 'success', 'data' => $count?$count:0]);
    }

}