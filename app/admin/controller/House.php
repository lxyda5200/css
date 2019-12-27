<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/18
 * Time: 9:16
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\House as houseModel;
use app\admin\model\HouseShort as houseShortModel;
class House extends Admin
{

    /*
     * 待审核房源
     * */
    public function dsh_list(){
        $model = new houseModel();

        $list = $model->where('status',2)->order('create_time','desc')->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->house_img = Db::name('house_img')->where('house_id',$v->id)->find();
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
            $v->area_name1 = Db::name('area')->where('id',$v->area_id1)->value('area_name1');
            $v->area_name2 = Db::name('area')->where('id',$v->area_id2)->value('area_name2');
        }

        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 长租房源
     * */
    public function long_list(){
        $param = $this->request->param();

        $model = new houseModel();

        if (!empty($param['title'])){
            $where['title'] = ['like',"%$param[title]%"];
        }

        if (!empty($param['renting_status'])){
            $where['renting_status'] = ['eq',$param['renting_status']];
        }

        $list = $model
            ->where('status','in','3,5')
            ->where('is_delete',0)
            ->where($where)
            ->order('create_time','desc')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->title = str_replace($param['title'],'<font color="red">'.$param['title'].'</font>',$v->title);
            $v->house_img = Db::name('house_img')->where('house_id',$v->id)->find();
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
            $sale = Db::name('sale')->where('sale_id',$v->sale_id)->find();
            $v->shop_name = Db::name('shop_info')->where('id',$sale['shop_id'])->value('shop_name');
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //待审核房源详情
    public function detail(){
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        #$model = new houseModel();

        //非提交操作
        $data = Db::view('house')
            ->view('house_type','name as type_name','house_type.id = house.house_type_id','left')
            ->view('subway_lines','lines_name','subway_lines.id = house.lines_id','left')
            ->view('subway_station','station_name','subway_station.id = house.station_id','left')
            ->where('house.id',$id)
            ->find();

        $tag_info = Db::name('house_tag')->where('id','in',$data['tag_id'])->select();
        foreach ($tag_info as $k=>$v){
            $tag_str .= $v['tag_name'].',';
        }
        $data['tag_str'] = trim($tag_str,',');

        $room_config_info = Db::name('room_config')->where('id','in',$data['room_config_id'])->select();
        foreach ($room_config_info as $k1=>$v1){
            $room_config_str .= $v1['name'].',';
        }
        $data['room_config_str'] = trim($room_config_str,',');

        $house_img = Db::name('house_img')->where('house_id',$data['id'])->select();
        $this->assign('house_img',$house_img);

        if(!empty($data)) {
            $this->assign('data',$data);
            $this->assign('title','房源详情');
            return $this->fetch();
        } else {
            return $this->error('id不正确');
        }

    }

    //长租房源详情
    public function long_detail(){
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

        //非提交操作
        $data = Db::view('house')
            ->view('house_type','name as type_name','house_type.id = house.house_type_id','left')
            ->view('subway_lines','lines_name','subway_lines.id = house.lines_id','left')
            ->view('subway_station','station_name','subway_station.id = house.station_id','left')
            ->where('house.id',$id)
            ->find();

        $tag_info = Db::name('house_tag')->where('id','in',$data['tag_id'])->select();
        foreach ($tag_info as $k=>$v){
            $tag_str .= $v['tag_name'].',';
        }
        $data['tag_str'] = trim($tag_str,',');

        $room_config_info = Db::name('room_config')->where('id','in',$data['room_config_id'])->select();
        foreach ($room_config_info as $k1=>$v1){
            $room_config_str .= $v1['name'].',';
        }
        $data['room_config_str'] = trim($room_config_str,',');

        $house_img = Db::name('house_img')->where('house_id',$data['id'])->select();
        $this->assign('house_img',$house_img);

        if ($data['renting_status'] == 2){
            $order_info = Db::name('long_order')->where('house_id',$id)->where('status',2)->find();
            $this->assign('order_info',$order_info);
        }elseif ($data['renting_status'] == 3){
            $order_info = Db::name('long_order')->where('house_id',$id)->where('status',2)->where('renting_status',1)->find();
            $this->assign('order_info',$order_info);
        }

        if(!empty($data)) {
            $this->assign('data',$data);
            $this->assign('title','房源详情');
            return $this->fetch();
        } else {
            return $this->error('id不正确');
        }

    }

    /*
     * 上架/下架 审核
     * */
    public function status()
    {
        //获取文件id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        if($id > 0) {
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                $status = $post['status'];
                if(false == Db::name('house')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    if ($status == 4) {
                        return $this->success('操作成功','admin/house/dsh_list');
                    }else{
                        return $this->success('操作成功','admin/house/long_list');
                    }

                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 长租房编辑
     * */
    public function publish(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([

                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $short = $model->where('id',$id)->find();
                if(empty($short)) {
                    return $this->error('id不正确');
                }

                $xiaoqu = Db::name('house_xiaoqu')->where('id',$post['xiaoqu_id'])->find();
                $post['city_id'] = $xiaoqu['city_id'];
                $post['area_id1'] = $xiaoqu['area_id1'];
                $post['area_id2'] = $xiaoqu['area_id2'];
                $post['xiaoqu_name'] = $xiaoqu['xiaoqu_name'];
                $post['address'] = $xiaoqu['address'];
                if ($post['is_subway']==0){
                    $post['lines_id'] = 0;
                    $post['station_id'] = 0;

                }
                $post['tag_id'] = implode(',',$post['tag_id']);
                $post['room_config_id'] = implode(',',$post['room_config_id']);

                if(false === $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {

                    Db::name('house_img')->where('house_id',$id)->delete();
                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['house_id'] = $model->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('house_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/house/long_list');
                }
            } else {
                //非提交操作
                $data = Db::view('house')
                    ->where('id',$id)
                    ->find();

                $house_img = Db::name('house_img')->where('house_id',$id)->select();
                $this->assign('house_img',$house_img);

                $house_type = Db::name('house_type')->where('type',1)->select();
                $this->assign('house_type',$house_type);

                $house_tag = Db::name('house_tag')->where('type',1)->select();
                $this->assign('house_tag',$house_tag);

                $house_room_config = Db::name('room_config')->where('type',1)->select();
                $this->assign('house_room_config',$house_room_config);


                $house_xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('house_xiaoqu',$house_xiaoqu);

                $lines = Db::name('subway_lines')->select();
                $this->assign('lines',$lines);

                $station = Db::name('subway_station')->select();
                $this->assign('station',$station);



                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','编辑长租');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     * 改为短租房
     * */
    public function edit_short()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseModel();
        $shortModel = new houseShortModel();
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {
                //是提交操作
                $post = $this->request->post();
                //验证  唯一规则： 表名，字段名，排除主键值，主键名
                $validate = new \think\Validate([

                ]);
                //验证部分数据合法性
                if (!$validate->check($post)) {
                    $this->error('提交失败：' . $validate->getError());
                }
                //验证菜单是否存在
                $house = $model->where('id',$id)->find();
                if(empty($house)) {
                    return $this->error('id不正确');
                }


                $post['house_id'] = $id;
                $xiaoqu = Db::name('house_xiaoqu')->where('id',$post['xiaoqu_id'])->find();
                $post['city_id'] = $xiaoqu['city_id'];
                $post['area_id1'] = $xiaoqu['area_id1'];
                $post['area_id2'] = $xiaoqu['area_id2'];
                $post['xiaoqu_name'] = $xiaoqu['xiaoqu_name'];
                $post['address'] = $xiaoqu['address'];
                if ($post['is_subway']==0){
                    $post['lines_id'] = 0;
                    $post['station_id'] = 0;

                }
                $post['tag_id'] = implode(',',$post['tag_id']);
                $post['room_config_id'] = implode(',',$post['room_config_id']);
                $post['traffic_tag_id'] = implode(',',$post['traffic_tag_id']);
                #dump($post);die;
                unset($post['id']);


                if(false == $shortModel->allowField(true)->save($post)) {
                    return $this->error('修改失败');
                } else {

                    Db::name('house')->where('id',$id)->setField('is_delete',1);

                    $post['short_id'] = $shortModel->id;
                    Db::name('short_rule')->strict(false)->insert($post);

                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['short_id'] = $shortModel->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('house_short_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/house/long_list');
                }
            } else {
                //非提交操作
                $data = Db::name('house')->where('id',$id)->find();

                $house_img = Db::name('house_img')->where('house_id',$id)->select();
                $this->assign('house_img',$house_img);

                $house_type = Db::name('house_type')->where('type',2)->select();
                $this->assign('house_type',$house_type);

                $house_tag = Db::name('house_tag')->where('type',2)->select();
                $this->assign('house_tag',$house_tag);

                $house_room_config = Db::name('room_config')->where('type',2)->select();
                $this->assign('house_room_config',$house_room_config);

                $short_traffic_tag = Db::name('short_traffic_tag')->where('status',1)->select();
                $this->assign('short_traffic_tag',$short_traffic_tag);

                $house_xiaoqu = Db::name('house_xiaoqu')->select();
                $this->assign('house_xiaoqu',$house_xiaoqu);

                $lines = Db::name('subway_lines')->select();
                $this->assign('lines',$lines);

                $station = Db::name('subway_station')->select();
                $this->assign('station',$station);

                if(!empty($data)) {
                    $this->assign('data',$data);
                    $this->assign('title','变为短租');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        } else {
            return $this->error('id不正确');
        }

    }

    public function is_recommend()
    {
        if($this->request->isPost()){
            $post = $this->request->post();
            if(false == Db::name('house')->where('id',$post['id'])->update(['is_recommend'=>$post['is_recommend']])) {
                return $this->error('设置失败');
            } else {
                addlog($post['id']);//写入日志
                return $this->success('设置成功','admin/house/long_list');
            }
        }
    }

    /*
    * 长租订单列表
    * */
    public function order_list(){

        $param = $this->request->param();

        if (!empty($param['status'])) {
            $where['long_order.status'] = ['eq',$param['status']];
        }

        $list = Db::name('long_order')
            ->join('sale','sale.sale_id = long_order.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->join('user','user.user_id = long_order.user_id','left')
            ->where($where)
            ->field('long_order.*,sale.nickname as sale_name,user.nickname as user_name,shop_info.shop_name')
            ->order('long_order.create_time','desc')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 长租订单详情
     * */
    public function order_detail(){
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        //是正常添加操作
        if($id > 0) {
            //是修改操作
            if($this->request->isPost()) {

            } else {
                //非提交操作
                $data = Db::view('long_order')
                    ->view('user','nickname,mobile','user.user_id = long_order.user_id','left')
                    ->view('shop_info','shop_name','shop_info.id = long_order.shop_id','left')
                    ->view('property','nickname as property_name','property.property_id = long_order.property_id','left')
                    ->view('house','title,rent','house.id = long_order.house_id','left')
                    ->view('sale','nickname as sale_name','sale.sale_id = long_order.sale_id','left')
                    ->where('long_order.id',$id)
                    ->find();

                $house_img = Db::name('house_img')->where('house_id',$data['house_id'])->select();
                $this->assign('house_img',$house_img);

                $rent_info = Db::name('long_rent_record')->where('order_id',$id)->select();
                $this->assign('rent_info',$rent_info);

                $sum_money = Db::name('long_rent_record')->where('order_id',$id)->sum('money');
                $this->assign('sum_money',$sum_money);

                if(!empty($data)) {

                    $this->assign('data',$data);

                    $this->assign('title','订单详情');
                    return $this->fetch();
                } else {
                    return $this->error('id不正确');
                }
            }
        }
    }
}