<?php

namespace Zls\Cache;

/**
 * Zls_Cache_Memcache.
 */
//'memcache'      => array(
//    'class'  => 'Zls_Cache_Memcache',
//    'config' => array(//memcache服务器信息，支持多个
//                      //array("new.host.ip",11211),
//    ),
//),
class Memcache implements \Zls_Cache
{
    private $config;
    private $handle;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function clean()
    {
        $this->_init();

        return $this->handle->flush();
    }

    private function _init()
    {
        if (empty($this->handle)) {
            $this->handle = new \Memcache();
            foreach ($this->config as $server) {
                $this->handle->addserver($server[0], $server[1]);
            }
        }
    }

    public function delete($key)
    {
        $this->_init();

        return $this->handle->delete($key);
    }

    public function get($key)
    {
        $this->_init();

        return ($data = $this->handle->get($key)) ? $data : null;
    }

    public function set($key, $value, $cacheTime = 0)
    {
        $this->_init();

        return $this->handle->set($key, $value, false, $cacheTime);
    }

    public function &instance($key = null, $isRead = true)
    {
        $this->_init();

        return $this->handle;
    }

    public function reset()
    {
        $this->handle = null;

        return $this;
    }
}
