<?php


namespace app\user_v7\model;
use think\Model;

class DynamicProductModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic_product';
    protected $dateFormat=false;
}