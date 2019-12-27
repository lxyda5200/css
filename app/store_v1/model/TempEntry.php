<?php


namespace app\store_v1\model;


use think\Model;

class TempEntry extends Model
{
    protected $table = 'temp_entry';

    protected $autoWriteTimestamp = false;
}