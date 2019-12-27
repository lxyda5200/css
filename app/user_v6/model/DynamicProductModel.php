<?php


namespace app\user_v6\model;
use think\Model;

class DynamicProductModel extends Model
{
    protected $pk = 'id';
    protected $table = 'dynamic_product';
    protected $dateFormat=false;
}