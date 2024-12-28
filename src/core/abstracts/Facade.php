<?php
namespace kitpress\core\abstracts;

use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\Kitpress;
use RuntimeException;

abstract class Facade {

    /**
     * 获取服务标识
     */
    abstract protected static function getFacadeAccessor() : string;

    /**
     * 获取服务实例
     */
    protected static function getFacadeRoot() {
        $container = Kitpress::getContainer();
        if (!$container ) {
            throw new \RuntimeException('No container available in Bootstrap');
        }
        return $container->get(static::getFacadeAccessor());
    }

    /**
     * 处理静态调用
     */
    public static function __callStatic($method, $args) {
        $instance = static::getFacadeRoot();
        return $instance->$method(...$args);
    }
}