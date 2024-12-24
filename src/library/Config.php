<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\core\traits\ConfigTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 配置管理类
 */
class Config extends Singleton {
    use ConfigTrait ;


    /**
     * 加载配置文件（静态代理方法）
     * @param string|array $names 配置文件名
     */
    public static function load($names) {
        static::getInstance()->loadResource($names, 'config');
    }

    /**
     * 获取配置值（静态代理方法）
     * @param string|null $key 配置键名
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
     * 设置配置值（静态代理方法）
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public static function set($key, $value) {
        static::getInstance()->setValue(
            static::getInstance()->items,
            $key,
            $value
        );
    }

    /**
     * 检查配置是否存在（静态代理方法）
     * @param string $key 配置键名
     * @return bool
     */
    public static function has($key) {
        return static::getInstance()->getValue(
                static::getInstance()->items,
                $key
            ) !== null;
    }

    /**
     * 重置配置（静态代理方法）
     */
    public static function reset() {
        static::getInstance()->items = [];
        static::getInstance()->loaded = [];
    }
} 