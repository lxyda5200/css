<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2019/1/8
 * Time: 16:15
 */

namespace app\mainstore\controller;


use app\common\controller\Base;
use app\mainstore\model\ProductOrder;
use think\Config;
use think\Db;
use think\Exception;
use think\Loader;
use think\response\Json;
use app\user\controller\AliPay;
use app\user\controller\WxPay;
class Order extends Base
{

//    /**
//     * 订单列表  普通订单  待发货 已发货 已完成
//     */
//    public function orderList(){
//        try{
//            //token 验证
//            $store_info = \app\store\common\Store::checkToken();
//            if ($store_info instanceof json){
//                return $store_info;
//            }
//
//            $page = input('page') ? intval(input('page')) : 1 ;
//            $size = input('size') ? intval(input('size')) : 10 ;
//            $status = input('status');
//            $address_status = input('address_status');  //0待处理
//            $address_status = isset($address_status) ? intval($address_status) : 1 ;
//            //订单状态 1待付款 2待团购 3待发货 4待收货 5待评价 6已完成 -1已取消
//
//            $where['distribution_mode'] = ['eq',2];
//
//            $where['store_id'] = $store_info['id'];
//
//            $data['daifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();
//
//            $data['yifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',4)->count();
//
//            $data['yiwancheng_number'] = Db::name('product_order')->where($where)->where('order_status','>',4)->count();
//
//            switch ($status){
//                case 1:
//                    $where['order_status'] = ['eq',3];
//                    $where['address_status'] = ['eq',$address_status];
//                    break;
//                case 2:
//                    $where['order_status'] = ['eq',4];
//                    break;
//                case 3:
//                    $where['order_status'] = ['gt',4];
//                    break;
//                default:
//                    $where['order_status'] = ['gt',2];
//                    break;
//            }
//
//            $total = Db::name('product_order')->where($where)->count();
//
//            $list = Db::name('product_order')
//                ->field('id,order_no,create_time,order_status,shouhuo_username,shouhuo_mobile,total_freight,address_status,total_platform_price')
//                ->where($where)
//                ->page($page,$size)
//                ->order('create_time','desc')
//                ->select();
//
//            foreach ($list as $k=>$v){
//                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
//                $product = Db::name('product_order_detail')
//                    ->field('cover,product_name,number,price,platform_price')
//                    ->where('product_order_detail.order_id',$v['id'])
//                    ->select();
//
//                $pay_money = 0;
//                foreach ($product as $k2=>$v2){
//                    $pay_money += $v2['number'] * $v2['price'];
//                }
//
//                $list[$k]['pay_money'] = $pay_money;
//
//                $list[$k]['product'] = $product;
//            }
//
//            $data['total'] = $total;
//            $data['max_page'] = ceil($total/$size);
//            $data['list'] = $list;
//
//            return \json(self::callback(1,'',$data));
//
//        }catch (\Exception $e){
//            return json(self::callback(0,$e->getMessage()));
//        }
//    }

    /**
     * 订单列表  普通订单  待发货 已发货 已完成 （2019.7.23改）
     */
    public function orderList_old2019910(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');
            $address_status = input('address_status');  //0待处理
            $address_status = isset($address_status) ? intval($address_status) : 1 ;
            //订单状态 1待付款 2待团购 3待发货 4待收货 5待评价 6已完成 -1已取消

            $where['distribution_mode'] = ['eq',2];

            $where['store_id'] = $store_info['id'];

            $where1['product_order.distribution_mode'] = ['eq',2]; //统计


            $data['daifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();

            $data['yifahuo_number'] = Db::name('product_order')->where($where)->where('order_status',4)->count();

            $data['yiwancheng_number'] = Db::name('product_order')->where($where)->where('order_status','>',4)->count();

            switch ($status){
                case 1:
                    $where['order_status'] = ['eq',3];
                    $where['address_status'] = ['eq',$address_status];
                    $where1['product_order.order_status'] = ['eq',3];
                    $where1['product_order.address_status'] = ['eq',$address_status];

                    break;
                case 2:
                    $where['order_status'] = ['eq',4];
                    $where1['product_order.order_status'] = ['eq',4];
                    break;
                case 3:
                    $where['order_status'] = ['gt',4];
                    $where1['product_order.order_status'] = ['gt',4];
                    break;
                default:
                    $where['order_status'] = ['gt',2];
                    $where1['product_order.order_status'] = ['gt',2];
                    break;
            }
//--------------------------------------
            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where($where1) //加上条件
                ->where('product_order_detail.is_shouhou','neq',1) //过滤掉售后订单
                ->group('product_order_detail.order_id')
                ->column('product_order_detail.order_id');
            $total = count($order_id);

//-------------------------------------

//            $total = Db::name('product_order')->where($where)->count();

            $list = Db::name('product_order')
                ->field('id,order_no,create_time,order_status,shouhuo_username,shouhuo_mobile,total_freight,address_status,total_platform_price')
                ->where('id','in',$order_id)
                ->where($where)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $product = Db::name('product_order_detail')
                    ->field('cover,product_name,number,price,platform_price')
                    ->where('product_order_detail.order_id',$v['id'])
                    ->select();

                $pay_money = 0;
                foreach ($product as $k2=>$v2){
                    $pay_money += $v2['number'] * $v2['price'];
                }

                $list[$k]['pay_money'] = $pay_money;

                $list[$k]['product'] = $product;
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }


    /**
     * 订单列表
     */
    public function orderList(){
        try{
            //token 验证
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id') ? intval(input('id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;
            $status = input('status');
            $keywords = input('keywords','','addslashes,strip_tags,trim');
            if(!$id ){
                throw new \Exception('参数错误!');
            }
            //订单状态 -1已取消 0全部订单 1待付款 3待发货 4待收货  5待评价  6已完成 7售后

            switch ($status){
                case -1:
                    $where['order_status'] = ['eq',-1];
                    $where1['product_order.order_status'] = ['eq',-1];
                    break;
                case 0:
                    break;
                case 1:
                    $where['order_status'] = ['eq',1];
                    $where1['product_order.order_status'] = ['eq',1];
                    break;
                case 3:
                    $where['order_status'] = ['eq',3];
                    $where1['product_order.order_status'] = ['eq',3];
                    break;
                case 4:
                    $where['order_status'] = ['eq',4];
                    $where1['product_order.order_status'] = ['eq',4];
                    break;
                case 5:
                    $where['order_status'] = ['eq',5];
                    $where1['product_order.order_status'] = ['eq',5];
                    break;
                case 6:
                    $where['order_status'] = ['eq',6];
                    $where1['product_order.order_status'] = ['eq',6];
                    break;
                default:
                    throw new \Exception('状态错误!');
                    break;
            }

            if($status!=7){
                $where2['product_order_detail.is_shouhou'] = ['neq',1];
            }else{
                $where2['product_order_detail.is_shouhou'] = ['eq',1];
            }
            if (!empty($keywords)) {
                if(substr_count($keywords,'%') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词%'));
                if(substr_count($keywords,'_') == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_'));
                if((substr_count($keywords,'_') + substr_count($keywords,'%')) == strlen($keywords))return \json(self::callback(0,'关键词不能包含关键词_%'));
                $where1['product_order.order_no|product_order.shouhuo_username|product_order.shouhuo_mobile|product_order.shouhuo_address|product_order_detail.product_id|product_order_detail.product_name'] = ['like', "%$keywords%"];
            }
            $where['store_id'] = ['eq',$id];
            $where1['product_order.store_id'] = ['eq',$id];
            $total=Db::view('product_order_detail','id,order_id')
                ->view('product_order','order_no','product_order_detail.order_id = product_order.id','left')
                ->where($where1)
                ->where($where2)
                ->count();
            $list = Db::view('product_order_detail','id,order_id,product_id,specs_id,cover,product_name,product_specs,number,price,store_coupon_money,coupon_money,product_coupon_money,realpay_money,freight')
                ->view('product_order','order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,pay_money,order_status,create_time,total_freight,finish_time','product_order_detail.order_id = product_order.id','left')
                ->where($where1)
                ->where($where2)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'查询成功!',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 自取订单列表  待取货 已取货
     */
    public function ziquOrderList(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $status = input('status');  //	状态 1待取货 2已取货 0全部

            $where['distribution_mode'] = ['eq',1];

            $where['store_id'] = $store_info['id'];

            $where1['product_order.distribution_mode'] = ['eq',1];//统计
            $data['daiquhuo_number'] = Db::name('product_order')->where($where)->where('order_status',3)->count();

            $data['yiquhuo_number'] = Db::name('product_order')->where($where)->where('order_status','>',3)->count();
            switch ($status){
                case 1:
                    $where['order_status'] = ['eq',3];
                    $where1['product_order.order_status'] = ['eq',3];
                    break;
                case 2:
                    $where['order_status'] = ['gt',3];
                    $where1['product_order.order_status'] = ['gt',3];
                    break;
                default:
                    $where['order_status'] = ['gt',2];
                    $where1['product_order.order_status'] = ['gt',2];
                    break;
            }
//----------------------
            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where($where1) //加上条件
                ->where('product_order_detail.is_shouhou','neq',1) //过滤掉售后订单
                ->group('product_order_detail.order_id')
                ->column('product_order_detail.order_id');
            $total = count($order_id);


//--------------------------

//            $total = Db::name('product_order')->where($where)->count();

            $list = Db::name('product_order')
                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,total_platform_price')
                ->where($where)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $list[$k]['product'] = Db::name('product_order_detail')
                    ->field('cover,product_name,platform_price')
                    ->where('order_id',$v['id'])
                    ->select();
            }
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));

        }catch (\Exception $e){
            return json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 订单详情
     */
    public function orderDetail(){
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $order_id = input('order_id');

            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $product_order = Db::name('product_order')
                ->field('id,create_time,order_no,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,address_status,logistics_info,total_freight,total_platform_price')
                ->where('id',$order_id)
                ->where('store_id',$store_info['id'])
                ->find();

            if (!$product_order) {
                throw new \Exception('订单不存在');
            }

            $product = Db::name('product_order_detail')
                ->field('cover,product_name,order_id,specs_id,number,price,freight,is_comment,platform_price')
                ->where('order_id',$product_order['id'])
                ->select();

            foreach ($product as $k=>$v){
                if ($v['is_comment'] == 1){
                    $comment = Db::name('product_comment')
                        ->field('id,content,create_time')
                        ->where('order_id',$v['order_id'])
                        ->where('specs_id',$v['specs_id'])
                        ->find();

                    $comment['comment_img'] = Db::name('product_comment_img')->where('comment_id',$comment['id'])->column('img_url');

                    $product[$k]['comment']  = $comment;
                }
            }

            $product_order['product'] = $product;

            return \json(self::callback(1,'',$product_order));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 发货
     */
    public function fahuo(){
        #throw new \Exception('禁止发货');
        try{
            //token 验证
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $order_id = input('order_id') ? intval(input('order_id')) : 0 ;
//            $logistics_info = input('logistics_info');  //物流信息
            $logistics_number = input('logistics_number');  //物流单号
            $logistics_company = input('logistics_company');  //物流公司

            if (!$order_id ){
                return \json(self::callback(0,'参数错误'),400);
            }

            $model = new ProductOrder();
            $product_order = $model->where('id',$order_id)->where('store_id',$store_info['id'])->where('')->find();

            if (!$product_order) {
                throw new \Exception('订单不存在');
            }
            if ($product_order->order_status != 3){
                throw new \Exception('该订单不支持该操作');
            }
            $product_order->order_status = 4;
            $product_order->fahuo_time = time();
            $product_order->operate_time = time();
            if ($logistics_number && $logistics_company) {
                $product_order->logistics_number = $logistics_number;
                $product_order->logistics_company = $logistics_company;
            }
            $res = $product_order->save();
            if (!$res){
                throw new \Exception('操作失败');
            }
            return \json(self::callback(1,''));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 到店订单列表
     */
    public function maidanOrderList(){
        try{
            $store_info = \app\mainstore\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $id = input('id') ? intval(input('id')) : 0 ;
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 15 ;

            if(!empty($id)){
                $where['store.id'] = ['eq', $id];
            }else{
                $where['store.p_id'] = ['eq', $store_info['id']];
            }
            $total = Db::view('maidan_order','id')
                ->view('store','store_name','maidan_order.store_id = store.id','left')
                ->where($where)
                ->where('maidan_order.pay_time','gt',0)
                ->count();
            $list =  Db::view('maidan_order','id,order_sn,pay_time,user_id,price_yj,discount,price_maidan,coupon_id,coupon_money')
                ->view('store','store_name','maidan_order.store_id = store.id','left')
                ->view('coupon','coupon_name','maidan_order.coupon_id = coupon.id','left')
                ->view('coupon_rule','type','coupon.coupon_id = coupon_rule.id','left')
                ->where($where)
                ->where('maidan_order.pay_time','gt',0)
                ->page($page,$size)
                ->order('maidan_order.pay_time','desc')
                ->select();
            foreach ($list as &$v){
                if($v['type']==2 || $v['type']==3){
                    $v['store_coupon_money']=$v['coupon_money'];
                    $v['coupon_money']=0;
                }else{
                    $v['store_coupon_money']=0;
                }
            }
            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;
            return \json(self::callback(1,'查询成功!',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /*
PHPExcel
*/
    public function excel(){
        try{
//        $store_info = \app\mainstore\common\Store::checkToken();
//        if ($store_info instanceof json){
//            return $store_info;
//        }
        $type = input('type',0,'intval'); //0全部 1数组
        $id = input('id',0,'intval');//店铺id
        $status = input('status',0,'intval');//订单状态 0全部订单 1待付款 2待发货 3待收货 4已完成 5售后
        $ids = input('ids');//id数组
            $ids= explode(',',$ids);

//        $ids = $this->request->post('ids/a');
        if(!$id){
            return \json(self::callback(0,'参数错误!'));
        }
        Loader::import('PHPExcel.Classes.PHPExcel'); //thinkphp5加载类库
        $objPHPExcel = new \PHPExcel();  //实例化PHPExcel类，
        $objSheet = $objPHPExcel->getActiveSheet();  //获取当前活动的sheet对象

        $objSheet->setTitle("order");  //给当前活动sheet起个名称
        /*字符串方式填充数据，开发中可以将数据库取出的数据根据具体情况遍历填充*/
        $objSheet->setCellValue("A1","序号")->setCellValue("B1","订单编号")->setCellValue("C1","商品编号")->setCellValue("D1","商品图片")->setCellValue("E1","商品名称")->setCellValue("F1","数量")->setCellValue("G1","商品规格")->setCellValue("H1","销售额")->setCellValue("I1","店铺优惠")->setCellValue("J1","平台补贴")->setCellValue("K1","实收款")->setCellValue("L1","买家信息")->setCellValue("M1","下单时间")->setCellValue("N1","完成时间")->setCellValue("O1","订单状态");  //填充数据
        // $objSheet->setCellValue("A2","张三")->setCellValue("B2","3434346354634563443634634634563")->setCellValue("C2","一班");  //填充数据
        //$objSheet->setCellValue("A2","张三")->setCellValueExplicit("B2","123216785321321321312",\PHPExcel_Cell_DataType::TYPE_STRING)->setCellValue("C2","一班");//填充数据时添加此方法，并且使用getNumberFormat方法和setFormatCode方法设置，可以让如订单号等长串数字不使用科学计数法

            $arr = $this->getorderData($id,$ids,$status,$type);

        $objSheet->fromArray($arr);  //填充数组数据，较为消耗资源且阅读不便，不推荐

        /*样式配置信息--方法配置*/
        //$objSheet->mergecells("B2:F2");  //合并单元格
        $objSheet->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//设置excel文件默认水平垂直方向居中,垂直setVertical，水平setHorizontal,因为是基于thinkPHP5所以这里PHPExcel_Style_Alignment前使用"\"引入
        $objSheet->getDefaultStyle()->getFont()->setSize(14)->setName("微软雅黑");//设置所有默认字体大小和格式
        //$objSheet->getStyle("B2:F2")->getFont()->setSize(20)->setBold(true);//设置指定范围内字体大小和加粗
        //$objSheet->getDefaultRowDimension()->setRowHeight(33);//设置所有行默认行高
        //$objSheet->getRowDimension(2)->setRowHeight(50);//设置指定行（第二行）行高
        //$objSheet->getStyle("B2:F2")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('EEC591');//指定填充背景颜色，不需要加"#"定义样式数组，字体，背景，边框等都此方法设置，这里展示边框
        //$objSheet->getStyle("B3")->getAlignment()->setWrapText(true);//设置文字自动换行，要用getStyle()方法选中范围，同时要在内容中添加"\n",而且该内容要用双引号才会解析
        //$objSheet->getStyle("E")->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);//设置某列单元格格式为文本格式，便于禁用科学计数法

        /*数组配置*/
        $styleArray = array(
            'borders' => array(
                'outline' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THICK,
                    'color' => array('rgb' => 'EE0000'),
                ),
            ),
        );
        //$objSheet->getStyle("B3:G3")->applyFromArray($styleArray);//设置指定区域的边框，设置边框必须要使用getStyle()选中范围

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');//生成objWriter对象，Excel2007(xlsx)为指定格式，还有Excel5表示Excel2003(xls)

            /*浏览器查看，浏览器保存*/
            browser_excel('Excel2007','order.xlsx');//输出到浏览器,参数1位Excel类型可为Excel5和Excel2007，第二个参数为文件名(需加后缀名)，此方法为自定义
            $objWriter->save("php://output");  //save()里可以直接填写保存路径
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    //查询excel数据
    public function getorderData($id,$ids,$status,$type){
        try{
        if(!$id ){
            throw new \Exception('参数错误!');
        }
            //订单状态 -1已取消 0全部订单 1待付款 3待发货 4待收货  5待评价  6已完成 7售后

            switch ($status){
                case -1:
                    $where['order_status'] = ['eq',-1];
                    $where1['product_order.order_status'] = ['eq',-1];
                    break;
                case 0:
                    break;
                case 1:
                    $where['order_status'] = ['eq',1];
                    $where1['product_order.order_status'] = ['eq',1];
                    break;
                case 3:
                    $where['order_status'] = ['eq',3];
                    $where1['product_order.order_status'] = ['eq',3];
                    break;
                case 4:
                    $where['order_status'] = ['eq',4];
                    $where1['product_order.order_status'] = ['eq',4];
                    break;
                case 5:
                    $where['order_status'] = ['eq',5];
                    $where1['product_order.order_status'] = ['eq',5];
                    break;
                case 6:
                    $where['order_status'] = ['eq',6];
                    $where1['product_order.order_status'] = ['eq',6];
                    break;
                default:
                    throw new \Exception('状态错误!');
                    break;
            }
        //查询部分
        if($type==1){
            $where1['product_order_detail.id'] = ['in',$ids];
        }
            if($status!=7){
                $where2['product_order_detail.is_shouhou'] = ['neq',1];
            }else{
                $where2['product_order_detail.is_shouhou'] = ['eq',1];
            }
        $where1['product_order.store_id'] = ['eq',$id];

        $list = Db::view('product_order_detail','id,order_id,product_id,specs_id,cover,product_name,product_specs,number,price,store_coupon_money,coupon_money,product_coupon_money,realpay_money,freight')
            ->view('product_order','order_no,shouhuo_username,shouhuo_mobile,shouhuo_address,pay_money,order_status,create_time,total_freight,finish_time','product_order_detail.order_id = product_order.id','left')
            ->where($where1)
            ->where($where2)
            ->order('create_time','desc')
            ->select();

        $data = [];
        for ($i=0;$i<15;$i++){
            $data[0][] = '';
        }
        $len = 0;

        $web_path = Config::get('web_path');

        foreach($list as $k=>&$v){
            ++$len;
            $data[$len][] = $len;
            $data[$len][] = $v['order_no'];
            $data[$len][] = $v['product_id'];
            $data[$len][] = $web_path.$v['cover'];
            $data[$len][] = $v['product_name'];
            $data[$len][] = $v['number'];
            $data[$len][] = $v['product_specs'];
            $data[$len][] =" ¥ ".$v['number']* $v['price']."元";
            $data[$len][] = " ¥ ".($v['store_coupon_money']+$v['product_coupon_money'])."元";
            $data[$len][] = " ¥ ".$v['coupon_money']."元";
            $data[$len][] = " ¥ ".$v['pay_money']."(含运费:".$v['total_freight'].")";
            $data[$len][] = "用户:".$v['shouhuo_username'].";手机号:".$v['shouhuo_mobile'].";收货地址:".$v['shouhuo_address'];
            $data[$len][] = date("Y-m-d H:i:s",$v['create_time']);
            if($v['finish_time']>0){
                $data[$len][] = date("Y-m-d H:i:s",$v['finish_time']);
            }else{
                $data[$len][] = '';
            }
            switch ($v['order_status']){
                case -1:
                    $data[$len][] ="已取消";
                    break;
                case 1:
                    $data[$len][] ="待付款";
                    break;
                case 3:
                    $data[$len][] ="待发货";
                    break;
                case 4:
                    $data[$len][] ="待收货";
                    break;
                case 5:
                    $data[$len][] ="待评价";
                    break;
                case 6:
                    $data[$len][] ="已完成";
                    break;
                case 7:
                    $data[$len][] ="售后";
                    break;
                default:
                    $data[$len][] ="未知";
                    break;
            }
        }
        return $data;
    }catch (\Exception $e){
return \json(self::callback(0,$e->getMessage()));
}
    }
    /**
     * 售后订单列表
     */
    public function shouhouOrderList(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;

            $order_id = Db::name('product_order_detail')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where('product_order_detail.is_shouhou',1)
                ->group('order_id')
                ->column('order_id');

            $total = count($order_id);

            $list = Db::name('product_order')
                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile')
                ->where('id','in',$order_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();

            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->count();
                $list[$k]['product'] = Db::name('product_order_detail')
                    ->field('cover,product_name')
                    ->where('is_shouhou',1)
                    ->where('order_id',$v['id'])
                    ->select();
            }

            $data['total'] = $total;
            $data['max_page'] = ceil($total/$size);
            $data['list'] = $list;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }

    /**
     * 售后订单详情
     */
    public function shouhouOrderDetail(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }

            $order_id = input('order_id');

            if (!$order_id) {
                return \json(self::callback(0,'参数错误'),400);
            }

            $product_order = Db::name('product_order')
                ->field('id,create_time,order_no,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address')
                ->where('id',$order_id)
                ->where('store_id',$store_info['id'])
                ->find();

            if (!$product_order) {
                throw new \Exception('订单不存在');
            }

            $product = Db::name('product_order_detail')
                ->field('cover,product_name,order_id,specs_id,number,price,freight,is_refund')
                ->where('order_id',$product_order['id'])
                ->where('is_shouhou',1)
                ->select();


            foreach ($product as $k=>$v){
                $shouhou = Db::name('product_shouhou')
                    ->field('id,link_name,link_mobile,description,refuse_description,create_time')
                    ->where('order_id',$v['order_id'])
                    ->where('specs_id',$v['specs_id'])
                    ->find();

                $shouhou['shouhou_img'] = Db::name('product_shouhou_img')->where('shouhou_id',$shouhou['id'])->column('img_url');

                $product[$k]['shouhou']  = $shouhou;
            }

            $product_order['product'] = $product;

            return \json(self::callback(1,'',$product_order));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }

    /**
     * 售后
     */
    public function shouhou(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;

            }

            $refuse_description = input('refuse_description');
            $specs_id = input('specs_id');
            $order_id = input('order_id');
            $is_refund = input('is_refund');
            if (!$specs_id || !$order_id || !$is_refund){
                return \json(self::callback(0,'参数错误'),400);
            }

            $order_info = Db::name('product_order')->where('id',$order_id)->find();
            $order_product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();

            Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->setField('is_refund',$is_refund);

            if ($is_refund == 1){

                $product_total_price = $order_product['number'] * $order_product['price'];
                //实际退款金额 = 退款单个商品金额 - 优惠券金额 * (退款单个商品金额/总订单金额) - 运费
                $refund_money = $product_total_price - $order_info['coupon_money'] * ($product_total_price/$order_info['pay_money']) - $order_product['freight'];

                $refund_money = round($refund_money,2);

                if ($refund_money > 0) {
                    if ($order_info['pay_type'] == '支付宝') {
                        $alipay = new AliPay();
                        $res = $alipay->alipay_refund($order_info['pay_order_no'],$refund_money);
                    }elseif ($order_info['pay_type'] == '微信'){
                        $wxpay = new WxPay();
                        $total_pay_money = Db::name('product_order')->where('pay_order_no',$order_info['pay_order_no'])->sum('pay_money');
                        $res = $wxpay->wxpay_refund($order_info['pay_order_no'],$total_pay_money,$refund_money);
                    }
                    /*//退款
                    $alipay = new AliPay();
                    $res = $alipay->alipay_refund($order_info['pay_order_no'],$refund_money);*/
                    if ($res){
                        //3退款通知
                        $msg_id = Db::name('user_msg')->insertGetId([
                            'title' => '退款通知',
                            'content' => '您的订单'.$order_info['order_no'].'拼团失败,订单金额已原路返回',
                            'type' => 2,
                            'create_time' => time()
                        ]);

                        Db::name('user_msg_link')->insert([
                            'user_id' => $order_info['user_id'],
                            'msg_id' => $msg_id
                        ]);
                    }
                }


            }else{
                if (!$refuse_description){ return \json(self::callback(0,'拒绝理由不能为空')); }

                Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->update(['refuse_description'=>$refuse_description]);
            }

            return \json(self::callback(1,''));

        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }



    /**
     * 售后列表
     */
    public function shouhouList(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $page = input('page') ? intval(input('page')) : 1 ;
            $size = input('size') ? intval(input('size')) : 10 ;
            $param = $this->request->post();
            $status=$param['status'];
            if(!$status){
                return \json(self::callback(0,'参数错误'),400);
            }
        switch ($status){
                //查询已拒绝
            case -1:
                $where1['refund_status'] = ['eq',-1];
                $where2['product_shouhou.refund_status'] = ['eq',-1];
                break;
                //查询全部
            case 1:
                break;
                //查询待处理
            case 2:
                $where1['refund_status'] = ['eq',1];
                $where2['product_shouhou.refund_status'] = ['eq',1];
                break;
                //查询待发货
            case 3:

                $where1['refund_status'] = ['eq',2];
                $where2['product_shouhou.refund_status'] = ['eq',2];
                break;
                //查询待收货
            case 4:

                $where1['refund_status'] = ['eq',3];
                $where2['product_shouhou.refund_status'] = ['eq',3];
                break;
                //查询已完成
            case 5:
                $where1['refund_status'] = ['eq',4];
                $where2['product_shouhou.refund_status'] = ['eq',4];
                break;
                //报错
            default:
                return \json(self::callback(0,'参数错误'),400);
        }
            $order_id = Db::name('product_order_detail')
                ->join('product_shouhou','product_shouhou.order_id = product_order_detail.order_id AND product_shouhou.specs_id = product_order_detail.specs_id','left')
                ->join('product_order','product_order.id = product_order_detail.order_id','left')
                ->where('product_order.store_id',$store_info['id'])
                ->where('product_order_detail.is_shouhou',1)
                ->where($where2)
                ->group('product_order_detail.order_id')
                ->column('product_order_detail.order_id');
            $total = count($order_id);
            $list = Db::name('product_order')
                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,user_id,coupon_id as user_css_coupon_id,coupon_money,total_freight,pay_type,pay_time,fahuo_time,logistics_info,total_platform_price')
                ->where('id','in',$order_id)
                ->page($page,$size)
                ->order('create_time','desc')
                ->select();
            foreach ($list as $k=>$v){
                $list[$k]['product_number'] = Db::name('product_order_detail')->where('order_id',$v['id'])->where('is_shouhou',1)->count();
                //查询所有的售后订单
                $list[$k]['product_order_detail'] = Db::name('product_order_detail')
                    ->field('order_id,cover,product_name,product_id,specs_id,product_specs,number,price,is_shouhou,is_refund,refund_time,refund_money,realpay_money')
                    ->where('is_shouhou',1)
                    ->where('order_id',$v['id'])
                    ->select();
                //查询所有的售后订单状态
                foreach ( $list[$k]['product_order_detail'] as $k1=>$v1){
                    $list[$k]['product_order_detail'][$k1]['product_shouhou'] = Db::name('product_shouhou')
                        ->field('description,refuse_description,return_mode,create_time,refund_type,goods_status,refund_status,refund_reason,logistics_company,logistics_number')
                        ->where('specs_id',$v1['specs_id'])
                        ->where('order_id',$v1['order_id'])
                        ->where($where1)
                        ->find();
                }
            }
                $data['total'] = $total;
                $data['max_page'] = ceil($total/$size);
                $data['list'] = $list;

            return \json(self::callback(1,'',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }

    }
    /**
     * 处理退款/退货退款
     */
    public function handleshouhou(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $specs_id=$param['specs_id'];
            $order_id=$param['order_id'];
            $refuse_description=$param['refuse_description'];
            $status=$param['status'];
            if (!$specs_id || !$order_id ||!$status){
                return \json(self::callback(0,'参数错误'),400);
            }
            $order_info = Db::name('product_order')->where('id',$order_id)->where('store_id',$store_info['id'])->find();
            $order_product = Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();
            $order_status = Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->find();
            if(empty($order_info)||empty($order_product)||empty($order_status)){
                return \json(self::callback(0,'没找到完整数据'),400);
            }
            if($order_product['is_shouhou']!=1 ||$order_product['is_refund']==1|| $order_status['refund_status']==4){
                return \json(self::callback(0,'该订单不支持该操作或已完成'),400);
            }
            if($status=='true'){
                //同意
                //判断退货退款类型
if(($order_status['refund_type']==1 && $order_status['refund_status']==1)||($order_status['refund_type']==2 && $order_status['refund_status']==3)){
    //1退款
//判断是否有优惠券
    if($order_info['coupon_id']>0 && $order_info['coupon_money']>0){
        $refund_money = $order_product['realpay_money'];
    }else{
        $product_total_price = $order_product['number'] * $order_product['price'];
        //实际退款金额 = 退款单个商品金额 - 优惠券金额 * (退款单个商品金额/总订单金额) - 运费
//        $refund_money = $product_total_price - $order_info['coupon_money'] * ($product_total_price/$order_info['pay_money']) - $order_product['freight'];
        $refund_money = round($product_total_price,2);
    }
    if ($refund_money > 0) {
        if ($order_info['pay_type'] == '支付宝') {
            $alipay = new AliPay();
            $res = $alipay->alipay_refund($order_info['pay_order_no'],$refund_money);
        }elseif ($order_info['pay_type'] == '微信'){
            $wxpay = new WxPay();
            $total_pay_money = Db::name('product_order')->where('pay_order_no',$order_info['pay_order_no'])->sum('pay_money');
            $res = $wxpay->wxpay_refund($order_info['pay_order_no'],$total_pay_money,$refund_money);
        }
        if ($res){
            //3退款通知
            $msg_id = Db::name('user_msg')->insertGetId([
                'title' => '退款通知',
                'content' => '您的订单'.$order_info['order_no'].'商家已同意退款,订单金额已原路返回',
                'type' => 2,
                'create_time' => time()
            ]);

            Db::name('user_msg_link')->insert([
                'user_id' => $order_info['user_id'],
                'msg_id' => $msg_id
            ]);
            $genxin = [
                'is_refund' => 1,
                'refund_time' => time(),
                'refund_money' => $refund_money
            ];
            $rst1=Db::name('product_order_detail')->where('order_id',$order_id)->where('specs_id',$specs_id)->update($genxin);//更新
            $rst2=Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->setField('refund_status',4);
            //判断是否该订单全部退款/退货退款
            $num = Db::name('product_order_detail')->where('order_id',$order_id)->count();
            $num2 = Db::name('product_order_detail')->where('order_id',$order_id)->where('is_shouhou',1)->count();
            if($num==$num2){
                Db::name('product_order')->where('id',$order_id)->setField('order_status',7);

            }else{

            }
        }

                }else{
                    return \json(self::callback(0,'退款金额不正确'),400);
                }


            }else if($order_status['refund_type']==2 && $order_status['refund_status']==1){
                //2退货退款

            $rst1=Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->setField('refund_status',2);
                    if($rst1===false){
                        return \json(self::callback(0,'操作失败'),400);
                    }
            }else{
                return \json(self::callback(0,'状态错误'),400);
            }
            }else if($status=='false'){
             //拒绝
                if(!$refuse_description){
                    return \json(self::callback(0,'拒绝理由不能为空'),400);
                }
                $genxin = [
                    'refund_status' => -1,
                    'refuse_description' => $refuse_description
                ];
                $rst1=Db::name('product_shouhou')->where('order_id',$order_id)->where('specs_id',$specs_id)->update($genxin);//更新
                if($rst1===false){
                    return \json(self::callback(0,'操作失败'),400);
                }
            }else{
                return \json(self::callback(0,'错误操作'),400);
            }

            return \json(self::callback(1,'操作成功',true));

        }catch (\Exception $e){

            return \json(self::callback(0,$e->getMessage()));
        }
    }
    /**
     * 售后订单详情
     */
    public function shouhouDetail(){
        try{
            $store_info = \app\store\common\Store::checkToken();
            if ($store_info instanceof json){
                return $store_info;
            }
            $param = $this->request->post();
            $specs_id=$param['specs_id'];
            $order_id=$param['order_id'];
            if (!$order_id || !$specs_id) {
                return \json(self::callback(0,'参数错误'),400);
            }
//            $product_order = Db::name('product_order')
//                ->field('id,order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,user_id,coupon_money,total_freight,pay_type,pay_time,fahuo_time,logistics_info,total_platform_price')
//                ->where('id',$order_id)
//                ->where('store_id',$store_info['id'])
//                ->find();
//
//            if (!$product_order) {
//                throw new \Exception('订单不存在');
//            }
            $product_order = Db::view('product_order_detail','order_id,cover,product_name,product_id,specs_id,product_specs,number,price,is_shouhou,is_refund,refund_time,refund_money,realpay_money')
                 ->view('product_shouhou','id,description,refuse_description,return_mode,create_time,refund_type,goods_status,refund_status,refund_reason,logistics_company,logistics_number','product_order_detail.order_id = product_shouhou.order_id','left')
                ->view('product_order','order_no,create_time,pay_money,order_status,shouhuo_username,shouhuo_mobile,shouhuo_address,user_id,coupon_id as user_css_coupon_id,coupon_money,total_freight,pay_type,pay_time,fahuo_time,logistics_info,total_platform_price','product_order_detail.order_id = product_order.id','left')
                ->where('product_order_detail.order_id',$order_id)
                 ->where('product_order_detail.is_shouhou',1)
                 ->where('product_order_detail.specs_id',$specs_id)
                 ->find();
            if (!$product_order) {
                throw new \Exception('订单不存在');
            }
            //查询所有图片
            $images=Db::name('product_shouhou_img')->where('shouhou_id',$product_order['id'])->select();
            $data['order_info']=$product_order;
            $data['order_info']['images']=$images;
            return \json(self::callback(1,'返回成功',$data));
        }catch (\Exception $e){
            return \json(self::callback(0,$e->getMessage()));
        }
    }
    
}