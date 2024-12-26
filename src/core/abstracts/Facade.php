<?php
namespace kitpress\core\abstracts;

use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\Kitpress;
use RuntimeException;

abstract class Facade {
    /**
     * 容器实例
     */
    protected static array $containers = [];


    /**
     * 设置容器实例
     */
    public static function setContainer(Container $container, ?string $namespace = null) {
        $namespace = $namespace ?? $container->getNamespace();
        static::$containers[$namespace] = $container;
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
    }

    /**
     * 获取当前容器
     */
    protected static function getFacadeContainer(): Container
    {
        $container = Kitpress::getContainer();

        if (!$container) {
            throw new \RuntimeException('No container available in Bootstrap');
        }

        return $container;
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