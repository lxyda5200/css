<?php
/**
 * Created by oneming
 * User: oneming
 * 服务注销
 */
define('BASE_PATH', __DIR__);
include BASE_PATH . '/Consul/Agent.php';

//eg:

$service_id = 'appwx1-1-80';//注册时的id
$agent = new Consul\Agent(array(
    'host' => 'http://saas.supersg.cn:8080'
));
$res = $agent->deregisterService($service_id);

echo "<pre>";
var_dump($res);