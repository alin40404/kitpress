<?php
namespace kitpress\functions;

use kitpress\core\Container;
use kitpress\Kitpress;

if (!defined('ABSPATH')) {
    exit;
}

if( !function_exists('kp') ){
    /**
     * 获取容器
     * @param string|null $namespace
     * @return Container
     */
    function kp(string $namespace = null): Container
    {
        return $namespace ? Container::getInstance($namespace,KITPRESS_VERSION) : Kitpress::getContainer();
    }
}

if( !function_exists('kp_config') ){
    function kp_config($name,$default = null,string $namespace = null)
    {
        return \kitpress\functions\kp($namespace)->get('config')->get($name,$default);
    }
}

if( !function_exists('kp_plugin') ){
    function kp_plugin(string $namespace = null)
    {
        return \kitpress\functions\kp($namespace)->get('plugin');
    }
}

if( !function_exists('kp_cache') ){
    function kp_cache(string $namespace = null)
    {
        return \kitpress\functions\kp($namespace)->get('cache');
    }
}

if( !function_exists('kp_session') ){
    function kp_session(string $namespace = null)
    {
        return \kitpress\functions\kp($namespace)->get('session');
    }
}

if( !function_exists('kp_log') ){
    function kp_log(string $namespace = null)
    {
        return \kitpress\functions\kp($namespace)->get('log');
    }
}



