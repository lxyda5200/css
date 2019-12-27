<?php
/**
 * Created by PhpStorm.
 * User: 贝拉
 * Date: 2018/9/26
 * Time: 15:48
 */

namespace app\admin\model;


use think\Model;

class Area extends Model
{

    protected $resultSetType = "collection";

    public function arealist($area,$id=0,$level=0){

        static $areas = array();
        foreach ($area as $value) {
            if ($value['pid']==$id) {
                $value['level'] = $level+1;
                if($level == 0)
                {
                    $value['str'] = str_repeat('',$value['level']);
                }
                elseif($level == 2)
                {
                    $value['str'] = '&emsp;&emsp;&emsp;&emsp;'.'└ ';
                }
                elseif($level == 3)
                {
                    $value['str'] = '&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;'.'└ ';
                }
                else
                {
                    $value['str'] = '&emsp;&emsp;'.'└ ';
                }
                $areas[] = $value;
                $this->arealist($area,$value['id'],$value['level']);
            }
        }
        return $areas;
    }

}