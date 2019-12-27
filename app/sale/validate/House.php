<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/7/30
 * Time: 11:24
 */

namespace app\sale\validate;


use think\Validate;

class House extends Validate
{

    protected $rule =   [
        'type' => 'require|in:1,2,3',    //类型 1整租 2合租 3整租合租
        'entrust_id' => 'number',    //委托id
        'title' => 'require',
        'description' => 'require',
        'rent' => 'require|number',
        'rent_mode' => 'require|in:1,2,3,4',   //租金方式 1押一付一 2押一付三 3半年付  4年付
        'decoration_mode' => 'require|in:1,2',  //装修方式 1简装 2精装
        'bedroom_number' => 'require|number',
        'parlour_number' => 'require|number',
        'toilet_number' => 'require|number',
        'acreage' => 'require|number',
        'floor_type' => 'require|in:1,2,3',   //楼层类型 1低楼层 2中楼层 3高楼层
        'floor' => 'require|number',
        'total_floor' => 'require|number',
        'orientation' => 'require',         //朝向
        'house_type_id' => 'require|number',   //类型id
        'years' => 'require|number',
        'is_elevator' => 'require|in:0,1',
        'xiaoqu_id' => 'require|number',
        'is_subway' => 'require|in:0,1',
        'lines_id' => 'number',
        'station_id' => 'number',
        'appoint_shop_id' => 'number',
        'appoint_sale_id' => 'number',
        'status' => 'require|in:1,2'
    ];

    protected $message = [
        'type.require' => '租房类型必须',
        'type.in' => '租房类型值必须在 1,2,3 范围内',
        'entrust_id.number' => '委托id必须为数字',
        'title.require' => '标题必须',
        'description.require' => '描述必须',
        'rent.require' => '租金必须',
        'rent.number' => '租金必须为数字',
        'rent_mode.require' => '租金缴纳方式必须',
        'decoration_mode.require' => '装修方式必须',
        'decoration_mode.in' => '装修方式值必须在 1,2 范围内',
        'bedroom_number.require' => '卧室数量必须',
        'bedroom_number.number' => '卧室数量必须为数字',
        'parlour_number.require' => '客厅数量必须',
        'parlour_number.number' => '客厅数量必须为数字',
        'toilet_number.require' => '卫生间数量必须',
        'toilet_number.number' => '卫生间数量必须为数字',
        'acreage.require' => '面积必须',
        'acreage.number' => '面积必须为数字',
        'floor_type.require' => '楼层类型必须',
        'floor_type.in' => '楼层类型值必须在 1,2,3 范围内',
        'floor.require' => '当前楼层必须',
        'floor.number' => '当前楼层必须为数字',
        'total_floor.require' => '总楼层必须',
        'total_floor.number' => '总楼层必须位数字',
        'orientation.require' => '朝向必须',
        'house_type_id.require' => '房源类型id必须',
        'house_type_id.number' => '房源类型id必须为数字',
        'years.require' => '年代必须',
        'years.number' => '年代必须为数字',
        'is_elevator.require' => '有无电梯必须',
        'is_elevator.number' => '有无电梯值必须在 0,1 范围内',
        'xiaoqu_id.require' => '小区id必须',
        'xiaoqu_id.number' => '小区id必须位数字',
        'is_subway.require' => '有无地铁必须',
        'is_subway.number' => '有无地铁必须为数字',
        'lines_id.number' => '地铁线路id必须为数字',
        'station_id.number' => '地铁站台id必须为数字',
        'status.require' => '是否提交必须',
        'appoint_shop_id.number' => '指定店铺id必须为数字',
        'appoint_sale_id.number' => '指定销售id必须为数字',
        'status.in' => '是否提交值必须在 1,2 范围内'
    ];

}