<?php
/**
 * Created by oneming
 * User: oneming
 * 服务维护，维护之后，服务将不会被发现
 */
define('BASE_PATH', __DIR__);
include BASE_PATH . '/Consul/Agent.php';


//eg:

$enable     = true; //true启用维护模式，false禁用维护模式
$service_id = 'appwx1-1-80';
$reason     = 'maintenance 1h';//原因，自定义，可空

$agent = new Consul\Agent(array(
    'host' => 'http://saas.supersg.cn:8080'
));

$res = $agent->maintenance($service_id, $enable, $reason);

echo "<pre>";
var_dump($res);