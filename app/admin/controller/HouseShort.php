<?php
/**
 * Created by PhpStorm.
 * User: long
 * Date: 2018/10/19
 * Time: 11:20
 */

namespace app\admin\controller;

use think\Db;
use app\admin\model\HouseShort as houseShortModel;
use app\admin\model\House as houseModel;
class HouseShort extends Admin
{


    public function index(){
        $model = new houseShortModel();

        $list = $model->paginate(15,false);

        foreach ($list as $k=>$v){
            $v->house_img = Db::name('house_short_img')->where('short_id',$v->id)->find();
            $v->city_name = Db::name('city')->where('id',$v->city_id)->value('city_name');
            $v->area_name1 = Db::name('area')->where('id',$v->area_id1)->value('area_name1');
            $v->area_name2 = Db::name('area')->where('id',$v->area_id2)->value('area_name2');
        }

        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 编辑短租房源
     * */
    public function publish()
    {
        //获取菜单id
        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        $model = new houseShortModel();
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

                if(false === $model->allowField(true)->save($post,['id'=>$id])) {
                    return $this->error('修改失败');
                } else {

                    if (!Db::name('short_rule')->where('short_id',$id)->count()) {
                        $post['short_id'] = $id;
                        Db::name('short_rule')->strict(false)->insert($post);
                    }else{
                        Db::name('short_rule')->where('short_id',$id)->strict(false)->update($post);
                    }


                    Db::name('house_short_img')->where('short_id',$id)->delete();
                    $img_url = $post['img_url'];
                    foreach ($img_url as $k=>$v){
                        $img_data[$k]['short_id'] = $model->id;
                        $img_data[$k]['img_url'] = $v;
                    }

                    Db::name('house_short_img')->insertAll($img_data);

                    addlog($id);//写入日志
                    return $this->success('修改成功','admin/house_short/index');
                }
            } else {
                //非提交操作
                $data = Db::view('house_short')
                    ->view('short_rule','fksz,yfff,xxsyj,ewfy,bdgh,jdsj,zsrz,rzsj,tfsj,zdrzts,short_id','short_rule.short_id = house_short.id','left')
                    ->where('house_short.id',$id)
                    ->find();

                $house_short_img = Db::name('house_short_img')->where('short_id',$id)->select();
                $this->assign('house_short_img',$house_short_img);

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
                    $this->assign('title','编辑短租');
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
                if(false == Db::name('house_short')->where('id',$id)->update(['status'=>$status])) {
                    return $this->error('设置失败');
                } else {
                    addlog($id);//写入日志
                    return $this->success('操作成功','admin/house_short/index');

                }
            }
        } else {
            return $this->error('id不正确');
        }
    }

    /*
     *
     * 短租订单列表*/
    public function order_list(){
        $param = $this->request->param();

        $list = Db::name('short_order')
            ->join('sale','sale.sale_id = short_order.sale_id','left')
            ->join('shop_info','shop_info.id = sale.shop_id','left')
            ->join('user','user.user_id = short_order.user_id','left')
            ->field('short_order.*,sale.nickname as sale_name,user.nickname as user_name,shop_info.shop_name')
            ->paginate(15,false);

        foreach ($list as $k=>$v){
            $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }

        $this->assign('param',$param);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
     * 短租订单详情
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
                $data = Db::view('short_order')
                    ->view('user','nickname,mobile','user.user_id = short_order.user_id','left')
                    ->view('shop_info','shop_name','shop_info.id = short_order.shop_id','left')
                    ->view('house_short','title,rent','house_short.id = short_order.short_id','left')
                    ->view('sale','nickname as sale_name','sale.sale_id = short_order.sale_id','left')
                    ->where('short_order.id',$id)
                    ->find();

                $house_img = Db::name('house_short_img')->where('short_id',$data['short_id'])->select();
                $this->assign('house_img',$house_img);

                $occupant_info = Db::name('short_occupant')->where('id','in',$data['occupant_id'])->select();
                $this->assign('occupant_info',$occupant_info);

                /*$sum_money = Db::name('long_rent_record')->where('order_id',$id)->sum('money');
                $this->assign('sum_money',$sum_money);*/

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