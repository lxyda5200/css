<?php


namespace app\admin\controller;


class CateAndStyle extends Admin
{

    public function cateStore(){
        return $this->fetch();
    }

    public function styleStore(){
        return $this->fetch();
    }

    public function cateProduct(){
        return $this->fetch();
    }

    public function styleProduct(){
        return $this->fetch();
    }

}