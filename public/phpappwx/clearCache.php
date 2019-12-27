<?php
/**
 * Created by oneming
 * User: oneming
 * 清除服务节点缓存
 */
define('BASE_PATH', __DIR__);
include BASE_PATH . '/Consul/Discovery.php';

//eg:

$serviceNames = array('appwx');//array(key1, key2, ...)，可一个，可多个
$all = false;//是否全部清除consul相关的缓存,默认：false 不，如果要清除全部 传true

$discovery = new Consul\Discovery(array(
    'host' => 'http://saas.supersg.cn:8080'
));
$res = $discovery->clearCache($serviceNames, $all);

var_dump($res);