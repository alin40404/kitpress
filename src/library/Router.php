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
     * 路由配置存储
     * @var array
     */
    private static $routes = [];

    /**
     * 已加载的路由文件记录
     * @var array
     */
    private static $loaded = [];

    /**
     * 加载路由配置
     * @param string|array $names 路由文件名
     */
    public static function load($names) {
        static::loadResource($names, 'routes');
    }

    /**
     * 获取路由配置
     * @param string|null $key 路由键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key = null, $default = null) {
        return self::getInstance()->getValue(self::$routes, $key, $default);
    }

    /**
     * 设置路由配置
     * @param string $key 路由键名
     * @param mixed $value 路由配置
     */
    public static function set($key, $value) {
        self::getInstance()->setValue(self::$routes, $key, $value);
    }

    /**
     * 检查路由是否存在
     * @param string $key 路由键名
     * @return bool
     */
    public static function has($key) {
        return self::getInstance()->getValue(self::$routes, $key) !== null;
    }

    /**
     * 重置路由（用于测试）
     */
    public static function reset() {
        self::$routes = [];
        self::$loaded = [];
    }
}