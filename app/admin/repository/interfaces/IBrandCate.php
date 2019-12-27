<?php

namespace app\admin\repository\interfaces;

interface IBrandCate
{
    public function getList($where, $type);

    public function addCate($pid, $title, $level);

    public function editCate($id);

    public function updateCate($pid, $title, $id, $level);

    public function delCate($id_arr);

    public function getChildren($id);
}