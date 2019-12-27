<?php


namespace app\business\model;


use think\Model;
use think\Db;
class ProductSpecsModel extends Model
{
    protected $pk = 'id';

    protected $table = 'product_specs';


    /**
     * 商品列表
     * @param $store_id     店铺ID
     * @param bool $status  状态 false-全部 0-下架 1-售完 2-上架
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getStoreProductList($store_id,$status = false,$page,$limit){
        $pre = ($page-1)*$limit;

        $where['p.store_id'] = $store_id;
        $where['p.sh_status'] = 1;
        if ($status !== false){
            switch ($status){
                case 0:
                    $wheres = ['a.status' => 0, 'a.total_stocks' => ['gt', 0]];
                    break;
                case 1:
                    $wheres['a.total_stocks'] = 0;
                    break;
                case 2:
                    $wheres = ['a.status' => 1, 'a.total_stocks' => ['gt', 0]];
                    break;
            }
        }
        //$data = ProductGoodsModel::where($where)->field(['status','total_stocks','store_id','id'])->limit($pre,$limit)->select();
        $subQuery = ProductGoodsModel::where($where)
            ->alias('p')
            ->join('product_specs ps', 'p.id = ps.product_id')
            ->field(['p.product_name','p.status','p.store_id','p.id as id','sum(stock) as total_stocks','p.create_time'])
            ->group('id')
            ->buildSql();
        $data = Db::table($subQuery . ' a')
            ->where($wheres)
            ->order('a.create_time desc')
            ->limit($pre,$limit)
            ->select();
        foreach ($data as $k => $v){
            $minPrice =self::where('product_id', $v['id'])->order('price','asc')->field(['cover','product_name','(price + platform_price) as price'])->find();
            $data[$k]['cover'] = $minPrice['cover'];
            $data[$k]['product_name'] = $minPrice['product_name'];
            $data[$k]['price'] = $minPrice['price'];
        }
        return $data;
    }

    /**
     *  获取商品数量【全部、下架、售完】
     * @param $store_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getProductNumByStatus($store_id){
        //$data = ProductGoodsModel::where(['store_id' => $store_id])->field(['status','total_stocks','store_id','id'])->select();
        $data = ProductGoodsModel::where(['p.store_id' => $store_id,'p.sh_status'=>1])
            ->alias('p')
            ->join('product_specs ps', 'p.id = ps.product_id')
            ->field(['p.product_name','p.status','p.store_id','p.id as id','sum(stock) as total_stocks'])
            ->group('id')
            ->select();
        // 总商品数
        $totalProduct = count($data);
        // 售完商品数
        $saleOutProduct = 0;
        // 下架商品数
        $shelvesProduct = 0;
        //出售中
        $onaleProduct = 0;
        foreach ($data as $k => $v){
            if ($v['total_stocks'] <= 0){
                $saleOutProduct++;
            }
            if($v['total_stocks'] > 0 && $v['status'] == 0){
                $shelvesProduct++;
            }
            if($v['total_stocks'] > 0 && $v['status'] == 1){
                $onaleProduct++;
            }
        }
        return compact('totalProduct', 'saleOutProduct', 'shelvesProduct', 'onaleProduct');
    }

    /**
     *  获取商品详情数据
     * @param $product_id
     * @param $store_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getProductDetails($product_id, $store_id){
        // 获取商品详情数据
        $productData = self::where(['ps.product_id' => $product_id])
            ->alias('ps')
            ->join('product p', 'ps.product_id = p.id and p.store_id = '. $store_id)
            ->field([
                'ps.cover','ps.product_name','ps.product_specs','ps.stock','ps.price','ps.platform_price','ps.product_id',
                'p.status','p.shangjia_time','p.xiajia_time',
            ])
            ->select();
        // 获取商品主图
        $productImgUrls = ProductImgModel::where(['product_id'=>$product_id]) -> field(['img_url']) -> select();
        $productImgUrl = [];
        foreach ($productImgUrls as $ki =>$vi){
            array_push($productImgUrl,$vi['img_url']);
        }
        //新的商品规格数据
        $productName = [];
        $productNewData = [];
        $total_stocks = 0;
        if(!empty($productData['0'])){
            foreach ($productData['0']['product_specs'] as $k => $v){
                array_push($productName,strval($k));
            }
            array_push($productName,'库存','价格');
            foreach ($productData as $kp => $vp){
                $arr = [];
                foreach ($vp['product_specs'] as $ks => $vs){
                    array_push($arr,$vs);
                }
                //价格 = 价格+ 平台加价
                $pr = $vp['price']+$vp['platform_price'];
                //$pr = number_format($pr, 2);
                $pr = sprintf("%.2f",$pr);
                $total_stocks += $vp['stock'];
                array_push($arr,$vp['stock'],$pr);
                array_push($productNewData,$arr);
            }
        }

        return compact('productData', 'productImgUrl','productName','productNewData','total_stocks');
    }


    public function getProductSpecsAttr($value)
    {
        return json_decode($value, true);
    }
}