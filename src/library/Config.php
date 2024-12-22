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
    use ConfigTrait {
        load as protected loadResource;
    }

    /**
     * 配置存储
     * @var array
     */
    private static $items = [];

    /**
     * 已加载的配置文件记录
     * @var array
     */
    private static $loaded = [];

    /**
     * 加载配置文件
     * @param string|array $names 配置文件名
     */
    public static function load($names) {
        static::loadResource($names, 'config');
    }

    /**
     * 获取配置值
     * @param string|null $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key = null, $default = null) {
        return self::getInstance()->getValue(self::$items, $key, $default);
    }

    /**
     * 设置配置值
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public static function set($key, $value) {
        self::getInstance()->setValue(self::$items, $key, $value);
    }

    /**
     * 检查配置是否存在
     * @param string $key 配置键名
     * @return bool
     */
    public static function has($key) {
        return self::getInstance()->getValue(self::$items, $key) !== null;
    }

    /**
     * 重置配置（用于测试）
     */
    public static function reset() {
        self::$items = [];
        self::$loaded = [];
    }
} 