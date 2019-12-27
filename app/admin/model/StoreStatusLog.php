<?php


namespace app\admin\model;


use think\Model;

class StoreStatusLog extends Model
{
    protected $table = 'store_status_log';

    protected $autoWriteTimestamp = true;

    protected $updateTime = false;
}