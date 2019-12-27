<?php

// 引入Consul工具类
require_once("./ConsulToolClass.php");

$web_path = \think\Config::get('web_path');

 $data = array(
              "ID"=>"appwx1-1-80",
              "Name"=>"appwx",
              "Tags"=>array("primary"),
              "Address"=>"appwx.supersg.cn",
              "Port"=>80,
              "Check"=>array("HTTP"=>"{$web_path}/phpappwx/health.php","Interval"=>"5s")
          );

      $consul = new ConsulToolClass();
      $consul->registerService(json_encode($data)); //往Consul里注册服务

