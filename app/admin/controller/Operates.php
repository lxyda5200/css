<?php


namespace app\admin\controller;


class Operates extends Admin
{

    /**
     * 人气单品
     * @return mixed
     */
    public function popularProduct(){
        return $this->fetch();
    }

    /**
     * 宿友推荐
     * @return mixed
     */
    public function roommateRecom(){
        return $this->fetch();
    }

    /**
     * 时尚新潮
     * @return mixed
     */
    public function newTrend(){
        return $this->fetch();
    }

}