<?php
namespace kitpress\core\abstracts;

use kitpress\core\Container;
use RuntimeException;

abstract class Facade {
    /**
     * 容器实例
     */
    protected static array $containers = [];
    protected static ?string $currentNamespace = null;


    /**
     * 设置容器实例
     */
    public static function setContainer(Container $container, ?string $namespace = null) {
        $namespace = $namespace ?? $container->getNamespace();
        static::$containers[$namespace] = $container;
        static::$currentNamespace = $namespace;
    }

    /**
     * 切换当前使用的容器命名空间
     * @param string $namespace
     */
    public static function useNamespace(string $namespace): void
    {
        if (!isset(static::$containers[$namespace])) {
            throw new \RuntimeException("Container for namespace '{$namespace}' not found");
        }
        static::$currentNamespace = $namespace;
    }

    /**
     * 获取当前容器
     */
    protected static function getFacadeContainer(): Container
    {
        if (empty(static::$currentNamespace)) {
            throw new \RuntimeException('No container namespace is set');
        }

        if (!isset(static::$containers[static::$currentNamespace])) {
            throw new \RuntimeException("Container for namespace '" . static::$currentNamespace . "' not found");
        }

        return static::$containers[static::$currentNamespace];
    }


    /**
     * 获取服务标识
     */
    abstract protected static function getFacadeAccessor() : string;

    /**
     * 获取服务实例
     */
    protected static function getFacadeRoot() {
        $container = static::getFacadeContainer();
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