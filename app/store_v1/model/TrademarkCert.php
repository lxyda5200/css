<?php


namespace app\store_v1\model;


use think\Model;

class TrademarkCert extends Model
{
    protected $table = 'trademark_cert';

    protected $autoWriteTimestamp = true;

    protected $updateTime = false;

    public function store() {
        return $this->belongsTo(Store::class);
    }
}