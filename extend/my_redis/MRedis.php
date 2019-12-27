<?php


namespace my_redis;


use think\Config;

class MRedis
{

    protected $host = "47.110.92.34";

    protected $port = 6379;

    protected $pwd = "one_4259";

    protected $redis;

    public function __construct()
    {
//        $conf = Config::get('redis_conf');
//        $this->host = (isset($conf['host']) && $conf['host'])?$conf['host']:$this->host;
//        $this->port = (isset($conf['port']) && $conf['port'])?$conf['port']:$this->port;
//        $this->host = (isset($conf['pwd']) && $conf['pwd'])?$conf['pwd']:$this->pwd;
        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port);
        $this->redis->auth($this->pwd);
       // $this->redis->select(1);

    }

    public function getRedis(){
        return $this->redis;
    }

}