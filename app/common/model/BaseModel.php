<?php
namespace app\common\model;

use app\common\traits\BaseOptionsTrait;
use think\Model;

abstract class BaseModel extends Model
{
    use BaseOptionsTrait;
}