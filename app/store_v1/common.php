<?php

##去掉字符串的[]
use think\Db;

function trimFunc($str){
    return str_replace('[','',str_replace(']','',$str));
}

/**
 * 生成缩略图
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function createThumb($path, $root, $type){
    $config = config('config_common.compress_config');
    $path = str_replace("\\","/",$path);
    $path = trim($path,'/');
    if(file_exists($path)){
        $img = \think\Image::open($path);
        $ext = substr($path, strrpos($path,'.'));
        $path = $root . time() . rand(100000,999999) . "_" . $config[$type][0] . "X" . $config[$type][1] . $ext;
        $img->thumb($config[$type][0], $config[$type][1])->save($path);
        $path = "/" . $path;
        return $path;
    }
    return "";
}


/**
 * 写入店铺主营分类
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function StoreCategory($store_category,$store_id){
    $store_category = explode(",",$store_category);
    $category=[];
    foreach ($store_category as $k=>$v){
        $category[$k]['store_id'] = $store_id;
        $category[$k]['cate_store_id'] = $v;
        $category[$k]['create_time'] = time();
    }
        if($category){
            return $category;
        }else{return false;}
}

/**
 * 写入店铺主营风格
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function StoreType($store_type,$store_id){
    $store_type = explode(",",$store_type);
    $type=[];
    foreach ($store_type as $k=>$v){
        $type[$k]['store_id'] = $store_id;
        $type[$k]['style_store_id'] = $v;
        $type[$k]['create_time'] = time();
    }
    if($type){
       return $type;
    }else{return false;}
}

/**
 * 删除店铺主营风格
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function StoreTypeDel($store_type,$store_id){
    $store_type = explode(",",$store_type);
    $rst=Db::name('store_style_store')->where('style_store_id','IN',$store_type)->where('store_id',$store_id)->delete();
    if($rst){return false;}else{return true;}
}

/**
 * 写入商品风格
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function ProductType($style_product,$product_id){
    $style_product = explode(",",$style_product);
    $style=[];
    foreach ($style_product as $k=>$v){
        $style[$k]['product_id'] = $product_id;
        $style[$k]['style_product_id'] = $v;
        $style[$k]['create_time'] = time();
    }
    if($style){
      return $style;
    }else{
        return false;
    }
}
/**
 * 删除商品风格
 * @param $path
 * @param $root
 * @param $type
 * @return string
 */
function ProductTypeDel($style_product,$product_id){
    $style_product = explode(",",$style_product);
    $rst=Db::name('product_style_product')->where('style_product_id','IN',$style_product)->where('product_id',$product_id)->delete();
    if($rst){return false;}else{return true;}
}

/**
 * 字符串过滤
 * @param $val
 * @return string
 */
function trimStr($val){
    return addslashes(strip_tags(trim($val)));
}


/**
 * 拆分图片
 */
function img_array($id,$imgs){
    $store_img = explode(',',$imgs);
    if(empty($store_img)){
        return false;
    }
    $data =[];
    foreach ($store_img as $k=>$v){
        $data[$k]['store_id'] = $id;
        $data[$k]['img_url'] = $v;
    }
    return $data;
}