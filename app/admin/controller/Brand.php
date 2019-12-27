<?php


namespace app\admin\controller;


class Brand extends Admin
{

    public function brandList(){
        return $this->fetch();
    }

    public function brandCheckList(){
        return $this->fetch();
    }

    public function brandCate(){
        return $this->fetch();
    }

}