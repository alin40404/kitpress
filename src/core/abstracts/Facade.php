<?php
namespace kitpress\core\abstracts;

use kitpress\core\Container;
use RuntimeException;

abstract class Facade {
    /**
     * 容器实例
     */
    protected static $container;

    /**
     * 已解析的实例
     */
    protected static $resolvedInstances = [];

    /**
     * 设置容器实例
     */
    public static function setContainer(Container $container) {
        static::$container = $container;
    }

    /**
     * 获取服务标识
     */
    protected static function getFacadeAccessor() {
        throw new RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * 获取服务实例
     */
    protected static function getFacadeRoot() {
        $name = static::getFacadeAccessor();

        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        if (static::$container) {
            return static::$resolvedInstances[$name] =
                static::$container->resolve($name);
        }

        throw new \RuntimeException('Container not set.');
    }

    /**
     * 处理静态调用
     */
    public static function __callStatic($method, $args) {
        $instance = static::getFacadeRoot();
        return $instance->$method(...$args);
    }
}