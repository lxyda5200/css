<?php


namespace app\admin\model;


use think\Model;

class MaidanOrder extends Model
{

    /**
     * 获取买单总原价
     * @return float|int
     */
    public function getTotalMaiDanYJ(){
        return $this->getTotalFieldSum('price_yj');
    }

    /**
     * 获取买单实付总额
     * @return float|int
     */
    public function getTotalMaiDanPay(){
        return $this->getTotalFieldSum('price_maidan');
    }

    /**
     * 获取买单店铺总收入
     * @return float|int
     */
    public function getTotalStorePrice(){
        return $this->getTotalFieldSum('price_store');
    }

    /**
     * 获取买单平台
     * @return float|int
     */
    public function getTotalPlatformProfit(){
        return $this->getTotalFieldSum('platform_profit');
    }

    /**
     * 获取字段总和
     * @param $field
     * @return float|int
     */
    private function getTotalFieldSum($field){
        return $this->where(['status'=>2])->sum($field);
    }

    /**
     * 获取某条件下的总买单原价
     * @param $where
     * @return float|int
     */
    public function getCurMaiDanYJ($where){
        return $this->getCurFieldSum($where,'price_yj');
    }

    /**
     * 获取某条件下的店铺实收
     * @param $where
     * @return float|int
     */
    public function getCurStorePrice($where){
        return $this->getCurFieldSum($where,'price_store');
    }

    /**
     * 获取某条件下的平台提成
     * @param $where
     * @return float|int
     */
    public function getCurPlatformProfit($where){
        return $this->getCurFieldSum($where, 'platform_profit');
    }

    /**
     * 获取某条件下的买单支付金额
     * @param $where
     * @return float|int
     */
    public function getCurMaiDanPay($where){
        return $this->getCurFieldSum($where, 'price_maidan');
    }

    /**
     * 获取某条件下某字段的总和
     * @param $where
     * @param $field
     * @return float|int
     */
    public function getCurFieldSum($where, $field){
        return $this->alias('mo')
            ->join('store s','s.id = mo.store_id','LEFT')
            ->where($where)
            ->sum("mo.{$field}");
    }

}