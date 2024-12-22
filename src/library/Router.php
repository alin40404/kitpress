<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\core\traits\ConfigTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 路由管理类
 */
class Router extends Singleton {
    use ConfigTrait {
        load as protected loadResource;
    }

    /**
     * 加载路由配置（静态代理方法）
     * @param string|array $names 路由文件名
     */
    public static function load($names) {
        static::getInstance()->loadResource($names, 'routes');
    }

    /**
     * 获取路由配置（静态代理方法）
     * @param string|null $key 路由键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key = null, $default = null) {
        return static::getInstance()->getValue(
            static::getInstance()->items,
            $key,
            $default
        );
    }

    /**
     * 设置路由配置（静态代理方法）
     * @param string $key 路由键名
     * @param mixed $value 路由配置
     */
    public static function set($key, $value) {
        static::getInstance()->setValue(
            static::getInstance()->items,
            $key,
            $value
        );
    }


    /**
     * 检查路由是否存在（静态代理方法）
     * @param string $key 路由键名
     * @return bool
     */
    public static function has($key) {
        return static::getInstance()->getValue(
                static::getInstance()->items,
                $key
            ) !== null;
    }

    /**
     * 重置路由（静态代理方法）
     */
    public static function reset() {
        static::getInstance()->items = [];
        static::getInstance()->loaded = [];
    }

}