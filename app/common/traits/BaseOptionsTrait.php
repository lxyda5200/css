<?php
namespace app\common\traits;

trait BaseOptionsTrait
{
    /**
     * 新增
     * @param $data
     * @return bool
     */
    public function storeBy($data) {
        if($this->allowField(true)->save($data)) {
            return $this->{$this->getPk()};
        }

        return false;
    }

    /**
     * 批量新增
     * @param $data
     * @return mixed
     */
    public function storeAllBy($data) {
        return $this->allowField(true)->saveAll($data);
    }

    /**
     * 更新
     * @param $id
     * @param $data
     * @return mixed
     */
    public function updateBy($id, $data) {
        return $this->save($data, [$this->getPk() => $id]);
    }

    /**
     * 批量更新
     * @param $data
     * @return mixed
     */
    public function updateAllBy($data) {
        return $this->isUpdate(true)->saveAll($data);
    }

    /**
     * 查找
     * @param $id
     * @param string $field
     * @return mixed
     */
    public function findBy($id, $field = '*') {
        return $this->where([$this->getPk() => $id])->field($field)->find();
    }

    /**
     * 删除
     * @param $id
     * @param null $data
     * @param bool $trace
     * @return mixed
     */
    public function deleteBy($id, $data = null, $trace = false) {
        if($trace) {
            return $this->where([$this->getPk() => $id])->delete();
        }else {
            return $this->save($data, [$this->getPk() => $id]);
        }
    }
}