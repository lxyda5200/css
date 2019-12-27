<?php


namespace app\user_v7\model;
use think\Model;
use think\db\Query;
use think\Validate;
use think\Db;
use app\common\controller\Base;

class NewTrendModel extends Model
{
    protected $pk = 'id';
    protected $table = 'new_trend';
    protected $dateFormat=false;
}