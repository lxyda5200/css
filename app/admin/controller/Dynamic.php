<?php


namespace app\admin\controller;


class Dynamic extends Admin
{

    public function dynamicList(){
        return $this->fetch();
    }

    public function dynamicRecomList(){
        return $this->fetch();
    }

}