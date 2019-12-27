<?php


namespace app\store\model;


use think\Model;

class BussinessReward extends Model
{
    protected $table = 'bussiness_reward';

    protected $autoWriteTimestamp = true;


    public function store() {
        return $this->belongsTo(Store::class);
    }
}